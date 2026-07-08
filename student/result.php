<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Result — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="student-container result-page">
    <h1>Quiz Completed!</h1>
    <div class="score-display" id="scoreDisplay">
        <p class="score-label">Your Score</p>
        <h2 id="scoreText"></h2>
        <p id="percentageText" class="percentage-text"></p>
        <div class="score-bar-container">
            <div class="score-bar" id="scoreBar"></div>
        </div>
        <p class="email-note">Your score has been sent to your email.</p>
    </div>
    <a href="index.php" class="btn-back">Take Another Quiz</a>
</div>

<script>
const score = parseInt(sessionStorage.getItem('score'));
const total = parseInt(sessionStorage.getItem('total'));

if (!score && score !== 0 || !total) {
    window.location.href = 'index.php';
} else {
    const pct = Math.round((score / total) * 100);
    document.getElementById('scoreText').textContent = score + ' / ' + total;
    document.getElementById('percentageText').textContent = pct + '%';

    // Animate the score bar
    setTimeout(function() {
        document.getElementById('scoreBar').style.width = pct + '%';
        if (pct >= 75) {
            document.getElementById('scoreBar').classList.add('score-bar-pass');
        } else {
            document.getElementById('scoreBar').classList.add('score-bar-fail');
        }
    }, 300);
}

// Clear localStorage for this submission (quiz is done — no resume needed)
var subId = sessionStorage.getItem('submission_id');
if (subId) {
    try { localStorage.removeItem('quiz_session_' + subId); } catch(e) {}
}

// Clear session
sessionStorage.clear();
</script>
</body>
</html>