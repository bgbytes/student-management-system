-- SQL Script: 04_students.sql
-- Table: students (Student registry and credential mappings)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `course_id` INT NOT NULL,
    `admission_date` DATE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default student registry profiles
-- Password: student123 (hashed using PASSWORD_DEFAULT)
INSERT INTO `students` (`student_id`, `name`, `email`, `phone`, `course_id`, `admission_date`, `password`) VALUES
('STU-2026-001', 'John Doe', 'john.doe@example.com', '+1 234-567-890', 1, '2026-07-01', '$2y$10$wO3oIeGg.GZ42zEef2/cnuQWdCptJc4KswR1jK0R4vC8/g2gW4Fw.'),
('STU-2026-002', 'Jane Smith', 'jane.smith@example.com', '+1 345-678-901', 7, '2026-07-05', '$2y$10$wO3oIeGg.GZ42zEef2/cnuQWdCptJc4KswR1jK0R4vC8/g2gW4Fw.')
ON DUPLICATE KEY UPDATE `student_id` = `student_id`;
