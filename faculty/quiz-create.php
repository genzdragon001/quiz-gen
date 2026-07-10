<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h1>Create New Quiz</h1>
<form id="quizForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <label>Title: <input type="text" name="title" required></label>

    <fieldset class="type-group">
        <legend>Question Types (check the types you want, then enter how many items each)</legend>
        <div class="type-row">
            <label><input type="checkbox" name="type_MCQ" value="MCQ"> Multiple Choice</label>
            <label>Items: <input type="number" name="num_MCQ" min="0" value="0" disabled></label>
        </div>
        <div class="type-row">
            <label><input type="checkbox" name="type_TF" value="TF"> True or False</label>
            <label>Items: <input type="number" name="num_TF" min="0" value="0" disabled></label>
        </div>
        <div class="type-row">
            <label><input type="checkbox" name="type_IDENTIFICATION" value="IDENTIFICATION"> Identification</label>
            <label>Items: <input type="number" name="num_IDENTIFICATION" min="0" value="0" disabled></label>
        </div>
    </fieldset>

    <label>Time Limit (minutes): <input type="number" name="time_limit_minutes" value="30" min="1" max="600" required></label>
    <label>Available From: <input type="datetime-local" name="available_from"> <small>(Asia/Manila time)</small></label>
    <label>Available Until: <input type="datetime-local" name="available_until"> <small>(Asia/Manila time)</small></label>
    <label><input type="checkbox" name="is_active"> Active (students can take it)</label>
    <button type="submit">Create Quiz</button>
</form>
<p id="msg"></p>

<script>
// Enable/disable the items input based on checkbox state
document.querySelectorAll('.type-group input[type="checkbox"]').forEach(function(cb) {
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

document.getElementById('quizForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    var types = ['MCQ', 'TF', 'IDENTIFICATION'];
    var selected = {};
    var totalItems = 0;
    var checkedCount = 0;

    types.forEach(function(t) {
        var cb = document.querySelector('input[name="type_' + t + '"]');
        if (cb && cb.checked) {
            checkedCount++;
            var n = parseInt(document.querySelector('input[name="num_' + t + '"]').value || '0', 10);
            if (n < 0) n = 0;
            selected[t] = n;
            totalItems += n;
        }
    });

    if (checkedCount === 0) {
        var msg = document.getElementById('msg');
        msg.textContent = 'Select at least one question type';
        msg.style.color = 'red';
        return;
    }
    if (totalItems === 0) {
        var msg = document.getElementById('msg');
        msg.textContent = 'Enter at least one item for the selected type(s)';
        msg.style.color = 'red';
        return;
    }

    var form = new FormData(e.target);
    form.set('type', checkedCount > 1 ? 'MIXED' : types.find(function(t) { return selected[t] !== undefined; }));
    form.set('num_mcq', selected['MCQ'] || 0);
    form.set('num_tf', selected['TF'] || 0);
    form.set('num_identification', selected['IDENTIFICATION'] || 0);

    try {
        var data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/quizzes.php', { method: 'POST', body: form });
        var msg = document.getElementById('msg');
        msg.textContent = 'Quiz created! Code: ' + (data.quiz_code || '') + ' — Redirecting to add questions...';
        msg.style.color = 'green';
        setTimeout(() => {
            window.location.href = 'quiz-edit.php?id=' + data.quiz_id;
        }, 1000);
    } catch(err) {
        var msg = document.getElementById('msg');
        msg.textContent = err.message;
        msg.style.color = 'red';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>