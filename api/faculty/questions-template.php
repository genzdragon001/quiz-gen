<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();

$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) {
    http_response_code(400);
    echo 'Missing quiz_id';
    exit;
}

// Verify ownership
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
$stmt->execute([$quizId, $faculty['faculty_id']]);
$quiz = $stmt->fetch();
if (!$quiz) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$type     = $quiz['type'];
$numMcq   = (int)$quiz['num_mcq'];
$numTf    = (int)$quiz['num_tf'];
$numIdent = (int)$quiz['num_identification'];

// Download CSV template
header('Content-Type: text/csv');
if ($type === 'MIXED') {
    $filename = 'mixed_questions_template.csv';
} elseif ($type === 'MCQ') {
    $filename = 'mcq_questions_template.csv';
} elseif ($type === 'TF') {
    $filename = 'tf_questions_template.csv';
} else {
    $filename = 'identification_questions_template.csv';
}
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// --- MCQ rows ---
if ($type === 'MCQ' || $type === 'MIXED') {
    $count = $type === 'MCQ' ? max($numMcq, 1) : max($numMcq, 0);
    if ($type === 'MIXED' && $numMcq > 0) {
        // Header written once for mixed; write it before the first row of any type
        // (handled below — we write header once at the top for MIXED)
    }
    for ($i = 0; $i < $count; $i++) {
        $row = $type === 'MIXED'
            ? ['MCQ', "Sample MCQ question " . ($i + 1), 'Option A', 'Option B', 'Option C', 'Option D', 'A']
            : ["Sample MCQ question " . ($i + 1), 'Option A', 'Option B', 'Option C', 'Option D', 'A'];
        if ($i === 0 && $type === 'MCQ') {
            fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer']);
        }
        if ($i === 0 && $type === 'MIXED') {
            fputcsv($output, ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer']);
        }
        fputcsv($output, $row);
    }
}

// --- TF rows ---
if ($type === 'TF' || $type === 'MIXED') {
    $count = $type === 'TF' ? max($numTf, 1) : max($numTf, 0);
    for ($i = 0; $i < $count; $i++) {
        $row = $type === 'MIXED'
            ? ['TF', "Sample True/False question " . ($i + 1), '', '', '', '', 'T']
            : ["Sample True/False question " . ($i + 1), '', '', '', '', 'T'];
        if ($i === 0 && $type === 'TF') {
            fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer']);
        }
        fputcsv($output, $row);
    }
}

// --- Identification rows ---
if ($type === 'IDENTIFICATION' || $type === 'MIXED') {
    $count = $type === 'IDENTIFICATION' ? max($numIdent, 1) : max($numIdent, 0);
    for ($i = 0; $i < $count; $i++) {
        $row = $type === 'MIXED'
            ? ['IDENTIFICATION', "Sample identification question " . ($i + 1), '', '', '', '', "Sample answer " . ($i + 1)]
            : ["Sample identification question " . ($i + 1), '', '', '', '', "Sample answer " . ($i + 1)];
        if ($i === 0 && $type === 'IDENTIFICATION') {
            fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer']);
        }
        fputcsv($output, $row);
    }
}

fclose($output);