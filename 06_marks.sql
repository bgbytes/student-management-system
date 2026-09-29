-- SQL Script: 06_marks.sql
-- Table: marks (Term examination scores sorted by student and course tracking IDs)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `marks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) NOT NULL,
    `course_id` INT NOT NULL,
    `term` VARCHAR(20) NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `grade_point` DECIMAL(3,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uidx_student_course_term` (`student_id`, `course_id`, `term`),
    FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
