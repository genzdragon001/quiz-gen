<?php
require_once __DIR__ . '/auth.php';
$faculty = requireFaculty();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Faculty</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<nav class="faculty-nav">
    <span class="brand"><?= APP_NAME ?></span>
    <a href="<?= BASE_URL ?>faculty/dashboard.php" class="nav-link">Dashboard</a>
    <a href="<?= BASE_URL ?>faculty/quiz-create.php" class="nav-link">New Quiz</a>
    <a href="<?= BASE_URL ?>faculty/students.php" class="nav-link">Students</a>
    <a href="<?= BASE_URL ?>faculty/grades.php" class="nav-link">Grades</a>
    <a href="#" class="nav-link" onclick="openPasswordModal(); return false;">Account Settings</a>
    <span class="nav-user"><?= htmlspecialchars($faculty['name']) ?></span>
    <a href="<?= BASE_URL ?>api/faculty/logout.php" class="nav-link nav-logout">Logout</a>
</nav>
<main>

<!-- Change Password Modal (available on all faculty pages) -->
<div class="modal-overlay" id="passwordModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Change Password</h3>
            <span class="modal-close" onclick="closePasswordModal()">&times;</span>
        </div>
        <form id="changePasswordForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <label>Current Password <input type="password" name="current_password" required></label>
            <label>New Password <input type="password" name="new_password" required minlength="8"></label>
            <label>Confirm New Password <input type="password" name="confirm_password" required minlength="8"></label>
            <button type="submit">Change Password</button>
        </form>
        <p id="passwordMsg" style="margin-top:0.5rem;"></p>
    </div>
</div>

<script>
// Global CSRF token — accessible to all page scripts
const CSRF_TOKEN = '<?= $csrf ?>';

// --- Shared fetchJson wrapper ---
async function fetchJson(url, options = {}) {
    try {
        const resp = await fetch(url, options);
        if (!resp.ok) {
            let err;
            try { err = (await resp.json()).error || `HTTP ${resp.status}`; }
            catch(e) { err = `HTTP ${resp.status}`; }
            throw new Error(err);
        }
        return await resp.json();
    } catch(e) {
        console.error('fetchJson error:', e);
        throw e;
    }
}

// --- CSRF wrapper for fetch ---
function fetchWithCsrf(url, options = {}) {
    if (!options.headers) options.headers = {};
    if (options.method && options.method !== 'GET') {
        if (options.body instanceof FormData) {
            options.body.append('csrf_token', CSRF_TOKEN);
        } else {
            options.headers['X-CSRF-Token'] = CSRF_TOKEN;
        }
    }
    return fetchJson(url, options);
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

function openPasswordModal() {
    document.getElementById('passwordModal').style.display = 'flex';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('passwordMsg').textContent = '';
    var form = document.getElementById('changePasswordForm');
    if (form) form.reset();
}

document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.getElementById('passwordModal');
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closePasswordModal();
        });
    }

    var form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            var formData = new FormData(e.target);
            var msg = document.getElementById('passwordMsg');

            try {
                var data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/change-password.php', {
                    method: 'POST',
                    body: formData
                });
                msg.textContent = 'Password changed successfully!';
                msg.style.color = 'green';
                e.target.reset();
                setTimeout(function() { closePasswordModal(); }, 1500);
            } catch(err) {
                msg.textContent = err.message;
                msg.style.color = 'red';
            }
        });
    }
});
</script>