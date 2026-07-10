<?php
// Application configuration
// Copy this file to config.php and fill in your real credentials.

define('APP_NAME', 'Online Quiz Generator');
define('BASE_URL', 'http://localhost/quiz-gen/');  // CHANGE for production

// SMTP settings for PHPMailer (Gmail with App Password)
// Generate an App Password: https://myaccount.google.com/apppasswords
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-16-char-app-password');
define('SMTP_FROM', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Online Quiz System');

// Anti-cheat
define('MAX_VIOLATIONS', 3);  // tab-switch strikes before auto-submit

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 7200);
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);
} else {
    session_set_cookie_params(7200, '/', '', false, true);
}
session_start();

// Set timezone to Asia/Manila (matches DB server)
date_default_timezone_set('Asia/Manila');