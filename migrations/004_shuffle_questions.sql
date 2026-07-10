-- Migration: Add question_order column to submissions for shuffled question order
-- Stores a JSON array of question_ids in the order they were presented to the student
-- Run this once on existing databases
USE quiz_generator;

ALTER TABLE submissions
    ADD COLUMN question_order JSON DEFAULT NULL COMMENT 'JSON array of question_ids in shuffled presentation order' AFTER current_question;