<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$studentId    = trim($_POST['student_id'] ?? '');

if (!$submissionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing submission_id']);
    exit;
}

$pdo = getDB();

$stmt = $pdo->prepare(
    "SELECT score, total_items, submitted_at, student_id
     FROM submissions WHERE submission_id = ?"
);
$stmt->execute([$submissionId]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    echo json_encode(['error' => 'Submission not found']);
    exit;
}

// Identity check: if student_id provided, it must match
if (!empty($studentId) && $submission['student_id'] !== $studentId) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!$submission['submitted_at']) {
    http_response_code(400);
    echo json_encode(['error' => 'Quiz not yet submitted']);
    exit;
}

echo json_encode([
    'success' => true,
    'score'   => (int)$submission['score'],
    'total'   => (int)$submission['total_items'],
]);