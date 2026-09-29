-- SQL script for setting up the Student Management System database

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

-- Create Admin Table
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Students Table
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `course` VARCHAR(50) NOT NULL,
    `admission_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default administrator:
-- Username: admin
-- Password: admin123 (hashed using password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO `admin` (`username`, `password`) 
VALUES ('admin', '$2y$12$Bx8ejlgzZCjBMNGnyBmQTO2RAAUlE5pWKLowahvEJk/50YnPXcf.K')
ON DUPLICATE KEY UPDATE `password` = '$2y$12$Bx8ejlgzZCjBMNGnyBmQTO2RAAUlE5pWKLowahvEJk/50YnPXcf.K';

CREATE TABLE `course_fees` (
  `course` varchar(50) NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
