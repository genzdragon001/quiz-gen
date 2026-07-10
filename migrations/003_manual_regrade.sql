-- Migration: Add manually_regraded column to answers table
-- Run this once on existing databases
USE quiz_generator;

ALTER TABLE answers
    ADD COLUMN manually_regraded TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = faculty overrode auto-grade' AFTER is_correct;