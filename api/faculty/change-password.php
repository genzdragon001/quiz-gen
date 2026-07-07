<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();
header('Content-Type: application/json');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['error' => 'New passwords do not match']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'New password must be at least 6 characters']);
    exit;
}

// Verify current password
$stmt = $pdo->prepare("SELECT password_hash FROM faculty WHERE faculty_id = ?");
$stmt->execute([$faculty['faculty_id']]);
$facultyRow = $stmt->fetch();

if (!$facultyRow || !password_verify($currentPassword, $facultyRow['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Current password is incorrect']);
    exit;
}

// Update password
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE faculty SET password_hash = ? WHERE faculty_id = ?");
$stmt->execute([$hash, $faculty['faculty_id']]);

echo json_encode(['success' => true]);