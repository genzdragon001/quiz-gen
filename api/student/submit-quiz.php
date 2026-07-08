<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$answersJson  = $_POST['answers'] ?? '{}';
$violations   = (int)($_POST['violations'] ?? 0);
$flagged      = (int)($_POST['flagged'] ?? 0);

$answers = json_decode($answersJson, true);
if (!is_array($answers)) {
    $answers = [];
}

$pdo = getDB();

// Get submission
$stmt = $pdo->prepare(
    "SELECT s.*, q.title AS quiz_title, q.type, st.name AS student_name, st.email AS student_email
     FROM submissions s
     JOIN quizzes q ON s.quiz_id = q.quiz_id
     JOIN students st ON s.student_id = st.student_id
     WHERE s.submission_id = ?"
);
$stmt->execute([$submissionId]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    echo json_encode(['error' => 'Submission not found']);
    exit;
}

if ($submission['submitted_at']) {
    http_response_code(409);
    echo json_encode(['error' => 'Already submitted']);
    exit;
}

// Get questions with correct answers
$stmt = $pdo->prepare(
    "SELECT question_id, correct_answer FROM questions WHERE quiz_id = ? ORDER BY sort_order"
);
$stmt->execute([$submission['quiz_id']]);
$questions = $stmt->fetchAll();

// Grade
$score = 0;
$total = count($questions);

$insertStmt = $pdo->prepare(
    "INSERT INTO answers (submission_id, question_id, student_answer, is_correct) VALUES (?, ?, ?, ?)"
);

foreach ($questions as $q) {
    $studentAnswer = $answers[$q['question_id']] ?? null;
    // Case-insensitive comparison for all types (identification especially)
    $isCorrect = ($studentAnswer !== null && strtolower(trim($studentAnswer)) === strtolower(trim($q['correct_answer']))) ? 1 : 0;
    if ($isCorrect) $score++;

    $insertStmt->execute([$submissionId, $q['question_id'], $studentAnswer, $isCorrect]);
}

// Update submission
$stmt = $pdo->prepare(
    "UPDATE submissions SET score = ?, total_items = ?, submitted_at = NOW(), violations = ?, flagged = ?, draft_answers = NULL WHERE submission_id = ?"
);
$stmt->execute([$score, $total, $violations, $flagged, $submissionId]);

// Send email
$emailTo = $submission['email_used'];
$subject = "Quiz Result: {$submission['quiz_title']}";
$body = "
Hello {$submission['student_name']},

You have completed the quiz: {$submission['quiz_title']}

Your score: {$score} out of {$total} (" . round(($score / max($total, 1)) * 100, 1) . "%)

" . ($flagged ? "NOTE: Your submission was flagged for tab-switching violations.\n" : "") . "
Thank you for participating.

-- " . APP_NAME;

sendEmail($emailTo, $subject, $body);

echo json_encode([
    'success' => true,
    'score'   => $score,
    'total'   => $total,
]);