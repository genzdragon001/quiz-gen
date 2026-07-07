<?php
require_once __DIR__ . '/../includes/header.php';

$quizId = (int)($_GET['id'] ?? 0);
if (!$quizId) {
    echo "<p>Invalid quiz ID.</p>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
$stmt->execute([$quizId, $faculty['faculty_id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    echo "<p>Quiz not found.</p>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Determine which types are active for this quiz
$quizTypes = [];
if ($quiz['type'] === 'MIXED') {
    if ((int)$quiz['num_mcq'] > 0)           $quizTypes[] = 'MCQ';
    if ((int)$quiz['num_tf'] > 0)            $quizTypes[] = 'TF';
    if ((int)$quiz['num_identification'] > 0) $quizTypes[] = 'IDENTIFICATION';
    // If all are 0 (legacy mixed quiz), show all checked
    if (empty($quizTypes)) $quizTypes = ['MCQ', 'TF', 'IDENTIFICATION'];
} else {
    $quizTypes[] = $quiz['type'];
}
?>

<h1>Edit: <?= htmlspecialchars($quiz['title']) ?></h1>
<p>Quiz Code: <strong><?= $quiz['quiz_code'] ? str_pad($quiz['quiz_code'], 4, '0', STR_PAD_LEFT) : '—' ?></strong> (share this 4-digit code with students)</p>
<?php
$parts = [];
if ($quiz['num_mcq'] > 0)           $parts[] = $quiz['num_mcq'] . ' MCQ';
if ($quiz['num_tf'] > 0)            $parts[] = $quiz['num_tf'] . ' True/False';
if ($quiz['num_identification'] > 0) $parts[] = $quiz['num_identification'] . ' Identification';
if (!empty($parts)) echo '<p>Type: <strong>' . implode(' + ', $parts) . '</strong></p>';
?>

<!-- Quiz settings form -->
<form id="quizSettings">
    <input type="hidden" name="quiz_id" value="<?= $quiz['quiz_id'] ?>">
    <label>Title: <input type="text" name="title" value="<?= htmlspecialchars($quiz['title']) ?>" required></label>

    <fieldset class="type-group">
        <legend>Question Types & Item Counts</legend>
        <div class="type-row">
            <label><input type="checkbox" name="type_MCQ" value="MCQ" <?= in_array('MCQ', $quizTypes) ? 'checked' : '' ?>> Multiple Choice</label>
            <label>Items: <input type="number" name="num_mcq" min="0" value="<?= (int)$quiz['num_mcq'] ?>" <?= in_array('MCQ', $quizTypes) ? '' : 'disabled' ?>></label>
        </div>
        <div class="type-row">
            <label><input type="checkbox" name="type_TF" value="TF" <?= in_array('TF', $quizTypes) ? 'checked' : '' ?>> True or False</label>
            <label>Items: <input type="number" name="num_tf" min="0" value="<?= (int)$quiz['num_tf'] ?>" <?= in_array('TF', $quizTypes) ? '' : 'disabled' ?>></label>
        </div>
        <div class="type-row">
            <label><input type="checkbox" name="type_IDENTIFICATION" value="IDENTIFICATION" <?= in_array('IDENTIFICATION', $quizTypes) ? 'checked' : '' ?>> Identification</label>
            <label>Items: <input type="number" name="num_identification" min="0" value="<?= (int)$quiz['num_identification'] ?>" <?= in_array('IDENTIFICATION', $quizTypes) ? '' : 'disabled' ?>></label>
        </div>
    </fieldset>

    <label>Time Limit (min): <input type="number" name="time_limit_minutes" value="<?= $quiz['time_limit_minutes'] ?>" min="1"></label>
    <label>Available From: <input type="datetime-local" name="available_from" value="<?= $quiz['available_from'] ? date('Y-m-d\TH:i', strtotime($quiz['available_from'])) : '' ?>"></label>
    <label>Available Until: <input type="datetime-local" name="available_until" value="<?= $quiz['available_until'] ? date('Y-m-d\TH:i', strtotime($quiz['available_until'])) : '' ?>"></label>
    <label><input type="checkbox" name="is_active" <?= $quiz['is_active'] ? 'checked' : '' ?>> Active</label>
    <button type="submit">Save Settings</button>
</form>
<p id="settingsMsg"></p>

<hr>

<!-- Add question form -->
<h2>Add Question</h2>
<form id="questionForm">
    <input type="hidden" name="quiz_id" value="<?= $quiz['quiz_id'] ?>">
    <input type="hidden" name="quiz_type" id="quizType" value="<?= $quiz['type'] ?>">
    <label>Question: <textarea name="question_text" required></textarea></label>

    <?php if ($quiz['type'] === 'MIXED'): ?>
    <!-- Per-question type selector for MIXED quizzes -->
    <label>Question Type:
        <select name="question_type" id="questionTypeSelect" required>
            <option value="MCQ">Multiple Choice</option>
            <option value="TF">True / False</option>
            <option value="IDENTIFICATION">Identification</option>
        </select>
    </label>
    <div id="mcqFields">
        <label>A: <input type="text" name="option_a" required></label>
        <label>B: <input type="text" name="option_b" required></label>
        <label>C: <input type="text" name="option_c" required></label>
        <label>D: <input type="text" name="option_d" required></label>
        <label>Correct Answer:
            <select name="correct_answer_mcq" id="correctAnswerMcq" required>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </label>
    </div>
    <div id="tfFields" style="display:none;">
        <label>Correct Answer:
            <select name="correct_answer_tf" id="correctAnswerTf">
                <option value="T">True</option>
                <option value="F">False</option>
            </select>
        </label>
    </div>
    <div id="identFields" style="display:none;">
        <label>Correct Answer: <input type="text" name="correct_answer_ident" id="correctAnswerIdent" placeholder="Enter the correct answer text"></label>
    </div>

    <script>
    // Toggle which fields show based on selected question type (MIXED quizzes only)
    (function() {
        const select = document.getElementById('questionTypeSelect');
        const mcqFields = document.getElementById('mcqFields');
        const tfFields = document.getElementById('tfFields');
        const identFields = document.getElementById('identFields');
        const mcqAnswer = document.getElementById('correctAnswerMcq');
        const tfAnswer = document.getElementById('correctAnswerTf');
        const identAnswer = document.getElementById('correctAnswerIdent');

        function updateFields() {
            const t = select.value;
            mcqFields.style.display = t === 'MCQ' ? '' : 'none';
            tfFields.style.display = t === 'TF' ? '' : 'none';
            identFields.style.display = t === 'IDENTIFICATION' ? '' : 'none';

            // Toggle required attributes so only visible fields are required
            mcqAnswer.required = (t === 'MCQ');
            tfAnswer.required = (t === 'TF');
            identAnswer.required = (t === 'IDENTIFICATION');
        }
        select.addEventListener('change', updateFields);
        updateFields();
    })();
    </script>

    <?php elseif ($quiz['type'] === 'MCQ'): ?>
        <label>A: <input type="text" name="option_a" required></label>
        <label>B: <input type="text" name="option_b" required></label>
        <label>C: <input type="text" name="option_c" required></label>
        <label>D: <input type="text" name="option_d" required></label>
        <label>Correct Answer:
            <select name="correct_answer" required>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </label>
    <?php elseif ($quiz['type'] === 'TF'): ?>
        <label>Correct Answer:
            <select name="correct_answer" required>
                <option value="T">True</option>
                <option value="F">False</option>
            </select>
        </label>
    <?php else: // IDENTIFICATION ?>
        <label>Correct Answer: <input type="text" name="correct_answer" placeholder="Enter the correct answer text" required></label>
    <?php endif; ?>
    <button type="submit">Add Question</button>
</form>
<p id="questionMsg"></p>

<hr>

<!-- Bulk upload questions -->
<h2>Bulk Upload Questions</h2>
<div class="bulk-upload-section">
    <p>Upload a CSV file to add multiple questions at once for this <?= $quiz['type'] === 'MCQ' ? 'Multiple Choice' : ($quiz['type'] === 'TF' ? 'True/False' : ($quiz['type'] === 'IDENTIFICATION' ? 'Identification' : 'Mixed')) ?> quiz.</p>
    <?php if ($quiz['type'] === 'MIXED'): ?>
        <p style="font-size:0.9rem;color:#7f8c8d;">CSV columns: question_type, question_text, option_a, option_b, option_c, option_d, correct_answer<br>
        question_type: MCQ / TF / IDENTIFICATION. For MCQ, correct_answer is A/B/C/D and all 4 options are required. For TF, correct_answer is T or F (options can be empty). For IDENTIFICATION, correct_answer is the text answer (options can be empty).</p>
    <?php elseif ($quiz['type'] === 'MCQ'): ?>
        <p style="font-size:0.9rem;color:#7f8c8d;">CSV columns: question_text, option_a, option_b, option_c, option_d, correct_answer (A/B/C/D)</p>
    <?php elseif ($quiz['type'] === 'TF'): ?>
        <p style="font-size:0.9rem;color:#7f8c8d;">CSV columns: question_text, option_a, option_b, option_c, option_d, correct_answer (T or F). Options can be left empty for True/False.</p>
    <?php else: ?>
        <p style="font-size:0.9rem;color:#7f8c8d;">CSV columns: question_text, correct_answer. Options can be left empty.</p>
    <?php endif; ?>
    <div class="bulk-upload-buttons">
        <button type="button" class="btn-template" onclick="downloadQuestionTemplate()">Download Template</button>
        <label class="btn-upload">
            Choose CSV File
            <input type="file" id="questionCsvFile" accept=".csv" style="display:none;" onchange="uploadQuestionCSV(this)">
        </label>
    </div>
    <p id="questionUploadMsg"></p>
    <div id="questionUploadResults" style="display:none;margin-top:1rem;"></div>
</div>

<hr>

<!-- Question list -->
<h2>Questions (<?= $quiz['type'] === 'MCQ' ? 'Multiple Choice' : ($quiz['type'] === 'TF' ? 'True/False' : ($quiz['type'] === 'IDENTIFICATION' ? 'Identification' : 'Mixed')) ?>)</h2>
<div id="questionList">Loading...</div>

<script>
// Enable/disable the items input based on checkbox state (settings form)
document.querySelectorAll('#quizSettings .type-group input[type="checkbox"]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var numInput = this.closest('.type-row').querySelector('input[type="number"]');
        numInput.disabled = !this.checked;
        if (this.checked && parseInt(numInput.value, 10) === 0) {
            numInput.value = 1;
        }
        if (!this.checked) {
            numInput.value = 0;
        }
    });
});
</script>

<script>
const quizId = <?= $quiz['quiz_id'] ?>;
const quizType = '<?= $quiz['type'] ?>';

async function loadQuestions() {
    const resp = await fetch(`<?= BASE_URL ?>api/faculty/questions.php?quiz_id=${quizId}`);
    const questions = await resp.json();
    const container = document.getElementById('questionList');
    if (!questions.length) {
        container.innerHTML = '<p>No questions yet.</p>';
        return;
    }
    container.innerHTML = questions.map((q, i) => {
        const typeLabel = quizType === 'MIXED'
            ? ` <span style="font-size:0.8rem;background:#e2e8f0;padding:2px 8px;border-radius:4px;margin-left:6px;">${q.question_type === 'MCQ' ? 'MCQ' : (q.question_type === 'TF' ? 'T/F' : 'Identification')}</span>`
            : '';
        return `
        <div class="question-item">
            <strong>${i + 1}. ${q.question_text}${typeLabel}</strong>
            ${q.option_a ? `<br>A: ${q.option_a}` : ''}
            ${q.option_b ? `<br>B: ${q.option_b}` : ''}
            ${q.option_c ? `<br>C: ${q.option_c}` : ''}
            ${q.option_d ? `<br>D: ${q.option_d}` : ''}
            <br><button onclick="deleteQuestion(${q.question_id})">Delete</button>
        </div>
    `}).join('');
}

async function deleteQuestion(qid) {
    if (!confirm('Delete this question?')) return;
    await fetch('<?= BASE_URL ?>api/faculty/questions.php?quiz_id=' + quizId, {
        method: 'DELETE',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'question_id=' + qid
    });
    loadQuestions();
}

document.getElementById('questionForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const quizType = document.getElementById('quizType').value;

    // For MIXED quizzes, consolidate correct_answer from the visible field
    // and set question_type for the API
    if (quizType === 'MIXED') {
        const qType = document.getElementById('questionTypeSelect').value;
        form.set('question_type', qType);
        // The individual correct_answer fields have different names to avoid conflicts
        let correctAnswer;
        if (qType === 'MCQ') {
            correctAnswer = form.get('correct_answer_mcq');
        } else if (qType === 'TF') {
            correctAnswer = form.get('correct_answer_tf');
        } else {
            correctAnswer = form.get('correct_answer_ident');
        }
        form.set('correct_answer', correctAnswer);
    } else {
        // Non-MIXED: question_type matches quiz type
        form.set('question_type', quizType);
    }

    const resp = await fetch('<?= BASE_URL ?>api/faculty/questions.php?quiz_id=' + quizId, {
        method: 'POST',
        body: form
    });
    const data = await resp.json();
    const qMsg = document.getElementById('questionMsg');
    if (data.success) {
        qMsg.textContent = 'Question added!';
        qMsg.style.color = 'green';
        e.target.reset();
        // For MIXED quizzes, reset the field visibility
        if (quizType === 'MIXED') {
            document.getElementById('questionTypeSelect').dispatchEvent(new Event('change'));
        }
        loadQuestions();
        setTimeout(() => { qMsg.textContent = ''; }, 2000);
    } else {
        qMsg.textContent = data.error;
        qMsg.style.color = 'red';
    }
});

document.getElementById('quizSettings').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    var data = {};
    form.forEach(function(v, k) { data[k] = v; });

    // is_active checkbox
    var checkbox = e.target.querySelector('input[name="is_active"]');
    data.is_active = checkbox.checked ? '1' : '0';

    // Compute type from checked boxes and gather item counts
    var types = ['MCQ', 'TF', 'IDENTIFICATION'];
    var selected = {};
    var checkedCount = 0;
    types.forEach(function(t) {
        var cb = e.target.querySelector('input[name="type_' + t + '"]');
        if (cb && cb.checked) {
            checkedCount++;
            var n = parseInt(e.target.querySelector('input[name="num_' + (t === 'IDENTIFICATION' ? 'identification' : t.toLowerCase()) + '"]').value || '0', 10);
            if (n < 0) n = 0;
            selected[t] = n;
        } else {
            selected[t] = 0;
        }
    });

    if (checkedCount === 0) {
        var msg = document.getElementById('settingsMsg');
        msg.textContent = 'Select at least one question type';
        msg.style.color = 'red';
        return;
    }

    data.type = checkedCount > 1 ? 'MIXED' : types.find(function(t) { return selected[t] > 0 || e.target.querySelector('input[name="type_' + t + '"]').checked; });
    data.num_mcq = selected['MCQ'] || 0;
    data.num_tf = selected['TF'] || 0;
    data.num_identification = selected['IDENTIFICATION'] || 0;
    data.available_from = e.target.querySelector('input[name="available_from"]').value || '';
    data.available_until = e.target.querySelector('input[name="available_until"]').value || '';

    const resp = await fetch('<?= BASE_URL ?>api/faculty/quizzes.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data).toString()
    });
    const result = await resp.json();
    const sMsg = document.getElementById('settingsMsg');
    if (result.success) {
        sMsg.textContent = 'Settings saved!';
        sMsg.style.color = 'green';
        setTimeout(() => { sMsg.textContent = ''; }, 2000);
    } else {
        sMsg.textContent = result.error;
        sMsg.style.color = 'red';
    }
});

loadQuestions();

function downloadQuestionTemplate() {
    window.location.href = '<?= BASE_URL ?>api/faculty/questions-template.php?quiz_id=' + quizId;
}

async function uploadQuestionCSV(input) {
    const file = input.files[0];
    if (!file) return;
    const uMsg = document.getElementById('questionUploadMsg');
    const results = document.getElementById('questionUploadResults');
    uMsg.textContent = 'Uploading...';
    uMsg.style.color = 'blue';
    results.style.display = 'none';

    const formData = new FormData();
    formData.append('csv_file', file);

    const resp = await fetch('<?= BASE_URL ?>api/faculty/questions.php?quiz_id=' + quizId, { method: 'POST', body: formData });
    const data = await resp.json();
    uMsg.textContent = '';

    if (data.success) {
        results.style.display = 'block';
        let html = `<p style="color:green;font-weight:bold;">Imported: ${data.imported} | Skipped: ${data.skipped}</p>`;
        if (data.errors.length > 0) {
            html += '<details><summary>View errors (' + data.errors.length + ')</summary><ul style="color:red;font-size:0.9rem;">';
            data.errors.forEach(e => html += `<li>${e}</li>`);
            html += '</ul></details>';
        }
        results.innerHTML = html;
        loadQuestions();
    } else {
        uMsg.textContent = data.error;
        uMsg.style.color = 'red';
    }
    input.value = '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>