<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$studentId = trim($_POST['student_id'] ?? '');
$quizId    = (int)($_POST['quiz_id'] ?? 0);
$email     = trim($_POST['email'] ?? '');

if (empty($studentId) || !$quizId || empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$pdo = getDB();

// Verify student exists
$stmt = $pdo->prepare("SELECT 1 FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found']);
    exit;
}

// Save the entered email to the student's record (update if different or empty)
$stmt = $pdo->prepare("SELECT email FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$currentEmail = $stmt->fetchColumn();

if ($email !== $currentEmail) {
    $stmt = $pdo->prepare("UPDATE students SET email = ? WHERE student_id = ?");
    $stmt->execute([$email, $studentId]);
}

// Verify quiz is active
$stmt = $pdo->prepare("SELECT quiz_id, title, type, time_limit_minutes, available_from, available_until FROM quizzes WHERE quiz_id = ? AND is_active = 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not available']);
    exit;
}

// Check date/time availability window
$now = date('Y-m-d H:i:s');
if ($quiz['available_from'] && $now < $quiz['available_from']) {
    http_response_code(403);
    echo json_encode(['error' => 'This quiz is not yet available. It opens on ' . date('M j, Y g:i A', strtotime($quiz['available_from']))]);
    exit;
}
if ($quiz['available_until'] && $now > $quiz['available_until']) {
    http_response_code(403);
    echo json_encode(['error' => 'This quiz has expired. It was available until ' . date('M j, Y g:i A', strtotime($quiz['available_until']))]);
    exit;
}

// Check for existing submission
$stmt = $pdo->prepare("SELECT 1 FROM submissions WHERE student_id = ? AND quiz_id = ?");
$stmt->execute([$studentId, $quizId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Already submitted']);
    exit;
}

// Count questions
$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$totalItems = (int)$stmt->fetchColumn();

if ($totalItems === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'This quiz has no questions yet']);
    exit;
}

// Create submission record
$stmt = $pdo->prepare(
    "INSERT INTO submissions (student_id, quiz_id, email_used, total_items, started_at)
     VALUES (?, ?, ?, ?, NOW())"
);
$stmt->execute([$studentId, $quizId, $email, $totalItems]);
$submissionId = $pdo->lastInsertId();

// Get questions WITHOUT correct_answer
$stmt = $pdo->prepare(
    "SELECT question_id, question_type, question_text, option_a, option_b, option_c, option_d, sort_order
     FROM questions WHERE quiz_id = ? ORDER BY sort_order"
);
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

echo json_encode([
    'success'       => true,
    'submission_id' => $submissionId,
    'quiz'          => $quiz,
    'questions'     => $questions,
]);