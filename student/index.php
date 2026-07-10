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

    <!-- Resume banner (shown if quiz.js saved state to localStorage) -->
    <div id="resumeBanner" style="display:none;" class="resume-banner">
        <p id="resumeText"></p>
        <button type="button" id="resumeBtn" class="btn-resume">Resume Quiz</button>
        <button type="button" id="discardBtn" class="btn-discard">Start New</button>
    </div>

    <form id="verifyForm">
        <label>Student ID <input type="text" name="student_id" id="studentIdInput" required <?= $prefillCode ? '' : 'autofocus' ?>></label>
        <label>Quiz Code <input type="text" name="quiz_code" id="quizCodeInput" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="4-digit code" value="<?= htmlspecialchars($prefillCode) ?>" <?= $prefillCode ? 'autofocus' : '' ?>></label>

        <div class="anti-cheat-notice">
            <h3>Anti-Cheat Rules</h3>
            <ul>
                <li>Right-click, copy, paste, and cut are disabled.</li>
                <li>Keyboard shortcuts (Ctrl+C, Ctrl+V, Ctrl+A, Ctrl+U, F12, DevTools) are blocked.</li>
                <li>Switching tabs or leaving the quiz window counts as a violation.</li>
                <li>You are allowed a maximum of <?= MAX_VIOLATIONS ?> tab-switch violations.</li>
                <li>On the <?= MAX_VIOLATIONS ?>rd violation, your quiz will be automatically submitted and flagged.</li>
                <li>A timer runs during the quiz &mdash; it auto-submits when time is up.</li>
                <li>If you lose connection or accidentally close the tab, you can resume from where you left off (as long as time hasn't expired and you haven't exhausted violations).</li>
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

// Check for saved quiz sessions in localStorage (from a previous tab close/refresh)
(function checkForResume() {
    var foundKey = null;
    var foundData = null;
    try {
        for (var i = 0; i < localStorage.length; i++) {
            var key = localStorage.key(i);
            if (key && key.indexOf('quiz_session_') === 0) {
                var raw = localStorage.getItem(key);
                var data = JSON.parse(raw);
                if (data && data.submissionId) {
                    foundKey = key;
                    foundData = data;
                    break;
                }
            }
        }
    } catch(e) { return; }

    if (foundData) {
        var banner = document.getElementById('resumeBanner');
        var text = document.getElementById('resumeText');
        banner.style.display = 'block';
        text.textContent = 'You have an unfinished quiz. Would you like to resume?';
        startBtn.disabled = true;
        agreeCheckbox.disabled = true;

        document.getElementById('resumeBtn').addEventListener('click', function() {
            sessionStorage.setItem('student', JSON.stringify({ student_id: foundData.studentId }));
            sessionStorage.setItem('quiz', JSON.stringify({ quiz_id: foundData.quizId }));
            sessionStorage.setItem('email', foundData.email);
            sessionStorage.setItem('submission_id', foundData.submissionId);
            sessionStorage.setItem('resuming', '1');
            window.location.href = 'quiz.php';
        });

        document.getElementById('discardBtn').addEventListener('click', function() {
            try { localStorage.removeItem(foundKey); } catch(e) {}
            banner.style.display = 'none';
            startBtn.disabled = true;
            agreeCheckbox.disabled = false;
            agreeCheckbox.checked = false;
        });
    }
})();

document.getElementById('verifyForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const err = document.getElementById('error');

    try {
        const resp = await fetch('<?= BASE_URL ?>api/student/verify.php', { method: 'POST', body: form });
        if (!resp.ok) {
            const data = await resp.json();
            err.textContent = data.error || 'Verification failed';
            err.style.display = 'block';
            return;
        }
        const data = await resp.json();

        if (data.success) {
            sessionStorage.setItem('student', JSON.stringify(data.student));
            sessionStorage.setItem('quiz', JSON.stringify(data.quiz));

            if (data.can_resume) {
                sessionStorage.setItem('submission_id', data.submission_id);
                sessionStorage.setItem('resuming', '1');
                // Set email from the existing submission so quiz.js doesn't redirect
                sessionStorage.setItem('email', data.email_used || data.student.email || '');
                window.location.href = 'quiz.php';
            } else {
                sessionStorage.removeItem('submission_id');
                sessionStorage.removeItem('resuming');
                // Skip email page if student already has email in DB
                if (data.has_email && data.student && data.student.email) {
                    sessionStorage.setItem('email', data.student.email);
                    window.location.href = 'quiz.php';
                } else {
                    window.location.href = 'email.php';
                }
            }
        } else {
            err.textContent = data.error;
            err.style.display = 'block';
        }
    } catch(ex) {
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
    }
});
</script>
</body>
</html>