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
$quizCode  = trim($_POST['quiz_code'] ?? '');

if (empty($studentId) || empty($quizCode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID and Quiz Code are required']);
    exit;
}

$pdo = getDB();

// Check student exists
$stmt = $pdo->prepare("SELECT student_id, name, email FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    echo json_encode(['error' => 'Student ID not found']);
    exit;
}

// Check quiz exists and is active — look up by 4-digit code
$stmt = $pdo->prepare("SELECT quiz_id, quiz_code, title, type, time_limit_minutes, available_from, available_until FROM quizzes WHERE quiz_code = ? AND is_active = 1");
$stmt->execute([$quizCode]);
$quiz = $stmt->fetch();

if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz code not found or quiz is not currently active']);
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

// Check if student already submitted this quiz
$stmt = $pdo->prepare("SELECT 1 FROM submissions WHERE student_id = ? AND quiz_id = ?");
$stmt->execute([$studentId, $quiz['quiz_id']]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'You have already taken this quiz']);
    exit;
}

echo json_encode([
    'success'   => true,
    'student'   => $student,
    'quiz'      => $quiz,
    'has_email' => !empty($student['email']),
]);