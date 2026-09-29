-- SQL Script: 03_courses.sql
-- Table: courses (Course metadata and base fees in INR)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) UNIQUE NOT NULL,
    `course_name` VARCHAR(100) UNIQUE NOT NULL,
    `fees_inr` DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-seed requested course tracks with codes and base INR fees (₹)
INSERT INTO `courses` (`course_code`, `course_name`, `fees_inr`) VALUES
('CS-101', 'Computer Science', 425000.00),
('IT-101', 'Information Technology', 408000.00),
('BA-101', 'Business Administration', 382500.00),
('ME-101', 'Mechanical Engineering', 442000.00),
('EE-101', 'Electrical Engineering', 433500.00),
('CE-101', 'Civil Engineering', 425000.00),
('DS-101', 'Data Science', 467500.00),
('CY-101', 'Cyber Security', 493000.00)
ON DUPLICATE KEY UPDATE `fees_inr` = VALUES(`fees_inr`);
