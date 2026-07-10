<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

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

$pdo = getDB();

// --- Brute-force protection ---
$lockoutRemaining = checkLoginLockout($pdo, $email);
if ($lockoutRemaining !== null) {
    http_response_code(429);
    $mins = ceil($lockoutRemaining / 60);
    echo json_encode(['error' => "Too many failed attempts. Try again in {$mins} minute(s)."]);
    exit;
}

$stmt = $pdo->prepare("SELECT faculty_id, name, password_hash FROM faculty WHERE email = ?");
$stmt->execute([$email]);
$faculty = $stmt->fetch();

if (!$faculty || !password_verify($password, $faculty['password_hash'])) {
    recordFailedLogin($pdo, $email);
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// Success — clear failed attempts and regenerate session ID
clearFailedLogins($pdo, $email);
session_regenerate_id(true);
$_SESSION['faculty_id']        = $faculty['faculty_id'];
$_SESSION['faculty_name']      = $faculty['name'];
$_SESSION['last_activity']     = time();
$_SESSION['last_regeneration'] = time();

echo json_encode([
    'success'    => true,
    'faculty_id' => $faculty['faculty_id'],
    'name'       => $faculty['name'],
    'redirect'   => BASE_URL . 'faculty/dashboard.php',
]);