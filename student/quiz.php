<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="quiz-body">
<div class="quiz-container">
    <div id="resumeNotice" style="display:none;" class="resume-notice">
        Welcome back! Your answers and timer have been restored. You can continue from where you left off.
    </div>
    <div class="quiz-header">
        <h1 id="quizTitle"></h1>
        <div class="quiz-header-right">
            <div class="timer" id="timer">--:--</div>
            <div class="progress">Question <span id="currentQ">1</span> of <span id="totalQ">0</span></div>
        </div>
    </div>

    <div class="quiz-progress-bar-container">
        <div class="quiz-progress-bar" id="quizProgressBar"></div>
    </div>

    <div class="question-card" id="questionCard">
        <p id="questionText"></p>
        <div id="optionsContainer"></div>
    </div>

    <div class="quiz-nav">
        <button id="prevBtn" disabled>Previous</button>
        <button id="nextBtn">Next</button>
        <button id="submitBtn" class="btn-submit" style="display:none;">Submit Quiz</button>
    </div>

    <div id="violationWarning" style="display:none;color:red;font-weight:bold;text-align:center;margin-top:1rem;"></div>
</div>

<script src="<?= BASE_URL ?>assets/js/quiz.js"></script>
</body>
</html>