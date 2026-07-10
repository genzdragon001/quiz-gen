<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();
header('Content-Type: application/json');
$pdo = getDB();

$submissionId = (int)($_GET['submission_id'] ?? $_POST['submission_id'] ?? 0);

if (!$submissionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing submission_id']);
    exit;
}

// Verify the submission belongs to a quiz owned by this faculty
$stmt = $pdo->prepare(
    "SELECT s.*, q.title AS quiz_title, q.type AS quiz_type, st.name AS student_name, st.student_id AS student_number
     FROM submissions s
     JOIN quizzes q ON s.quiz_id = q.quiz_id
     JOIN students st ON s.student_id = st.student_id
     WHERE s.submission_id = ? AND q.faculty_id = ?"
);
$stmt->execute([$submissionId, $faculty['faculty_id']]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied or submission not found']);
    exit;
}

// --- POST: Manual regrade of an identification answer ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $answerId    = (int)($_POST['answer_id'] ?? 0);
    $isCorrect   = (int)($_POST['is_correct'] ?? 0);
    $newScore    = (int)($_POST['new_score'] ?? null);

    if (!$answerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing answer_id']);
        exit;
    }

    // Verify the answer belongs to this submission
    $stmt = $pdo->prepare(
        "SELECT a.answer_id, a.is_correct, a.question_id, q.question_type
         FROM answers a
         JOIN questions q ON a.question_id = q.question_id
         WHERE a.answer_id = ? AND a.submission_id = ?"
    );
    $stmt->execute([$answerId, $submissionId]);
    $answer = $stmt->fetch();

    if (!$answer) {
        http_response_code(404);
        echo json_encode(['error' => 'Answer not found']);
        exit;
    }

    // Only allow regrading identification questions
    if ($answer['question_type'] !== 'IDENTIFICATION') {
        http_response_code(400);
        echo json_encode(['error' => 'Only identification answers can be manually regraded']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Update the answer
        $stmt = $pdo->prepare(
            "UPDATE answers SET is_correct = ?, manually_regraded = 1 WHERE answer_id = ?"
        );
        $stmt->execute([$isCorrect, $answerId]);

        // Recalculate the submission score
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total, SUM(is_correct) AS score
             FROM answers WHERE submission_id = ?"
        );
        $stmt->execute([$submissionId]);
        $result = $stmt->fetch();

        $stmt = $pdo->prepare(
            "UPDATE submissions SET score = ? WHERE submission_id = ?"
        );
        $stmt->execute([(int)$result['score'], $submissionId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'new_score' => (int)$result['score'],
            'total'     => (int)$result['total'],
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Regrade failed: ' . $e->getMessage()]);
    }
    exit;
}

// --- GET: Return submission details with all answers and questions ---
$stmt = $pdo->prepare(
    "SELECT a.answer_id, a.question_id, a.student_answer, a.is_correct, a.manually_regraded,
            q.question_text, q.question_type, q.option_a, q.option_b, q.option_c, q.option_d,
            q.correct_answer, q.sort_order
     FROM answers a
     JOIN questions q ON a.question_id = q.question_id
     WHERE a.submission_id = ?
     ORDER BY q.sort_order"
);
$stmt->execute([$submissionId]);
$answers = $stmt->fetchAll();

echo json_encode([
    'success'    => true,
    'submission' => $submission,
    'answers'    => $answers,
]);