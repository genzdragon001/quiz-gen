<?php
require_once __DIR__ . '/../config/config.php';
// If already logged in, redirect to dashboard
if (!empty($_SESSION['faculty_id'])) {
    header('Location: ' . BASE_URL . 'faculty/dashboard.php');
    exit;
}
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
    <form id="loginForm">
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
    const resp = await fetch('<?= BASE_URL ?>api/faculty/login.php', {
        method: 'POST',
        body: form
    });
    const data = await resp.json();
    if (data.success) {
        window.location.href = data.redirect;
    } else {
        document.getElementById('error').textContent = data.error;
        document.getElementById('error').style.display = 'block';
    }
});
</script>
</body>
</html>