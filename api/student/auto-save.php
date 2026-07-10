<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$submissionId    = (int)($_POST['submission_id'] ?? 0);
$answersJson     = $_POST['answers'] ?? '{}';
$violations      = (int)($_POST['violations'] ?? 0);
$currentQuestion = (int)($_POST['current_question'] ?? 0);
$studentId       = trim($_POST['student_id'] ?? '');

if (!$submissionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing submission_id']);
    exit;
}

// Validate JSON
$answers = json_decode($answersJson, true);
if (!is_array($answers)) {
    $answers = [];
}

$pdo = getDB();

// Verify submission exists, is not yet submitted, AND belongs to the requesting student
$stmt = $pdo->prepare("SELECT submitted_at, student_id FROM submissions WHERE submission_id = ?");
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

// Identity verification: if student_id is provided, it must match
if (!empty($studentId) && $submission['student_id'] !== $studentId) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Save draft state
$stmt = $pdo->prepare(
    "UPDATE submissions
     SET draft_answers = ?, violations = ?, current_question = ?
     WHERE submission_id = ?"
);
$stmt->execute([
    json_encode($answers),
    $violations,
    $currentQuestion,
    $submissionId,
]);

echo json_encode(['success' => true]);