// ============================================================
// Quiz Engine + Anti-Cheat + Disconnect Recovery
// ============================================================

// --- State ---
let questions = [];
let currentIndex = 0;
let answers = {};        // { question_id: 'A'|'B'|'C'|'D'|'T'|'F'|text }
let submissionId = null;
let quizData = null;
let timeRemaining = 0;   // seconds
let timerInterval = null;
let violations = 0;
let submitting = false;  // disables anti-cheat during submit
let autoSaveTimer = null;
let initialized = false;
let studentId = '';      // for identity verification on API calls
let savingProgressInterval = null; // progress bar interval for saving overlay
const MAX_VIOLATIONS = (typeof window.QUIZ_MAX_VIOLATIONS !== 'undefined') ? window.QUIZ_MAX_VIOLATIONS : 3;

// localStorage keys (per submission so different quizzes don't collide)
const LS_KEY = 'quiz_session';

function getLSKey() {
    return LS_KEY + '_' + submissionId;
}

// --- XSS escape helper ---
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// --- Shared fetchJson wrapper ---
async function fetchJson(url, options = {}) {
    try {
        const resp = await fetch(url, options);
        if (!resp.ok) {
            let err;
            try { err = (await resp.json()).error || 'HTTP ' + resp.status; }
            catch(e) { err = 'HTTP ' + resp.status; }
            throw new Error(err);
        }
        return await resp.json();
    } catch(e) {
        console.error('fetchJson error:', e);
        throw e;
    }
}

// Save full session state to localStorage
function saveToLocal() {
    if (!submissionId) return;
    try {
        localStorage.setItem(getLSKey(), JSON.stringify({
            submissionId: submissionId,
            studentId: studentId,
            quizId: sessionStorage.getItem('quiz_id') || '',
            email: sessionStorage.getItem('email') || '',
            answers: answers,
            currentIndex: currentIndex,
            violations: violations,
            timeRemaining: timeRemaining,
            timestamp: Date.now(),
        }));
    } catch(e) { /* localStorage may be full or disabled */ }
}

// Clear localStorage for this session
function clearLocal() {
    if (!submissionId) return;
    try {
        localStorage.removeItem(getLSKey());
    } catch(e) {}
}

// Auto-save to server (fire-and-forget)
function autoSave() {
    if (!submissionId || submitting) return;
    const form = new FormData();
    form.append('submission_id', submissionId);
    form.append('answers', JSON.stringify(answers));
    form.append('violations', violations);
    form.append('current_question', currentIndex);
    form.append('student_id', studentId);

    fetch('../api/student/auto-save.php', { method: 'POST', body: form })
        .catch(() => {}); // silent — don't interrupt the student
}

// --- Init ---
(async function init() {
    const student = JSON.parse(sessionStorage.getItem('student'));
    const quiz = JSON.parse(sessionStorage.getItem('quiz'));
    const email = sessionStorage.getItem('email');
    const resumeSubmissionId = sessionStorage.getItem('submission_id');

    if (!student || !quiz || !email) {
        window.location.href = 'index.php';
        return;
    }

    studentId = student.student_id;

    // Start quiz on server (or resume)
    const form = new FormData();
    form.append('student_id', student.student_id);
    form.append('quiz_id', quiz.quiz_id);
    form.append('email', email);
    if (resumeSubmissionId) {
        form.append('submission_id', resumeSubmissionId);
    }

    let data;
    try {
        data = await fetchJson('../api/student/start-quiz.php', { method: 'POST', body: form });
    } catch(e) {
        alert('Failed to start quiz: ' + e.message);
        window.location.href = 'index.php';
        return;
    }

    // If time expired while away, server auto-submitted — go to results
    if (data.expired) {
        sessionStorage.setItem('score', data.score);
        sessionStorage.setItem('total', data.total);
        clearLocal();
        window.location.href = 'result.php';
        return;
    }

    if (!data.success) {
        alert(data.error);
        window.location.href = 'index.php';
        return;
    }

    questions = data.questions;
    submissionId = data.submission_id;
    quizData = data.quiz;
    initialized = true;

    // Restore saved state on resume
    if (data.resumed) {
        answers = data.draft_answers || {};
        violations = data.violations || 0;
        currentIndex = Math.min(data.current_question || 0, questions.length - 1);
        timeRemaining = data.time_remaining || quizData.time_limit_minutes * 60;

        // Show resume notice
        const notice = document.getElementById('resumeNotice');
        if (notice) notice.style.display = 'block';
    } else {
        timeRemaining = data.time_remaining || quizData.time_limit_minutes * 60;
    }

    // Store IDs in sessionStorage for cross-page persistence
    sessionStorage.setItem('student_id', student.student_id);
    sessionStorage.setItem('quiz_id', quiz.quiz_id);
    sessionStorage.setItem('submission_id', submissionId);

    document.getElementById('quizTitle').textContent = quizData.title;
    document.getElementById('totalQ').textContent = questions.length;

    if (questions.length === 0) {
        document.getElementById('questionText').textContent = 'This quiz has no questions.';
        return;
    }

    // If there are saved violations, show the warning
    if (violations > 0 && violations < MAX_VIOLATIONS) {
        const warning = document.getElementById('violationWarning');
        warning.textContent = 'WARNING: Tab switch detected! (' + violations + '/' + MAX_VIOLATIONS + ')';
        warning.style.display = 'block';
    }

    renderQuestion();
    renderNavigator();
    startTimer();
    setupAntiCheat();
    updateProgressBar();

    // Start auto-save every 15 seconds
    autoSaveTimer = setInterval(autoSave, 15000);
})();

// --- Progress Bar ---
function updateProgressBar() {
    const answered = questions.filter(q => answers[q.question_id]).length;
    const pct = (answered / questions.length) * 100;
    const bar = document.getElementById('quizProgressBar');
    if (bar) bar.style.width = pct + '%';
}

// --- Navigator ---
function renderNavigator() {
    const grid = document.getElementById('navigatorGrid');
    if (!grid) return;

    grid.innerHTML = questions.map((q, i) => {
        const isAnswered = !!answers[q.question_id];
        const isCurrent = i === currentIndex;
        const classes = ['nav-cell'];
        if (isAnswered) classes.push('answered');
        if (isCurrent) classes.push('current');
        return '<div class="' + classes.join(' ') + '" onclick="jumpTo(' + i + ')" role="button" tabindex="0" aria-label="Question ' + (i + 1) + '">' + (i + 1) + '</div>';
    }).join('');

    updateNavigatorSummary();
}

function updateNavigator() {
    const cells = document.querySelectorAll('.nav-cell');
    cells.forEach((cell, i) => {
        const q = questions[i];
        const isAnswered = !!answers[q.question_id];
        const isCurrent = i === currentIndex;

        cell.classList.toggle('answered', isAnswered);
        cell.classList.toggle('current', isCurrent);
    });
    updateNavigatorSummary();
}

function updateNavigatorSummary() {
    const answered = questions.filter(q => answers[q.question_id]).length;
    const unanswered = questions.length - answered;
    const answeredEl = document.getElementById('answeredCount');
    const unansweredEl = document.getElementById('unansweredCount');
    if (answeredEl) answeredEl.textContent = answered;
    if (unansweredEl) unansweredEl.textContent = unanswered;
}

function jumpTo(index) {
    if (index < 0 || index >= questions.length) return;
    currentIndex = index;
    renderQuestion();
    updateNavigator();
}

// --- Render ---
function renderQuestion() {
    const q = questions[currentIndex];
    document.getElementById('currentQ').textContent = currentIndex + 1;
    document.getElementById('questionText').textContent = q.question_text;

    const container = document.getElementById('optionsContainer');
    const savedAnswer = answers[q.question_id];

    // Determine this question's type: use per-question question_type if present,
    // otherwise fall back to the quiz-level type (for non-MIXED quizzes)
    const qType = q.question_type || quizData.type;

    if (qType === 'TF') {
        container.innerHTML =
            '<label class="option ' + (savedAnswer === 'T' ? 'selected' : '') + '">' +
                '<input type="radio" name="answer" value="T" ' + (savedAnswer === 'T' ? 'checked' : '') +
                       ' onchange="setAnswer(' + q.question_id + ', \'T\')"> True' +
            '</label>' +
            '<label class="option ' + (savedAnswer === 'F' ? 'selected' : '') + '">' +
                '<input type="radio" name="answer" value="F" ' + (savedAnswer === 'F' ? 'checked' : '') +
                       ' onchange="setAnswer(' + q.question_id + ', \'F\')"> False' +
            '</label>';
    } else if (qType === 'IDENTIFICATION') {
        container.innerHTML =
            '<input type="text" class="ident-input" id="identInput"' +
                   ' placeholder="Type your answer here"' +
                   ' value="' + (savedAnswer ? escapeHtml(savedAnswer) : '') + '"' +
                   ' oninput="setAnswer(' + q.question_id + ', this.value)">';
    } else {
        // MCQ (default)
        const options = [
            { key: 'A', text: q.option_a },
            { key: 'B', text: q.option_b },
            { key: 'C', text: q.option_c },
            { key: 'D', text: q.option_d },
        ];
        container.innerHTML = options.map(o =>
            '<label class="option ' + (savedAnswer === o.key ? 'selected' : '') + '">' +
                '<input type="radio" name="answer" value="' + o.key + '" ' + (savedAnswer === o.key ? 'checked' : '') +
                       ' onchange="setAnswer(' + q.question_id + ', \'' + o.key + '\')"> ' + o.key + '. ' + escapeHtml(o.text) +
            '</label>'
        ).join('');
    }

    // Nav buttons
    document.getElementById('prevBtn').disabled = currentIndex === 0;
    const hasAnswer = !!answers[q.question_id];
    const isLast = currentIndex === questions.length - 1;

    if (isLast) {
        document.getElementById('nextBtn').style.display = 'none';
        document.getElementById('skipBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'inline-block';
    } else if (hasAnswer) {
        document.getElementById('nextBtn').style.display = 'inline-block';
        document.getElementById('skipBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'none';
    } else {
        document.getElementById('nextBtn').style.display = 'none';
        document.getElementById('skipBtn').style.display = 'inline-block';
        document.getElementById('submitBtn').style.display = 'none';
    }
    updateProgressBar();
    updateNavigator();

    // Save current position to localStorage
    saveToLocal();
}

function setAnswer(questionId, value) {
    answers[questionId] = value;
    const q = questions[currentIndex];
    const qType = q.question_type || quizData.type;
    if (qType !== 'IDENTIFICATION') {
        renderQuestion();
    }
    // Update navigator and progress bar on every answer
    updateNavigator();
    updateProgressBar();
    // Save to localStorage on every answer
    saveToLocal();
}

// --- Navigation ---
document.getElementById('prevBtn').addEventListener('click', () => {
    if (currentIndex > 0) {
        currentIndex--;
        renderQuestion();
    }
});

document.getElementById('nextBtn').addEventListener('click', () => {
    if (currentIndex < questions.length - 1) {
        currentIndex++;
        renderQuestion();
    }
});

document.getElementById('skipBtn').addEventListener('click', () => {
    if (currentIndex < questions.length - 1) {
        currentIndex++;
        renderQuestion();
    } else {
        // On the last question, skip jumps to submit
        document.getElementById('submitBtn').style.display = 'inline-block';
        document.getElementById('nextBtn').style.display = 'none';
    }
});

document.getElementById('submitBtn').addEventListener('click', () => {
    submitting = true; // prevent confirm() dialog from triggering violations
    showSubmitConfirmation();
});

// --- Submit Confirmation Modal ---
function showSubmitConfirmation() {
    const unanswered = questions.filter(q => !answers[q.question_id]);
    const unansweredCount = unanswered.length;
    const modal = document.getElementById('submitModal');
    const title = document.getElementById('submitModalTitle');
    const message = document.getElementById('submitModalMessage');
    const listDiv = document.getElementById('submitModalUnansweredList');

    if (unansweredCount > 0) {
        title.textContent = 'You have unanswered questions!';
        message.innerHTML = 'You have <strong style="color:var(--danger);">' + unansweredCount + '</strong> unanswered question(s). ' +
            'Highlighted in red on the side. Are you sure you want to submit?';
        // Show which question numbers are unanswered
        const unansweredNums = unanswered.map(q => questions.indexOf(q) + 1);
        listDiv.innerHTML = '<span class="submit-unanswered-label">Unanswered questions:</span> ' +
            unansweredNums.map(n => '<span class="submit-unanswered-badge">' + n + '</span>').join(' ');
        listDiv.style.display = 'block';
    } else {
        title.textContent = 'Submit Quiz?';
        message.textContent = 'You have answered all questions. Are you sure you want to submit? You cannot change answers after submission.';
        listDiv.style.display = 'none';
    }

    // Highlight unanswered nav cells red
    highlightUnansweredNav();

    modal.style.display = 'flex';

    document.getElementById('submitCancelBtn').onclick = function() {
        modal.style.display = 'none';
        clearUnansweredHighlight();
        submitting = false;
    };

    document.getElementById('submitConfirmBtn').onclick = function() {
        modal.style.display = 'none';
        clearUnansweredHighlight();
        doSubmitQuiz(false);
    };
}

// Highlight unanswered navigator cells in red
function highlightUnansweredNav() {
    const cells = document.querySelectorAll('.nav-cell');
    cells.forEach((cell, i) => {
        const q = questions[i];
        if (!answers[q.question_id]) {
            cell.classList.add('unanswered-warn');
        }
    });
}

// Clear red highlight
function clearUnansweredHighlight() {
    document.querySelectorAll('.nav-cell.unanswered-warn').forEach(c => c.classList.remove('unanswered-warn'));
}

// --- Saving Overlay ---
function showSavingOverlay() {
    const overlay = document.getElementById('savingOverlay');
    const bar = document.getElementById('savingProgressBar');
    overlay.style.display = 'flex';
    bar.style.width = '0%';

    // Animate progress to 90% over ~3 seconds (hold at 90% until server responds)
    let pct = 0;
    savingProgressInterval = setInterval(() => {
        if (pct < 90) {
            pct += Math.random() * 8 + 2;
            if (pct > 90) pct = 90;
            bar.style.width = pct + '%';
        }
    }, 150);
}

function completeSavingOverlay() {
    const bar = document.getElementById('savingProgressBar');
    if (bar) bar.style.width = '100%';
    clearInterval(savingProgressInterval);
}

// Actual submit
function doSubmitQuiz(flagged) {
    const btn = document.getElementById('submitBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
    showSavingOverlay();
    // Use requestAnimationFrame + small timeout to ensure the overlay paints
    // before the async fetch begins
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            submitQuiz(flagged);
        });
    });
}

// --- Timer ---
function startTimer() {
    updateTimerDisplay();
    timerInterval = setInterval(() => {
        timeRemaining--;
        updateTimerDisplay();
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            clearInterval(autoSaveTimer);
            submitting = true;
            const btn = document.getElementById('submitBtn');
            if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
            alert('Time is up! Your quiz will be submitted automatically.');
            doSubmitQuiz(false);
        }
        // Save time to localStorage every 5 seconds
        if (timeRemaining % 5 === 0) {
            saveToLocal();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const mins = Math.floor(timeRemaining / 60);
    const secs = timeRemaining % 60;
    document.getElementById('timer').textContent =
        String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

    // Flash red when under 60 seconds
    if (timeRemaining <= 60) {
        document.getElementById('timer').style.color = 'red';
    }
}

// --- Anti-Cheat ---
function setupAntiCheat() {
    // Disable right-click
    document.addEventListener('contextmenu', (e) => e.preventDefault());

    // Disable copy/paste/cut
    document.addEventListener('copy', (e) => e.preventDefault());
    document.addEventListener('paste', (e) => e.preventDefault());
    document.addEventListener('cut', (e) => e.preventDefault());

    // Disable keyboard shortcuts for copy/paste/devtools
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && ['c', 'v', 'x', 'a', 'u'].includes(e.key.toLowerCase())) {
            e.preventDefault();
        }
        // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J (dev tools)
        if (e.key === 'F12' ||
            (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase()))) {
            e.preventDefault();
        }
    });

    // Tab switch detection — single state machine
    // Only counts a violation when going from "present" to "away"
    // Only resets when coming back. Prevents double-counting from
    // visibilitychange + blur firing for the same tab switch.
    let isAway = false;

    function handleAway() {
        if (!isAway && !submitting && initialized) {
            isAway = true;
            recordViolation();
        }
    }

    function handleReturn() {
        isAway = false;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            handleAway();
        } else {
            handleReturn();
        }
    });

    window.addEventListener('blur', () => {
        // Only count if not already away (visibilitychange may have fired first)
        handleAway();
    });

    window.addEventListener('focus', () => {
        handleReturn();
    });

    // Save state before page unload (refresh/close) — fire-and-forget
    window.addEventListener('beforeunload', () => {
        if (!submitting && submissionId) {
            saveToLocal();
            // Fire auto-save via sendBeacon (works during unload)
            const payload = new FormData();
            payload.append('submission_id', submissionId);
            payload.append('answers', JSON.stringify(answers));
            payload.append('violations', violations);
            payload.append('current_question', currentIndex);
            payload.append('student_id', studentId);
            try {
                navigator.sendBeacon('../api/student/auto-save.php', payload);
            } catch(e) {}
        }
    });
}

function recordViolation() {
    violations++;
    const warning = document.getElementById('violationWarning');
    warning.textContent = 'WARNING: Tab switch detected! (' + violations + '/' + MAX_VIOLATIONS + ')';
    warning.style.display = 'block';

    // Save state immediately on violation
    saveToLocal();
    autoSave();

    if (violations >= MAX_VIOLATIONS) {
        warning.textContent = 'Maximum violations reached. Quiz auto-submitted.';
        clearInterval(timerInterval);
        clearInterval(autoSaveTimer);
        submitting = true;
        const btn = document.getElementById('submitBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
        doSubmitQuiz(true); // flagged
    }
}

// --- Submit ---
async function submitQuiz(flagged = false) {
    clearInterval(timerInterval);
    clearInterval(autoSaveTimer);

    const form = new FormData();
    form.append('submission_id', submissionId);
    form.append('student_id', studentId);
    form.append('answers', JSON.stringify(answers));
    form.append('violations', violations);
    form.append('flagged', flagged ? '1' : '0');

    try {
        const data = await fetchJson('../api/student/submit-quiz.php', { method: 'POST', body: form });

        if (data.success) {
            completeSavingOverlay();
            sessionStorage.setItem('score', data.score);
            sessionStorage.setItem('total', data.total);
            clearLocal();
            // Brief delay so student sees 100% progress bar
            setTimeout(() => { window.location.href = 'result.php'; }, 600);
        } else {
            completeSavingOverlay();
            document.getElementById('savingOverlay').style.display = 'none';
            alert('Error submitting quiz: ' + data.error);
            submitting = false;
            const btn = document.getElementById('submitBtn');
            if (btn) { btn.disabled = false; btn.textContent = 'Submit Quiz'; }
        }
    } catch(e) {
        completeSavingOverlay();
        document.getElementById('savingOverlay').style.display = 'none';
        alert('Network error submitting quiz. Please try again.');
        submitting = false;
        const btn = document.getElementById('submitBtn');
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Quiz'; }
    }
}