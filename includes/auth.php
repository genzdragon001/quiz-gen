<?php
// Require this at the top of any faculty-only page
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function requireFaculty(): array {
    // Check session timeout
    if (!empty($_SESSION['faculty_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            session_start();
            header('Location: ' . BASE_URL . 'faculty/login.php?reason=timeout');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    if (empty($_SESSION['faculty_id'])) {
        header('Location: ' . BASE_URL . 'faculty/login.php');
        exit;
    }
    return [
        'faculty_id' => $_SESSION['faculty_id'],
        'name'       => $_SESSION['faculty_name'],
    ];
}

function isFacultyLoggedIn(): bool {
    return !empty($_SESSION['faculty_id']);
}

// --- Brute-force protection ---
function checkLoginLockout(PDO $pdo, string $email): ?int {
    // Returns remaining lockout seconds, or null if not locked out
    try {
        $stmt = $pdo->prepare(
            "SELECT attempt_count, last_attempt_at FROM login_attempts
             WHERE email = ? AND last_attempt_at > (NOW() - INTERVAL " . LOGIN_LOCKOUT_SECONDS . " SECOND)
             ORDER BY last_attempt_at DESC LIMIT 1"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && (int)$row['attempt_count'] >= LOGIN_MAX_ATTEMPTS) {
            $remaining = LOGIN_LOCKOUT_SECONDS - (time() - strtotime($row['last_attempt_at']));
            return max(0, $remaining);
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet — silently skip lockout check
        if ($e->getCode() !== '42S02') throw $e;
    }
    return null;
}

function recordFailedLogin(PDO $pdo, string $email): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO login_attempts (email, attempt_count, last_attempt_at)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt_at = NOW()"
        );
        $stmt->execute([$email]);
    } catch (PDOException $e) {
        // Table doesn't exist yet — silently skip
        if ($e->getCode() !== '42S02') throw $e;
    }
}

function clearFailedLogins(PDO $pdo, string $email): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);
    } catch (PDOException $e) {
        // Table doesn't exist yet — silently skip
        if ($e->getCode() !== '42S02') throw $e;
    }
}