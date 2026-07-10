<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();
header('Content-Type: application/json');
$pdo = getDB();

// Helper: verify quiz belongs to this faculty
function verifyQuizOwnership(PDO $pdo, int $quizId, int $facultyId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
    $stmt->execute([$quizId, $facultyId]);
    return (bool)$stmt->fetch();
}

$quizId = (int)($_REQUEST['quiz_id'] ?? 0);
if (!$quizId || !verifyQuizOwnership($pdo, $quizId, $faculty['faculty_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Quiz not found or access denied']);
    exit;
}

// CREATE question (single or bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    // Get quiz type once
    $stmt = $pdo->prepare("SELECT type FROM quizzes WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    // Bulk upload via CSV file
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot read uploaded file']);
            exit;
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty CSV file']);
            exit;
        }

        // Normalize header
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $colMap = array_flip($header);

        // Validate required columns
        $required = ['question_text', 'correct_answer'];
        if ($quiz['type'] === 'MIXED') {
            $required[] = 'question_type';
        }
        foreach ($required as $req) {
            if (!isset($colMap[$req])) {
                $cols = $quiz['type'] === 'MIXED'
                    ? 'question_type, question_text, option_a, option_b, option_c, option_d, correct_answer'
                    : 'question_text, option_a, option_b, option_c, option_d, correct_answer';
                http_response_code(400);
                echo json_encode(['error' => "Missing required column: $req. Expected columns: $cols"]);
                exit;
            }
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $lineNum  = 1;

        // Get current max sort_order
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $sortOrder = (int)$stmt->fetchColumn();

        $insertStmt = $pdo->prepare(
            "INSERT INTO questions (quiz_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNum++;
                $questionText  = trim($row[$colMap['question_text']] ?? '');
                $correctAnswer = trim($row[$colMap['correct_answer']] ?? '');
                $optionA = trim($row[$colMap['option_a'] ?? null] ?? '');
                $optionB = trim($row[$colMap['option_b'] ?? null] ?? '');
                $optionC = trim($row[$colMap['option_c'] ?? null] ?? '');
                $optionD = trim($row[$colMap['option_d'] ?? null] ?? '');

                // Determine question type
                if ($quiz['type'] === 'MIXED') {
                    $qType = strtoupper(trim($row[$colMap['question_type']] ?? ''));
                } else {
                    $qType = $quiz['type'];
                }

                if (empty($questionText) || empty($correctAnswer)) {
                    $errors[] = "Line $lineNum: missing question_text or correct_answer (skipped)";
                    $skipped++;
                    continue;
                }

                if (!in_array($qType, ['MCQ', 'TF', 'IDENTIFICATION'])) {
                    $errors[] = "Line $lineNum: invalid question_type '$qType', must be MCQ/TF/IDENTIFICATION (skipped)";
                    $skipped++;
                    continue;
                }

                // Validate answer format based on question type
                if ($qType === 'TF') {
                    $correctAnswer = strtoupper($correctAnswer);
                    if (!in_array($correctAnswer, ['T', 'F'])) {
                        $errors[] = "Line $lineNum: correct_answer must be T or F, got '$correctAnswer' (skipped)";
                        $skipped++;
                        continue;
                    }
                } elseif ($qType === 'MCQ') {
                    $correctAnswer = strtoupper($correctAnswer);
                    if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                        $errors[] = "Line $lineNum: correct_answer must be A/B/C/D, got '$correctAnswer' (skipped)";
                        $skipped++;
                        continue;
                    }
                    if (empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD)) {
                        $errors[] = "Line $lineNum: MCQ requires all 4 options (A/B/C/D) (skipped)";
                        $skipped++;
                        continue;
                    }
                }
                // IDENTIFICATION: no format validation, accept any text

                $sortOrder++;
                $insertStmt->execute([$quizId, $qType, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $sortOrder]);
                $imported++;
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            fclose($handle);
            http_response_code(500);
            echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            exit;
        }
        fclose($handle);

        echo json_encode([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
        exit;
    }

    // Single question add
    $questionText  = trim($_POST['question_text'] ?? '');
    $correctAnswer = trim($_POST['correct_answer'] ?? '');
    $questionType  = trim($_POST['question_type'] ?? '');
    $optionA = trim($_POST['option_a'] ?? '');
    $optionB = trim($_POST['option_b'] ?? '');
    $optionC = trim($_POST['option_c'] ?? '');
    $optionD = trim($_POST['option_d'] ?? '');

    if (empty($questionText) || empty($correctAnswer)) {
        http_response_code(400);
        echo json_encode(['error' => 'Question text and correct answer are required']);
        exit;
    }

    // Determine question type: for MIXED quizzes use the submitted question_type;
    // for non-MIXED quizzes it must match the quiz type
    if ($quiz['type'] === 'MIXED') {
        $questionType = strtoupper($questionType);
        if (!in_array($questionType, ['MCQ', 'TF', 'IDENTIFICATION'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Question type must be MCQ, TF, or IDENTIFICATION']);
            exit;
        }
    } else {
        $questionType = $quiz['type'];
    }

    // Validate answer format based on question type
    if ($questionType === 'TF') {
        $correctAnswer = strtoupper($correctAnswer);
        if (!in_array($correctAnswer, ['T', 'F'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Correct answer must be T or F for True/False questions']);
            exit;
        }
    } elseif ($questionType === 'MCQ') {
        $correctAnswer = strtoupper($correctAnswer);
        if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Correct answer must be A, B, C, or D for MCQ questions']);
            exit;
        }
        if (empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD)) {
            http_response_code(400);
            echo json_encode(['error' => 'All 4 options (A/B/C/D) are required for MCQ questions']);
            exit;
        }
    }
    // IDENTIFICATION: no validation on format, accept any text

    // Get next sort_order
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM questions WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    $sortOrder = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "INSERT INTO questions (quiz_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$quizId, $questionType, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $sortOrder]);

    echo json_encode(['success' => true, 'question_id' => $pdo->lastInsertId()]);
    exit;
}

// DELETE question
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    verifyCsrfToken();

    parse_str(file_get_contents("php://input"), $data);
    $questionId = (int)($data['question_id'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = ? AND quiz_id = ?");
    $stmt->execute([$questionId, $quizId]);

    echo json_encode(['success' => true]);
    exit;
}

// LIST questions (WITHOUT correct_answer — safe for student API reuse)
$stmt = $pdo->prepare(
    "SELECT question_id, question_type, question_text, option_a, option_b, option_c, option_d, sort_order
     FROM questions WHERE quiz_id = ? ORDER BY sort_order"
);
$stmt->execute([$quizId]);
echo json_encode($stmt->fetchAll());