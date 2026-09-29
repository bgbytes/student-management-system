-- SQL Script: 05_attendance.sql
-- Table: attendance (Daily attendance log per student, course, and faculty)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) NOT NULL,
    `faculty_id` VARCHAR(20) NOT NULL,
    `course_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('Present', 'Absent', 'Leave') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uidx_student_course_date` (`student_id`, `course_id`, `date`),
    FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
