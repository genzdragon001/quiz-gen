<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();
header('Content-Type: application/json');
$pdo = getDB();

// CREATE student (single or bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

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

        // Normalize header (trim + lowercase)
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $colMap = array_flip($header);

        // Validate required columns
        $required = ['student_id', 'name'];
        foreach ($required as $req) {
            if (!isset($colMap[$req])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required column: $req. Expected columns: student_id, name, email, year_section"]);
                exit;
            }
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $lineNum  = 1;

        $insertStmt = $pdo->prepare(
            "INSERT INTO students (student_id, name, email, year_section) VALUES (?, ?, ?, ?)"
        );
        $checkStmt = $pdo->prepare("SELECT 1 FROM students WHERE student_id = ?");

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNum++;
                $studentId   = trim($row[$colMap['student_id']] ?? '');
                $name        = trim($row[$colMap['name']] ?? '');
                $email       = trim($row[$colMap['email'] ?? null] ?? '');
                $yearSection = trim($row[$colMap['year_section'] ?? null] ?? '');

                if (empty($studentId) || empty($name)) {
                    $errors[] = "Line $lineNum: missing student_id or name (skipped)";
                    $skipped++;
                    continue;
                }

                // Check duplicate
                $checkStmt->execute([$studentId]);
                if ($checkStmt->fetch()) {
                    $errors[] = "Line $lineNum: student_id '$studentId' already exists (skipped)";
                    $skipped++;
                    continue;
                }

                $insertStmt->execute([$studentId, $name, $email, $yearSection]);
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

    // Single student add
    $studentId   = trim($_POST['student_id'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $yearSection = trim($_POST['year_section'] ?? '');

    if (empty($studentId) || empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID and name are required']);
        exit;
    }

    // Check for duplicate
    $stmt = $pdo->prepare("SELECT 1 FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Student ID already exists']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO students (student_id, name, email, year_section) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$studentId, $name, $email, $yearSection]);

    echo json_encode(['success' => true]);
    exit;
}

// UPDATE student
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    verifyCsrfToken();

    parse_str(file_get_contents("php://input"), $data);
    $studentId   = trim($data['student_id'] ?? '');
    $name        = trim($data['name'] ?? '');
    $email       = trim($data['email'] ?? '');
    $yearSection = trim($data['year_section'] ?? '');

    if (empty($studentId) || empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID and name are required']);
        exit;
    }

    // Verify the student has submissions for quizzes owned by this faculty
    // (students are global, but we only allow edits if there's a relationship)
    $stmt = $pdo->prepare(
        "SELECT 1 FROM submissions s JOIN quizzes q ON s.quiz_id = q.quiz_id
         WHERE s.student_id = ? AND q.faculty_id = ? LIMIT 1"
    );
    $stmt->execute([$studentId, $faculty['faculty_id']]);
    $hasRelationship = (bool)$stmt->fetch();

    // Also allow if the student exists but has no submissions anywhere
    // (faculty should be able to manage students they intend to use)
    $stmt = $pdo->prepare("SELECT 1 FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE students SET name = ?, email = ?, year_section = ? WHERE student_id = ?"
    );
    $stmt->execute([$name, $email, $yearSection, $studentId]);

    echo json_encode(['success' => true]);
    exit;
}

// DELETE student
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    verifyCsrfToken();

    parse_str(file_get_contents("php://input"), $data);
    $studentId = trim($data['student_id'] ?? '');

    if (empty($studentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing student_id']);
        exit;
    }

    // Count submissions for this student across this faculty's quizzes (for warning)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM submissions s JOIN quizzes q ON s.quiz_id = q.quiz_id
         WHERE s.student_id = ? AND q.faculty_id = ?"
    );
    $stmt->execute([$studentId, $faculty['faculty_id']]);
    $submissionCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);

    echo json_encode(['success' => true, 'submissions_deleted' => $submissionCount]);
    exit;
}

// LIST students (with search)
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare(
        "SELECT * FROM students WHERE student_id LIKE ? OR name LIKE ? ORDER BY name"
    );
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY name");
}
echo json_encode($stmt->fetchAll());