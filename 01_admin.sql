-- SQL Script: 01_admin.sql
-- Table: admin (Administrative authentication logs)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default administrator account
-- Username: admin
-- Password: admin123 (hashed using PASSWORD_DEFAULT)
INSERT INTO `admin` (`username`, `password`) 
VALUES ('admin', '$2y$10$Bx8ejlgzZCjBMNGnyBmQTO2RAAUlE5pWKLowahvEJk/50YnPXcf.K')
ON DUPLICATE KEY UPDATE `password` = '$2y$10$Bx8ejlgzZCjBMNGnyBmQTO2RAAUlE5pWKLowahvEJk/50YnPXcf.K';
