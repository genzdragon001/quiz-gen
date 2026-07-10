<?php
require_once __DIR__ . '/../../config/config.php';

// Destroy session completely
$_SESSION = [];
session_regenerate_id(true);
session_destroy();
header('Location: ' . BASE_URL . 'faculty/login.php');
exit;