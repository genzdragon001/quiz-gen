<?php require_once __DIR__ . '/../config/config.php';
$prefillCode = isset($_GET['code']) ? preg_replace('/[^0-9]/', '', $_GET['code']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="student-container">
    <div class="step-indicator">
        <span class="step active">1</span>
        <span class="step-line"></span>
        <span class="step">2</span>
        <span class="step-line"></span>
        <span class="step">3</span>
    </div>
    <h1><?= APP_NAME ?></h1>
    <form id="verifyForm">
        <label>Student ID <input type="text" name="student_id" required <?= $prefillCode ? '' : 'autofocus' ?>></label>
        <label>Quiz Code <input type="text" name="quiz_code" id="quizCodeInput" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="4-digit code" value="<?= htmlspecialchars($prefillCode) ?>" <?= $prefillCode ? 'autofocus' : '' ?>></label>

        <div class="anti-cheat-notice">
            <h3>Anti-Cheat Rules</h3>
            <ul>
                <li>Right-click, copy, paste, and cut are disabled.</li>
                <li>Keyboard shortcuts (Ctrl+C, Ctrl+V, Ctrl+A, Ctrl+U, F12, DevTools) are blocked.</li>
                <li>Switching tabs or leaving the quiz window counts as a violation.</li>
                <li>You are allowed a maximum of 3 tab-switch violations.</li>
                <li>On the 3rd violation, your quiz will be automatically submitted and flagged.</li>
                <li>A timer runs during the quiz &mdash; it auto-submits when time is up.</li>
            </ul>
            <label class="agree-label">
                <input type="checkbox" id="agreeCheckbox" required>
                I have read and agree to the anti-cheat rules. I understand that violating them will flag or auto-submit my quiz.
            </label>
        </div>

        <button type="submit" id="startBtn" disabled>Start Quiz</button>
    </form>
    <p id="error" style="color:red;display:none;"></p>
    <a href="<?= BASE_URL ?>" class="back-home">&larr; Back to Home</a>
</div>

<script>
var agreeCheckbox = document.getElementById('agreeCheckbox');
var startBtn = document.getElementById('startBtn');

agreeCheckbox.addEventListener('change', function() {
    startBtn.disabled = !this.checked;
});

document.getElementById('verifyForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const resp = await fetch('<?= BASE_URL ?>api/student/verify.php', { method: 'POST', body: form });
    const data = await resp.json();

    if (data.success) {
        sessionStorage.setItem('student', JSON.stringify(data.student));
        sessionStorage.setItem('quiz', JSON.stringify(data.quiz));
        window.location.href = 'email.php';
    } else {
        const err = document.getElementById('error');
        err.textContent = data.error;
        err.style.display = 'block';
    }
});
</script>
</body>
</html>