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
            <th>Submitted</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="gradesTable">Loading...</tbody>
</table>

<script>
async function loadGrades() {
    const quizId = <?= $quizId ?>;
    const url = `<?= BASE_URL ?>api/faculty/grades.php${quizId ? '?quiz_id=' + quizId : ''}`;
    const resp = await fetch(url);
    const grades = await resp.json();

    document.getElementById('gradesTable').innerHTML = grades.map(g => `
        <tr class="${g.flagged ? 'flagged-row' : ''}">
            <td>${g.student_name || g.student_id}</td>
            <td>${g.quiz_title || ''}</td>
            <td>${g.score} / ${g.total_items}</td>
            <td>${g.total_items > 0 ? Math.round((g.score / g.total_items) * 100) : 0}%</td>
            <td>${g.violations}</td>
            <td>${g.flagged ? 'FLAGGED' : '--'}</td>
            <td>${g.submitted_at || 'In progress'}</td>
            <td><button class="btn-sm btn-del" onclick="deleteSubmission(${g.submission_id})">Delete</button></td>
        </tr>
    `).join('') || '<tr><td colspan="8">No submissions yet.</td></tr>';
}

async function deleteSubmission(submissionId) {
    if (!confirm('Delete this submission record?')) return;

    var body = new URLSearchParams();
    body.append('submission_id', submissionId);

    const resp = await fetch('<?= BASE_URL ?>api/faculty/grades.php', {
        method: 'DELETE',
        body: body
    });
    const data = await resp.json();
    if (data.success) {
        loadGrades();
    } else {
        alert(data.error || 'Failed to delete record');
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

    const resp = await fetch('<?= BASE_URL ?>api/faculty/grades.php<?= $quizId ? '?quiz_id=' . $quizId : '' ?>', {
        method: 'POST',
        body: formData
    });
    const data = await resp.json();
    if (data.success) {
        alert('Deleted ' + data.deleted + ' record(s).');
        loadGrades();
    } else {
        alert(data.error || 'Failed to delete records');
    }
}

loadGrades();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>