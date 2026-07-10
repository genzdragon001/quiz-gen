<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body class="quiz-body">
<div class="quiz-layout">
    <!-- Main quiz area -->
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
            <button id="skipBtn" class="btn-skip">Skip</button>
            <button id="nextBtn">Next</button>
            <button id="submitBtn" class="btn-submit" style="display:none;">Submit Quiz</button>
        </div>

        <div id="violationWarning" style="display:none;color:red;font-weight:bold;text-align:center;margin-top:1rem;"></div>
    </div>

    <!-- Navigator sidebar -->
    <div class="quiz-navigator" id="quizNavigator">
        <h3>Questions</h3>
        <div class="navigator-summary" id="navigatorSummary">
            <span class="nav-stat answered-stat">Answered: <strong id="answeredCount">0</strong></span>
            <span class="nav-stat unanswered-stat">Unanswered: <strong id="unansweredCount">0</strong></span>
        </div>
        <div class="navigator-grid" id="navigatorGrid"></div>
        <div class="navigator-legend">
            <span class="legend-item"><span class="legend-dot legend-answered"></span> Answered</span>
            <span class="legend-item"><span class="legend-dot legend-current"></span> Current</span>
            <span class="legend-item"><span class="legend-dot legend-unanswered"></span> Unanswered</span>
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div id="submitModal" class="quiz-modal-overlay" style="display:none;">
    <div class="quiz-modal">
        <h2 id="submitModalTitle">Submit Quiz?</h2>
        <p id="submitModalMessage"></p>
        <div id="submitModalUnansweredList" class="submit-unanswered-list" style="display:none;"></div>
        <div class="quiz-modal-actions">
            <button type="button" id="submitCancelBtn" class="btn-cancel">Go Back</button>
            <button type="button" id="submitConfirmBtn" class="btn-submit-confirm">Submit Anyway</button>
        </div>
    </div>
</div>

<!-- Saving Overlay (locks page while submitting) -->
<div id="savingOverlay" class="quiz-saving-overlay" style="display:none;">
    <div class="quiz-saving-content">
        <div class="quiz-saving-spinner"></div>
        <h2>Saving your quiz...</h2>
        <p id="savingStatusText">Submitting your answers. Please wait.</p>
        <div class="quiz-saving-progress-container">
            <div class="quiz-saving-progress-bar" id="savingProgressBar"></div>
        </div>
        <p class="quiz-saving-note">Do not close this window.</p>
    </div>
</div>

<script>
// Inject PHP config values before quiz.js loads
window.QUIZ_MAX_VIOLATIONS = <?= defined('MAX_VIOLATIONS') ? MAX_VIOLATIONS : 3 ?>;
</script>
<script src="<?= BASE_URL ?>assets/js/quiz.js?v=<?= filemtime(__DIR__ . '/../assets/js/quiz.js') ?>"></script>
</body>
</html>