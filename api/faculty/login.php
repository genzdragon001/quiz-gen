<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT faculty_id, name, password_hash FROM faculty WHERE email = ?");
$stmt->execute([$email]);
$faculty = $stmt->fetch();

if (!$faculty || !password_verify($password, $faculty['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

$_SESSION['faculty_id']   = $faculty['faculty_id'];
$_SESSION['faculty_name'] = $faculty['name'];

echo json_encode([
    'success'    => true,
    'faculty_id' => $faculty['faculty_id'],
    'name'       => $faculty['name'],
    'redirect'   => BASE_URL . 'faculty/dashboard.php',
]);