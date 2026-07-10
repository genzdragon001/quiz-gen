<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE faculty_id = ?");
$stmt->execute([$faculty['faculty_id']]);
$quizCount = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$studentCount = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM submissions s JOIN quizzes q ON s.quiz_id = q.quiz_id WHERE q.faculty_id = ?"
);
$stmt->execute([$faculty['faculty_id']]);
$submissionCount = $stmt->fetchColumn();
?>

<h1>Dashboard</h1>
<div class="stats">
    <div class="stat-card stat-card-blue">
        <h3><?= $quizCount ?></h3>
        <p>Quizzes</p>
    </div>
    <div class="stat-card stat-card-green">
        <h3><?= $studentCount ?></h3>
        <p>Students</p>
    </div>
    <div class="stat-card stat-card-orange">
        <h3><?= $submissionCount ?></h3>
        <p>Submissions</p>
    </div>
</div>

<h2>Your Quizzes</h2>
<table>
    <thead>
        <tr><th>Code</th><th>Title</th><th>Type</th><th>Status</th><th>Deadline</th><th>Quiz Link</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE faculty_id = ? ORDER BY created_at DESC");
    $stmt->execute([$faculty['faculty_id']]);
    while ($quiz = $stmt->fetch()):
        $code = $quiz['quiz_code'] ? str_pad($quiz['quiz_code'], 4, '0', STR_PAD_LEFT) : '';
        $link = BASE_URL . 'student/index.php?code=' . $quiz['quiz_code'];
    ?>
        <tr>
            <td><strong><?= $code ?: '—' ?></strong></td>
            <td><?= htmlspecialchars($quiz['title']) ?></td>
            <td><?= $quiz['type'] === 'MCQ' ? 'Multiple Choice' : ($quiz['type'] === 'TF' ? 'True/False' : ($quiz['type'] === 'IDENTIFICATION' ? 'Identification' : 'Mixed')) ?></td>
            <td><?= $quiz['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td>
                <?php
                if ($quiz['available_until']) {
                    $deadline = strtotime($quiz['available_until']);
                    $expired = $deadline < time();
                    $label = date('M j, Y g:i A', $deadline);
                    if ($expired) {
                        echo '<span class="deadline-expired">' . htmlspecialchars($label) . ' (Expired)</span>';
                    } else {
                        echo '<span class="deadline-active">' . htmlspecialchars($label) . '</span>';
                    }
                } else {
                    echo '<span class="deadline-none">No deadline</span>';
                }
                ?>
            </td>
            <td>
                <?php if ($code): ?>
                <input type="text" class="quiz-link-input" value="<?= htmlspecialchars($link) ?>" readonly onclick="this.select()">
                <a href="#" class="copy-link" data-link="<?= htmlspecialchars($link, ENT_QUOTES) ?>">Copy</a>
                <?php else: ?>
                —
                <?php endif; ?>
            </td>
            <td>
                <a href="quiz-edit.php?id=<?= $quiz['quiz_id'] ?>" class="btn-sm btn-edit">Edit</a>
                <a href="grades.php?quiz_id=<?= $quiz['quiz_id'] ?>" class="btn-sm btn-grades">Grades</a>
                <a href="#" class="btn-sm btn-del delete-quiz" data-quiz-id="<?= $quiz['quiz_id'] ?>" data-quiz-title="<?= htmlspecialchars($quiz['title'], ENT_QUOTES) ?>">Delete</a>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<script>
document.querySelectorAll('.copy-link').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var link = this.dataset.link;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(link).then(function() {
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = 'Copy'; }, 1500);
            }).catch(function() {
                fallbackCopy(link, btn);
            });
        } else {
            fallbackCopy(link, btn);
        }
    });
});

function fallbackCopy(text, btn) {
    var input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    try { document.execCommand('copy'); btn.textContent = 'Copied!'; setTimeout(function() { btn.textContent = 'Copy'; }, 1500); }
    catch(err) { alert('Copy this link: ' + text); }
    document.body.removeChild(input);
}

document.querySelectorAll('.delete-quiz').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var quizId   = this.dataset.quizId;
        var title    = this.dataset.quizTitle;
        if (!confirm('Delete quiz "' + title + '"? This removes its questions and submissions too.')) return;

        var fd = new FormData();
        fd.append('quiz_id', quizId);
        var body = new URLSearchParams(fd);

        fetchWithCsrf('<?= BASE_URL ?>api/faculty/quizzes.php', {
            method: 'DELETE',
            body: body
        })
        .then(function(data) {
            var row = btn.closest('tr');
            if (row) row.remove();
        })
        .catch(function(err) { alert(err.message); });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>