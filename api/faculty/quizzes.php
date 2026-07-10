<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();
header('Content-Type: application/json');

$pdo = getDB();

// Generate a unique 4-digit quiz code (1000–9999)
function generateUniqueQuizCode(PDO $pdo): int {
    $maxAttempts = 1000;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = random_int(1000, 9999);
        $stmt = $pdo->prepare("SELECT 1 FROM quizzes WHERE quiz_code = ?");
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    http_response_code(500);
    echo json_encode(['error' => 'Could not generate a unique quiz code']);
    exit;
}

// Validate availability window
function validateAvailabilityWindow(?string $from, ?string $until): ?string {
    if ($from && $until) {
        $f = strtotime($from);
        $u = strtotime($until);
        if ($f === false || $u === false) return 'Invalid date format for availability window';
        if ($f >= $u) return 'Available From must be earlier than Available Until';
    }
    return null;
}

// CREATE quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $title            = trim($_POST['title'] ?? '');
    $type             = $_POST['type'] ?? '';
    $timeLimitMinutes = (int)($_POST['time_limit_minutes'] ?? 30);
    $isActive         = isset($_POST['is_active']) ? 1 : 0;
    $numMcq           = (int)($_POST['num_mcq'] ?? 0);
    $numTf            = (int)($_POST['num_tf'] ?? 0);
    $numIdent         = (int)($_POST['num_identification'] ?? 0);
    $availableFrom    = trim($_POST['available_from'] ?? '') ?: null;
    $availableUntil   = trim($_POST['available_until'] ?? '') ?: null;

    if (empty($title) || !in_array($type, ['MCQ', 'TF', 'IDENTIFICATION', 'MIXED'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and valid type (MCQ, TF, IDENTIFICATION, or MIXED) are required']);
        exit;
    }

    if ($timeLimitMinutes < 1 || $timeLimitMinutes > 600) {
        http_response_code(400);
        echo json_encode(['error' => 'Time limit must be between 1 and 600 minutes']);
        exit;
    }

    $dateError = validateAvailabilityWindow($availableFrom, $availableUntil);
    if ($dateError) {
        http_response_code(400);
        echo json_encode(['error' => $dateError]);
        exit;
    }

    $quizCode = generateUniqueQuizCode($pdo);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO quizzes (quiz_code, faculty_id, title, type, num_mcq, num_tf, num_identification, time_limit_minutes, is_active, available_from, available_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$quizCode, $faculty['faculty_id'], $title, $type, $numMcq, $numTf, $numIdent, $timeLimitMinutes, $isActive, $availableFrom, $availableUntil]);
    } catch (PDOException $e) {
        // Duplicate quiz_code — retry once
        if ($e->getCode() == 23000) {
            $quizCode = generateUniqueQuizCode($pdo);
            $stmt->execute([$quizCode, $faculty['faculty_id'], $title, $type, $numMcq, $numTf, $numIdent, $timeLimitMinutes, $isActive, $availableFrom, $availableUntil]);
        } else {
            throw $e;
        }
    }

    echo json_encode([
        'success'   => true,
        'quiz_id'   => $pdo->lastInsertId(),
        'quiz_code' => $quizCode,
    ]);
    exit;
}

// UPDATE quiz (PUT)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    verifyCsrfToken();

    parse_str(file_get_contents("php://input"), $data);
    $quizId            = (int)($data['quiz_id'] ?? 0);
    $title             = trim($data['title'] ?? '');
    $timeLimitMinutes  = (int)($data['time_limit_minutes'] ?? 30);
    $isActive          = isset($data['is_active']) ? 1 : 0;
    $numMcq            = (int)($data['num_mcq'] ?? 0);
    $numTf             = (int)($data['num_tf'] ?? 0);
    $numIdent          = (int)($data['num_identification'] ?? 0);
    $availableFrom     = trim($data['available_from'] ?? '') ?: null;
    $availableUntil    = trim($data['available_until'] ?? '') ?: null;

    // Verify ownership
    $stmt = $pdo->prepare("SELECT 1 FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
    $stmt->execute([$quizId, $faculty['faculty_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if ($timeLimitMinutes < 1 || $timeLimitMinutes > 600) {
        http_response_code(400);
        echo json_encode(['error' => 'Time limit must be between 1 and 600 minutes']);
        exit;
    }

    $dateError = validateAvailabilityWindow($availableFrom, $availableUntil);
    if ($dateError) {
        http_response_code(400);
        echo json_encode(['error' => $dateError]);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE quizzes SET title = ?, time_limit_minutes = ?, is_active = ?, num_mcq = ?, num_tf = ?, num_identification = ?, available_from = ?, available_until = ? WHERE quiz_id = ?"
    );
    $stmt->execute([$title, $timeLimitMinutes, $isActive, $numMcq, $numTf, $numIdent, $availableFrom, $availableUntil, $quizId]);

    echo json_encode(['success' => true]);
    exit;
}

// DELETE quiz (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    verifyCsrfToken();

    parse_str(file_get_contents("php://input"), $data);
    $quizId = (int)($data['quiz_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT 1 FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
    $stmt->execute([$quizId, $faculty['faculty_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
    $stmt->execute([$quizId]);

    echo json_encode(['success' => true]);
    exit;
}

// LIST quizzes (for this faculty)
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE faculty_id = ? ORDER BY created_at DESC");
$stmt->execute([$faculty['faculty_id']]);
echo json_encode($stmt->fetchAll());