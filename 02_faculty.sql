-- SQL Script: 02_faculty.sql
-- Table: faculty (Teacher/instructor registry and credentials)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `faculty` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` VARCHAR(20) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default faculty account
-- Username/ID: FAC-2026-001
-- Password: faculty123 (hashed using PASSWORD_DEFAULT)
INSERT INTO `faculty` (`faculty_id`, `name`, `email`, `phone`, `department`, `password`)
VALUES ('FAC-2026-001', 'Professor Alan Turing', 'turing@edumanage.com', '+1 555-0199', 'Computer Science', '$2y$10$p8mN47i97Wz3m57HlU4rAelUuP8FvP9XqQvR8.eO7X1X/C/Pj2K5q')
ON DUPLICATE KEY UPDATE `faculty_id` = `faculty_id`;
