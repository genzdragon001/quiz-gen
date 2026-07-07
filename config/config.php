<?php
// Application configuration

define('APP_NAME', 'Online Quiz Generator');
define('BASE_URL', 'http://localhost/quiz-gen/');  // CHANGE for production

// SMTP settings for PHPMailer (Gmail with App Password)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'genzdragon@gmail.com');
define('SMTP_PASS', 'mgno yfqd kmzq juqx');
define('SMTP_FROM', 'genzdragon@gmail.com');
define('SMTP_FROM_NAME', 'Online Quiz System');

// Anti-cheat
define('MAX_VIOLATIONS', 3);  // tab-switch strikes before auto-submit

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Set timezone to Asia/Manila (matches DB server)
date_default_timezone_set('Asia/Manila');