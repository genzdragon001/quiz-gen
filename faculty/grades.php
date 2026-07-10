<?php
require_once __DIR__ . '/../includes/header.php';

$quizId = (int)($_GET['quiz_id'] ?? 0);
$pdo = getDB();

// Get quiz list for filter dropdown
$stmt = $pdo->prepare("SELECT quiz_id, title FROM quizzes WHERE faculty_id = ? ORDER BY created_at DESC");
$stmt->execute([$faculty['faculty_id']]);
$quizzes = $stmt->fetchAll();
?>

<h1>Grades</h1>

<form method="GET" style="margin-bottom:1rem;">
    <label>Filter by Quiz:
        <select name="quiz_id" onchange="this.form.submit()">
            <option value="">All Quizzes</option>
            <?php foreach ($quizzes as $q): ?>
                <option value="<?= $q['quiz_id'] ?>" <?= $quizId == $q['quiz_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($q['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<button type="button" class="btn-del" id="deleteAllBtn" style="margin-bottom:1rem;" onclick="deleteAllSubmissions()">
    Delete All Records<?= $quizId ? ' (This Quiz)' : ' (All Quizzes)' ?>
</button>

<a href="<?= BASE_URL ?>api/faculty/grades.php?export=csv<?= $quizId ? '&quiz_id=' . $quizId : '' ?>" class="btn-sm btn-export" style="margin-bottom:1rem;display:inline-block;text-decoration:none;">
    Export to CSV (Excel)
</a>

<table>
    <thead>
        <tr>
            <th>Student</th>
            <th>Quiz</th>
            <th>Score</th>
            <th>%</th>
            <th>Violations</th>
            <th>Flagged</th>
            <th>Timer</th>
            <th>Submitted</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="gradesTable">Loading...</tbody>
</table>

<script>
// Keep track of timer intervals so we can clear them on reload
let timerIntervals = {};

async function loadGrades() {
    // Clear existing timers
    Object.values(timerIntervals).forEach(clearInterval);
    timerIntervals = {};

    const quizId = <?= $quizId ?>;
    const url = '<?= BASE_URL ?>api/faculty/grades.php' + (quizId ? '?quiz_id=' + quizId : '');
    try {
        const grades = await fetchJson(url);

        document.getElementById('gradesTable').innerHTML = grades.map(g => {
            const isInProgress = !g.submitted_at;
            const isFlagged = g.flagged == 1;
            const rowClass = isFlagged ? 'flagged-row' : (isInProgress ? 'in-progress-row' : '');

            // Timer cell
            let timerCell = '—';
            if (isInProgress && g.deadline) {
                timerCell = '<span class="live-timer" data-deadline="' + g.deadline + '" data-submission-id="' + g.submission_id + '">--:--</span>';
            } else if (isInProgress) {
                timerCell = '<span class="timer-unknown">No deadline</span>';
            }

            // Actions
            let actions = '';
            if (g.submitted_at) {
                actions += '<a href="submission-review.php?id=' + g.submission_id + '" class="btn-sm btn-edit">Review</a> ';
            }
            if (isFlagged) {
                actions += '<button class="btn-sm btn-retract" onclick="retractSubmission(' + g.submission_id + ')">Retract</button> ';
            }
            actions += '<button class="btn-sm btn-del" onclick="deleteSubmission(' + g.submission_id + ')">Delete</button>';

            return '<tr class="' + rowClass + '">' +
                '<td>' + escapeHtml(g.student_name || g.student_id) + '</td>' +
                '<td>' + escapeHtml(g.quiz_title || '') + '</td>' +
                '<td>' + g.score + ' / ' + g.total_items + '</td>' +
                '<td>' + (g.total_items > 0 ? Math.round((g.score / g.total_items) * 100) : 0) + '%</td>' +
                '<td>' + g.violations + '</td>' +
                '<td>' + (isFlagged ? '<span class="flagged-badge">FLAGGED</span>' : (isInProgress ? '<span class="in-progress-badge">In Progress</span>' : '--')) + '</td>' +
                '<td id="timer-' + g.submission_id + '">' + timerCell + '</td>' +
                '<td>' + (g.submitted_at || '—') + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }).join('') || '<tr><td colspan="9">No submissions yet.</td></tr>';

        // Start live timers for in-progress submissions
        startLiveTimers(grades);
    } catch(e) {
        document.getElementById('gradesTable').innerHTML = '<tr><td colspan="9" style="color:red;">Failed to load grades.</td></tr>';
    }
}

function startLiveTimers(grades) {
    grades.forEach(g => {
        if (!g.submitted_at && g.deadline) {
            const cell = document.getElementById('timer-' + g.submission_id);
            if (!cell) return;

            const updateTimer = () => {
                const now = new Date();
                const deadline = new Date(g.deadline.replace(' ', 'T'));
                const remaining = Math.max(0, Math.floor((deadline - now) / 1000));

                if (remaining <= 0) {
                    cell.innerHTML = '<span class="timer-expired">Expired</span>';
                    clearInterval(timerIntervals[g.submission_id]);
                    return;
                }

                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                const display = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

                let cssClass = 'timer-ok';
                if (remaining <= 60) cssClass = 'timer-critical';
                else if (remaining <= 300) cssClass = 'timer-warning';

                cell.innerHTML = '<span class="' + cssClass + '">' + display + '</span>';
            };

            updateTimer();
            timerIntervals[g.submission_id] = setInterval(updateTimer, 1000);
        }
    });
}

async function retractSubmission(submissionId) {
    if (!confirm('Retract this flagged submission? The student will be able to resume the quiz with a fresh timer.')) return;

    var formData = new FormData();
    formData.append('retract', '1');
    formData.append('submission_id', submissionId);

    try {
        const data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/grades.php', {
            method: 'POST',
            body: formData
        });
        alert(data.message || 'Submission retracted.');
        loadGrades();
    } catch(err) {
        alert(err.message);
    }
}

async function deleteSubmission(submissionId) {
    if (!confirm('Delete this submission record?')) return;

    var body = new URLSearchParams();
    body.append('submission_id', submissionId);

    try {
        await fetchWithCsrf('<?= BASE_URL ?>api/faculty/grades.php', {
            method: 'DELETE',
            body: body
        });
        loadGrades();
    } catch(err) {
        alert(err.message);
    }
}

async function deleteAllSubmissions() {
    var scope = <?= $quizId ?> ? 'all submissions for this quiz' : 'ALL submissions for all your quizzes';
    if (!confirm('Delete ' + scope + '? This cannot be undone.')) return;

    var formData = new FormData();
    formData.append('delete_all', '1');
    <?php if ($quizId): ?>
    formData.append('quiz_id', <?= $quizId ?>);
    <?php endif; ?>

    try {
        const data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/grades.php<?= $quizId ? '?quiz_id=' . $quizId : '' ?>', {
            method: 'POST',
            body: formData
        });
        alert('Deleted ' + data.deleted + ' record(s).');
        loadGrades();
    } catch(err) {
        alert(err.message);
    }
}

// Auto-refresh every 30 seconds to keep timers accurate
setInterval(loadGrades, 30000);

loadGrades();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>