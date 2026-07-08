<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Email — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="student-container">
    <div class="step-indicator">
        <span class="step done">1</span>
        <span class="step-line"></span>
        <span class="step active">2</span>
        <span class="step-line"></span>
        <span class="step">3</span>
    </div>
    <h1>Enter Your Email</h1>
    <p class="page-description">Your score will be sent to this email address.</p>
    <form id="emailForm">
        <label>Email <input type="email" name="email" id="emailInput" required autofocus></label>
        <button type="submit">Continue</button>
    </form>
    <p id="error" style="color:red;display:none;"></p>
</div>

<script>
const student = JSON.parse(sessionStorage.getItem('student'));
const quiz = JSON.parse(sessionStorage.getItem('quiz'));

if (!student || !quiz) {
    window.location.href = 'index.php';
}

// If resuming, skip the email page — go straight to quiz
if (sessionStorage.getItem('resuming') === '1') {
    window.location.href = 'quiz.php';
}

if (student.email) {
    document.getElementById('emailInput').value = student.email;
}

document.getElementById('emailForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const email = document.getElementById('emailInput').value.trim();
    if (!email) {
        const err = document.getElementById('error');
        err.textContent = 'Email is required';
        err.style.display = 'block';
        return;
    }
    sessionStorage.setItem('email', email);
    window.location.href = 'quiz.php';
});
</script>
</body>
</html>