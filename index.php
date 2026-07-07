<?php require_once __DIR__ . '/config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="landing-container">
    <div class="landing-logo"><?= APP_NAME ?></div>
    <h1>Online Examination Platform</h1>
    <p class="landing-subtitle">Take quizzes online with anti-cheat protection and instant scoring</p>
    <div class="landing-cards">
        <a href="<?= BASE_URL ?>student/" class="landing-card landing-card-student">
            <div class="landing-card-icon">&#9998;</div>
            <h2>Student</h2>
            <p>Take a quiz</p>
        </a>
        <a href="<?= BASE_URL ?>faculty/login.php" class="landing-card landing-card-faculty">
            <div class="landing-card-icon">&#128221;</div>
            <h2>Faculty</h2>
            <p>Manage quizzes &amp; grades</p>
        </a>
    </div>
    <div class="landing-credit">
        Design and Created by: Engr. Genesis A. Tumbaga, CCpE
    </div>
</div>
</body>
</html>