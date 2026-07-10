<?php
require_once __DIR__ . '/../config/config.php';
// If already logged in, redirect to dashboard
if (!empty($_SESSION['faculty_id'])) {
    header('Location: ' . BASE_URL . 'faculty/dashboard.php');
    exit;
}
$csrf = csrf_token();
$timeoutMsg = isset($_GET['reason']) && $_GET['reason'] === 'timeout'
    ? 'Your session has expired. Please log in again.'
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Login — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="login-container">
    <div class="login-logo"><?= APP_NAME ?></div>
    <h1>Faculty Login</h1>
    <?php if ($timeoutMsg): ?>
        <p style="color:#f59e0b;text-align:center;margin-bottom:1rem;font-weight:600;"><?= htmlspecialchars($timeoutMsg) ?></p>
    <?php endif; ?>
    <form id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <label>Email <input type="email" name="email" required autofocus></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Sign In</button>
    </form>
    <p id="error" style="color:red;display:none;"></p>
    <a href="<?= BASE_URL ?>" class="back-home">&larr; Back to Home</a>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const errEl = document.getElementById('error');

    try {
        const resp = await fetch('<?= BASE_URL ?>api/faculty/login.php', {
            method: 'POST',
            body: form
        });
        const data = await resp.json();
        if (data.success) {
            window.location.href = data.redirect;
        } else {
            errEl.textContent = data.error;
            errEl.style.display = 'block';
        }
    } catch(err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
    }
});
</script>
</body>
</html>