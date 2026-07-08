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
const MAX_VIOLATIONS = 3;

// localStorage keys (per submission so different quizzes don't collide)
const LS_KEY = 'quiz_session';

function getLSKey() {
    return LS_KEY + '_' + submissionId;
}

// Save full session state to localStorage
function saveToLocal() {
    if (!submissionId) return;
    try {
        localStorage.setItem(getLSKey(), JSON.stringify({
            submissionId: submissionId,
            studentId: sessionStorage.getItem('student_id') || '',
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

    // Start quiz on server (or resume)
    const form = new FormData();
    form.append('student_id', student.student_id);
    form.append('quiz_id', quiz.quiz_id);
    form.append('email', email);
    if (resumeSubmissionId) {
        form.append('submission_id', resumeSubmissionId);
    }

    const resp = await fetch('../api/student/start-quiz.php', { method: 'POST', body: form });
    const data = await resp.json();

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
    startTimer();
    setupAntiCheat();
    updateProgressBar();

    // Start auto-save every 15 seconds
    autoSaveTimer = setInterval(autoSave, 15000);

    // Save to localStorage on every answer change and navigation
    // (handled inside setAnswer and navigation handlers)
})();

// --- Progress Bar ---
function updateProgressBar() {
    const pct = ((currentIndex + 1) / questions.length) * 100;
    const bar = document.getElementById('quizProgressBar');
    if (bar) bar.style.width = pct + '%';
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
        container.innerHTML = `
            <label class="option ${savedAnswer === 'T' ? 'selected' : ''}">
                <input type="radio" name="answer" value="T" ${savedAnswer === 'T' ? 'checked' : ''}
                       onchange="setAnswer(${q.question_id}, 'T')"> True
            </label>
            <label class="option ${savedAnswer === 'F' ? 'selected' : ''}">
                <input type="radio" name="answer" value="F" ${savedAnswer === 'F' ? 'checked' : ''}
                       onchange="setAnswer(${q.question_id}, 'F')"> False
            </label>
        `;
    } else if (qType === 'IDENTIFICATION') {
        container.innerHTML = `
            <input type="text" class="ident-input" id="identInput"
                   placeholder="Type your answer here"
                   value="${savedAnswer ? savedAnswer.replace(/"/g, '&quot;') : ''}"
                   oninput="setAnswer(${q.question_id}, this.value)">
        `;
    } else {
        // MCQ (default)
        const options = [
            { key: 'A', text: q.option_a },
            { key: 'B', text: q.option_b },
            { key: 'C', text: q.option_c },
            { key: 'D', text: q.option_d },
        ];
        container.innerHTML = options.map(o => `
            <label class="option ${savedAnswer === o.key ? 'selected' : ''}">
                <input type="radio" name="answer" value="${o.key}" ${savedAnswer === o.key ? 'checked' : ''}
                       onchange="setAnswer(${q.question_id}, '${o.key}')"> ${o.key}. ${o.text}
            </label>
        `).join('');
    }

    // Nav buttons
    document.getElementById('prevBtn').disabled = currentIndex === 0;
    if (currentIndex === questions.length - 1) {
        document.getElementById('nextBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'inline-block';
    } else {
        document.getElementById('nextBtn').style.display = 'inline-block';
        document.getElementById('submitBtn').style.display = 'none';
    }
    updateProgressBar();

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

document.getElementById('submitBtn').addEventListener('click', () => {
    submitting = true; // prevent confirm() dialog from triggering violations
    const btn = document.getElementById('submitBtn');
    const unanswered = questions.filter(q => !answers[q.question_id]).length;
    if (unanswered > 0) {
        if (!confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) {
            submitting = false;
            return;
        }
    } else {
        if (!confirm('Submit your quiz? You cannot change answers after submission.')) {
            submitting = false;
            return;
        }
    }
    btn.disabled = true;
    btn.textContent = 'Submitting...';
    submitQuiz();
});

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
            submitQuiz();
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
            try {
                navigator.sendBeacon('../api/student/auto-save.php', payload);
            } catch(e) {}
        }
    });
}

function recordViolation() {
    violations++;
    const warning = document.getElementById('violationWarning');
    warning.textContent = `WARNING: Tab switch detected! (${violations}/${MAX_VIOLATIONS})`;
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
        submitQuiz(true); // flagged
    }
}

// --- Submit ---
async function submitQuiz(flagged = false) {
    clearInterval(timerInterval);
    clearInterval(autoSaveTimer);

    const form = new FormData();
    form.append('submission_id', submissionId);
    form.append('answers', JSON.stringify(answers));
    form.append('violations', violations);
    form.append('flagged', flagged ? '1' : '0');

    const resp = await fetch('../api/student/submit-quiz.php', { method: 'POST', body: form });
    const data = await resp.json();

    if (data.success) {
        sessionStorage.setItem('score', data.score);
        sessionStorage.setItem('total', data.total);
        clearLocal();
        window.location.href = 'result.php';
    } else {
        alert('Error submitting quiz: ' + data.error);
        submitting = false;
        const btn = document.getElementById('submitBtn');
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Quiz'; }
    }
}