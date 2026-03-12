-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2026 at 04:54 PM
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
-- Database: `ams`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `calculate_work_order_progress` (IN `work_order_id_param` INT, OUT `progress_percentage` DECIMAL(5,2))   BEGIN
    DECLARE task_count INT;
    DECLARE completed_tasks INT;
    
    -- Count total tasks
    SELECT COUNT(*) INTO task_count
    FROM work_order_tasks
    WHERE work_order_id = work_order_id_param;
    
    -- Count completed tasks
    SELECT COUNT(*) INTO completed_tasks
    FROM work_order_tasks
    WHERE work_order_id = work_order_id_param 
    AND task_status = 'Completed';
    
    IF task_count = 0 THEN
        SET progress_percentage = 0;
    ELSE
        SET progress_percentage = (completed_tasks * 100.0) / task_count;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_work_order_number` (OUT `new_wo_number` VARCHAR(50))   BEGIN
    DECLARE prefix VARCHAR(10) DEFAULT 'WO';
    DECLARE date_part VARCHAR(8);
    DECLARE seq_num INT;
    
    SET date_part = DATE_FORMAT(CURDATE(), '%Y%m%d');
    
    -- Get the next sequence number for today
    SELECT COALESCE(MAX(CAST(SUBSTRING(work_order_number, -3) AS UNSIGNED)), 0) + 1 
    INTO seq_num
    FROM subcontractor_work_orders 
    WHERE work_order_number LIKE CONCAT(prefix, '-', date_part, '-%');
    
    SET new_wo_number = CONCAT(prefix, '-', date_part, '-', LPAD(seq_num, 3, '0'));
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','absent','late','half_day','leave') DEFAULT 'absent',
  `hours_worked` decimal(5,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `date`, `check_in`, `check_out`, `status`, `hours_worked`, `notes`, `marked_by`, `created_at`, `updated_at`) VALUES
(1, 2, '2026-01-22', '10:00:41', '10:00:50', 'present', NULL, NULL, 1, '2026-01-22 09:00:41', '2026-01-22 09:00:50');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_reports`
--

CREATE TABLE `attendance_reports` (
  `id` int(11) NOT NULL,
  `report_month` date NOT NULL,
  `employee_id` int(11) NOT NULL,
  `total_days` int(11) DEFAULT 0,
  `present_days` int(11) DEFAULT 0,
  `absent_days` int(11) DEFAULT 0,
  `late_days` int(11) DEFAULT 0,
  `leave_days` int(11) DEFAULT 0,
  `half_days` int(11) DEFAULT 0,
  `total_hours` decimal(10,2) DEFAULT 0.00,
  `ot_hours` decimal(10,2) DEFAULT 0.00,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_settings`
--

CREATE TABLE `attendance_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_settings`
--

INSERT INTO `attendance_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`) VALUES
(1, 'office_start_time', '09:00:00', 'Official office start time', '2026-01-22 07:38:36'),
(2, 'office_end_time', '17:00:00', 'Official office end time', '2026-01-22 07:38:36'),
(3, 'late_threshold', '09:15:00', 'Time after which employee is marked late', '2026-01-22 07:38:36'),
(4, 'half_day_threshold', '4', 'Minimum hours for half day', '2026-01-22 07:38:36'),
(5, 'work_days', '1,2,3,4,5', 'Monday-Friday (1=Monday, 7=Sunday)', '2026-01-22 07:38:36'),
(6, 'auto_checkout_time', '18:00:00', 'Auto checkout time if employee forgets', '2026-01-22 07:38:36'),
(7, 'ip_restriction', '0', 'Whether to restrict attendance by IP', '2026-01-22 07:38:36'),
(8, 'geo_location', '0', 'Enable GPS location tracking', '2026-01-22 07:38:36'),
(9, 'break_time', '60', 'Break time in minutes', '2026-01-22 07:38:36'),
(10, 'ot_start_after', '8', 'OT starts after these hours', '2026-01-22 07:38:36');

-- --------------------------------------------------------

--
-- Table structure for table `backup_settings`
--

CREATE TABLE `backup_settings` (
  `id` int(11) NOT NULL,
  `backup_enabled` tinyint(1) DEFAULT 1,
  `backup_frequency` enum('daily','weekly','monthly') DEFAULT 'weekly',
  `backup_day` varchar(20) DEFAULT 'monday',
  `backup_time` time DEFAULT '02:00:00',
  `backup_retention` int(11) DEFAULT 30,
  `max_backups` int(11) DEFAULT 10,
  `compress_backup` tinyint(1) DEFAULT 1,
  `email_notification` tinyint(1) DEFAULT 1,
  `email_address` varchar(255) DEFAULT NULL,
  `backup_method` enum('mysqldump','php','both') DEFAULT 'php',
  `last_backup` datetime DEFAULT NULL,
  `last_backup_file` varchar(255) DEFAULT NULL,
  `last_backup_size` int(11) DEFAULT NULL,
  `backup_path` varchar(500) DEFAULT './backups/',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_settings`
--

INSERT INTO `backup_settings` (`id`, `backup_enabled`, `backup_frequency`, `backup_day`, `backup_time`, `backup_retention`, `max_backups`, `compress_backup`, `email_notification`, `email_address`, `backup_method`, `last_backup`, `last_backup_file`, `last_backup_size`, `backup_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'weekly', 'monday', '02:00:00', 30, 10, 1, 1, 'mdjhk19@gmail.com', 'php', '2026-01-22 20:21:03', 'backup_2026-01-22_15-21-03.sql.gz', 12488, 'C:xampphtdocsamsadmin/backups/', '2026-01-22 13:36:44', '2026-01-22 14:21:03');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `country` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `email`, `phone`, `company_name`, `address`, `status`, `photo_url`, `created_at`, `updated_at`, `country`, `state`, `city`, `zip_code`) VALUES
(2, 'Md. Jahidul Hakim', 'mdjhk19@gmail.com', '+8801837090666', 'AR TECH SOLUTION', 'Dhaka,Gazipur,Boardbazar,National university,south khailkur,38no woard,sohid siddik road, holding no:446', 'inactive', 'uploads/cust_693d7ee66c9da6.27482376.png', '2025-12-13 14:57:42', '2026-01-22 11:15:31', 'Bangladesh', 'Gazipur', 'Gazipur', '1704'),
(3, 'ytjgfjhfgj', 'admin@example.com', '019875254554', 'ghghfdtgh', 'Holdin no: 343/A,Sarker Bari,Uttar Khan,(Helal Market).', 'active', 'uploads/cust_6940fd3ea3bff6.96430988.jpg', '2025-12-16 06:33:34', '2025-12-16 06:33:34', 'Bangladesh', '', 'Dhaka, Bangladesh', '1230');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `full_name`, `email`, `phone`, `designation`, `department`, `joining_date`, `salary`, `status`, `created_at`, `photo`) VALUES
(1, 'Md. ashik', 'mdjhk19@gmail.com', '+8801837090666', 'wig operator', '', '2025-12-20', 5000.00, 'Active', '2025-12-20 10:56:33', NULL),
(2, 'JAHID KHAN', 'mdjhk300@gmail.com', '+8801957288638', 'wig operator', '', '2025-12-20', 30000.00, 'Active', '2025-12-20 11:01:23', '1766230722_2384.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `holiday_name` varchar(255) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('public','company','optional') DEFAULT 'public',
  `description` text DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` decimal(10,2) DEFAULT 0.00,
  `min_stock_level` decimal(10,2) DEFAULT 10.00,
  `unit` varchar(50) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('sick','casual','annual','maternity','paternity','unpaid') DEFAULT 'casual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_events`
--

CREATE TABLE `login_events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_events`
--

INSERT INTO `login_events` (`id`, `user_id`, `username`, `success`, `ip`, `notes`, `created_at`) VALUES
(1, 1, 'admin', 1, '203.0.113.5', NULL, '2025-12-12 16:31:59'),
(2, 2, 'manager1', 1, '198.51.100.12', NULL, '2025-12-12 15:31:59'),
(3, 3, 'manager2', 0, '198.51.100.33', NULL, '2025-12-11 17:31:59'),
(4, 2, 'manager1', 1, '198.51.100.12', NULL, '2025-12-09 17:31:59'),
(5, 1, 'admin', 1, '203.0.113.5', NULL, '2025-12-05 17:31:59'),
(6, 1, 'admin', 1, '::1', NULL, '2025-12-12 17:35:35'),
(7, 1, 'admin', 1, '::1', NULL, '2025-12-12 17:37:09'),
(8, 1, 'admin', 1, '::1', NULL, '2025-12-12 22:06:31'),
(9, 1, 'admin', 1, '::1', NULL, '2025-12-13 12:37:42'),
(10, 1, 'admin', 1, '::1', NULL, '2025-12-13 13:43:56'),
(11, 1, 'admin', 1, '::1', NULL, '2025-12-13 16:32:51'),
(12, 1, 'admin', 1, '::1', NULL, '2025-12-14 17:15:45'),
(13, 1, 'admin', 1, '::1', NULL, '2025-12-15 18:31:03'),
(14, 1, 'admin', 1, '::1', NULL, '2025-12-15 22:32:59'),
(15, 1, 'admin', 1, '::1', NULL, '2025-12-16 11:09:41'),
(16, 1, 'admin', 1, '::1', NULL, '2025-12-16 19:21:40'),
(17, 1, 'admin', 1, '::1', NULL, '2025-12-16 22:12:21'),
(18, 1, 'admin', 1, '::1', NULL, '2025-12-17 18:20:38'),
(19, 1, 'admin', 1, '::1', NULL, '2025-12-17 21:07:10'),
(20, 1, 'admin', 1, '::1', NULL, '2025-12-17 22:09:20'),
(21, 1, 'admin', 1, '::1', NULL, '2025-12-18 02:02:58'),
(22, 1, 'admin', 1, '::1', NULL, '2025-12-18 11:33:07'),
(23, 1, 'admin', 1, '::1', NULL, '2025-12-19 12:03:17'),
(24, 1, 'admin', 1, '::1', NULL, '2025-12-19 19:24:14'),
(25, 1, 'admin', 1, '::1', NULL, '2025-12-19 21:59:25'),
(26, 1, 'admin', 1, '::1', NULL, '2025-12-20 05:33:17'),
(27, 1, 'admin', 1, '::1', NULL, '2025-12-20 15:00:14'),
(28, 1, 'admin', 1, '::1', NULL, '2025-12-20 19:06:56'),
(29, 1, 'admin', 1, '::1', NULL, '2025-12-21 17:17:17'),
(30, 1, 'admin', 1, '::1', NULL, '2025-12-22 18:07:39'),
(31, NULL, 'admin@example.com', 0, '::1', NULL, '2025-12-23 17:48:41'),
(32, 1, 'admin', 1, '::1', NULL, '2025-12-23 17:48:51'),
(33, 1, 'admin', 1, '::1', NULL, '2025-12-24 19:10:42'),
(34, NULL, 'users@example.com', 0, '::1', NULL, '2025-12-24 23:22:39'),
(35, 1, 'admin', 1, '::1', NULL, '2025-12-24 23:22:43'),
(36, 1, 'admin', 1, '::1', NULL, '2025-12-25 00:22:33'),
(37, 1, 'admin', 1, '::1', NULL, '2025-12-25 10:59:58'),
(38, NULL, 'admin', 0, '::1', NULL, '2025-12-25 14:23:10'),
(39, 1, 'jahid', 1, '::1', NULL, '2025-12-25 14:23:16'),
(40, NULL, 'admin', 0, '::1', NULL, '2025-12-26 00:17:37'),
(41, 1, 'jahid', 1, '::1', NULL, '2025-12-26 00:17:42'),
(42, 1, 'jahid', 1, '::1', NULL, '2025-12-26 01:03:54'),
(43, 1, 'jahid', 1, '::1', NULL, '2025-12-26 02:42:20'),
(44, 1, 'jahid', 1, '::1', NULL, '2025-12-26 11:29:22'),
(45, 1, 'jahid', 1, '::1', NULL, '2025-12-26 15:47:50'),
(46, 1, 'jahid', 1, '::1', NULL, '2025-12-27 15:44:24'),
(47, 1, 'jahid', 0, '::1', NULL, '2025-12-28 17:01:56'),
(48, 1, 'jahid', 0, '::1', NULL, '2025-12-28 17:02:00'),
(49, 1, 'jahid', 1, '::1', NULL, '2025-12-28 17:02:31'),
(50, 1, 'jahid', 1, '::1', NULL, '2025-12-29 20:26:50'),
(51, 1, 'jahid', 1, '::1', NULL, '2025-12-30 15:31:36'),
(52, 1, 'jahid', 1, '::1', NULL, '2025-12-30 21:27:26'),
(53, NULL, 'admin@example.com', 0, '::1', NULL, '2026-01-05 22:11:46'),
(54, 1, 'jahid', 0, '::1', NULL, '2026-01-05 22:11:57'),
(55, 1, 'jahid', 0, '::1', NULL, '2026-01-05 22:12:03'),
(56, 1, 'jahid', 0, '::1', NULL, '2026-01-05 22:12:07'),
(57, 1, 'jahid', 0, '::1', NULL, '2026-01-05 22:12:24'),
(58, 1, 'jahid', 0, '::1', NULL, '2026-01-05 22:12:31'),
(59, 1, 'jahid', 1, '::1', NULL, '2026-01-05 22:13:31'),
(60, NULL, 'admin@example.com', 0, '::1', NULL, '2026-01-09 19:05:05'),
(61, 1, 'jahid', 0, '::1', NULL, '2026-01-09 19:05:15'),
(62, NULL, 'admin', 0, '::1', NULL, '2026-01-09 19:05:30'),
(63, NULL, 'admin', 0, '::1', NULL, '2026-01-09 19:05:41'),
(64, 1, 'jahid', 1, '::1', NULL, '2026-01-09 19:06:48'),
(65, 1, 'jahid', 1, '::1', NULL, '2026-01-12 19:25:21'),
(66, NULL, 'admin@example.com', 0, '::1', NULL, '2026-01-13 15:04:23'),
(67, 1, 'jahid', 1, '::1', NULL, '2026-01-13 15:04:35'),
(68, 1, 'jahid', 0, '::1', NULL, '2026-01-14 17:52:13'),
(69, 1, 'jahid', 1, '::1', NULL, '2026-01-14 17:52:27'),
(70, 1, 'jahid', 1, '::1', NULL, '2026-01-14 18:40:11'),
(71, 1, 'jahid', 0, '::1', NULL, '2026-01-16 19:01:16'),
(72, 1, 'jahid', 1, '::1', NULL, '2026-01-16 19:01:27'),
(73, 1, 'jahid', 1, '::1', NULL, '2026-01-21 17:42:42'),
(74, 1, 'jahid', 1, '::1', NULL, '2026-01-22 10:04:34'),
(75, 1, 'jahid', 1, '::1', NULL, '2026-01-22 17:11:28'),
(76, 1, 'jahid', 1, '::1', NULL, '2026-01-22 19:12:37'),
(77, 1, 'jahid', 0, '::1', NULL, '2026-01-22 20:42:43'),
(78, 1, 'jahid', 0, '::1', NULL, '2026-01-22 20:42:50'),
(79, 1, 'jahid', 1, '::1', NULL, '2026-01-22 20:43:25'),
(80, 1, 'jahid', 1, '::1', 'Password reset requested', '2026-01-22 20:43:38'),
(81, 1, 'jahid', 1, '::1', 'Password reset requested', '2026-01-22 21:03:09'),
(82, 1, 'jahid', 1, '::1', NULL, '2026-01-22 21:03:19'),
(83, 2, 'manager1', 1, '::1', NULL, '2026-01-22 21:12:52'),
(84, 1, 'jahid', 1, '::1', NULL, '2026-01-22 21:34:05');

-- --------------------------------------------------------

--
-- Table structure for table `logistics_expenses`
--

CREATE TABLE `logistics_expenses` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `provider` varchar(100) NOT NULL COMMENT 'e.g. DHL, FedEx, Local',
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `status` varchar(20) DEFAULT 'Paid',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logistics_expenses`
--

INSERT INTO `logistics_expenses` (`id`, `reference_no`, `provider`, `expense_date`, `amount`, `payment_method`, `status`, `description`, `created_at`) VALUES
(1, 'dfdfdf', 'ddfjiioiiiii', '2025-12-23', 500.00, 'Cash', 'Paid', 'gffdfdgfgfg', '2025-12-23 16:50:40'),
(2, 'INV-000001', 'o0i9-098-', '2025-12-24', 50.00, 'Cash', 'Paid', 'o=09=9=o9i', '2025-12-24 14:00:53');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `token_used` tinyint(1) DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_activities`
--

CREATE TABLE `payment_activities` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `work_order_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_activities`
--

INSERT INTO `payment_activities` (`id`, `payment_id`, `work_order_id`, `action`, `description`, `performed_by`, `created_at`) VALUES
(11, 4, 2, 'payment_recorded', 'Payment of $50.00 recorded via Cash', 1, '2026-01-22 05:21:59'),
(12, 4, 2, 'payment_refunded', 'Payment of $50.00 marked as refunded', 1, '2026-01-22 05:22:08'),
(13, 5, 2, 'payment_recorded', 'Payment of $100.00 recorded via Cash', 1, '2026-01-22 11:39:53');

-- --------------------------------------------------------

--
-- Table structure for table `payment_history`
--

CREATE TABLE `payment_history` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_by` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_history`
--

INSERT INTO `payment_history` (`id`, `purchase_id`, `amount`, `paid_by`, `paid_at`) VALUES
(6, 34, 5000.00, NULL, '2025-12-25 22:42:16'),
(7, 34, 1000.00, NULL, '2025-12-25 22:49:25'),
(8, 34, 5000.00, NULL, '2025-12-25 22:50:01');

-- --------------------------------------------------------

--
-- Table structure for table `production_costs`
--

CREATE TABLE `production_costs` (
  `id` int(11) NOT NULL,
  `batch_name` varchar(100) NOT NULL COMMENT 'e.g. Winter Jacket Batch 001',
  `production_date` date NOT NULL,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vendor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_costs`
--

INSERT INTO `production_costs` (`id`, `batch_name`, `production_date`, `total_cost`, `notes`, `created_at`, `vendor_id`) VALUES
(1, 'ffdd', '2025-12-17', 5000.00, 'dfd', '2025-12-17 16:35:21', NULL),
(2, 'pant', '2025-12-19', 13000.00, '', '2025-12-19 06:23:27', 4);

-- --------------------------------------------------------

--
-- Table structure for table `production_items`
--

CREATE TABLE `production_items` (
  `id` int(11) NOT NULL,
  `production_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_items`
--

INSERT INTO `production_items` (`id`, `production_id`, `item_name`, `quantity`, `unit_cost`, `subtotal`) VALUES
(1, 1, 'fdfd', 10.00, 500.00, 5000.00),
(2, 2, 'suta', 100.00, 20.00, 2000.00),
(3, 2, 'color', 20.00, 10.00, 200.00),
(4, 2, 'cemical', 10.00, 80.00, 800.00),
(5, 2, 'lavour', 20.00, 500.00, 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT 'Uncategorized',
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `batch_name` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 5,
  `max_stock` int(11) DEFAULT 100,
  `unit_type` varchar(50) DEFAULT 'pcs',
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `sku`, `category`, `description`, `image_url`, `vendor_id`, `batch_name`, `created_by`, `quantity`, `min_stock`, `max_stock`, `unit_type`, `purchase_price`, `selling_price`, `discount`, `tax_rate`, `status`, `created_at`, `updated_at`) VALUES
(1, 'jahid', 'HR-st-01', 'Bundles', 'yyty', 'uploads/products/prod_1765816508_694038bce4ea0.png', 2, NULL, 1, 5, 5, 100, 'pcs', 80.00, 800.00, 0.00, 2.00, 'Active', '2025-12-15 22:35:08', '2026-01-22 17:29:11'),
(2, 'pant and shoes', 'SKU-244252', 'Hair Care', 'dfdfdfsdf', 'uploads/products/prod_1766126320_6944f2f063276.jpg', 1, NULL, 1, 100, 5, 100, 'pcs', 180.00, 20000.00, 0.00, 1.00, 'Active', '2025-12-19 12:38:40', '2025-12-19 12:38:40'),
(3, 'jfiofj0oif', 'SKU-821891', 'Frontals', 'rrr', 'uploads/products/prod_1766126707_6944f473e18c1.jpg', 4, NULL, 1, 1000, 5, 100, 'pcs', 18.00, 200000.00, 0.00, 0.00, 'Active', '2025-12-19 12:45:07', '2025-12-19 12:45:07'),
(4, 'opi', 'SKU-263499', 'Wigs & Lace', 'ffhhf', 'uploads/products/prod_1766129427_6944ff13dc904.jpg', 2, NULL, 1, 0, 5, 100, 'pcs', 1800.00, 2000.00, 0.00, 1.00, 'Active', '2025-12-19 13:30:27', '2026-01-14 17:53:04'),
(5, 'T shirt', 'SKU-788470', 'Tools', 'uojhgujoh', 'uploads/products/prod_1766131801_694508597f361.jpg', 3, NULL, 1, 100, 5, 100, 'pcs', 180.00, 2000.00, 0.00, 2.00, 'Active', '2025-12-19 14:10:01', '2025-12-19 14:10:01'),
(7, 'jahid 66', 'SKU-890051', 'Bundles', '[\'[\'', 'uploads/products/prod_1766188310_6945e51611444.jpg', 1, 'ffdd, pant', 1, 10, 5, 100, 'pcs', 1800.00, 2000.00, 0.00, 1.00, 'Active', '2025-12-20 05:51:50', '2025-12-20 05:51:50');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `reference_no` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_name` varchar(255) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('Pending','Received','Partial','Cancelled') DEFAULT 'Pending',
  `payment_status` enum('Unpaid','Partial','Paid') DEFAULT 'Unpaid',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `customer_id`, `reference_no`, `order_date`, `total_amount`, `product_name`, `paid_amount`, `status`, `payment_status`, `note`, `created_at`) VALUES
(34, 2, 'INV-000001', '2025-12-25', 16000.00, 'jahid', 15000.00, '', 'Partial', 'fgfdsg', '2025-12-25 14:47:41'),
(36, 2, 'INV-000035', '2026-01-14', 2000.00, 'opi', 0.00, '', 'Unpaid', '', '2026-01-14 11:53:04'),
(37, 2, 'INV-000037', '2026-01-22', 12000.00, 'jahid', 0.00, 'Pending', 'Unpaid', 'gsddg', '2026-01-22 11:29:11');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_id`, `product_id`, `quantity`, `unit_cost`, `subtotal`) VALUES
(1, 6, 1, 1, 800.00, 800.00),
(2, 7, 1, 11, 800.00, 8800.00),
(3, 8, 1, 25, 800.00, 20000.00),
(4, 9, 1, 22, 800.00, 17600.00),
(5, 10, 1, 20, 800.00, 16000.00),
(6, 11, 1, 100, 800.00, 80000.00),
(7, 12, 1, 501, 800.00, 400800.00),
(8, 13, 1, 62, 800.00, 49600.00),
(9, 14, 1, 62, 800.00, 49600.00),
(10, 15, 1, 62, 800.00, 49600.00),
(11, 16, 1, 200, 800.00, 160000.00),
(12, 17, 1, 90, 800.00, 72000.00),
(13, 18, 1, 500, 800.00, 400000.00),
(14, 19, 1, 59, 800.00, 47200.00),
(15, 20, 1, 1, 800.00, 800.00),
(16, 21, 1, 20, 800.00, 16000.00),
(17, 22, 1, 10, 800.00, 8000.00),
(18, 23, 1, 10, 800.00, 8000.00),
(19, 24, 1, 10, 800.00, 8000.00),
(20, 25, 1, 10, 800.00, 8000.00),
(21, 26, 1, 10, 800.00, 8000.00),
(22, 27, 1, 20, 800.00, 16000.00),
(23, 28, 1, 20, 800.00, 16000.00),
(24, 29, 1, 50, 800.00, 40000.00),
(25, 30, 1, 200, 800.00, 160000.00),
(26, 31, 1, 100, 800.00, 80000.00),
(27, 32, 4, 9, 2000.00, 18000.00),
(28, 33, 1, 50, 800.00, 40000.00),
(29, 34, 1, 20, 800.00, 16000.00),
(30, 35, 1, 20, 800.00, 16000.00),
(31, 36, 4, 1, 2000.00, 2000.00),
(32, 37, 1, 15, 800.00, 12000.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `total_refund` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_payments`
--

CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payment_month` varchar(7) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_payments`
--

INSERT INTO `salary_payments` (`id`, `employee_id`, `payment_month`, `payment_date`, `amount`, `payment_method`, `note`, `created_at`) VALUES
(3, 2, '2025-12', '2025-12-24', 30000.00, 'Cash', '', '2025-12-24 16:01:51'),
(4, 1, '2025-12', '2025-12-24', 5000.00, 'Cash', '', '2025-12-24 16:02:06'),
(5, 2, '2025-11', '2025-12-24', 30000.00, 'Cash', '', '2025-12-24 16:03:11');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `sub_total` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `final_total` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `customer_name`, `sale_date`, `sub_total`, `tax`, `discount`, `final_total`, `payment_method`, `notes`) VALUES
(1, 'Walk-in Customer', '2025-12-17 22:29:37', 0.00, 0.00, 0.00, 120.50, 'Cash', NULL),
(2, 'John Doe', '2025-12-15 22:29:37', 0.00, 0.00, 0.00, 450.00, 'Card', NULL),
(3, 'Jane Smith', '2025-12-12 22:29:37', 0.00, 0.00, 0.00, 1200.00, 'Bank Transfer', NULL),
(4, 'Acme Corp', '2025-12-07 22:29:37', 0.00, 0.00, 0.00, 3500.00, 'Check', NULL),
(5, 'XYZ Ltd', '2025-11-17 22:29:37', 0.00, 0.00, 0.00, 540.00, 'Cash', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `change_type` enum('Purchase','Sale','Return','Adjustment','Damage') NOT NULL,
  `quantity_changed` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subcontractors`
--

CREATE TABLE `subcontractors` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `bank_account_info` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `contract_start` date DEFAULT NULL,
  `contract_end` date DEFAULT NULL,
  `project_rate` decimal(10,2) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive','Pending') DEFAULT 'Active',
  `rating` decimal(3,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcontractors`
--

INSERT INTO `subcontractors` (`id`, `company_name`, `contact_person`, `bank_account_info`, `email`, `phone`, `address`, `specialization`, `tax_id`, `registration_date`, `contract_start`, `contract_end`, `project_rate`, `payment_terms`, `status`, `rating`, `notes`, `created_at`, `updated_at`) VALUES
(7, 'AR TECH SOLUTION', 'Md. Jahidul Hakim', NULL, 'mdjhk19@gmail.com', '(880) 183-7090', 'hngdfh', 'Other', '', '2025-12-30', NULL, NULL, 0.00, NULL, 'Active', 0.00, 'ghdgh', '2025-12-30 10:48:48', '2026-01-21 15:32:54'),
(8, 'ABC Construction Co.', 'John Smith', NULL, 'john@abcconstruction.com', '+1-555-123-4567', NULL, 'General Contracting', NULL, NULL, NULL, NULL, 85.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(9, 'XYZ Electrical', 'Jane Doe', NULL, 'jane@xyzelectrical.com', '+1-555-987-6543', NULL, 'Electrical', NULL, NULL, NULL, NULL, 65.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(10, 'Best Plumbing Inc.', 'Robert Johnson', NULL, 'robert@bestplumbing.com', '+1-555-456-7890', NULL, 'Plumbing', NULL, NULL, NULL, NULL, 75.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(11, 'Quality Carpentry', 'Michael Brown', NULL, 'michael@qualitycarpentry.com', '+1-555-321-0987', NULL, 'Carpentry', NULL, NULL, NULL, NULL, 70.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(12, 'City Painting Services', 'Sarah Wilson', NULL, 'sarah@citypainting.com', '+1-555-789-0123', NULL, 'Painting', NULL, NULL, NULL, NULL, 55.00, NULL, 'Inactive', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:44:27');

-- --------------------------------------------------------

--
-- Table structure for table `subcontractor_work_orders`
--

CREATE TABLE `subcontractor_work_orders` (
  `id` int(11) NOT NULL,
  `subcontractor_id` int(11) NOT NULL,
  `work_order_number` varchar(50) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `work_description` text NOT NULL,
  `location` varchar(500) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `actual_cost` decimal(12,2) DEFAULT 0.00,
  `work_status` enum('Assigned','In Progress','On Hold','Completed','Cancelled','Pending Review','Draft') DEFAULT 'Assigned',
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Unpaid','Partially Paid','Fully Paid','Overpaid') DEFAULT 'Unpaid',
  `last_payment_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcontractor_work_orders`
--

INSERT INTO `subcontractor_work_orders` (`id`, `subcontractor_id`, `work_order_number`, `project_name`, `work_description`, `location`, `start_date`, `end_date`, `estimated_cost`, `actual_cost`, `work_status`, `priority`, `notes`, `created_by`, `created_at`, `updated_at`, `completed_at`, `total_paid`, `payment_status`, `last_payment_date`) VALUES
(2, 7, 'WO-20260122-581', 'igjdofgjdg', 'asfdafaf', 'sfsfsfsf', '2026-01-22', '2026-01-29', 500.00, 500.00, 'In Progress', 'Medium', '', NULL, '2026-01-22 05:14:54', '2026-01-22 11:39:53', NULL, 100.00, 'Partially Paid', '2026-01-22');

--
-- Triggers `subcontractor_work_orders`
--
DELIMITER $$
CREATE TRIGGER `log_work_order_status_change` AFTER UPDATE ON `subcontractor_work_orders` FOR EACH ROW BEGIN
    IF OLD.work_status != NEW.work_status THEN
        INSERT INTO work_order_updates (work_order_id, update_type, old_value, new_value, description)
        VALUES (NEW.id, 'Status Change', OLD.work_status, NEW.work_status, 
                CONCAT('Work order status changed from ', OLD.work_status, ' to ', NEW.work_status));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `setting_type` varchar(20) DEFAULT 'text',
  `label` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `options` text DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `setting_type`, `label`, `description`, `options`, `is_required`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'ali hair wigs', 'general', 'text', 'Company Name', 'Your company name displayed throughout the system', '', 1, 1, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(2, 'company_email', 'admin@nexusadmin.com', 'general', 'email', 'Company Email', 'Primary email address for system notifications', '', 1, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(3, 'company_phone', '+1 (555) 123-4567', 'general', 'text', 'Company Phone', 'Contact phone number', '', 0, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(4, 'company_address', '123 Business Street, Suite 100', 'general', 'textarea', 'Company Address', 'Physical business address', '', 0, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(5, 'company_website', 'https://nexusadmin.com', 'general', 'url', 'Company Website', 'Official website URL', '', 0, 5, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(6, 'timezone', 'America/New_York', 'system', 'select', 'Timezone', 'Default timezone for the system', '[\"America/New_York\",\"America/Chicago\",\"America/Denver\",\"America/Los_Angeles\",\"UTC\",\"Europe/London\",\"Europe/Paris\",\"Asia/Tokyo\",\"Asia/Singapore\"]', 1, 1, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(7, 'date_format', 'Y-m-d', 'system', 'select', 'Date Format', 'Default date display format', '[\"Y-m-d\",\"d/m/Y\",\"m/d/Y\",\"F j, Y\",\"d M, Y\"]', 1, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(8, 'time_format', 'H:i', 'system', 'select', 'Time Format', 'Default time display format', '[\"H:i\",\"h:i A\",\"h:i:s A\",\"H:i:s\"]', 1, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(9, 'currency', 'USD', 'system', 'select', 'Currency', 'Default currency for financial operations', '[\"USD\",\"EUR\",\"GBP\",\"INR\",\"CAD\",\"AUD\",\"JPY\",\"CNY\"]', 1, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(10, 'currency_symbol', '$', 'system', 'text', 'Currency Symbol', 'Currency symbol to display', '', 1, 5, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(11, 'items_per_page', '20', 'system', 'number', 'Items Per Page', 'Number of items to display per page in lists', '', 1, 6, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(12, 'session_timeout', '30', 'system', 'number', 'Session Timeout (minutes)', 'User session timeout in minutes', '', 1, 7, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(13, 'smtp_host', 'smtp.gmail.com', 'email', 'text', 'SMTP Host', 'Outgoing mail server hostname', '', 0, 1, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(14, 'smtp_port', '587', 'email', 'number', 'SMTP Port', 'Outgoing mail server port', '', 0, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(15, 'smtp_username', 'your-email@gmail.com', 'email', 'text', 'SMTP Username', 'Email address for SMTP authentication', '', 0, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(16, 'smtp_password', '', 'email', 'password', 'SMTP Password', 'Password for SMTP authentication', '', 0, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(17, 'smtp_encryption', 'tls', 'email', 'select', 'SMTP Encryption', 'Encryption method for email', '[\"none\",\"ssl\",\"tls\"]', 0, 5, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(18, 'email_from_name', 'NexusAdmin System', 'email', 'text', 'From Name', 'Name displayed as sender in emails', '', 0, 6, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(19, 'theme_mode', 'light', 'appearance', 'select', 'Theme Mode', 'Default theme mode for the system', '[\"light\",\"dark\",\"auto\"]', 1, 1, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(20, 'primary_color', '#4F46E5', 'appearance', 'color', 'Primary Color', 'Main brand color for the interface', '', 1, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(21, 'sidebar_color', '#111827', 'appearance', 'color', 'Sidebar Color', 'Background color for the sidebar', '', 1, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(22, 'enable_animations', '1', 'appearance', 'checkbox', 'Enable Animations', 'Enable interface animations and transitions', '', 0, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(23, 'logo_url', '', 'appearance', 'url', 'Logo URL', 'Custom logo image URL (leave empty for default)', '', 0, 5, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(24, 'password_min_length', '8', 'security', 'number', 'Minimum Password Length', 'Minimum characters required for passwords', '', 1, 1, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(25, 'password_require_uppercase', '1', 'security', 'checkbox', 'Require Uppercase', 'Passwords must contain at least one uppercase letter', '', 0, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(26, 'password_require_lowercase', '1', 'security', 'checkbox', 'Require Lowercase', 'Passwords must contain at least one lowercase letter', '', 0, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(27, 'password_require_numbers', '1', 'security', 'checkbox', 'Require Numbers', 'Passwords must contain at least one number', '', 0, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(28, 'password_require_symbols', '0', 'security', 'checkbox', 'Require Symbols', 'Passwords must contain at least one special character', '', 0, 5, '2026-01-22 13:27:40', '2026-01-22 13:27:40'),
(29, 'login_attempts', '5', 'security', 'number', 'Max Login Attempts', 'Maximum failed login attempts before lockout', '', 1, 6, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(30, 'lockout_duration', '15', 'security', 'number', 'Lockout Duration (minutes)', 'Account lockout duration after max attempts', '', 1, 7, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(31, 'enable_2fa', '0', 'security', 'checkbox', 'Enable Two-Factor Authentication', 'Require 2FA for admin users', '', 0, 8, '2026-01-22 13:27:40', '2026-01-22 13:27:40'),
(32, 'auto_backup', '0', 'backup', 'checkbox', 'Enable Auto Backup', 'Automatically backup database daily', '', 0, 1, '2026-01-22 13:27:40', '2026-01-22 13:27:40'),
(33, 'backup_time', '02:00', 'backup', 'time', 'Backup Time', 'Daily time to perform automatic backups', '', 0, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(34, 'backup_retention', '30', 'backup', 'number', 'Backup Retention (days)', 'Number of days to keep backup files', '', 0, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(35, 'backup_email_notify', '1', 'backup', 'checkbox', 'Email Notification', 'Send email notification after backup', '', 0, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(36, 'notify_new_user', '1', 'notifications', 'checkbox', 'New User Registration', 'Notify when new users register', '', 0, 1, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(37, 'notify_new_order', '1', 'notifications', 'checkbox', 'New Orders', 'Notify when new orders are placed', '', 0, 2, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(38, 'notify_low_stock', '1', 'notifications', 'checkbox', 'Low Stock Alerts', 'Notify when inventory is low', '', 0, 3, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(39, 'notify_attendance', '1', 'notifications', 'checkbox', 'Attendance Alerts', 'Notify of attendance anomalies', '', 0, 4, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(40, 'notify_email', '1', 'notifications', 'checkbox', 'Email Notifications', 'Send notifications via email', '', 0, 5, '2026-01-22 13:27:40', '2026-01-22 13:28:26'),
(41, 'notify_sms', '0', 'notifications', 'checkbox', 'SMS Notifications', 'Send notifications via SMS', '', 0, 6, '2026-01-22 13:27:40', '2026-01-22 13:27:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager') NOT NULL DEFAULT 'manager',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `last_login`, `avatar`, `name`, `email`, `phone`, `bio`, `notifications_enabled`, `two_factor_enabled`, `last_updated`, `reset_token`, `reset_expires`) VALUES
(1, 'jahid', 'Nw9QEoCW', 'admin', '2025-10-13 17:31:59', '2026-01-22 21:34:05', 'uploads/profile/profile_1_1766640193.jpg', '', 'mdjhk19@gmail.com', '', '', 1, 1, '2026-01-22 15:34:05', NULL, NULL),
(2, 'manager1', 'manager', 'manager', '2025-11-12 17:31:59', '2026-01-22 21:12:52', NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-01-22 15:12:52', NULL, NULL),
(3, 'manager2', 'ManagerPass123!', 'manager', '2025-12-02 17:31:59', '2025-12-09 17:31:59', NULL, NULL, NULL, NULL, NULL, 1, 0, '2025-12-25 08:51:31', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `supply_type` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `logo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `company_name`, `contact_person`, `email`, `phone`, `website`, `category`, `supply_type`, `country`, `city`, `address`, `status`, `logo_url`, `created_at`) VALUES
(1, 'AR TECH SOLUTION', 'Md. Jahidul Hakim', 'mdjhk19@gmail.com', '+8801837090666', 'https://alihairwigs.com/', 'Packaging & Branding', 'Wholesaler', 'Bangladesh', 'Gazipur', 'Dhaka,Gazipur,Boardbazar,National university,south khailkur,38no woard,sohid siddik road, holding no:446', 'Active', 'uploads/vendors/v_6940138d35a2a.png', '2025-12-13 17:16:20'),
(2, 'Luxe Hair Supply', 'Sarah Jenkins', 'sarah@luxehair.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', NULL, '2025-12-15 15:34:40'),
(3, 'Global Wigs Co', 'David Chen', 'sales@globalwigs.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', NULL, '2025-12-15 15:34:40'),
(4, 'Beauty Packaging Ltd', 'Mike Ross', 'mike@packaging.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', NULL, '2025-12-15 15:34:40');

-- --------------------------------------------------------

--
-- Table structure for table `work_order_attachments`
--

CREATE TABLE `work_order_attachments` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_attendance`
--

CREATE TABLE `work_order_attendance` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `subcontractor_id` int(11) NOT NULL,
  `worker_name` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `hours_worked` decimal(4,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_comments`
--

CREATE TABLE `work_order_comments` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment_text` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_cost_breakdown`
--

CREATE TABLE `work_order_cost_breakdown` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `cost_category` enum('Materials','Labor','Equipment','Transportation','Permits','Overhead','Profit','Other') DEFAULT 'Materials',
  `description` varchar(500) NOT NULL,
  `estimated_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actual_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(10,3) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_documents`
--

CREATE TABLE `work_order_documents` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `document_type` enum('Contract','Drawing','Specification','Photo','Invoice','Receipt','Certificate','Report','Other') DEFAULT 'Other',
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_inspections`
--

CREATE TABLE `work_order_inspections` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `inspected_by` varchar(255) DEFAULT NULL,
  `inspection_type` enum('Initial','Progress','Final','Safety','Quality') DEFAULT 'Progress',
  `status` enum('Passed','Failed','Pending') DEFAULT 'Pending',
  `score` decimal(5,2) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `corrective_actions` text DEFAULT NULL,
  `next_inspection_date` date DEFAULT NULL,
  `inspector_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_materials`
--

CREATE TABLE `work_order_materials` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_of_measure` varchar(50) DEFAULT 'Each',
  `specifications` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Allocated','Delivered','Used','Returned') DEFAULT 'Pending',
  `delivery_date` date DEFAULT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_order_materials`
--

INSERT INTO `work_order_materials` (`id`, `work_order_id`, `product_name`, `product_code`, `quantity`, `unit_price`, `total_price`, `unit_of_measure`, `specifications`, `notes`, `status`, `delivery_date`, `received_by`, `created_at`, `updated_at`) VALUES
(2, 2, 'sfsf', NULL, 10.000, 50.00, 500.00, 'Each', NULL, '', 'Pending', NULL, NULL, '2026-01-22 05:14:54', '2026-01-22 05:14:54');

--
-- Triggers `work_order_materials`
--
DELIMITER $$
CREATE TRIGGER `update_work_order_cost_after_material` AFTER INSERT ON `work_order_materials` FOR EACH ROW BEGIN
    UPDATE subcontractor_work_orders 
    SET actual_cost = actual_cost + NEW.total_price,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.work_order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_notifications`
--

CREATE TABLE `work_order_notifications` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `notification_type` enum('Status Change','Payment Due','Overdue','Completion','Inspection','Material Request','Document Upload') DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `recipient_type` enum('Client','Subcontractor','Admin','All') DEFAULT 'Admin',
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_payments`
--

CREATE TABLE `work_order_payments` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_type` enum('partial','final','advance','milestone') DEFAULT 'partial',
  `notes` text DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `refund_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_order_payments`
--

INSERT INTO `work_order_payments` (`id`, `work_order_id`, `amount`, `payment_date`, `payment_method`, `reference_number`, `payment_type`, `notes`, `status`, `created_by`, `created_at`, `refund_date`) VALUES
(4, 2, 50.00, '2026-01-22', 'Cash', '', 'partial', '', 'refunded', 1, '2026-01-22 05:21:59', '2026-01-22 11:22:08'),
(5, 2, 100.00, '2026-01-22', 'Cash', '', 'partial', '', 'completed', 1, '2026-01-22 11:39:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `work_order_ratings`
--

CREATE TABLE `work_order_ratings` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `subcontractor_id` int(11) NOT NULL,
  `rating_topic` varchar(255) NOT NULL,
  `rating_value` tinyint(4) NOT NULL,
  `comments` text DEFAULT NULL,
  `rated_by` int(11) DEFAULT NULL,
  `rated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_signatures`
--

CREATE TABLE `work_order_signatures` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `signature_type` enum('Client Approval','Contractor Approval','Supervisor Approval','Safety Officer') DEFAULT 'Client Approval',
  `signatory_name` varchar(255) NOT NULL,
  `signatory_title` varchar(255) DEFAULT NULL,
  `signature_data` longtext DEFAULT NULL,
  `signed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `verification_code` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_status_history`
--

CREATE TABLE `work_order_status_history` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `work_order_summary_view`
-- (See below for the actual view)
--
CREATE TABLE `work_order_summary_view` (
`id` int(11)
,`work_order_number` varchar(50)
,`project_name` varchar(255)
,`work_status` enum('Assigned','In Progress','On Hold','Completed','Cancelled','Pending Review','Draft')
,`priority` enum('Low','Medium','High','Urgent')
,`start_date` date
,`end_date` date
,`estimated_cost` decimal(12,2)
,`actual_cost` decimal(12,2)
,`created_at` timestamp
,`company_name` varchar(255)
,`contact_person` varchar(255)
,`subcontractor_email` varchar(255)
,`subcontractor_phone` varchar(50)
,`specialization` varchar(255)
,`material_count` bigint(21)
,`materials_cost` decimal(34,2)
,`payment_count` bigint(21)
,`total_payments` decimal(32,2)
,`duration_days` int(7)
,`cost_variance_percent` decimal(19,2)
,`project_status` varchar(9)
);

-- --------------------------------------------------------

--
-- Table structure for table `work_order_tasks`
--

CREATE TABLE `work_order_tasks` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `actual_hours` decimal(6,2) DEFAULT NULL,
  `task_status` enum('Not Started','In Progress','Completed','On Hold','Cancelled') DEFAULT 'Not Started',
  `assigned_to` varchar(255) DEFAULT NULL,
  `completion_percentage` tinyint(4) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_updates`
--

CREATE TABLE `work_order_updates` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `update_type` enum('Status Change','Cost Update','Date Change','Material Added','Payment Added','Document Added','Note Added','System') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_order_updates`
--

INSERT INTO `work_order_updates` (`id`, `work_order_id`, `user_id`, `update_type`, `old_value`, `new_value`, `description`, `ip_address`, `created_at`) VALUES
(3, 2, NULL, 'Status Change', 'Assigned', 'In Progress', 'Work order status changed from Assigned to In Progress', NULL, '2026-01-22 05:21:26');

-- --------------------------------------------------------

--
-- Structure for view `work_order_summary_view`
--
DROP TABLE IF EXISTS `work_order_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `work_order_summary_view`  AS SELECT `wo`.`id` AS `id`, `wo`.`work_order_number` AS `work_order_number`, `wo`.`project_name` AS `project_name`, `wo`.`work_status` AS `work_status`, `wo`.`priority` AS `priority`, `wo`.`start_date` AS `start_date`, `wo`.`end_date` AS `end_date`, `wo`.`estimated_cost` AS `estimated_cost`, `wo`.`actual_cost` AS `actual_cost`, `wo`.`created_at` AS `created_at`, `s`.`company_name` AS `company_name`, `s`.`contact_person` AS `contact_person`, `s`.`email` AS `subcontractor_email`, `s`.`phone` AS `subcontractor_phone`, `s`.`specialization` AS `specialization`, (select count(0) from `work_order_materials` where `work_order_materials`.`work_order_id` = `wo`.`id`) AS `material_count`, (select sum(`work_order_materials`.`total_price`) from `work_order_materials` where `work_order_materials`.`work_order_id` = `wo`.`id`) AS `materials_cost`, (select count(0) from `work_order_payments` where `work_order_payments`.`work_order_id` = `wo`.`id`) AS `payment_count`, (select sum(`work_order_payments`.`amount`) from `work_order_payments` where `work_order_payments`.`work_order_id` = `wo`.`id`) AS `total_payments`, to_days(`wo`.`end_date`) - to_days(`wo`.`start_date`) AS `duration_days`, CASE WHEN `wo`.`actual_cost` > 0 THEN round((`wo`.`estimated_cost` - `wo`.`actual_cost`) / `wo`.`estimated_cost` * 100,2) ELSE 0 END AS `cost_variance_percent`, CASE WHEN `wo`.`work_status` = 'Completed' THEN 'Completed' WHEN `wo`.`work_status` = 'Cancelled' THEN 'Cancelled' WHEN `wo`.`end_date` < curdate() AND `wo`.`work_status` not in ('Completed','Cancelled') THEN 'Overdue' WHEN `wo`.`start_date` > curdate() THEN 'Scheduled' ELSE 'Active' END AS `project_status` FROM (`subcontractor_work_orders` `wo` left join `subcontractors` `s` on(`wo`.`subcontractor_id` = `s`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`employee_id`,`date`);

--
-- Indexes for table `attendance_reports`
--
ALTER TABLE `attendance_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_monthly_report` (`employee_id`,`report_month`);

--
-- Indexes for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `backup_settings`
--
ALTER TABLE `backup_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_holiday` (`holiday_date`,`holiday_name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `login_events`
--
ALTER TABLE `login_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `logistics_expenses`
--
ALTER TABLE `logistics_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_activities`
--
ALTER TABLE `payment_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_paid_at` (`paid_at`);

--
-- Indexes for table `production_costs`
--
ALTER TABLE `production_costs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `production_items`
--
ALTER TABLE `production_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_id` (`production_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `subcontractors`
--
ALTER TABLE `subcontractors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subcontractor_work_orders`
--
ALTER TABLE `subcontractor_work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `work_order_number` (`work_order_number`),
  ADD KEY `idx_work_order_number` (`work_order_number`),
  ADD KEY `idx_subcontractor_id` (`subcontractor_id`),
  ADD KEY `idx_work_status` (`work_status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work_order_attachments`
--
ALTER TABLE `work_order_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`);

--
-- Indexes for table `work_order_attendance`
--
ALTER TABLE `work_order_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`work_order_id`,`subcontractor_id`,`date`),
  ADD KEY `subcontractor_id` (`subcontractor_id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `work_order_comments`
--
ALTER TABLE `work_order_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `work_order_cost_breakdown`
--
ALTER TABLE `work_order_cost_breakdown`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_cost_category` (`cost_category`);

--
-- Indexes for table `work_order_documents`
--
ALTER TABLE `work_order_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Indexes for table `work_order_inspections`
--
ALTER TABLE `work_order_inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_inspection_date` (`inspection_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `work_order_materials`
--
ALTER TABLE `work_order_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `work_order_notifications`
--
ALTER TABLE `work_order_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `work_order_payments`
--
ALTER TABLE `work_order_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `work_order_ratings`
--
ALTER TABLE `work_order_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_subcontractor_id` (`subcontractor_id`);

--
-- Indexes for table `work_order_signatures`
--
ALTER TABLE `work_order_signatures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_signature` (`work_order_id`,`signature_type`),
  ADD KEY `idx_work_order_id` (`work_order_id`);

--
-- Indexes for table `work_order_status_history`
--
ALTER TABLE `work_order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`);

--
-- Indexes for table `work_order_tasks`
--
ALTER TABLE `work_order_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_task_status` (`task_status`),
  ADD KEY `idx_assigned_to` (`assigned_to`);

--
-- Indexes for table `work_order_updates`
--
ALTER TABLE `work_order_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_order_id` (`work_order_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_reports`
--
ALTER TABLE `attendance_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `backup_settings`
--
ALTER TABLE `backup_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_events`
--
ALTER TABLE `login_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `logistics_expenses`
--
ALTER TABLE `logistics_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_activities`
--
ALTER TABLE `payment_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `production_costs`
--
ALTER TABLE `production_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `production_items`
--
ALTER TABLE `production_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcontractors`
--
ALTER TABLE `subcontractors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `subcontractor_work_orders`
--
ALTER TABLE `subcontractor_work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `work_order_attachments`
--
ALTER TABLE `work_order_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_attendance`
--
ALTER TABLE `work_order_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_comments`
--
ALTER TABLE `work_order_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_cost_breakdown`
--
ALTER TABLE `work_order_cost_breakdown`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_documents`
--
ALTER TABLE `work_order_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_inspections`
--
ALTER TABLE `work_order_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_materials`
--
ALTER TABLE `work_order_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `work_order_notifications`
--
ALTER TABLE `work_order_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_payments`
--
ALTER TABLE `work_order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `work_order_ratings`
--
ALTER TABLE `work_order_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_signatures`
--
ALTER TABLE `work_order_signatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_status_history`
--
ALTER TABLE `work_order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_tasks`
--
ALTER TABLE `work_order_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_updates`
--
ALTER TABLE `work_order_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_reports`
--
ALTER TABLE `attendance_reports`
  ADD CONSTRAINT `attendance_reports_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `login_events`
--
ALTER TABLE `login_events`
  ADD CONSTRAINT `login_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_activities`
--
ALTER TABLE `payment_activities`
  ADD CONSTRAINT `payment_activities_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `work_order_payments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payment_activities_ibfk_2` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`),
  ADD CONSTRAINT `payment_activities_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_items`
--
ALTER TABLE `production_items`
  ADD CONSTRAINT `production_items_ibfk_1` FOREIGN KEY (`production_id`) REFERENCES `production_costs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subcontractor_work_orders`
--
ALTER TABLE `subcontractor_work_orders`
  ADD CONSTRAINT `subcontractor_work_orders_ibfk_1` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_attachments`
--
ALTER TABLE `work_order_attachments`
  ADD CONSTRAINT `work_order_attachments_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_attendance`
--
ALTER TABLE `work_order_attendance`
  ADD CONSTRAINT `work_order_attendance_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_order_attendance_ibfk_2` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_comments`
--
ALTER TABLE `work_order_comments`
  ADD CONSTRAINT `work_order_comments_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_order_comments_ibfk_2` FOREIGN KEY (`parent_comment_id`) REFERENCES `work_order_comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_cost_breakdown`
--
ALTER TABLE `work_order_cost_breakdown`
  ADD CONSTRAINT `work_order_cost_breakdown_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_documents`
--
ALTER TABLE `work_order_documents`
  ADD CONSTRAINT `work_order_documents_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_inspections`
--
ALTER TABLE `work_order_inspections`
  ADD CONSTRAINT `work_order_inspections_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_materials`
--
ALTER TABLE `work_order_materials`
  ADD CONSTRAINT `work_order_materials_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_notifications`
--
ALTER TABLE `work_order_notifications`
  ADD CONSTRAINT `work_order_notifications_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_payments`
--
ALTER TABLE `work_order_payments`
  ADD CONSTRAINT `work_order_payments_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`),
  ADD CONSTRAINT `work_order_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `work_order_ratings`
--
ALTER TABLE `work_order_ratings`
  ADD CONSTRAINT `work_order_ratings_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_order_ratings_ibfk_2` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_signatures`
--
ALTER TABLE `work_order_signatures`
  ADD CONSTRAINT `work_order_signatures_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_status_history`
--
ALTER TABLE `work_order_status_history`
  ADD CONSTRAINT `work_order_status_history_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_tasks`
--
ALTER TABLE `work_order_tasks`
  ADD CONSTRAINT `work_order_tasks_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_updates`
--
ALTER TABLE `work_order_updates`
  ADD CONSTRAINT `work_order_updates_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `subcontractor_work_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
