-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 09, 2026 at 06:31 PM
-- Server version: 8.0.45
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`, `full_name`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$10$Z5hrRSvFe8Lb1XYgMaOtqe5HP4iKfIAJH1nbQkECZjoFoohI6EE8S', 'System Administrator', 'admin@edutrack.com', '2026-07-08 15:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int NOT NULL,
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `semester` int NOT NULL,
  `total_classes` int DEFAULT '0',
  `classes_attended` int DEFAULT '0',
  `attendance_percentage` decimal(5,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_id`, `course_id`, `semester`, `total_classes`, `classes_attended`, `attendance_percentage`) VALUES
(1, 1, 1, 5, 30, 28, 93.33),
(2, 1, 2, 5, 40, 38, 95.00),
(3, 1, 3, 5, 40, 36, 90.00),
(4, 1, 4, 5, 30, 27, 90.00),
(5, 1, 5, 5, 30, 29, 96.67),
(6, 1, 6, 5, 20, 19, 95.00),
(13, 2, 6, 5, 20, 18, 90.00),
(14, 2, 5, 5, 30, 26, 86.67),
(15, 2, 4, 5, 30, 28, 93.33),
(16, 2, 3, 5, 40, 35, 87.50),
(17, 2, 2, 5, 40, 36, 90.00),
(18, 2, 1, 5, 30, 27, 90.00),
(19, 3, 6, 5, 20, 20, 100.00),
(20, 3, 5, 5, 30, 28, 93.33),
(21, 3, 4, 5, 30, 29, 96.67),
(22, 3, 3, 5, 40, 38, 95.00),
(23, 3, 2, 5, 40, 39, 97.50),
(24, 3, 1, 5, 30, 29, 96.67),
(25, 4, 6, 5, 20, 16, 80.00),
(26, 4, 5, 5, 30, 24, 80.00),
(27, 4, 4, 5, 30, 25, 83.33),
(28, 4, 3, 5, 40, 32, 80.00),
(29, 4, 2, 5, 40, 33, 82.50),
(30, 4, 1, 5, 30, 24, 80.00),
(31, 5, 6, 5, 20, 19, 95.00),
(32, 5, 5, 5, 30, 29, 96.67),
(33, 5, 4, 5, 30, 27, 90.00),
(34, 5, 3, 5, 40, 36, 90.00),
(35, 5, 2, 5, 40, 37, 92.50),
(36, 5, 1, 5, 30, 28, 93.33),
(37, 6, 6, 5, 20, 17, 85.00),
(38, 6, 5, 5, 30, 27, 90.00),
(39, 6, 4, 5, 30, 26, 86.67),
(40, 6, 3, 5, 40, 34, 85.00),
(41, 6, 2, 5, 40, 35, 87.50),
(42, 6, 1, 5, 30, 26, 86.67),
(43, 7, 6, 5, 20, 19, 95.00),
(44, 7, 5, 5, 30, 30, 100.00),
(45, 7, 4, 5, 30, 29, 96.67),
(46, 7, 3, 5, 40, 39, 97.50),
(47, 7, 2, 5, 40, 38, 95.00),
(48, 7, 1, 5, 30, 30, 100.00),
(49, 8, 6, 5, 20, 18, 90.00),
(50, 8, 5, 5, 30, 26, 86.67),
(51, 8, 4, 5, 30, 24, 80.00),
(52, 8, 3, 5, 40, 33, 82.50),
(53, 8, 2, 5, 40, 34, 85.00),
(54, 8, 1, 5, 30, 25, 83.33),
(55, 9, 6, 5, 20, 20, 100.00),
(56, 9, 5, 5, 30, 29, 96.67),
(57, 9, 4, 5, 30, 28, 93.33),
(58, 9, 3, 5, 40, 38, 95.00),
(59, 9, 2, 5, 40, 37, 92.50),
(60, 9, 1, 5, 30, 29, 96.67),
(61, 10, 6, 5, 20, 18, 90.00),
(62, 10, 5, 5, 30, 28, 93.33),
(63, 10, 4, 5, 30, 26, 86.67),
(64, 10, 3, 5, 40, 37, 92.50),
(65, 10, 2, 5, 40, 36, 90.00),
(66, 10, 1, 5, 30, 27, 90.00);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credits` int NOT NULL,
  `semester` int NOT NULL,
  `faculty_name` varchar(100) DEFAULT NULL,
  `faculty_email` varchar(100) DEFAULT NULL,
  `description` text,
  `syllabus` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credits`, `semester`, `faculty_name`, `faculty_email`, `description`, `syllabus`, `created_at`) VALUES
(1, 'CS301', 'Database Management Systems', 3, 5, 'Dr. Sarah Connor', 'sconnor@edutrack.com', 'Introduction to relational databases, SQL language, schema design, normalization, transactions, and indexing.', 'Unit 1: ER Models, Unit 2: Relational Algebra & SQL, Unit 3: Normalization, Unit 4: Transaction & Concurrency control.', '2026-07-08 15:25:08'),
(2, 'CS302', 'Computer Networks', 4, 5, 'Prof. Charles Xavier', 'cxavier@edutrack.com', 'Study of computer networking concepts, OSI model layers, IP routing, TCP/UDP, and application layer protocols.', 'Unit 1: Physical & Data Link Layers, Unit 2: Network Layer & Routing, Unit 3: Transport Layer (TCP/UDP), Unit 4: Application Layer.', '2026-07-08 15:25:08'),
(3, 'CS303', 'Operating Systems', 4, 5, 'Dr. Alan Turing', 'aturing@edutrack.com', 'Concepts of OS structure, process management, CPU scheduling, deadlocks, memory management, and file systems.', 'Unit 1: OS Overview & Processes, Unit 2: CPU Scheduling & Synchronization, Unit 3: Memory Management & Virtual Memory, Unit 4: File Systems.', '2026-07-08 15:25:08'),
(4, 'CS304', 'Software Engineering', 3, 5, 'Prof. Grace Hopper', 'ghopper@edutrack.com', 'Methodologies for software development lifecycle (SDLC), Agile principles, design patterns, testing, and project management.', 'Unit 1: SDLC Models & Agile, Unit 2: Requirements Analysis, Unit 3: Software Design, Unit 4: Software Testing.', '2026-07-08 15:25:08'),
(5, 'CS305', 'Web Development', 3, 5, 'Mr. Tim Berners-Lee', 'timbl@edutrack.com', 'Frontend and backend web technologies including HTML5, CSS3, JavaScript, PHP, MySQL, and REST APIs.', 'Unit 1: HTML, CSS & JS, Unit 2: Backend Development with PHP, Unit 3: Database Integration, Unit 4: Modern Web APIs.', '2026-07-08 15:25:08'),
(6, 'CS306', 'Data Structures Lab', 2, 5, 'Dr. Sarah Connor', 'sconnor@edutrack.com', 'Hands-on practical sessions implementing tree, graph, sorting, and search algorithms in C/C++.', 'Lab 1: Stack & Queue, Lab 2: Binary Trees, Lab 3: Graphs (DFS/BFS), Lab 4: Hashing & Sorting.', '2026-07-08 15:25:08'),
(7, 'CS101', 'Introduction to Programming', 4, 1, 'Prof. Dennis Ritchie', 'dritchie@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(8, 'CS102', 'Mathematics I', 4, 1, 'Dr. Leonhard Euler', 'leuler@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(9, 'CS103', 'Physics', 3, 1, 'Dr. Albert Einstein', 'aeinstein@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(10, 'CS201', 'Data Structures & Algorithms', 4, 2, 'Prof. Donald Knuth', 'dknuth@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(11, 'CS202', 'Mathematics II', 4, 2, 'Dr. Leonhard Euler', 'leuler@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(12, 'CS203', 'Chemistry', 3, 2, 'Dr. Marie Curie', 'mcurie@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(13, 'CS311', 'Discrete Mathematics', 4, 3, 'Prof. George Boole', 'gboole@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(14, 'CS312', 'Digital Electronics', 4, 3, 'Dr. Claude Shannon', 'cshannon@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(15, 'CS313', 'Object Oriented Programming', 3, 3, 'Mr. Bjarne Stroustrup', 'bjarne@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(16, 'CS401', 'Design & Analysis of Algorithms', 4, 4, 'Prof. Donald Knuth', 'dknuth@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(17, 'CS402', 'Computer Organization', 4, 4, 'Dr. John von Neumann', 'jvonneumann@edutrack.com', NULL, NULL, '2026-07-08 15:25:08'),
(18, 'CS403', 'Theory of Computation', 3, 4, 'Dr. Alan Turing', 'aturing@edutrack.com', NULL, NULL, '2026-07-08 15:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `semester` int NOT NULL,
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `semester`, `enrolled_at`) VALUES
(1, 1, 1, 5, '2026-07-08 15:25:08'),
(2, 1, 2, 5, '2026-07-08 15:25:08'),
(3, 1, 3, 5, '2026-07-08 15:25:08'),
(4, 1, 4, 5, '2026-07-08 15:25:08'),
(5, 1, 5, 5, '2026-07-08 15:25:08'),
(6, 1, 6, 5, '2026-07-08 15:25:08'),
(7, 1, 7, 1, '2026-07-08 15:25:08'),
(8, 1, 8, 1, '2026-07-08 15:25:08'),
(9, 1, 9, 1, '2026-07-08 15:25:08'),
(10, 1, 10, 2, '2026-07-08 15:25:08'),
(11, 1, 11, 2, '2026-07-08 15:25:08'),
(12, 1, 12, 2, '2026-07-08 15:25:08'),
(13, 1, 13, 3, '2026-07-08 15:25:08'),
(14, 1, 14, 3, '2026-07-08 15:25:08'),
(15, 1, 15, 3, '2026-07-08 15:25:08'),
(16, 1, 16, 4, '2026-07-08 15:25:08'),
(17, 1, 17, 4, '2026-07-08 15:25:08'),
(18, 1, 18, 4, '2026-07-08 15:25:08'),
(37, 2, 6, 5, '2026-07-08 16:50:20'),
(38, 2, 5, 5, '2026-07-08 16:50:20'),
(39, 2, 4, 5, '2026-07-08 16:50:20'),
(40, 2, 3, 5, '2026-07-08 16:50:20'),
(41, 2, 2, 5, '2026-07-08 16:50:20'),
(42, 2, 1, 5, '2026-07-08 16:50:20'),
(43, 3, 6, 5, '2026-07-08 16:50:20'),
(44, 3, 5, 5, '2026-07-08 16:50:20'),
(45, 3, 4, 5, '2026-07-08 16:50:20'),
(46, 3, 3, 5, '2026-07-08 16:50:20'),
(47, 3, 2, 5, '2026-07-08 16:50:20'),
(48, 3, 1, 5, '2026-07-08 16:50:20'),
(49, 4, 6, 5, '2026-07-08 16:50:20'),
(50, 4, 5, 5, '2026-07-08 16:50:20'),
(51, 4, 4, 5, '2026-07-08 16:50:20'),
(52, 4, 3, 5, '2026-07-08 16:50:20'),
(53, 4, 2, 5, '2026-07-08 16:50:20'),
(54, 4, 1, 5, '2026-07-08 16:50:20'),
(55, 5, 6, 5, '2026-07-08 16:50:20'),
(56, 5, 5, 5, '2026-07-08 16:50:20'),
(57, 5, 4, 5, '2026-07-08 16:50:20'),
(58, 5, 3, 5, '2026-07-08 16:50:20'),
(59, 5, 2, 5, '2026-07-08 16:50:20'),
(60, 5, 1, 5, '2026-07-08 16:50:20'),
(61, 6, 6, 5, '2026-07-08 16:50:20'),
(62, 6, 5, 5, '2026-07-08 16:50:20'),
(63, 6, 4, 5, '2026-07-08 16:50:20'),
(64, 6, 3, 5, '2026-07-08 16:50:20'),
(65, 6, 2, 5, '2026-07-08 16:50:20'),
(66, 6, 1, 5, '2026-07-08 16:50:20'),
(67, 7, 6, 5, '2026-07-08 16:50:20'),
(68, 7, 5, 5, '2026-07-08 16:50:20'),
(69, 7, 4, 5, '2026-07-08 16:50:20'),
(70, 7, 3, 5, '2026-07-08 16:50:20'),
(71, 7, 2, 5, '2026-07-08 16:50:20'),
(72, 7, 1, 5, '2026-07-08 16:50:20'),
(73, 8, 6, 5, '2026-07-08 16:50:20'),
(74, 8, 5, 5, '2026-07-08 16:50:20'),
(75, 8, 4, 5, '2026-07-08 16:50:20'),
(76, 8, 3, 5, '2026-07-08 16:50:20'),
(77, 8, 2, 5, '2026-07-08 16:50:20'),
(78, 8, 1, 5, '2026-07-08 16:50:20'),
(79, 9, 6, 5, '2026-07-08 16:50:20'),
(80, 9, 5, 5, '2026-07-08 16:50:20'),
(81, 9, 4, 5, '2026-07-08 16:50:20'),
(82, 9, 3, 5, '2026-07-08 16:50:20'),
(83, 9, 2, 5, '2026-07-08 16:50:20'),
(84, 9, 1, 5, '2026-07-08 16:50:20'),
(85, 10, 6, 5, '2026-07-08 16:50:20'),
(86, 10, 5, 5, '2026-07-08 16:50:20'),
(87, 10, 4, 5, '2026-07-08 16:50:20'),
(88, 10, 3, 5, '2026-07-08 16:50:20'),
(89, 10, 2, 5, '2026-07-08 16:50:20'),
(90, 10, 1, 5, '2026-07-08 16:50:20'),
(100, 10, 7, 1, '2026-07-08 16:50:20'),
(101, 9, 7, 1, '2026-07-08 16:50:20'),
(102, 8, 7, 1, '2026-07-08 16:50:20'),
(103, 7, 7, 1, '2026-07-08 16:50:20'),
(104, 6, 7, 1, '2026-07-08 16:50:20'),
(105, 5, 7, 1, '2026-07-08 16:50:20'),
(106, 4, 7, 1, '2026-07-08 16:50:20'),
(107, 3, 7, 1, '2026-07-08 16:50:20'),
(108, 2, 7, 1, '2026-07-08 16:50:20'),
(109, 10, 8, 1, '2026-07-08 16:50:20'),
(110, 9, 8, 1, '2026-07-08 16:50:20'),
(111, 8, 8, 1, '2026-07-08 16:50:20'),
(112, 7, 8, 1, '2026-07-08 16:50:20'),
(113, 6, 8, 1, '2026-07-08 16:50:20'),
(114, 5, 8, 1, '2026-07-08 16:50:20'),
(115, 4, 8, 1, '2026-07-08 16:50:20'),
(116, 3, 8, 1, '2026-07-08 16:50:20'),
(117, 2, 8, 1, '2026-07-08 16:50:20'),
(118, 10, 9, 1, '2026-07-08 16:50:20'),
(119, 9, 9, 1, '2026-07-08 16:50:20'),
(120, 8, 9, 1, '2026-07-08 16:50:20'),
(121, 7, 9, 1, '2026-07-08 16:50:20'),
(122, 6, 9, 1, '2026-07-08 16:50:20'),
(123, 5, 9, 1, '2026-07-08 16:50:20'),
(124, 4, 9, 1, '2026-07-08 16:50:20'),
(125, 3, 9, 1, '2026-07-08 16:50:20'),
(126, 2, 9, 1, '2026-07-08 16:50:20'),
(127, 10, 10, 2, '2026-07-08 16:50:20'),
(128, 9, 10, 2, '2026-07-08 16:50:20'),
(129, 8, 10, 2, '2026-07-08 16:50:20'),
(130, 7, 10, 2, '2026-07-08 16:50:20'),
(131, 6, 10, 2, '2026-07-08 16:50:20'),
(132, 5, 10, 2, '2026-07-08 16:50:20'),
(133, 4, 10, 2, '2026-07-08 16:50:20'),
(134, 3, 10, 2, '2026-07-08 16:50:20'),
(135, 2, 10, 2, '2026-07-08 16:50:20'),
(136, 10, 11, 2, '2026-07-08 16:50:20'),
(137, 9, 11, 2, '2026-07-08 16:50:20'),
(138, 8, 11, 2, '2026-07-08 16:50:20'),
(139, 7, 11, 2, '2026-07-08 16:50:20'),
(140, 6, 11, 2, '2026-07-08 16:50:20'),
(141, 5, 11, 2, '2026-07-08 16:50:20'),
(142, 4, 11, 2, '2026-07-08 16:50:20'),
(143, 3, 11, 2, '2026-07-08 16:50:20'),
(144, 2, 11, 2, '2026-07-08 16:50:20'),
(145, 10, 12, 2, '2026-07-08 16:50:20'),
(146, 9, 12, 2, '2026-07-08 16:50:20'),
(147, 8, 12, 2, '2026-07-08 16:50:20'),
(148, 7, 12, 2, '2026-07-08 16:50:20'),
(149, 6, 12, 2, '2026-07-08 16:50:20'),
(150, 5, 12, 2, '2026-07-08 16:50:20'),
(151, 4, 12, 2, '2026-07-08 16:50:20'),
(152, 3, 12, 2, '2026-07-08 16:50:20'),
(153, 2, 12, 2, '2026-07-08 16:50:20'),
(154, 10, 13, 3, '2026-07-08 16:50:20'),
(155, 9, 13, 3, '2026-07-08 16:50:20'),
(156, 8, 13, 3, '2026-07-08 16:50:20'),
(157, 7, 13, 3, '2026-07-08 16:50:20'),
(158, 6, 13, 3, '2026-07-08 16:50:20'),
(159, 5, 13, 3, '2026-07-08 16:50:20'),
(160, 4, 13, 3, '2026-07-08 16:50:20'),
(161, 3, 13, 3, '2026-07-08 16:50:20'),
(162, 2, 13, 3, '2026-07-08 16:50:20'),
(163, 10, 14, 3, '2026-07-08 16:50:20'),
(164, 9, 14, 3, '2026-07-08 16:50:20'),
(165, 8, 14, 3, '2026-07-08 16:50:20'),
(166, 7, 14, 3, '2026-07-08 16:50:20'),
(167, 6, 14, 3, '2026-07-08 16:50:20'),
(168, 5, 14, 3, '2026-07-08 16:50:20'),
(169, 4, 14, 3, '2026-07-08 16:50:20'),
(170, 3, 14, 3, '2026-07-08 16:50:20'),
(171, 2, 14, 3, '2026-07-08 16:50:20'),
(172, 10, 15, 3, '2026-07-08 16:50:20'),
(173, 9, 15, 3, '2026-07-08 16:50:20'),
(174, 8, 15, 3, '2026-07-08 16:50:20'),
(175, 7, 15, 3, '2026-07-08 16:50:20'),
(176, 6, 15, 3, '2026-07-08 16:50:20'),
(177, 5, 15, 3, '2026-07-08 16:50:20'),
(178, 4, 15, 3, '2026-07-08 16:50:20'),
(179, 3, 15, 3, '2026-07-08 16:50:20'),
(180, 2, 15, 3, '2026-07-08 16:50:20'),
(181, 10, 16, 4, '2026-07-08 16:50:20'),
(182, 9, 16, 4, '2026-07-08 16:50:20'),
(183, 8, 16, 4, '2026-07-08 16:50:20'),
(184, 7, 16, 4, '2026-07-08 16:50:20'),
(185, 6, 16, 4, '2026-07-08 16:50:20'),
(186, 5, 16, 4, '2026-07-08 16:50:20'),
(187, 4, 16, 4, '2026-07-08 16:50:20'),
(188, 3, 16, 4, '2026-07-08 16:50:20'),
(189, 2, 16, 4, '2026-07-08 16:50:20'),
(190, 10, 17, 4, '2026-07-08 16:50:20'),
(191, 9, 17, 4, '2026-07-08 16:50:20'),
(192, 8, 17, 4, '2026-07-08 16:50:20'),
(193, 7, 17, 4, '2026-07-08 16:50:20'),
(194, 6, 17, 4, '2026-07-08 16:50:20'),
(195, 5, 17, 4, '2026-07-08 16:50:20'),
(196, 4, 17, 4, '2026-07-08 16:50:20'),
(197, 3, 17, 4, '2026-07-08 16:50:20'),
(198, 2, 17, 4, '2026-07-08 16:50:20'),
(199, 10, 18, 4, '2026-07-08 16:50:20'),
(200, 9, 18, 4, '2026-07-08 16:50:20'),
(201, 8, 18, 4, '2026-07-08 16:50:20'),
(202, 7, 18, 4, '2026-07-08 16:50:20'),
(203, 6, 18, 4, '2026-07-08 16:50:20'),
(204, 5, 18, 4, '2026-07-08 16:50:20'),
(205, 4, 18, 4, '2026-07-08 16:50:20'),
(206, 3, 18, 4, '2026-07-08 16:50:20'),
(207, 2, 18, 4, '2026-07-08 16:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `extracurricular_activities`
--

CREATE TABLE `extracurricular_activities` (
  `activity_id` int NOT NULL,
  `student_id` int NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_name` varchar(100) NOT NULL,
  `description` text,
  `organization` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Completed',
  `achievement` varchar(100) DEFAULT NULL,
  `certificate_url` varchar(255) DEFAULT NULL,
  `points` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `extracurricular_activities`
--

INSERT INTO `extracurricular_activities` (`activity_id`, `student_id`, `activity_type`, `activity_name`, `description`, `organization`, `start_date`, `end_date`, `status`, `achievement`, `certificate_url`, `points`, `created_at`) VALUES
(1, 1, 'Sports', 'Inter-College Basketball Championship', 'Represented the college team as a point guard and won the tournament.', 'Sports Council', '2024-02-10', '2024-02-15', 'Completed', 'Winner', 'cert_basketball.pdf', 50, '2026-07-08 15:25:08'),
(2, 1, 'Club', 'Coding Club Lead Web Developer', 'Led the technical team of the coding club, organizing hackathons and development bootcamps.', 'EduTrack Coding Club', '2024-08-01', '2024-12-15', 'Completed', 'Appreciation Certificate', 'cert_coding_club.pdf', 80, '2026-07-08 15:25:08'),
(3, 1, 'Certification', 'AWS Certified Cloud Practitioner', 'Successfully passed the AWS Certified Cloud Practitioner exam.', 'Amazon Web Services', '2024-10-05', '2024-10-05', 'Completed', 'AWS Certified', 'aws_cert_practitioner.pdf', 100, '2026-07-08 15:25:08'),
(4, 1, 'Competition', 'EduHack Hackathon 2024', 'Built a prototype of a student information system using PHP/MySQL under 24 hours.', 'TechFest committee', '2024-11-20', '2024-11-21', 'Completed', '1st Position', 'cert_hackathon.pdf', 150, '2026-07-08 15:25:08'),
(5, 1, 'Volunteer', 'Campus Blood Donation Drive', 'Helped in logistics, management, and voter registry during the blood drive.', 'Red Cross Society', '2024-09-12', '2024-09-12', 'Completed', 'Volunteering Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 15:25:08'),
(6, 1, 'Sports', 'Inter-College Basketball Championship', 'Represented the college team as a point guard and won the tournament.', 'Sports Council', '2024-02-10', '2024-02-15', 'Completed', 'Winner', 'cert_basketball.pdf', 50, '2026-07-08 15:52:19'),
(7, 1, 'Club', 'Coding Club Lead Web Developer', 'Led the technical team of the coding club, organizing hackathons and development bootcamps.', 'EduTrack Coding Club', '2024-08-01', '2024-12-15', 'Completed', 'Appreciation Certificate', 'cert_coding_club.pdf', 80, '2026-07-08 15:52:19'),
(8, 1, 'Certification', 'AWS Certified Cloud Practitioner', 'Successfully passed the AWS Certified Cloud Practitioner exam.', 'Amazon Web Services', '2024-10-05', '2024-10-05', 'Completed', 'AWS Certified', 'aws_cert_practitioner.pdf', 100, '2026-07-08 15:52:19'),
(9, 1, 'Competition', 'EduHack Hackathon 2024', 'Built a prototype of a student information system using PHP/MySQL under 24 hours.', 'TechFest committee', '2024-11-20', '2024-11-21', 'Completed', '1st Position', 'cert_hackathon.pdf', 150, '2026-07-08 15:52:19'),
(10, 1, 'Volunteer', 'Campus Blood Donation Drive', 'Helped in logistics, management, and voter registry during the blood drive.', 'Red Cross Society', '2024-09-12', '2024-09-12', 'Completed', 'Volunteering Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 15:52:19'),
(11, 2, 'Competition', 'Inter-College Hackathon', 'Developed an innovative software prototype during a 24-hour hackathon.', 'University Tech Fest', '2024-08-10', '2024-08-11', 'Completed', 'Finalist', 'cert_hackathon.pdf', 80, '2026-07-08 16:50:20'),
(12, 5, 'Competition', 'Inter-College Hackathon', 'Developed an innovative software prototype during a 24-hour hackathon.', 'University Tech Fest', '2024-08-10', '2024-08-11', 'Completed', 'Finalist', 'cert_hackathon.pdf', 80, '2026-07-08 16:50:20'),
(13, 8, 'Competition', 'Inter-College Hackathon', 'Developed an innovative software prototype during a 24-hour hackathon.', 'University Tech Fest', '2024-08-10', '2024-08-11', 'Completed', 'Finalist', 'cert_hackathon.pdf', 80, '2026-07-08 16:50:20'),
(14, 3, 'Sports', 'University Sports Meet', 'Represented the department in the annual university sports competition.', 'Sports Council', '2024-02-12', '2024-02-15', 'Completed', 'Participant', 'cert_sports.pdf', 40, '2026-07-08 16:50:20'),
(15, 6, 'Sports', 'University Sports Meet', 'Represented the department in the annual university sports competition.', 'Sports Council', '2024-02-12', '2024-02-15', 'Completed', 'Participant', 'cert_sports.pdf', 40, '2026-07-08 16:50:20'),
(16, 10, 'Sports', 'University Sports Meet', 'Represented the department in the annual university sports competition.', 'Sports Council', '2024-02-12', '2024-02-15', 'Completed', 'Participant', 'cert_sports.pdf', 40, '2026-07-08 16:50:20'),
(17, 4, 'Certification', 'Cloud Computing Fundamentals', 'Completed professional training in cloud computing concepts and services.', 'Technology Learning Center', '2024-06-01', '2024-07-01', 'Completed', 'Certified', 'cert_cloud.pdf', 100, '2026-07-08 16:50:20'),
(18, 7, 'Certification', 'Cloud Computing Fundamentals', 'Completed professional training in cloud computing concepts and services.', 'Technology Learning Center', '2024-06-01', '2024-07-01', 'Completed', 'Certified', 'cert_cloud.pdf', 100, '2026-07-08 16:50:20'),
(19, 9, 'Certification', 'Cloud Computing Fundamentals', 'Completed professional training in cloud computing concepts and services.', 'Technology Learning Center', '2024-06-01', '2024-07-01', 'Completed', 'Certified', 'cert_cloud.pdf', 100, '2026-07-08 16:50:20'),
(20, 2, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(21, 3, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(22, 4, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(23, 5, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(24, 6, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(25, 7, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(26, 8, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(27, 9, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20'),
(28, 10, 'Volunteer', 'Campus Community Service', 'Participated in student-led community outreach and volunteering activities.', 'Student Welfare Committee', '2024-09-05', '2024-09-05', 'Completed', 'Volunteer Certificate', 'cert_volunteer.pdf', 30, '2026-07-08 16:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int NOT NULL,
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `semester` int NOT NULL,
  `internal_marks` int DEFAULT '0',
  `external_marks` int DEFAULT '0',
  `total_marks` int DEFAULT '0',
  `grade` varchar(2) DEFAULT NULL,
  `grade_points` decimal(4,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`grade_id`, `student_id`, `course_id`, `semester`, `internal_marks`, `external_marks`, `total_marks`, `grade`, `grade_points`) VALUES
(1, 1, 7, 1, 42, 45, 87, 'A', 9.00),
(2, 1, 8, 1, 38, 40, 78, 'B+', 8.00),
(3, 1, 9, 1, 45, 47, 92, 'A+', 10.00),
(4, 1, 10, 2, 44, 42, 86, 'A', 9.00),
(5, 1, 11, 2, 40, 38, 78, 'B+', 8.00),
(6, 1, 12, 2, 48, 46, 94, 'A+', 10.00),
(7, 1, 13, 3, 35, 40, 75, 'B+', 8.00),
(8, 1, 14, 3, 42, 44, 86, 'A', 9.00),
(9, 1, 15, 3, 47, 48, 95, 'A+', 10.00),
(10, 1, 16, 4, 43, 45, 88, 'A', 9.00),
(11, 1, 17, 4, 40, 42, 82, 'A', 9.00),
(12, 1, 18, 4, 45, 40, 85, 'A', 9.00),
(25, 10, 7, 1, 48, 38, 86, 'A', 9.00),
(26, 9, 7, 1, 45, 36, 81, 'A', 9.00),
(27, 8, 7, 1, 42, 47, 89, 'A', 9.00),
(28, 7, 7, 1, 39, 45, 84, 'A', 9.00),
(29, 6, 7, 1, 36, 43, 79, 'B+', 8.00),
(30, 5, 7, 1, 48, 41, 89, 'A', 9.00),
(31, 4, 7, 1, 45, 39, 84, 'A', 9.00),
(32, 3, 7, 1, 42, 37, 79, 'B+', 8.00),
(33, 2, 7, 1, 39, 48, 87, 'A', 9.00),
(34, 10, 8, 1, 35, 41, 76, 'B+', 8.00),
(35, 9, 8, 1, 47, 39, 86, 'A', 9.00),
(36, 8, 8, 1, 44, 37, 81, 'A', 9.00),
(37, 7, 8, 1, 41, 48, 89, 'A', 9.00),
(38, 6, 8, 1, 38, 46, 84, 'A', 9.00),
(39, 5, 8, 1, 35, 44, 79, 'B+', 8.00),
(40, 4, 8, 1, 47, 42, 89, 'A', 9.00),
(41, 3, 8, 1, 44, 40, 84, 'A', 9.00),
(42, 2, 8, 1, 41, 38, 79, 'B+', 8.00),
(43, 10, 9, 1, 37, 44, 81, 'A', 9.00),
(44, 9, 9, 1, 34, 42, 76, 'B+', 8.00),
(45, 8, 9, 1, 46, 40, 86, 'A', 9.00),
(46, 7, 9, 1, 43, 38, 81, 'A', 9.00),
(47, 6, 9, 1, 40, 36, 76, 'B+', 8.00),
(48, 5, 9, 1, 37, 47, 84, 'A', 9.00),
(49, 4, 9, 1, 34, 45, 79, 'B+', 8.00),
(50, 3, 9, 1, 46, 43, 89, 'A', 9.00),
(51, 2, 9, 1, 43, 41, 84, 'A', 9.00),
(52, 10, 10, 2, 39, 47, 86, 'A', 9.00),
(53, 9, 10, 2, 36, 45, 81, 'A', 9.00),
(54, 8, 10, 2, 48, 43, 91, 'A+', 10.00),
(55, 7, 10, 2, 45, 41, 86, 'A', 9.00),
(56, 6, 10, 2, 42, 39, 81, 'A', 9.00),
(57, 5, 10, 2, 39, 37, 76, 'B+', 8.00),
(58, 4, 10, 2, 36, 48, 84, 'A', 9.00),
(59, 3, 10, 2, 48, 46, 94, 'A+', 10.00),
(60, 2, 10, 2, 45, 44, 89, 'A', 9.00),
(61, 10, 11, 2, 41, 37, 78, 'B+', 8.00),
(62, 9, 11, 2, 38, 48, 86, 'A', 9.00),
(63, 8, 11, 2, 35, 46, 81, 'A', 9.00),
(64, 7, 11, 2, 47, 44, 91, 'A+', 10.00),
(65, 6, 11, 2, 44, 42, 86, 'A', 9.00),
(66, 5, 11, 2, 41, 40, 81, 'A', 9.00),
(67, 4, 11, 2, 38, 38, 76, 'B+', 8.00),
(68, 3, 11, 2, 35, 36, 71, 'B+', 8.00),
(69, 2, 11, 2, 47, 47, 94, 'A+', 10.00),
(70, 10, 12, 2, 43, 40, 83, 'A', 9.00),
(71, 9, 12, 2, 40, 38, 78, 'B+', 8.00),
(72, 8, 12, 2, 37, 36, 73, 'B+', 8.00),
(73, 7, 12, 2, 34, 47, 81, 'A', 9.00),
(74, 6, 12, 2, 46, 45, 91, 'A+', 10.00),
(75, 5, 12, 2, 43, 43, 86, 'A', 9.00),
(76, 4, 12, 2, 40, 41, 81, 'A', 9.00),
(77, 3, 12, 2, 37, 39, 76, 'B+', 8.00),
(78, 2, 12, 2, 34, 37, 71, 'B+', 8.00),
(79, 10, 13, 3, 45, 43, 88, 'A', 9.00),
(80, 9, 13, 3, 42, 41, 83, 'A', 9.00),
(81, 8, 13, 3, 39, 39, 78, 'B+', 8.00),
(82, 7, 13, 3, 36, 37, 73, 'B+', 8.00),
(83, 6, 13, 3, 48, 48, 96, 'A+', 10.00),
(84, 5, 13, 3, 45, 46, 91, 'A+', 10.00),
(85, 4, 13, 3, 42, 44, 86, 'A', 9.00),
(86, 3, 13, 3, 39, 42, 81, 'A', 9.00),
(87, 2, 13, 3, 36, 40, 76, 'B+', 8.00),
(88, 10, 14, 3, 47, 46, 93, 'A+', 10.00),
(89, 9, 14, 3, 44, 44, 88, 'A', 9.00),
(90, 8, 14, 3, 41, 42, 83, 'A', 9.00),
(91, 7, 14, 3, 38, 40, 78, 'B+', 8.00),
(92, 6, 14, 3, 35, 38, 73, 'B+', 8.00),
(93, 5, 14, 3, 47, 36, 83, 'A', 9.00),
(94, 4, 14, 3, 44, 47, 91, 'A+', 10.00),
(95, 3, 14, 3, 41, 45, 86, 'A', 9.00),
(96, 2, 14, 3, 38, 43, 81, 'A', 9.00),
(97, 10, 15, 3, 34, 36, 70, 'B+', 8.00),
(98, 9, 15, 3, 46, 47, 93, 'A+', 10.00),
(99, 8, 15, 3, 43, 45, 88, 'A', 9.00),
(100, 7, 15, 3, 40, 43, 83, 'A', 9.00),
(101, 6, 15, 3, 37, 41, 78, 'B+', 8.00),
(102, 5, 15, 3, 34, 39, 73, 'B+', 8.00),
(103, 4, 15, 3, 46, 37, 83, 'A', 9.00),
(104, 3, 15, 3, 43, 48, 91, 'A+', 10.00),
(105, 2, 15, 3, 40, 46, 86, 'A', 9.00),
(106, 10, 16, 4, 36, 39, 75, 'B+', 8.00),
(107, 9, 16, 4, 48, 37, 85, 'A', 9.00),
(108, 8, 16, 4, 45, 48, 93, 'A+', 10.00),
(109, 7, 16, 4, 42, 46, 88, 'A', 9.00),
(110, 6, 16, 4, 39, 44, 83, 'A', 9.00),
(111, 5, 16, 4, 36, 42, 78, 'B+', 8.00),
(112, 4, 16, 4, 48, 40, 88, 'A', 9.00),
(113, 3, 16, 4, 45, 38, 83, 'A', 9.00),
(114, 2, 16, 4, 42, 36, 78, 'B+', 8.00),
(115, 10, 17, 4, 38, 42, 80, 'A', 9.00),
(116, 9, 17, 4, 35, 40, 75, 'B+', 8.00),
(117, 8, 17, 4, 47, 38, 85, 'A', 9.00),
(118, 7, 17, 4, 44, 36, 80, 'A', 9.00),
(119, 6, 17, 4, 41, 47, 88, 'A', 9.00),
(120, 5, 17, 4, 38, 45, 83, 'A', 9.00),
(121, 4, 17, 4, 35, 43, 78, 'B+', 8.00),
(122, 3, 17, 4, 47, 41, 88, 'A', 9.00),
(123, 2, 17, 4, 44, 39, 83, 'A', 9.00),
(124, 10, 18, 4, 40, 45, 85, 'A', 9.00),
(125, 9, 18, 4, 37, 43, 80, 'A', 9.00),
(126, 8, 18, 4, 34, 41, 75, 'B+', 8.00),
(127, 7, 18, 4, 46, 39, 85, 'A', 9.00),
(128, 6, 18, 4, 43, 37, 80, 'A', 9.00),
(129, 5, 18, 4, 40, 48, 88, 'A', 9.00),
(130, 4, 18, 4, 37, 46, 83, 'A', 9.00),
(131, 3, 18, 4, 34, 44, 78, 'B+', 8.00),
(132, 2, 18, 4, 46, 42, 88, 'A', 9.00);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int NOT NULL,
  `enrollment_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT 'Male',
  `address` text,
  `department` varchar(100) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `current_semester` int DEFAULT '1',
  `admission_year` int DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT 'default-avatar.png',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `enrollment_id`, `password`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, `gender`, `address`, `department`, `program`, `current_semester`, `admission_year`, `profile_photo`, `created_at`) VALUES
(1, 'STU001', '$2y$10$TQSh8Ye781U3sB/oh5G8POacN9fehuyCF7wp0inXVgVLHIQ/T/G/a', 'John', 'Doe', 'john.doe@edutrack.com', '1234567890', '2003-05-15', 'Male', '123 University Street, Tech City', 'Computer Science & Engineering', 'B.Tech', 5, 2022, 'student_1_1781531269.png', '2026-07-08 15:25:08'),
(2, 'STU002', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Aarav', 'Sharma', 'aarav.sharma@edutrack.com', '9876500002', '2003-04-12', 'Male', 'Bengaluru, Karnataka', 'Computer Applications', 'MCA', 5, 2025, 'student_2_1781529485.png', '2026-07-08 16:44:32'),
(3, 'STU003', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Priya', 'Patel', 'priya.patel@edutrack.com', '9876500003', '2003-07-21', 'Female', 'Mysuru, Karnataka', 'Computer Applications', 'MCA', 5, 2025, 'student_3_1781531323.png', '2026-07-08 16:44:32'),
(4, 'STU004', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Rahul', 'Kumar', 'rahul.kumar@edutrack.com', '9876500004', '2002-11-08', 'Male', 'Bengaluru, Karnataka', 'Computer Applications', 'MCA', 5, 2025, 'student_4_1781530814.png', '2026-07-08 16:44:32'),
(5, 'STU005', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Sneha', 'Reddy', 'sneha.reddy@edutrack.com', '9876500005', '2003-02-17', 'Female', 'Hyderabad, Telangana', 'Computer Applications', 'MCA', 5, 2025, 'student_5_1781530645.png', '2026-07-08 16:44:32'),
(6, 'STU006', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Arjun', 'Rao', 'arjun.rao@edutrack.com', '9876500006', '2002-09-25', 'Male', 'Mangaluru, Karnataka', 'Computer Applications', 'MCA', 5, 2025, 'student_6_1781531360.png', '2026-07-08 16:44:32'),
(7, 'STU007', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Kavya', 'Nair', 'kavya.nair@edutrack.com', '9876500007', '2003-06-14', 'Female', 'Kochi, Kerala', 'Computer Applications', 'MCA', 5, 2025, 'student_7_1781531413.png', '2026-07-08 16:44:32'),
(8, 'STU008', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Rohan', 'Singh', 'rohan.singh@edutrack.com', '9876500008', '2002-12-03', 'Male', 'Pune, Maharashtra', 'Computer Applications', 'MCA', 5, 2025, 'student_8_1781531438.png', '2026-07-08 16:44:32'),
(9, 'STU009', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Ananya', 'Joshi', 'ananya.joshi@edutrack.com', '9876500009', '2003-08-19', 'Female', 'Mumbai, Maharashtra', 'Computer Applications', 'MCA', 5, 2025, 'student_9_1781531612.png', '2026-07-08 16:44:32'),
(10, 'STU010', '$2y$10$bkDThzBV/sOUaTficf/roev9sNAU2reUXgyKAULZJno2WFanGF/ve', 'Vikram', 'Shetty', 'vikram.shetty@edutrack.com', '9876500010', '2002-05-30', 'Male', 'Bengaluru, Karnataka', 'Computer Applications', 'MCA', 5, 2025, 'student_10_1781531490.png', '2026-07-08 16:44:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `student_course_attendance` (`student_id`,`course_id`,`semester`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_course_semester` (`student_id`,`course_id`,`semester`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `extracurricular_activities`
--
ALTER TABLE `extracurricular_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD UNIQUE KEY `student_course_grade` (`student_id`,`course_id`,`semester`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `enrollment_id` (`enrollment_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=227;

--
-- AUTO_INCREMENT for table `extracurricular_activities`
--
ALTER TABLE `extracurricular_activities`
  MODIFY `activity_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `extracurricular_activities`
--
ALTER TABLE `extracurricular_activities`
  ADD CONSTRAINT `extracurricular_activities_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
