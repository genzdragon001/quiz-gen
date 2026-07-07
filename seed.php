<?php
// Run this ONCE to create the first faculty account, then DELETE this file.
// Usage: visit https://yourdomain.com/quiz-gen/seed.php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$pdo = getDB();

// Check if faculty table is empty
$stmt = $pdo->query("SELECT COUNT(*) FROM faculty");
if ($stmt->fetchColumn() > 0) {
    die("Faculty already exists. Delete this file.");
}

$name     = 'Admin';
$email    = 'admin@example.com';     // CHANGE THIS
$password = 'changeme123';           // CHANGE THIS
$hash     = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO faculty (name, email, password_hash) VALUES (?, ?, ?)");
$stmt->execute([$name, $email, $hash]);

echo "Faculty account created!<br>";
echo "Email: $email<br>";
echo "Password: $password<br>";
echo "<strong>DELETE THIS FILE NOW.</strong>";