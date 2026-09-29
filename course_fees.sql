-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 10:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `course_fees`
--

CREATE TABLE `course_fees` (
  `course` varchar(50) NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_fees`
--

INSERT INTO `course_fees` (`course`, `fee_amount`) VALUES
('Business Administration', 4500.00),
('Civil Engineering', 5000.00),
('Computer Science', 5000.00),
('Cyber Security', 5800.00),
('Data Science', 5500.00),
('Electrical Engineering', 5100.00),
('Information Technology', 4800.00),
('Mechanical Engineering', 5200.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `course_fees`
--
ALTER TABLE `course_fees`
  ADD PRIMARY KEY (`course`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
