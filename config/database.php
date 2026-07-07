<?php
// Database connection (PDO)
// Update credentials for Hostinger production

define('DB_HOST', 'localhost');
define('DB_NAME', 'quiz_generator');
define('DB_USER', 'root');       // CHANGE for production
define('DB_PASS', '');           // CHANGE for production

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}