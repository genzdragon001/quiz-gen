<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$studentId    = trim($_POST['student_id'] ?? '');
$quizId       = (int)($_POST['quiz_id'] ?? 0);
$email        = trim($_POST['email'] ?? '');
$resumeSubmissionId = (int)($_POST['submission_id'] ?? 0);

if (empty($studentId) || !$quizId || empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$pdo = getDB();

// Verify student exists
$stmt = $pdo->prepare("SELECT 1 FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found']);
    exit;
}

// Save the entered email to the student's record (update if different or empty)
$stmt = $pdo->prepare("SELECT email FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$currentEmail = $stmt->fetchColumn();

if ($email !== $currentEmail) {
    $stmt = $pdo->prepare("UPDATE students SET email = ? WHERE student_id = ?");
    $stmt->execute([$email, $studentId]);
}

// Verify quiz is active
$stmt = $pdo->prepare("SELECT quiz_id, title, type, time_limit_minutes, available_from, available_until FROM quizzes WHERE quiz_id = ? AND is_active = 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not available']);
    exit;
}

// Check date/time availability window
$now = date('Y-m-d H:i:s');
if ($quiz['available_from'] && $now < $quiz['available_from']) {
    http_response_code(403);
    echo json_encode(['error' => 'This quiz is not yet available. It opens on ' . date('M j, Y g:i A', strtotime($quiz['available_from']))]);
    exit;
}
if ($quiz['available_until'] && $now > $quiz['available_until']) {
    http_response_code(403);
    echo json_encode(['error' => 'This quiz has expired. It was available until ' . date('M j, Y g:i A', strtotime($quiz['available_until']))]);
    exit;
}

// Helper: reorder questions by a stored question_order JSON array
function reorderQuestions(array $questions, ?string $orderJson): array {
    if (empty($orderJson)) return $questions;
    $orderIds = json_decode($orderJson, true);
    if (!is_array($orderIds)) return $questions;

    // Build a map of question_id => question data
    $map = [];
    foreach ($questions as $q) {
        $map[$q['question_id']] = $q;
    }

    // Reorder according to stored order; append any missing questions at the end
    $result = [];
    foreach ($orderIds as $qid) {
        if (isset($map[$qid])) {
            $result[] = $map[$qid];
            unset($map[$qid]);
        }
    }
    foreach ($map as $q) {
        $result[] = $q;
    }
    return $result;
}

// Helper: build resume response
function buildResumeResponse(PDO $pdo, array $existing, array $quiz, int $quizId): void {
    $draftAnswers = json_decode($existing['draft_answers'] ?? '{}', true);
    if (!is_array($draftAnswers)) $draftAnswers = [];

    $now = time();
    $timeRemaining = 0;
    if (!empty($existing['deadline'])) {
        $timeRemaining = max(0, strtotime($existing['deadline']) - $now);
    } else {
        // Fallback for submissions without deadline (created before this feature)
        $timeRemaining = (int)$quiz['time_limit_minutes'] * 60;
    }

    // Get questions WITHOUT correct_answer
    $stmt = $pdo->prepare(
        "SELECT question_id, question_type, question_text, option_a, option_b, option_c, option_d, sort_order
         FROM questions WHERE quiz_id = ? ORDER BY sort_order"
    );
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll();

    // Reorder questions to match the shuffled order stored when the quiz was started
    $questions = reorderQuestions($questions, $existing['question_order'] ?? null);

    echo json_encode([
        'success'          => true,
        'resumed'          => true,
        'submission_id'    => (int)$existing['submission_id'],
        'quiz'             => $quiz,
        'questions'        => $questions,
        'draft_answers'    => $draftAnswers,
        'current_question' => (int)$existing['current_question'],
        'violations'       => (int)$existing['violations'],
        'time_remaining'   => $timeRemaining,
    ]);
    exit;
}

// ----- RESUME MODE -----
// If a submission_id is provided, resume that unsubmitted submission
if ($resumeSubmissionId) {
    $stmt = $pdo->prepare(
        "SELECT submission_id, submitted_at, draft_answers, current_question, violations, deadline, question_order
         FROM submissions WHERE submission_id = ? AND student_id = ? AND quiz_id = ?"
    );
    $stmt->execute([$resumeSubmissionId, $studentId, $quizId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Submission not found']);
        exit;
    }

    if ($existing['submitted_at']) {
        http_response_code(409);
        echo json_encode(['error' => 'This quiz has already been submitted']);
        exit;
    }

    // Check if deadline has passed — if so, auto-submit the draft
    $deadline = $existing['deadline'];
    if ($deadline && $now > $deadline) {
        // Time expired while away — auto-submit whatever draft we have
        $draftAnswers = json_decode($existing['draft_answers'] ?? '{}', true);
        if (!is_array($draftAnswers)) $draftAnswers = [];

        // Get questions for grading
        $stmt = $pdo->prepare("SELECT question_id, correct_answer FROM questions WHERE quiz_id = ? ORDER BY sort_order");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();

        $score = 0;
        $total = count($questions);

        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("INSERT INTO answers (submission_id, question_id, student_answer, is_correct) VALUES (?, ?, ?, ?)");

            foreach ($questions as $q) {
                $studentAnswer = $draftAnswers[$q['question_id']] ?? null;
                $isCorrect = ($studentAnswer !== null && strtolower(trim($studentAnswer)) === strtolower(trim($q['correct_answer']))) ? 1 : 0;
                if ($isCorrect) $score++;
                $insertStmt->execute([$resumeSubmissionId, $q['question_id'], $studentAnswer, $isCorrect]);
            }

            $stmt = $pdo->prepare("UPDATE submissions SET score = ?, total_items = ?, submitted_at = NOW(), violations = ?, flagged = ?, draft_answers = NULL WHERE submission_id = ?");
            $stmt->execute([$score, $total, (int)$existing['violations'], 0, $resumeSubmissionId]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to auto-submit expired quiz']);
            exit;
        }

        echo json_encode([
            'success'     => true,
            'expired'     => true,
            'score'       => $score,
            'total'       => $total,
        ]);
        exit;
    }

    // Valid resume — return saved state
    buildResumeResponse($pdo, $existing, $quiz, $quizId);
}

// ----- NEW SUBMISSION MODE -----
// Check for existing submission
$stmt = $pdo->prepare("SELECT submission_id, submitted_at, draft_answers, current_question, violations, deadline, question_order FROM submissions WHERE student_id = ? AND quiz_id = ?");
$stmt->execute([$studentId, $quizId]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['submitted_at']) {
        http_response_code(409);
        echo json_encode(['error' => 'Already submitted']);
        exit;
    }
    // Unsubmitted exists — resume it instead of creating a duplicate
    // Set a deadline if one doesn't exist yet
    if (empty($existing['deadline'])) {
        $deadline = date('Y-m-d H:i:s', time() + (int)$quiz['time_limit_minutes'] * 60);
        $stmt = $pdo->prepare("UPDATE submissions SET deadline = ? WHERE submission_id = ?");
        $stmt->execute([$deadline, $existing['submission_id']]);
        $existing['deadline'] = $deadline;
    }

    buildResumeResponse($pdo, $existing, $quiz, $quizId);
}

// Create submission record with deadline
$deadline = date('Y-m-d H:i:s', time() + (int)$quiz['time_limit_minutes'] * 60);

// Get questions WITHOUT correct_answer
$stmt = $pdo->prepare(
    "SELECT question_id, question_type, question_text, option_a, option_b, option_c, option_d, sort_order
     FROM questions WHERE quiz_id = ? ORDER BY sort_order"
);
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

if (count($questions) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'This quiz has no questions yet']);
    exit;
}

// Shuffle questions so each student gets a different order
shuffle($questions);

// Store the shuffled question order as a JSON array of question_ids
$questionOrder = json_encode(array_column($questions, 'question_id'));

$totalItems = count($questions);

$stmt = $pdo->prepare(
    "INSERT INTO submissions (student_id, quiz_id, email_used, total_items, started_at, deadline, question_order)
     VALUES (?, ?, ?, ?, NOW(), ?, ?)"
);
$stmt->execute([$studentId, $quizId, $email, $totalItems, $deadline, $questionOrder]);
$submissionId = $pdo->lastInsertId();

echo json_encode([
    'success'       => true,
    'submission_id' => $submissionId,
    'quiz'          => $quiz,
    'questions'     => $questions,
    'time_remaining' => (int)$quiz['time_limit_minutes'] * 60,
]);