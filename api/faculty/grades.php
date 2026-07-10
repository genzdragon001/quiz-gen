<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();
header('Content-Type: application/json');
$pdo = getDB();

$quizId = (int)($_GET['quiz_id'] ?? 0);

if ($quizId) {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT 1 FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
    $stmt->execute([$quizId, $faculty['faculty_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
}

// DELETE a single submission (and its answers)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    verifyCsrfToken();
    parse_str(file_get_contents("php://input"), $data);
    $submissionId = (int)($data['submission_id'] ?? 0);

    if (!$submissionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing submission_id']);
        exit;
    }

    // Verify the submission belongs to a quiz owned by this faculty
    $stmt = $pdo->prepare(
        "SELECT 1 FROM submissions s JOIN quizzes q ON s.quiz_id = q.quiz_id
         WHERE s.submission_id = ? AND q.faculty_id = ?"
    );
    $stmt->execute([$submissionId, $faculty['faculty_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Delete answers first, then submission (FK CASCADE handles this, but be explicit)
    $stmt = $pdo->prepare("DELETE FROM answers WHERE submission_id = ?");
    $stmt->execute([$submissionId]);
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE submission_id = ?");
    $stmt->execute([$submissionId]);

    echo json_encode(['success' => true]);
    exit;
}

// RETRACT a flagged submission — clear answers and reset so student can resume
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retract'])) {
    verifyCsrfToken();
    $submissionId = (int)($_POST['submission_id'] ?? 0);

    if (!$submissionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing submission_id']);
        exit;
    }

    // Verify ownership
    $stmt = $pdo->prepare(
        "SELECT s.submission_id, s.flagged, s.submitted_at, s.deadline, q.time_limit_minutes
         FROM submissions s JOIN quizzes q ON s.quiz_id = q.quiz_id
         WHERE s.submission_id = ? AND q.faculty_id = ?"
    );
    $stmt->execute([$submissionId, $faculty['faculty_id']]);
    $sub = $stmt->fetch();

    if (!$sub) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!$sub['submitted_at']) {
        http_response_code(400);
        echo json_encode(['error' => 'Submission is already in progress']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Delete all graded answers
        $stmt = $pdo->prepare("DELETE FROM answers WHERE submission_id = ?");
        $stmt->execute([$submissionId]);

        // Reset submission: clear submitted_at, flagged, score, violations; set new deadline
        $newDeadline = date('Y-m-d H:i:s', time() + (int)$sub['time_limit_minutes'] * 60);
        $stmt = $pdo->prepare(
            "UPDATE submissions
             SET submitted_at = NULL, flagged = 0, score = 0, violations = 0,
                 deadline = ?, draft_answers = NULL, current_question = 0
             WHERE submission_id = ?"
        );
        $stmt->execute([$newDeadline, $submissionId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Submission retracted. Student can now resume the quiz.',
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Retract failed: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE ALL submissions for a quiz (or all quizzes for this faculty)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    verifyCsrfToken();

    if ($quizId) {
        // Delete all submissions for this specific quiz
        $stmt = $pdo->prepare("SELECT submission_id FROM submissions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $submissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($submissionIds)) {
            $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM answers WHERE submission_id IN ($placeholders)");
            $stmt->execute($submissionIds);
            $stmt = $pdo->prepare("DELETE FROM submissions WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
        }

        echo json_encode(['success' => true, 'deleted' => count($submissionIds)]);
    } else {
        // Delete all submissions for all quizzes owned by this faculty
        $stmt = $pdo->prepare(
            "SELECT s.submission_id FROM submissions s
             JOIN quizzes q ON s.quiz_id = q.quiz_id
             WHERE q.faculty_id = ?"
        );
        $stmt->execute([$faculty['faculty_id']]);
        $submissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($submissionIds)) {
            $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM answers WHERE submission_id IN ($placeholders)");
            $stmt->execute($submissionIds);
            $stmt = $pdo->prepare(
                "DELETE FROM submissions WHERE submission_id IN ($placeholders)"
            );
            $stmt->execute($submissionIds);
        }

        echo json_encode(['success' => true, 'deleted' => count($submissionIds)]);
    }
    exit;
}

// CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($quizId) {
        $stmt = $pdo->prepare(
            "SELECT s.*, st.name AS student_name, st.student_id AS student_number, st.year_section
             FROM submissions s
             JOIN students st ON s.student_id = st.student_id
             WHERE s.quiz_id = ?
             ORDER BY st.name ASC"
        );
        $stmt->execute([$quizId]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT s.*, st.name AS student_name, st.student_id AS student_number, st.year_section, q.title AS quiz_title
             FROM submissions s
             JOIN students st ON s.student_id = st.student_id
             JOIN quizzes q ON s.quiz_id = q.quiz_id
             WHERE q.faculty_id = ?
             ORDER BY q.title ASC, st.name ASC"
        );
        $stmt->execute([$faculty['faculty_id']]);
    }

    $rows = $stmt->fetchAll();

    // Get quiz title for filename
    $filename = 'grades';
    if ($quizId) {
        $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $quizTitle = $stmt->fetchColumn();
        if ($quizTitle) {
            $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $quizTitle);
        }
    }
    $filename .= '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    if ($quizId) {
        fputcsv($output, ['Student Name', 'Student ID', 'Year/Section', 'Score', 'Total Items', 'Percentage', 'Violations', 'Flagged', 'Submitted At']);
    } else {
        fputcsv($output, ['Student Name', 'Student ID', 'Year/Section', 'Quiz', 'Score', 'Total Items', 'Percentage', 'Violations', 'Flagged', 'Submitted At']);
    }

    foreach ($rows as $row) {
        $pct = $row['total_items'] > 0 ? round(($row['score'] / $row['total_items']) * 100, 1) . '%' : '0%';
        $data = [
            $row['student_name'] ?? '',
            $row['student_number'] ?? $row['student_id'],
            $row['year_section'] ?? '',
        ];
        if (!$quizId) {
            $data[] = $row['quiz_title'] ?? '';
        }
        $data[] = $row['score'];
        $data[] = $row['total_items'];
        $data[] = $pct;
        $data[] = $row['violations'];
        $data[] = $row['flagged'] ? 'Yes' : 'No';
        $data[] = $row['submitted_at'] ?? 'In progress';
        fputcsv($output, $data);
    }

    fclose($output);
    exit;
}

// LIST submissions
if ($quizId) {
    $stmt = $pdo->prepare(
        "SELECT s.*, st.name AS student_name, st.year_section
         FROM submissions s
         JOIN students st ON s.student_id = st.student_id
         WHERE s.quiz_id = ?
         ORDER BY s.submitted_at DESC"
    );
    $stmt->execute([$quizId]);
} else {
    // All quizzes for this faculty
    $stmt = $pdo->prepare(
        "SELECT s.*, st.name AS student_name, st.year_section, q.title AS quiz_title
         FROM submissions s
         JOIN students st ON s.student_id = st.student_id
         JOIN quizzes q ON s.quiz_id = q.quiz_id
         WHERE q.faculty_id = ?
         ORDER BY s.submitted_at DESC"
    );
    $stmt->execute([$faculty['faculty_id']]);
}

echo json_encode($stmt->fetchAll());