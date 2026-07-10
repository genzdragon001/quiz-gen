<?php
require_once __DIR__ . '/../includes/header.php';

$submissionId = (int)($_GET['id'] ?? 0);
if (!$submissionId) {
    echo "<p>Invalid submission ID.</p>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<h1>Submission Review</h1>
<div id="reviewContent">Loading...</div>

<script>
const submissionId = <?= $submissionId ?>;

async function loadReview() {
    try {
        const data = await fetchJson('<?= BASE_URL ?>api/faculty/submission-review.php?submission_id=' + submissionId);
        if (!data.success) {
            document.getElementById('reviewContent').innerHTML = '<p style="color:red;">' + (data.error || 'Failed to load') + '</p>';
            return;
        }

        const sub = data.submission;
        const answers = data.answers;

        let html = '<div class="review-header">' +
            '<p><strong>Student:</strong> ' + escapeHtml(sub.student_name) + ' (' + escapeHtml(sub.student_number) + ')</p>' +
            '<p><strong>Quiz:</strong> ' + escapeHtml(sub.quiz_title) + '</p>' +
            '<p><strong>Score:</strong> <span id="reviewScore">' + sub.score + ' / ' + sub.total_items + '</span> (' + Math.round((sub.score / sub.total_items) * 100) + '%)</p>' +
            '<p><strong>Submitted:</strong> ' + (sub.submitted_at || 'In progress') + '</p>' +
            '<p><strong>Violations:</strong> ' + sub.violations + (sub.flagged ? ' <span class="flagged-badge">FLAGGED</span>' : '') + '</p>' +
            '</div>';

        html += '<div class="review-answers">';
        answers.forEach((a, i) => {
            const isCorrect = a.is_correct == 1;
            const isIdent = a.question_type === 'IDENTIFICATION';
            const regraded = a.manually_regraded == 1;

            html += '<div class="review-answer-card ' + (isCorrect ? 'correct' : 'incorrect') + '">' +
                '<div class="review-q-header">' +
                    '<strong>Q' + (i + 1) + '.</strong> ' + escapeHtml(a.question_text) +
                    ' <span class="q-type-badge">' + (a.question_type === 'MCQ' ? 'MCQ' : (a.question_type === 'TF' ? 'T/F' : 'Identification')) + '</span>' +
                '</div>';

            // Show options for MCQ
            if (a.question_type === 'MCQ') {
                html += '<div class="review-options">' +
                    (a.option_a ? '<div class="review-opt' + (a.correct_answer === 'A' ? ' review-correct-answer' : '') + (a.student_answer === 'A' ? ' review-student-answer' : '') + '">A. ' + escapeHtml(a.option_a) + '</div>' : '') +
                    (a.option_b ? '<div class="review-opt' + (a.correct_answer === 'B' ? ' review-correct-answer' : '') + (a.student_answer === 'B' ? ' review-student-answer' : '') + '">B. ' + escapeHtml(a.option_b) + '</div>' : '') +
                    (a.option_c ? '<div class="review-opt' + (a.correct_answer === 'C' ? ' review-correct-answer' : '') + (a.student_answer === 'C' ? ' review-student-answer' : '') + '">C. ' + escapeHtml(a.option_c) + '</div>' : '') +
                    (a.option_d ? '<div class="review-opt' + (a.correct_answer === 'D' ? ' review-correct-answer' : '') + (a.student_answer === 'D' ? ' review-student-answer' : '') + '">D. ' + escapeHtml(a.option_d) + '</div>' : '') +
                '</div>';
            }

            html += '<div class="review-answer-detail">' +
                '<span class="review-label">Student Answer:</span> ' +
                '<span class="' + (isCorrect ? 'review-answer-ok' : 'review-answer-wrong') + '">' +
                    (a.student_answer !== null ? escapeHtml(a.student_answer) : '<em>No answer</em>') +
                '</span>' +
            '</div>';

            html += '<div class="review-answer-detail">' +
                '<span class="review-label">Correct Answer:</span> ' +
                '<span class="review-answer-ok">' + escapeHtml(a.correct_answer) + '</span>' +
            '</div>';

            // For identification questions, show regrade button
            if (isIdent) {
                html += '<div class="review-regrade">' +
                    '<span class="review-label">Auto-graded:</span> ' +
                    '<span class="' + (isCorrect ? 'review-answer-ok' : 'review-answer-wrong') + '">' +
                        (isCorrect ? 'Correct' : 'Incorrect') + (regraded ? ' (overridden)' : '') +
                    '</span>' +
                    '<div class="regrade-buttons">' +
                        '<button class="btn-sm btn-edit" onclick="regradeAnswer(' + a.answer_id + ', 1)">Mark Correct</button> ' +
                        '<button class="btn-sm btn-del" onclick="regradeAnswer(' + a.answer_id + ', 0)">Mark Incorrect</button>' +
                    '</div>' +
                '</div>';
            }

            html += '</div>';
        });
        html += '</div>';

        html += '<div style="margin-top:1.5rem;">' +
            '<a href="grades.php" class="btn-back">&larr; Back to Grades</a>' +
        '</div>';

        document.getElementById('reviewContent').innerHTML = html;
    } catch(e) {
        document.getElementById('reviewContent').innerHTML = '<p style="color:red;">Failed to load review: ' + escapeHtml(e.message) + '</p>';
    }
}

async function regradeAnswer(answerId, isCorrect) {
    if (!confirm('Change this answer to ' + (isCorrect ? 'Correct' : 'Incorrect') + '?')) return;

    var formData = new FormData();
    formData.append('submission_id', submissionId);
    formData.append('answer_id', answerId);
    formData.append('is_correct', isCorrect);

    try {
        const data = await fetchWithCsrf('<?= BASE_URL ?>api/faculty/submission-review.php', {
            method: 'POST',
            body: formData
        });

        if (data.success) {
            // Update the score display
            const scoreEl = document.getElementById('reviewScore');
            if (scoreEl) {
                scoreEl.textContent = data.new_score + ' / ' + data.total;
            }
            // Reload to reflect changes
            loadReview();
        } else {
            alert(data.error || 'Regrade failed');
        }
    } catch(err) {
        alert(err.message);
    }
}

loadReview();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>