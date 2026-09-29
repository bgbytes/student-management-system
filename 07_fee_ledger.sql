-- SQL Script: 07_fee_ledger.sql
-- Table: fee_ledger (Dynamic financial balances tracking Paid, Partially Paid, and Pending statuses)
-- EduManage Student Management System

CREATE DATABASE IF NOT EXISTS `student_db`;
USE `student_db`;

CREATE TABLE IF NOT EXISTS `fee_ledger` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) NOT NULL,
    `course_id` INT NOT NULL,
    `amount_due_inr` DECIMAL(12,2) NOT NULL,
    `amount_paid_inr` DECIMAL(12,2) DEFAULT 0.00,
    `status` ENUM('Paid', 'Partially Paid', 'Pending') NOT NULL DEFAULT 'Pending',
    `due_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uidx_student_course` (`student_id`, `course_id`),
    FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default billing ledgers for existing students
INSERT INTO `fee_ledger` (`student_id`, `course_id`, `amount_due_inr`, `amount_paid_inr`, `status`, `due_date`) VALUES
('STU-2026-001', 1, 425000.00, 0.00, 'Pending', '2026-08-01'),
('STU-2026-002', 7, 467500.00, 0.00, 'Pending', '2026-08-05')
ON DUPLICATE KEY UPDATE `amount_due_inr` = VALUES(`amount_due_inr`);
