<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h1>Student Management</h1>

<!-- Add student form -->
<h2>Add Student</h2>
<form id="studentForm">
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

<script>
async function loadStudents(search = '') {
    const resp = await fetch(`<?= BASE_URL ?>api/faculty/students.php?search=${encodeURIComponent(search)}`);
    const students = await resp.json();
    document.getElementById('studentTable').innerHTML = students.map(s => `
        <tr>
            <td>${s.student_id}</td>
            <td>${s.name}</td>
            <td>${s.email || '-'}</td>
            <td>${s.year_section || '-'}</td>
            <td>
                <button class="btn-sm btn-edit" data-sid="${s.student_id}" data-name="${s.name.replace(/"/g, '&quot;')}" data-email="${(s.email||'').replace(/"/g, '&quot;')}" data-section="${(s.year_section||'').replace(/"/g, '&quot;')}" onclick="editStudent(this)">Edit</button>
                <button class="btn-sm btn-del" onclick="deleteStudent('${s.student_id}')">Delete</button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="5">No students found.</td></tr>';
}

document.getElementById('studentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const resp = await fetch('<?= BASE_URL ?>api/faculty/students.php', { method: 'POST', body: form });
    const data = await resp.json();
    const msg = document.getElementById('formMsg');
    if (data.success) {
        msg.textContent = 'Student added!';
        msg.style.color = 'green';
        e.target.reset();
        loadStudents();
        setTimeout(() => { msg.textContent = ''; }, 2000);
    } else {
        msg.textContent = data.error;
        msg.style.color = 'red';
    }
});

function editStudent(btn) {
    const sid = btn.dataset.sid;
    const name = btn.dataset.name;
    const email = btn.dataset.email;
    const section = btn.dataset.section;
    // Simple inline edit via prompt
    const newName = prompt('Full Name:', name);
    if (newName === null) return;
    const newEmail = prompt('Email:', email);
    if (newEmail === null) return;
    const newSection = prompt('Year & Section:', section);
    if (newSection === null) return;

    const data = new URLSearchParams({
        student_id: sid,
        name: newName,
        email: newEmail,
        year_section: newSection
    });

    fetch('<?= BASE_URL ?>api/faculty/students.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data.toString()
    }).then(() => loadStudents());
}

async function deleteStudent(sid) {
    if (!confirm(`Delete student ${sid}? This also deletes their submissions.`)) return;
    await fetch('<?= BASE_URL ?>api/faculty/students.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'student_id=' + encodeURIComponent(sid)
    });
    loadStudents();
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

    const resp = await fetch('<?= BASE_URL ?>api/faculty/students.php', { method: 'POST', body: formData });
    const data = await resp.json();
    msg.textContent = '';

    if (data.success) {
        results.style.display = 'block';
        let html = `<p style="color:green;font-weight:bold;">Imported: ${data.imported} | Skipped: ${data.skipped}</p>`;
        if (data.errors.length > 0) {
            html += '<details><summary>View errors (' + data.errors.length + ')</summary><ul style="color:red;font-size:0.9rem;">';
            data.errors.forEach(e => html += `<li>${e}</li>`);
            html += '</ul></details>';
        }
        results.innerHTML = html;
        loadStudents();
    } else {
        msg.textContent = data.error;
        msg.style.color = 'red';
    }
    input.value = '';
}

loadStudents();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>