<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h1>Student Management</h1>

<!-- Add student form -->
<h2>Add Student</h2>
<form id="studentForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <label>Student ID: <input type="text" name="student_id" required></label>
    <label>Full Name: <input type="text" name="name" required></label>
    <label>Email: <input type="email" name="email"></label>
    <label>Year & Section: <input type="text" name="year_section"></label>
    <button type="submit">Add Student</button>
</form>
<p id="formMsg"></p>

<hr>

<!-- Bulk upload -->
<h2>Bulk Upload Students</h2>
<div class="bulk-upload-section">
    <p>Upload a CSV file to add multiple students at once.</p>
    <div class="bulk-upload-buttons">
        <button type="button" class="btn-template" onclick="downloadTemplate()">Download Template</button>
        <label class="btn-upload">
            Choose CSV File
            <input type="file" id="csvFile" accept=".csv" style="display:none;" onchange="uploadCSV(this)">
        </label>
    </div>
    <p id="uploadMsg"></p>
    <div id="uploadResults" style="display:none;margin-top:1rem;"></div>
</div>

<hr>

<!-- Search + list -->
<h2>Student List</h2>
<input type="text" id="searchBox" placeholder="Search by ID or name..." style="width:100%;padding:0.6rem;margin-bottom:1rem;border:1px solid #ddd;border-radius:4px;">
<table>
    <thead>
        <tr><th>Student ID</th><th>Name</th><th>Email</th><th>Year/Section</th><th>Actions</th></tr>
    </thead>
    <tbody id="studentTable"></tbody>
</table>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Student</h3>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form id="editStudentForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="student_id" id="editStudentId">
            <label>Full Name <input type="text" name="name" id="editName" required></label>
            <label>Email <input type="email" name="email" id="editEmail"></label>
            <label>Year & Section <input type="text" name="year_section" id="editSection"></label>
            <button type="submit">Save Changes</button>
        </form>
        <p id="editMsg" style="margin-top:0.5rem;"></p>
    </div>
</div>

<script>
async function loadStudents(search = '') {
    try {
        const students = await fetchJson('<?= BASE_URL ?>api/faculty/students.php?search=' + encodeURIComponent(search));
        document.getElementById('studentTable').innerHTML = students.map(s =>
            '<tr>' +
                '<td>' + escapeHtml(s.student_id) + '</td>' +
                '<td>' + escapeHtml(s.name) + '</td>' +
                '<td>' + escapeHtml(s.email || '-') + '</td>' +
                '<td>' + escapeHtml(s.year_section || '-') + '</td>' +
                '<td>' +
                    '<button class="btn-sm btn-edit" onclick="openEditModal(\'' + escapeHtml(s.student_id) + '\', \'' + escapeHtml(s.name) + '\', \'' + escapeHtml(s.email || '') + '\', \'' + escapeHtml(s.year_section || '') + '\')">Edit</button> ' +
                    '<button class="btn-sm btn-del" onclick="deleteStudent(\'' + escapeHtml(s.student_id) + '\')">Delete</button>' +
                '</td>' +
            '</tr>'
        ).join('') || '<tr><td colspan="5">No students found.</td></tr>';
    } catch(e) {
        document.getElementById('studentTable').innerHTML = '<tr><td colspan="5" style="color:red;">Failed to load students.</td></tr>';
    }
}

document.getElementById('studentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const msg = document.getElementById('formMsg');
    try {
        await fetchWithCsrf('<?= BASE_URL ?>api/faculty/students.php', { method: 'POST', body: form });
        msg.textContent = 'Student added!';
        msg.style.color = 'green';
        e.target.reset();
        loadStudents();
        setTimeout(() => { msg.textContent = ''; }, 2000);
    } catch(err) {
        msg.textContent = err.message;
        msg.style.color = 'red';
    }
});

// --- Edit Modal ---
function openEditModal(sid, name, email, section) {
    document.getElementById('editStudentId').value = sid;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editSection').value = section;
    document.getElementById('editMsg').textContent = '';
    document.getElementById('editStudentModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editStudentModal').style.display = 'none';
}

document.getElementById('editStudentModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.getElementById('editStudentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = new URLSearchParams(form);
    const msg = document.getElementById('editMsg');
    try {
        await fetchWithCsrf('<?= BASE_URL ?>api/faculty/students.php', {
            method: 'PUT',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: data.toString()
        });
        msg.textContent = 'Student updated!';
        msg.style.color = 'green';
        loadStudents();
        setTimeout(() => { closeEditModal(); }, 1000);
    } catch(err) {
        msg.textContent = err.message;
        msg.style.color = 'red';
    }
});

async function deleteStudent(sid) {
    if (!confirm('Delete student ' + sid + '? This also deletes their submissions.')) return;
    try {
        const data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/students.php', {
            method: 'DELETE',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'student_id=' + encodeURIComponent(sid)
        });
        if (data.submissions_deleted > 0) {
            alert('Student deleted along with ' + data.submissions_deleted + ' submission(s).');
        }
        loadStudents();
    } catch(err) {
        alert(err.message);
    }
}

document.getElementById('searchBox').addEventListener('input', (e) => {
    loadStudents(e.target.value);
});

function downloadTemplate() {
    window.location.href = '<?= BASE_URL ?>api/faculty/students-template.php';
}

async function uploadCSV(input) {
    const file = input.files[0];
    if (!file) return;
    const msg = document.getElementById('uploadMsg');
    const results = document.getElementById('uploadResults');
    msg.textContent = 'Uploading...';
    msg.style.color = 'blue';
    results.style.display = 'none';

    const formData = new FormData();
    formData.append('csv_file', file);

    try {
        const data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/students.php', { method: 'POST', body: formData });
        msg.textContent = '';

        if (data.success) {
            results.style.display = 'block';
            let html = '<p style="color:green;font-weight:bold;">Imported: ' + data.imported + ' | Skipped: ' + data.skipped + '</p>';
            if (data.errors.length > 0) {
                html += '<details><summary>View errors (' + data.errors.length + ')</summary><ul style="color:red;font-size:0.9rem;">';
                data.errors.forEach(e => html += '<li>' + escapeHtml(e) + '</li>');
                html += '</ul></details>';
            }
            results.innerHTML = html;
            loadStudents();
        } else {
            msg.textContent = data.error;
            msg.style.color = 'red';
        }
    } catch(err) {
        msg.textContent = err.message;
        msg.style.color = 'red';
    }
    input.value = '';
}

loadStudents();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>