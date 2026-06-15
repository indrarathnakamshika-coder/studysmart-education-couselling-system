-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 15, 2026 at 03:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `studysmart_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `intake_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `applied_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `qualification_required` varchar(100) DEFAULT NULL,
  `stream_required` varchar(50) DEFAULT NULL,
  `fees` decimal(10,2) DEFAULT NULL,
  `registration_fee` decimal(10,2) DEFAULT NULL,
  `installment` decimal(10,2) DEFAULT NULL,
  `study_mode` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_catalog`
--

CREATE TABLE `course_catalog` (
  `course_id` int(10) UNSIGNED NOT NULL,
  `course_name` varchar(120) NOT NULL,
  `field` varchar(100) NOT NULL,
  `level` varchar(50) NOT NULL,
  `mode` varchar(50) NOT NULL,
  `average_fee` decimal(10,2) NOT NULL,
  `duration_months` int(10) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `qualification_required` varchar(120) DEFAULT '',
  `stream_required` varchar(120) DEFAULT '',
  `fees` decimal(10,2) DEFAULT 0.00,
  `registration_fee` decimal(10,2) DEFAULT 0.00,
  `installment_amount` decimal(10,2) DEFAULT 0.00,
  `study_mode` varchar(50) DEFAULT '',
  `intake_month` varchar(20) DEFAULT '',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `category` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_catalog`
--

INSERT INTO `course_catalog` (`course_id`, `course_name`, `field`, `level`, `mode`, `average_fee`, `duration_months`, `description`, `qualification_required`, `stream_required`, `fees`, `registration_fee`, `installment_amount`, `study_mode`, `intake_month`, `status`, `category`) VALUES
(7, 'Certificate in Web Development', 'IT', 'None', 'Part-time', 80000.00, 6, NULL, 'None', 'IT', 80000.00, 10000.00, 15000.00, 'Part-time', 'March', 'Active', 'IT'),
(8, 'Certificate in Digital Marketing', 'Business', 'None', 'Part-time', 60000.00, 4, NULL, 'None', 'Business', 60000.00, 8000.00, 12000.00, 'Part-time', 'June', 'Active', 'Business'),
(9, 'Certificate in Graphic Design', 'Design', 'None', 'Part-time', 70000.00, 5, NULL, 'None', 'Design', 70000.00, 9000.00, 13000.00, 'Part-time', 'September', 'Active', 'Design'),
(10, 'Foundation in Information Technology', 'IT', 'O/l', 'Full-time', 150000.00, 12, NULL, 'O/l', 'IT', 150000.00, 15000.00, 20000.00, 'Full-time', 'January', 'Active', 'IT'),
(11, 'Foundation in Business Management', 'Business', 'O/l', 'Full-time', 140000.00, 12, NULL, 'O/l', 'Business', 140000.00, 15000.00, 18000.00, 'Full-time', 'February', 'Active', 'Business'),
(12, 'Foundation in Law', 'Law', 'O/l', 'Full-time', 160000.00, 12, NULL, 'O/l', 'Law', 160000.00, 15000.00, 20000.00, 'Full-time', 'March', 'Active', 'Law'),
(13, 'HND in Software Engineering', 'IT', 'A/L or Equivalent', 'Full-time', 400000.00, 24, NULL, 'A/L or Equivalent', 'IT', 400000.00, 25000.00, 35000.00, 'Full-time', 'January', 'Active', 'IT'),
(14, 'HND in Business Management', 'Business', 'A/L or Equivalent', 'Full-time', 380000.00, 24, NULL, 'A/L or Equivalent', 'Business', 380000.00, 25000.00, 30000.00, 'Full-time', 'April', 'Active', 'Business'),
(15, 'HND in Interior Design', 'Design', 'A/L or Equivalent', 'Full-time', 420000.00, 24, NULL, 'A/L or Equivalent', 'Design', 420000.00, 25000.00, 32000.00, 'Full-time', 'July', 'Active', 'Design'),
(16, 'BSc Computer Science', 'IT', 'A/L', 'Full-time', 1200000.00, 36, NULL, 'A/L', 'IT', 1200000.00, 50000.00, 60000.00, 'Full-time', 'January', 'Active', 'IT'),
(17, 'BBA Marketing', 'Business', 'A/L', 'Full-time', 1100000.00, 36, NULL, 'A/L', 'Business', 1100000.00, 50000.00, 55000.00, 'Full-time', 'May', 'Active', 'Business'),
(18, 'BSc Software Engineering', 'IT', 'A/L', 'Full-time', 1200000.00, 36, NULL, 'A/L', 'IT', 1200000.00, 50000.00, 60000.00, 'Full-time', 'January', 'Active', 'IT'),
(19, 'BSc Cybersecurity', 'IT', 'A/L', 'Full-time', 1200000.00, 36, NULL, 'A/L', 'IT', 1200000.00, 50000.00, 60000.00, 'Full-time', 'January', 'Active', 'IT'),
(20, 'BA Interior Design', 'Design', 'A/L', 'Part-time', 1100000.00, 36, NULL, 'A/L', 'Design', 1100000.00, 50000.00, 55000.00, 'Part-time', 'March', 'Active', 'Design'),
(21, 'HND in Human Resource Management', 'Business', 'A/L or Equivalent', 'Full-time', 400000.00, 24, NULL, 'A/L or Equivalent', 'Business', 400000.00, 25000.00, 35000.00, 'Full-time', 'February', 'Active', 'Business'),
(22, 'HND in Legal Studies', 'Law', 'A/L or Equivalent', 'Full-time', 38000.00, 24, NULL, 'A/L or Equivalent', 'Law', 38000.00, 25000.00, 30000.00, 'Full-time', 'April', 'Active', 'Law'),
(23, 'Foundation in Accounting', 'Business', 'O/l', 'Full-time', 150000.00, 12, NULL, 'O/l', 'Business', 150000.00, 15000.00, 20000.00, 'Full-time', 'March', 'Active', 'Business'),
(24, 'Foundation in Design Studies', 'Design', 'O/l', 'Full-time', 140000.00, 12, NULL, 'O/l', 'Design', 140000.00, 15000.00, 18000.00, 'Full-time', 'March', 'Active', 'Design'),
(25, 'Certificate in UI/UX Design', 'Design', 'None', 'Part-time', 80000.00, 6, NULL, 'None', 'Design', 80000.00, 10000.00, 15000.00, 'Part-time', 'February', 'Active', 'Design'),
(26, 'Certificate in Interior Design Basics', 'Design', 'None', 'Part-time', 60000.00, 6, NULL, 'None', 'Design', 60000.00, 8000.00, 12000.00, 'Part-time', 'April', 'Active', 'Design'),
(28, 'BEng Biomedical Engineering', 'Engineering', 'Undergraduate', 'Full-time', 540000.00, 48, 'Biomedical systems, medical devices, and healthcare engineering fundamentals.', 'Undergraduate', 'Engineering', 540000.00, 2500.00, 180000.00, 'Full-time', '', 'Active', 'Engineering');

-- --------------------------------------------------------

--
-- Table structure for table `course_intakes`
--

CREATE TABLE `course_intakes` (
  `intake_id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `intake_name` varchar(120) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `available_seats` int(10) UNSIGNED DEFAULT 0,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_intakes`
--

INSERT INTO `course_intakes` (`intake_id`, `course_id`, `intake_name`, `start_date`, `end_date`, `available_seats`, `status`, `created_at`) VALUES
(1, 20, 'January', '2027-01-02', '2030-01-06', 50, 'Closed', '2026-04-19 03:34:24'),
(2, 8, 'April', '2026-04-05', '2026-12-27', 0, 'Closed', '2026-04-19 03:35:02'),
(3, 11, 'September', '2026-09-20', '2027-09-19', 100, 'Open', '2026-04-19 03:35:50'),
(4, 21, 'May', '2026-05-10', '2028-04-30', 120, 'Open', '2026-04-19 03:36:36');

-- --------------------------------------------------------

--
-- Table structure for table `intakes`
--

CREATE TABLE `intakes` (
  `intake_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `intake_name` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `seats` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `stream` varchar(50) DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `full_name`, `phone`, `city`, `country`, `qualification`, `stream`, `interests`, `budget`, `created_at`) VALUES
(1, 1, 'Kamshika', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 15:37:00'),
(2, 2, 'Admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 15:38:52'),
(3, 3, 'Mathushana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 19:41:10'),
(4, 4, 'kamala', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 20:27:29'),
(5, 5, 'Dilu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 20:33:42'),
(6, 6, 'nawam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 21:04:20'),
(7, 7, 'keerthi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-18 22:09:02'),
(8, 8, 'Dinesh', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-19 03:38:19'),
(9, 9, 'Jana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-19 03:59:35'),
(10, 10, 'Karthika', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-19 05:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `student_applications`
--

CREATE TABLE `student_applications` (
  `application_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `intake_id` int(10) UNSIGNED DEFAULT NULL,
  `applicant_name` varchar(160) NOT NULL DEFAULT '',
  `applicant_email` varchar(160) NOT NULL DEFAULT '',
  `applicant_phone` varchar(40) NOT NULL DEFAULT '',
  `applicant_message` text DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'Unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_applications`
--

INSERT INTO `student_applications` (`application_id`, `user_id`, `course_id`, `status`, `applied_at`, `intake_id`, `applicant_name`, `applicant_email`, `applicant_phone`, `applicant_message`, `payment_status`) VALUES
(1, 1, 27, 'Approved', '2026-04-18 19:30:46', NULL, 'Kamshika', 'indrarathnakamshika@gmail.com', '0763219066', '', 'Paid'),
(2, 3, 23, 'Approved', '2026-04-18 20:04:51', NULL, 'Mathushana', 'mathu@gmail.com', '0762098213', '', 'Paid'),
(3, 4, 15, 'Approved', '2026-04-18 20:32:31', NULL, 'kamala', 'kamala@gmail.com', '0762098213', '', 'Unpaid'),
(4, 5, 12, 'Approved', '2026-04-18 20:37:41', NULL, 'Dilu', 'dilu@gmail.com', '0772233444', '', 'Pending'),
(5, 6, 16, 'Approved', '2026-04-18 21:08:08', NULL, 'Nawam', 'indrarathnakamshika@gmail.com', '0764540349', '', 'Unpaid'),
(6, 7, 23, 'Approved', '2026-04-18 22:12:34', NULL, 'keerthi', 'kamshidoc100@gmail.com', '0765544333', '', 'Paid'),
(7, 8, 25, 'Rejected', '2026-04-19 03:41:28', NULL, 'Dinesh', 'dinesh@gmail.com', '0778899900', '', 'Unpaid'),
(8, 9, 19, 'Approved', '2026-04-19 04:03:41', NULL, 'Jana', 'jana@gmail.com', '0765922286', '', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `student_payments`
--

CREATE TABLE `student_payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `registration_fee` decimal(10,2) NOT NULL DEFAULT 2500.00,
  `payment_plan` varchar(20) NOT NULL DEFAULT 'Full',
  `payment_method` varchar(30) DEFAULT 'Not selected',
  `payment_status` varchar(30) NOT NULL DEFAULT 'Unpaid',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `application_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_payments`
--

INSERT INTO `student_payments` (`payment_id`, `user_id`, `registration_fee`, `payment_plan`, `payment_method`, `payment_status`, `updated_at`, `application_id`) VALUES
(1, 5, 175000.00, 'Full', 'Card · 1235 **** **** 6543', 'Pending', '2026-04-19 09:13:01', 4),
(2, 7, 165000.00, 'Full', 'Card · 1234 **** **** 3456', 'Paid', '2026-04-19 03:30:41', 6),
(4, 1, 542500.00, 'Full', 'Card · 9876 **** **** 5432', 'Paid', '2026-04-19 04:51:41', 1),
(5, 9, 1250000.00, 'Full', 'Card · 9080 **** **** 5040', 'Pending', '2026-04-19 04:53:09', 8);

-- --------------------------------------------------------

--
-- Table structure for table `student_payment_history`
--

CREATE TABLE `student_payment_history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `payment_plan` varchar(20) NOT NULL,
  `payment_method` varchar(30) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status_note` varchar(80) NOT NULL DEFAULT 'Recorded',
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `application_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_payment_history`
--

INSERT INTO `student_payment_history` (`history_id`, `user_id`, `payment_plan`, `payment_method`, `amount_paid`, `status_note`, `paid_at`, `application_id`) VALUES
(1, 3, 'Course fee', 'Card · 1234 **** **** 3214', 165000.00, 'Course fee — Foundation in Accounting', '2026-04-18 20:17:47', 2),
(2, 5, 'Course fee · Install', 'Card · 4321 **** **** 0987', 20000.00, 'Course fee (installment) — Foundation in Law — Remaining: LKR 155,000.00', '2026-04-18 20:45:48', 4),
(3, 7, 'Course fee · Full', 'Card · 1234 **** **** 3456', 165000.00, 'Course fee (full) — Foundation in Accounting', '2026-04-19 03:30:41', 6),
(4, 5, 'Course fee · Install', 'Card · 2345 **** **** 2345', 20000.00, 'Course fee (installment) — Foundation in Law — Remaining: LKR 135,000.00', '2026-04-19 04:45:05', 4),
(5, 1, 'Course fee · Full', 'Card · 9876 **** **** 5432', 542500.00, 'Course fee (full) — BEng Biomedical Engineering', '2026-04-19 04:51:41', 1),
(6, 9, 'Course fee · Install', 'Card · 9080 **** **** 5040', 60000.00, 'Course fee (installment) — BSc Cybersecurity — Remaining: LKR 1,190,000.00', '2026-04-19 04:53:09', 8),
(7, 5, 'Course fee · Install', 'Card · 1234 **** **** 5678', 20000.00, 'Course fee (installment) — Foundation in Law — Remaining: LKR 115,000.00', '2026-04-19 05:55:02', 4),
(8, 5, 'Course fee · Install', 'Card · 1235 **** **** 6543', 20000.00, 'Course fee (installment) — Foundation in Law — Remaining: LKR 95,000.00', '2026-04-19 09:13:01', 4),
(9, 5, 'Course fee · Install', 'Card · 1235 **** **** 6543', 20000.00, 'Course fee (installment) — Foundation in Law — Remaining: LKR 75,000.00', '2026-04-19 09:13:29', 4);

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `profile_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) DEFAULT '',
  `phone` varchar(20) DEFAULT '',
  `date_of_birth` date DEFAULT NULL,
  `address` varchar(255) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `country` varchar(100) DEFAULT '',
  `highest_education` varchar(120) DEFAULT '',
  `institution` varchar(120) DEFAULT '',
  `graduation_year` varchar(10) DEFAULT '',
  `gpa` varchar(10) DEFAULT '',
  `financial_status` varchar(50) DEFAULT '',
  `budget_range` varchar(50) DEFAULT '',
  `study_mode` varchar(50) DEFAULT '',
  `preferred_field` varchar(100) DEFAULT '',
  `preferred_level` varchar(50) DEFAULT '',
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `education_qualification` varchar(40) NOT NULL DEFAULT '',
  `al_pass_count` int(10) UNSIGNED DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `progression_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `eligible_category` varchar(160) NOT NULL DEFAULT '',
  `profile_completed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`profile_id`, `user_id`, `full_name`, `phone`, `date_of_birth`, `address`, `city`, `country`, `highest_education`, `institution`, `graduation_year`, `gpa`, `financial_status`, `budget_range`, `study_mode`, `preferred_field`, `preferred_level`, `notes`, `updated_at`, `education_qualification`, `al_pass_count`, `interests`, `progression_level`, `eligible_category`, `profile_completed`) VALUES
(1, 1, 'Kamshika', '0763219066', '2001-04-01', '18/21A, Mawilmada', 'Kandy', 'Sri Lanka', 'G.C.E A/L', 'national school', '2022', '3S', 'Self-funded', '300000', 'Full-time', '', '', '', '2026-04-18 18:10:23', 'al', 3, NULL, 4, 'degree programs', 1),
(3, 3, 'Mathushana', '0762098213', '2003-04-01', 'Katugusthota', 'Kandy', 'Sri Lanka', 'G.C.E O/L', 'national school', '2024', '9B', 'Self-funded', '200000', 'Full-time', '', '', '', '2026-04-18 19:43:39', 'ol', 0, 'Business', 2, 'foundation programs', 1),
(4, 4, 'kamala', '0772098413', '2004-01-01', 'Peradeniya', 'Kandy', 'Sri Lanka', 'G.C.E A/L', 'Girls high School', '2025', '2C', 'Loan support', '300000', 'Full-time', '', '', '', '2026-04-18 20:31:29', 'al', 2, 'IT', 3, 'HND programs', 1),
(5, 5, 'Dilu', '0742233444', '2008-09-02', 'Araliya mawatha', 'Kandy', 'Sri Lanka', 'G.C.E O/L', 'Central College', '2024', '5A 4C', 'Self-funded', '400000', 'Full-time', '', '', '', '2026-04-18 20:36:49', 'ol', 0, 'Law', 2, 'foundation programs', 1),
(6, 6, 'Nawam', '0764540349', '2000-02-26', 'Bomaluwa', 'Kandy', 'Sri Lanka', 'HND in Software Engineering', 'Esoft Uni', '2025', 'Distinctio', 'Self-funded', '1100000', 'Full-time', '', '', '', '2026-04-18 21:07:00', 'al', 3, 'IT', 4, 'degree programs', 1),
(7, 7, 'Keerthi', '0765544333', '2001-04-02', 'Bomaluwa', 'Kandy', 'Sri Lanka', 'Diploma in Business Management', 'Esoft Uni', '2025', 'Pass', 'Scholarship needed', '100000', 'Part-time', '', '', '', '2026-04-18 22:11:34', 'ol', 0, 'Business', 2, 'foundation programs', 1),
(8, 8, 'Dinesh', '0778899900', '2003-04-15', 'Digana', 'Kandy', 'Sri Lanka', 'G.C.E O/L', 'Central College', '2025', '5S', 'Self-funded', '100000', 'Full-time', '', '', '', '2026-04-19 03:40:31', 'below_ol', 0, 'Design', 1, 'skill-based courses', 1),
(9, 9, 'Jana', '0765922286', '2000-02-08', 'Main Street', 'Madawala', 'Sri Lanka', 'G.C.E A/L', 'Central College', '2025', '3B', 'Loan support', '1500000', 'Full-time', '', '', '', '2026-04-19 04:02:59', 'al', 3, 'Web Development', 4, 'degree programs', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `username`, `email`, `password`, `role`) VALUES
(1, NULL, 'kamshi01', 'kamshi@gmail.com', '$2y$10$sewAuVwWIeKM/5dj2ooCl.ygSL9P3ZQjq4j7QQQjMneETRzZc2c9m', 'student'),
(2, NULL, 'admin1', 'admin@gmail.com', '$2y$10$JzY8TLKzdNGtZQtUG3j.fu5ZaaYBrAPIGIKPpMKU1aZMWKUjqfiOu', 'admin'),
(3, NULL, 'mathu01', 'mathu@gmail.com', '$2y$10$Np3GRGhm.wPB1VL2heA0nOUB.kzzUNgDS/ww5fn7kVjdBQTAkCe0u', 'student'),
(4, NULL, 'kamala01', 'kamala@gmail.com', '$2y$10$A/4rVNfiZyg8mTxXSAGleeZp2xnR2bHIXC01fin2mWJs.hExQxzru', 'student'),
(5, NULL, 'dilu01', 'dilu@gmail.com', '$2y$10$/dSCg7odL//O/xWCZJ/mLeJHvElPmvtoIqm1crr8CBg.WcqTajdAK', 'student'),
(6, NULL, 'nawam01', 'indrarathnakamshika@gmail.com', '$2y$10$ZXHfDHEeLWrLpa2LDoyKBuqWiOQvI1GlmLt38bzKtICQR6zEA82AW', 'student'),
(7, NULL, 'keerthi01', 'kamshidoc1001@gmail.com', '$2y$10$2oNq6sNzN1MU/Xrtxjf3Tedm/RGcgMuZU7LVcN.3sUInkM032VMFy', 'student'),
(8, NULL, 'dinesh01', 'dinesh@gmail.com', '$2y$10$/NzeJnUi0MAS0lTIdo.k9eKG185m/T3HAJ97CLxn5MitACXMiK/0C', 'student'),
(9, NULL, 'jana01', 'jana@gmail.com', '$2y$10$rZTTSGcnh5kHJYY6NBC/9.zw.W1oW7rinE9rfRFjrpwEaWaj5URxy', 'student'),
(10, NULL, 'karthi01', 'karthi@gmail.com', '$2y$10$RoNO/4NFD/5vWVKBAVTgvOKHpKoPPWgZveVIkZmFyyzw01UN1OaqW', 'student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `course_catalog`
--
ALTER TABLE `course_catalog`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `course_intakes`
--
ALTER TABLE `course_intakes`
  ADD PRIMARY KEY (`intake_id`);

--
-- Indexes for table `intakes`
--
ALTER TABLE `intakes`
  ADD PRIMARY KEY (`intake_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `student_applications`
--
ALTER TABLE `student_applications`
  ADD PRIMARY KEY (`application_id`);

--
-- Indexes for table `student_payments`
--
ALTER TABLE `student_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `student_payment_history`
--
ALTER TABLE `student_payment_history`
  ADD PRIMARY KEY (`history_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_catalog`
--
ALTER TABLE `course_catalog`
  MODIFY `course_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `course_intakes`
--
ALTER TABLE `course_intakes`
  MODIFY `intake_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `intakes`
--
ALTER TABLE `intakes`
  MODIFY `intake_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `student_applications`
--
ALTER TABLE `student_applications`
  MODIFY `application_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_payments`
--
ALTER TABLE `student_payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_payment_history`
--
ALTER TABLE `student_payment_history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `profile_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
