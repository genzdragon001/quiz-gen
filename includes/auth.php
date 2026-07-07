<?php
// Require this at the top of any faculty-only page
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function requireFaculty(): array {
    if (empty($_SESSION['faculty_id'])) {
        header('Location: ' . BASE_URL . 'faculty/login.php');
        exit;
    }
    return [
        'faculty_id' => $_SESSION['faculty_id'],
        'name'       => $_SESSION['faculty_name'],
    ];
}

function isFacultyLoggedIn(): bool {
    return !empty($_SESSION['faculty_id']);
}