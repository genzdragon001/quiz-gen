-- Migration: Add resume/disconnect-recovery support
-- Run this once on existing databases (e.g., Hostinger phpMyAdmin)
USE quiz_generator;

ALTER TABLE submissions
    ADD COLUMN draft_answers JSON DEFAULT NULL COMMENT 'saved answers for resume: {"question_id":"answer"}' AFTER flagged,
    ADD COLUMN current_question INT DEFAULT 0 COMMENT 'last-viewed question index for resume' AFTER draft_answers,
    ADD COLUMN deadline DATETIME DEFAULT NULL COMMENT 'started_at + time_limit; used for server-side timer on resume' AFTER current_question;