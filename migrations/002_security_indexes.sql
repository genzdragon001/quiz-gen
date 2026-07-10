-- Migration: Add indexes and login_attempts table for security
-- Run this once on existing databases (e.g., Hostinger phpMyAdmin)
USE quiz_generator;

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_quiz_code ON quizzes(quiz_code);
CREATE INDEX IF NOT EXISTS idx_sub_student_quiz ON submissions(student_id, quiz_id);

-- Brute-force protection on faculty login
CREATE TABLE IF NOT EXISTS login_attempts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(150) NOT NULL,
    attempt_count   INT NOT NULL DEFAULT 1,
    last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB;