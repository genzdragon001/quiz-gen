-- Online Quiz Generator Database Schema
-- Run this on Hostinger MySQL (phpMyAdmin or import)

CREATE DATABASE IF NOT EXISTS quiz_generator
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quiz_generator;

-- Faculty accounts
CREATE TABLE faculty (
    faculty_id    INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Students (pre-registered by faculty)
CREATE TABLE students (
    student_id   VARCHAR(30) PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    email        VARCHAR(150) DEFAULT NULL,
    year_section VARCHAR(50) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Quizzes
CREATE TABLE quizzes (
    quiz_id            INT AUTO_INCREMENT PRIMARY KEY,
    quiz_code          INT UNSIGNED DEFAULT NULL UNIQUE COMMENT '4-digit code students use to join',
    faculty_id         INT NOT NULL,
    title              VARCHAR(200) NOT NULL,
    type             ENUM('MCQ','TF','IDENTIFICATION','MIXED') NOT NULL,
    num_mcq            INT NOT NULL DEFAULT 0,
    num_tf             INT NOT NULL DEFAULT 0,
    num_identification INT NOT NULL DEFAULT 0,
    time_limit_minutes INT NOT NULL DEFAULT 30,
    is_active          TINYINT(1) NOT NULL DEFAULT 0,
    available_from     DATETIME DEFAULT NULL COMMENT 'when students can start taking the quiz',
    available_until    DATETIME DEFAULT NULL COMMENT 'when the quiz expires',
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    INDEX idx_quiz_code (quiz_code)
) ENGINE=InnoDB;

-- Questions (correct_answer is NEVER sent to student browser)
-- question_type is per-question so MIXED quizzes can contain MCQ, TF, and Identification together.
-- For non-MIXED quizzes question_type is set to match quizzes.type for convenience.
CREATE TABLE questions (
    question_id    INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id        INT NOT NULL,
    question_type  ENUM('MCQ','TF','IDENTIFICATION') NOT NULL DEFAULT 'MCQ',
    question_text  TEXT NOT NULL,
    option_a       VARCHAR(500) DEFAULT NULL,
    option_b       VARCHAR(500) DEFAULT NULL,
    option_c       VARCHAR(500) DEFAULT NULL,
    option_d       VARCHAR(500) DEFAULT NULL,
    correct_answer VARCHAR(500) NOT NULL COMMENT 'A/B/C/D for MCQ, T/F for TF, text answer for Identification',
    sort_order     INT NOT NULL DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Student quiz submissions
CREATE TABLE submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id    VARCHAR(30) NOT NULL,
    quiz_id       INT NOT NULL,
    email_used    VARCHAR(150) NOT NULL,
    score         INT NOT NULL DEFAULT 0,
    total_items   INT NOT NULL,
    started_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at  TIMESTAMP NULL,
    violations    INT NOT NULL DEFAULT 0 COMMENT 'tab-switch count',
    flagged       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'auto-submitted due to violations',
    draft_answers JSON DEFAULT NULL COMMENT 'saved answers for resume: {"question_id":"answer"}',
    current_question INT DEFAULT 0 COMMENT 'last-viewed question index for resume',
    question_order JSON DEFAULT NULL COMMENT 'JSON array of question_ids in shuffled presentation order',
    deadline      DATETIME DEFAULT NULL COMMENT 'started_at + time_limit; used for server-side timer on resume',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
    INDEX idx_sub_student_quiz (student_id, quiz_id)
) ENGINE=InnoDB;

-- Individual answers
CREATE TABLE answers (
    answer_id      INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    question_id    INT NOT NULL,
    student_answer VARCHAR(500) DEFAULT NULL COMMENT 'NULL = unanswered',
    is_correct     TINYINT(1) NOT NULL DEFAULT 0,
    -- Manual regrade: faculty can override identification correctness
    manually_regraded TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = faculty overrode auto-grade',
    FOREIGN KEY (submission_id) REFERENCES submissions(submission_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Brute-force protection on faculty login
CREATE TABLE login_attempts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(150) NOT NULL,
    attempt_count   INT NOT NULL DEFAULT 1,
    last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB;