-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 07, 2026 at 02:48 AM
-- Server version: 10.11.15-MariaDB-cll-lve
-- PHP Version: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `alihairw_alisoft`
--

-- --------------------------------------------------------

--
-- Table structure for table `accessory_items`
--

CREATE TABLE `accessory_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `accessory_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(10) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accessory_purchases`
--

CREATE TABLE `accessory_purchases` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `accessory_purchases`
--

INSERT INTO `accessory_purchases` (`id`, `vendor_id`, `purchase_date`, `total_cost`, `notes`, `created_at`) VALUES
(1, 6, '2026-02-27', 500.00, '', '2026-02-27 07:10:12');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'demo_login', 'Demo user logged in', '103.106.243.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-24 11:15:34');

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
(1, 2, '2026-01-22', '10:00:41', '10:00:50', 'present', NULL, NULL, 1, '2026-01-22 09:00:41', '2026-01-22 09:00:50'),
(2, 2, '2026-01-24', '09:00:00', '17:00:00', 'present', 8.00, '', 1, '2026-01-24 14:30:24', '2026-01-24 14:30:24'),
(3, 1, '2026-01-24', '09:00:00', '17:00:00', 'present', 8.00, '', 1, '2026-01-24 14:30:24', '2026-01-24 14:30:24'),
(4, 3, '2026-01-24', '09:00:00', '17:00:00', 'present', 8.00, '', 1, '2026-01-24 14:30:24', '2026-01-24 14:30:24');

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
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 1, 'weekly', 'monday', '02:00:00', 30, 10, 1, 1, 'mdjhk19@gmail.com', 'php', '2026-01-25 14:15:54', 'backup_2026-01-25_08-15-54.sql.gz', 14806, 'C:xampphtdocsamsadmin/backups/', '2026-01-22 13:36:44', '2026-01-25 08:15:54');

-- --------------------------------------------------------

--
-- Table structure for table `batch_payments`
--

CREATE TABLE `batch_payments` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_payments`
--

INSERT INTO `batch_payments` (`id`, `batch_id`, `amount`, `payment_date`, `note`, `created_at`) VALUES
(1, 1, 1000.00, '2026-03-06', NULL, '2026-03-06 20:18:28'),
(2, 1, 1000.00, '2026-03-06', NULL, '2026-03-06 20:29:54'),
(3, 1, 1000.00, '2026-03-06', NULL, '2026-03-06 20:31:19'),
(4, 1, 1000.00, '2026-03-06', NULL, '2026-03-06 20:31:26'),
(5, 1, 50.00, '2026-03-06', NULL, '2026-03-06 20:35:32'),
(6, 1, 50.00, '2026-03-06', NULL, '2026-03-06 20:40:24'),
(7, 1, 50.00, '2026-03-06', NULL, '2026-03-06 20:46:10');

-- --------------------------------------------------------

--
-- Table structure for table `batch_returns`
--

CREATE TABLE `batch_returns` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `size` varchar(50) NOT NULL,
  `returned_qty` decimal(10,3) NOT NULL DEFAULT 0.000,
  `damaged_qty` decimal(10,3) NOT NULL DEFAULT 0.000,
  `return_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `batch_returns`
--

INSERT INTO `batch_returns` (`id`, `batch_id`, `type`, `size`, `returned_qty`, `damaged_qty`, `return_date`, `notes`, `created_at`) VALUES
(1, 1, 'top8', '7x5', 50.000, 50.000, '2026-03-06', '', '2026-03-06 19:20:52');

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
(6, 'Md. Jahidul Hakim', 'mdjhk19@gmail.com', '+8801837090666', 'AR TECH SOLUTION', 'Dhaka,Gazipur,Boardbazar,National university,\r\nsouth khailkur,38no woard,sohid siddik road, holding no:446', 'active', 'uploads/cust_69859db40e5bc7.21495882.jpg', '2026-02-06 07:52:20', '2026-02-06 07:52:20', 'Bangladesh', '', 'Gazipur', '1704'),
(7, 'Gulzar', 'artechsolution.online@gmail.com', '019208990311', 'Gulazar Hair', 'india kolkata', 'active', 'uploads/cust_699e84779b1011.00237355.jpg', '2026-02-25 05:11:19', '2026-03-06 09:08:09', 'India', 'kolkata', 'kolkata', '1700');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `email_type` varchar(50) DEFAULT NULL,
  `email_to` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
(2, 'JAHID KHAN', 'mdjhk300@gmail.com', '+8801957288638', 'wig operator', '', '2025-12-20', 30000.00, 'Active', '2025-12-20 11:01:23', '1766230722_2384.jpeg'),
(3, 'Rajon pathan', 'rajon420@gmail.com', '+880 1898-947989', 'Cleaner', '', '2026-01-24', 5000.00, 'Active', '2026-01-24 08:33:23', 'uploads/697483d34355d_1769243603.jpeg'),
(4, 'Romzan', 'no@gmail.com', '01920899031', 'Worker', 'Operations', '2026-02-25', 5000.00, 'Active', '2026-02-25 06:38:21', 'https://ui-avatars.com/api/?name=Romzan&background=random&color=fff');

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
(84, 1, 'jahid', 1, '::1', NULL, '2026-01-22 21:34:05'),
(85, 1, 'jahid', 1, '::1', NULL, '2026-01-23 12:07:03'),
(86, 1, 'jahid', 1, '::1', NULL, '2026-01-23 13:05:08'),
(87, 1, 'jahid', 1, '::1', NULL, '2026-01-23 13:09:20'),
(88, 1, 'jahid', 1, '::1', NULL, '2026-01-23 13:09:20'),
(89, 1, 'jahid', 1, '::1', NULL, '2026-01-23 13:49:18'),
(90, 1, 'jahid', 1, '::1', NULL, '2026-01-23 14:47:05'),
(91, 1, 'jahid', 1, '::1', NULL, '2026-01-23 15:01:30'),
(92, 1, 'jahid', 1, '::1', NULL, '2026-01-23 15:07:27'),
(93, 1, 'jahid', 1, '::1', NULL, '2026-01-23 16:55:35'),
(94, 1, 'jahid', 1, '::1', NULL, '2026-01-23 17:46:06'),
(95, 1, 'jahid', 1, '::1', NULL, '2026-01-23 18:10:59'),
(96, 1, 'jahid', 1, '::1', NULL, '2026-01-23 18:12:58'),
(97, 1, 'jahid', 0, '::1', NULL, '2026-01-23 23:08:07'),
(98, 1, 'jahid', 1, '::1', NULL, '2026-01-23 23:08:15'),
(99, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 00:34:49'),
(100, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 10:36:58'),
(101, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 10:38:58'),
(102, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 10:44:20'),
(103, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 10:51:03'),
(104, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-24 10:52:43'),
(105, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-24 10:52:54'),
(106, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-24 10:53:11'),
(107, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-24 10:53:34'),
(108, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 10:54:01'),
(109, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 10:54:03'),
(110, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 11:24:06'),
(111, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 11:37:35'),
(112, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 12:59:29'),
(113, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:27:54'),
(114, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:28:08'),
(115, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:30:44'),
(116, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:32:05'),
(117, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:34:22'),
(118, NULL, 'alihairwig.bd@gmail.com', 0, '103.106.243.219', NULL, '2026-01-24 13:36:12'),
(119, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:36:16'),
(120, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:41:33'),
(121, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:43:20'),
(122, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:43:28'),
(123, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:43:33'),
(124, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:46:29'),
(125, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:46:45'),
(126, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:47:06'),
(127, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:50:16'),
(128, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:50:49'),
(129, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:51:01'),
(130, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 13:54:03'),
(131, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 14:51:51'),
(132, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 16:04:08'),
(133, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-24 16:19:04'),
(134, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-24 16:44:12'),
(135, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-24 16:44:18'),
(136, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 17:51:44'),
(137, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 18:17:24'),
(138, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 19:52:43'),
(139, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 19:53:01'),
(140, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 19:53:13'),
(141, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 20:15:22'),
(142, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 20:24:22'),
(143, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 20:50:22'),
(144, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 20:51:19'),
(145, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 20:51:52'),
(146, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 22:24:29'),
(147, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:18:53'),
(148, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:31:22'),
(149, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:31:27'),
(150, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:31:33'),
(151, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:31:42'),
(152, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:31:49'),
(153, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:31:58'),
(154, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:32:05'),
(155, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:32:28'),
(156, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:34:25'),
(157, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:35:11'),
(158, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-24 23:35:50'),
(159, 1, 'jahid', 0, '103.184.24.122', NULL, '2026-01-25 00:51:10'),
(160, 1, 'jahid', 1, '103.184.24.122', 'Password reset requested', '2026-01-25 00:51:25'),
(161, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 00:51:39'),
(162, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 02:13:39'),
(163, 1, 'jahid', 0, '103.184.24.122', NULL, '2026-01-25 02:30:18'),
(164, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 02:37:20'),
(165, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 09:57:42'),
(166, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 11:05:35'),
(167, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 11:34:11'),
(168, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 11:34:19'),
(169, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 11:34:41'),
(170, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 11:58:31'),
(171, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-25 12:02:42'),
(172, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 12:32:11'),
(173, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 14:10:54'),
(174, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 14:20:23'),
(175, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 15:01:40'),
(176, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 15:09:06'),
(177, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 15:27:53'),
(178, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 15:28:35'),
(179, 1, 'jahid', 1, '103.184.24.122', 'Password reset requested', '2026-01-25 15:32:17'),
(180, 1, 'jahid', 1, '103.184.24.122', NULL, '2026-01-25 15:32:27'),
(181, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-25 16:04:11'),
(182, 1, 'jahid', 1, '103.106.243.219', 'Password reset requested', '2026-01-25 16:04:33'),
(183, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 16:04:44'),
(184, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 16:05:52'),
(185, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 16:06:02'),
(186, 1, 'jahid', 1, '103.106.243.219', 'Password reset requested', '2026-01-25 16:15:04'),
(187, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 16:15:28'),
(188, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 16:18:03'),
(189, 1, 'jahid', 0, '103.106.243.219', NULL, '2026-01-25 18:10:22'),
(190, 1, 'jahid', 1, '103.106.243.219', 'Password reset requested', '2026-01-25 18:11:31'),
(191, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 18:11:52'),
(192, 2, 'manager1', 1, '103.106.243.219', NULL, '2026-01-25 19:27:47'),
(193, 2, 'manager1', 1, '103.106.243.219', NULL, '2026-01-25 19:29:07'),
(194, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 20:06:38'),
(195, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 20:07:10'),
(196, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 20:10:27'),
(197, 1, 'jahid', 1, '103.106.243.219', NULL, '2026-01-25 21:02:07'),
(198, 1, 'jahid', 1, '103.184.24.126', NULL, '2026-01-25 22:26:09'),
(199, 1, 'jahid', 1, '103.184.24.126', NULL, '2026-01-25 22:27:54'),
(200, 1, 'jahid', 1, '45.117.62.212', NULL, '2026-01-26 13:15:14'),
(201, NULL, 'jahid', 0, '45.117.62.212', NULL, '2026-01-26 13:17:54'),
(202, NULL, 'jahid', 0, '45.117.62.212', NULL, '2026-01-26 13:17:59'),
(203, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-26 13:18:21'),
(204, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-26 13:19:13'),
(205, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 16:48:07'),
(206, NULL, 'jahid', 0, '103.146.57.186', NULL, '2026-01-26 19:04:31'),
(207, NULL, 'jahid', 0, '103.146.57.186', NULL, '2026-01-26 19:06:12'),
(208, 1, 'alihair26', 1, '103.146.57.186', 'Password reset requested', '2026-01-26 19:08:42'),
(209, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:09:02'),
(210, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:17:39'),
(211, 1, 'alihair26', 1, '103.146.57.186', 'Password reset requested', '2026-01-26 19:19:07'),
(212, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:19:36'),
(213, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:19:36'),
(214, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:20:46'),
(215, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:20:46'),
(216, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:20:54'),
(217, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:20:59'),
(218, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:21:01'),
(219, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:21:10'),
(220, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:21:21'),
(221, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:21:32'),
(222, 1, 'alihair26', 1, '103.146.57.186', 'Password reset requested', '2026-01-26 19:21:38'),
(223, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:22:01'),
(224, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:22:16'),
(225, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:23:33'),
(226, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:23:56'),
(227, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:23:59'),
(228, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:24:05'),
(229, 1, 'alihair26', 1, '103.146.57.186', 'Password reset requested', '2026-01-26 19:24:21'),
(230, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:24:54'),
(231, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:27:22'),
(232, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:27:41'),
(233, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:28:11'),
(234, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:29:23'),
(235, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:29:53'),
(236, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:29:54'),
(237, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:48:08'),
(238, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 19:48:17'),
(239, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 19:52:43'),
(240, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-26 20:10:06'),
(241, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 20:23:21'),
(242, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 20:24:20'),
(243, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 20:27:44'),
(244, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 21:45:00'),
(245, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-26 22:06:19'),
(246, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 11:11:02'),
(247, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-01-27 11:17:27'),
(248, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 11:18:44'),
(249, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 11:20:48'),
(250, 1, 'alihair26', 0, '103.108.63.30', NULL, '2026-01-27 11:24:53'),
(251, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 15:24:43'),
(252, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 15:58:31'),
(253, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 16:21:46'),
(254, 1, 'alihair26', 0, '103.146.57.186', NULL, '2026-01-27 16:32:24'),
(255, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 16:32:31'),
(256, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-27 17:00:33'),
(257, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 18:23:20'),
(258, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 19:13:20'),
(259, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 19:39:48'),
(260, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 19:39:59'),
(261, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 22:34:30'),
(262, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-27 22:35:59'),
(263, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-01-28 01:07:54'),
(264, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 11:31:10'),
(265, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 11:34:54'),
(266, NULL, 'alihari26', 0, '103.146.57.186', NULL, '2026-01-28 12:27:27'),
(267, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 12:27:41'),
(268, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 12:29:25'),
(269, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 12:29:44'),
(270, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 12:37:07'),
(271, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-28 13:49:16'),
(272, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 16:51:30'),
(273, 2, 'manager1', 0, '103.146.57.186', NULL, '2026-01-28 16:52:19'),
(274, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 16:52:25'),
(275, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-28 22:00:52'),
(276, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 01:50:09'),
(277, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 10:35:12'),
(278, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 10:35:22'),
(279, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 10:35:30'),
(280, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 10:36:07'),
(281, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 10:36:13'),
(282, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-29 10:40:13'),
(283, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-29 12:14:58'),
(284, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-29 12:21:48'),
(285, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-30 14:13:55'),
(286, 1, 'alihair26', 1, '59.152.5.222', NULL, '2026-01-30 18:45:20'),
(287, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-30 21:51:21'),
(288, 1, 'alihair26', 1, '45.117.62.212', NULL, '2026-01-31 02:08:37'),
(289, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-01-31 19:10:19'),
(290, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-01 10:37:04'),
(291, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-01 18:04:36'),
(292, 1, 'alihair26', 1, '119.148.46.238', NULL, '2026-02-02 10:34:44'),
(293, 1, 'alihair26', 1, '119.148.46.238', NULL, '2026-02-02 13:15:53'),
(294, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-02 17:40:17'),
(295, 1, 'alihair26', 1, '119.148.46.238', NULL, '2026-02-03 12:09:06'),
(296, 1, 'alihair26', 1, '119.148.46.238', NULL, '2026-02-03 12:41:44'),
(297, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-03 18:49:22'),
(298, NULL, 'alihairwig.bd@gmail.com', 0, '103.146.57.186', NULL, '2026-02-03 19:46:22'),
(299, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-03 19:46:26'),
(300, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-03 21:30:38'),
(301, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-04 11:14:30'),
(302, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-04 11:43:22'),
(303, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-04 15:51:04'),
(304, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-04 16:52:07'),
(305, NULL, 'alihairwig.bd@gmail.com', 0, '103.146.57.186', NULL, '2026-02-04 16:57:42'),
(306, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-04 16:57:46'),
(307, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-04 17:00:54'),
(308, NULL, 'alihairwig.bd@gmail.com', 0, '103.146.57.186', NULL, '2026-02-04 19:02:52'),
(309, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-04 19:02:59'),
(310, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-05 11:45:31'),
(311, NULL, 'admin@example.com', 0, '103.184.24.127', NULL, '2026-02-05 13:24:00'),
(312, NULL, 'alihairwigs26', 0, '103.184.24.127', NULL, '2026-02-05 13:24:22'),
(313, NULL, 'jahid', 0, '103.184.24.127', NULL, '2026-02-05 13:24:49'),
(314, 1, 'alihair26', 0, '103.184.24.127', NULL, '2026-02-05 13:27:03'),
(315, 1, 'alihair26', 0, '103.184.24.127', NULL, '2026-02-05 13:27:29'),
(316, 1, 'alihair26', 0, '103.184.24.127', NULL, '2026-02-05 13:28:10'),
(317, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-05 13:30:04'),
(318, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-05 13:36:53'),
(319, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-05 13:56:51'),
(320, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-05 14:02:06'),
(321, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 10:33:35'),
(322, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 12:05:55'),
(323, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 14:10:30'),
(324, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-06 16:10:00'),
(325, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 17:07:35'),
(326, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 20:36:19'),
(327, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 20:54:27'),
(328, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-06 22:39:33'),
(329, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-07 09:45:13'),
(330, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-08 10:53:54'),
(331, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-08 10:57:58'),
(332, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-08 11:02:12'),
(333, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-08 20:35:52'),
(334, NULL, 'alihairwig.bd@gmail.com', 0, '103.184.24.127', NULL, '2026-02-09 01:15:53'),
(335, NULL, 'alihairwig.bd@gmail.com', 0, '103.184.24.127', NULL, '2026-02-09 01:15:56'),
(336, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-09 01:15:59'),
(337, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-09 12:11:29'),
(338, 1, 'alihair26', 1, '103.146.57.185', NULL, '2026-02-09 13:03:27'),
(339, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-09 20:52:46'),
(340, 1, 'alihair26', 1, '103.184.24.127', NULL, '2026-02-10 11:00:24'),
(341, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-10 19:42:02'),
(342, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-11 19:46:04'),
(343, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-12 00:30:46'),
(344, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-12 15:55:49'),
(345, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-13 01:43:18'),
(346, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-13 14:12:48'),
(347, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-14 20:24:09'),
(348, 1, 'alihair26', 0, '45.117.62.215', NULL, '2026-02-15 09:52:30'),
(349, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-15 09:53:14'),
(350, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-15 12:34:15'),
(351, 1, 'alihair26', 0, '103.108.63.30', NULL, '2026-02-15 13:21:15'),
(352, NULL, 'aDMIN12345', 0, '103.108.63.30', NULL, '2026-02-15 13:22:16'),
(353, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-15 13:23:11'),
(354, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-15 13:24:42'),
(355, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-15 19:57:03'),
(356, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-16 08:37:05'),
(357, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-16 19:04:53'),
(358, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-16 19:18:56'),
(359, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-16 19:28:52'),
(360, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-17 20:23:27'),
(361, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 12:44:32'),
(362, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 13:50:26'),
(363, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 13:50:52'),
(364, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 15:18:27'),
(365, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 15:20:40'),
(366, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 15:21:23'),
(367, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 15:24:07'),
(368, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 15:25:34'),
(369, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 15:44:26'),
(370, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-18 17:04:56'),
(371, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-18 17:25:31'),
(372, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-18 22:54:39'),
(373, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-19 07:40:02'),
(374, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-19 09:26:34'),
(375, 1, 'alihair26', 1, '103.184.24.125', NULL, '2026-02-19 12:13:05'),
(376, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-21 01:14:41'),
(377, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-21 14:32:06'),
(378, 1, 'alihair26', 1, '45.117.62.215', NULL, '2026-02-23 13:21:54'),
(379, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-23 16:08:51'),
(380, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-23 17:01:41'),
(381, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-24 17:26:27'),
(382, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-24 19:07:51'),
(383, 1, 'alihair26', 0, '103.108.63.30', NULL, '2026-02-25 10:42:48'),
(384, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 10:43:26'),
(385, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 12:23:32'),
(386, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 12:25:42'),
(387, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 12:30:33'),
(388, 1, 'alihair26', 0, '103.108.63.30', NULL, '2026-02-25 12:32:13'),
(389, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 12:32:41'),
(390, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 12:37:58'),
(391, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 12:56:36'),
(392, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 13:44:31'),
(393, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 14:12:10'),
(394, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 14:39:47'),
(395, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 14:59:02'),
(396, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-25 15:35:20'),
(397, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-25 18:45:41'),
(398, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-26 07:41:42'),
(399, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-26 20:22:42'),
(400, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-27 11:02:06'),
(401, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-27 12:16:43'),
(402, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-27 13:02:51'),
(403, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-27 15:25:18'),
(404, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-27 15:41:50'),
(405, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-27 16:35:21'),
(406, 1, 'alihair26', 1, '103.252.127.1', NULL, '2026-02-27 17:23:39'),
(407, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-02-27 18:16:09'),
(408, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-02-28 08:57:11'),
(409, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-02-28 14:09:14'),
(410, 1, 'alihair26', 1, '119.148.46.238', NULL, '2026-03-01 10:15:19'),
(411, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-03-01 23:49:52'),
(412, 1, 'alihair26', 1, '103.136.212.124', NULL, '2026-03-02 12:08:10'),
(413, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-02 18:40:17'),
(414, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-02 18:58:51'),
(415, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-02 19:10:02'),
(416, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-02 19:27:42'),
(417, 1, 'alihair26', 1, '59.152.5.203', NULL, '2026-03-02 19:31:37'),
(418, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-02 20:08:45'),
(419, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-02 20:21:05'),
(420, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-02 20:22:40'),
(421, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-02 23:11:23'),
(422, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-02 23:12:00'),
(423, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-02 23:12:09'),
(424, 1, 'alihair26', 1, '119.148.46.238', NULL, '2026-03-03 10:15:55'),
(425, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-03-03 10:39:07'),
(426, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-03-03 14:38:22'),
(427, 1, 'alihair26', 1, '103.108.63.30', NULL, '2026-03-04 16:30:21'),
(428, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-04 20:19:32'),
(429, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-05 12:23:03'),
(430, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-05 22:01:53'),
(431, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 13:49:59'),
(432, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:41:33'),
(433, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:52:14'),
(434, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:52:59'),
(435, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:53:15'),
(436, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:53:23'),
(437, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:53:42'),
(438, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 14:56:17'),
(439, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 15:07:13'),
(440, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:11:01'),
(441, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:11:09'),
(442, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:11:14'),
(443, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:12:43'),
(444, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:13:59'),
(445, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:19:12'),
(446, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:24:56'),
(447, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 17:34:34'),
(448, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 18:59:59'),
(449, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-06 19:00:20'),
(450, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-06 19:50:27'),
(451, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-06 20:41:31'),
(452, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-06 20:43:59'),
(453, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-06 21:20:31'),
(454, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-06 22:58:46'),
(455, 1, 'alihair26', 1, '103.146.57.186', NULL, '2026-03-06 23:00:18'),
(456, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-07 01:05:41'),
(457, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-07 01:13:26'),
(458, 1, 'alihair26', 1, '103.136.212.125', NULL, '2026-03-07 02:29:25');

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
-- Table structure for table `password_reset_codes`
--

CREATE TABLE `password_reset_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(4) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `password_reset_codes`
--

INSERT INTO `password_reset_codes` (`id`, `user_id`, `code`, `expires_at`, `used`, `created_at`) VALUES
(1, 1, '885103', '2026-01-27 10:15:25', 0, '2026-01-27 10:00:25'),
(2, 1, '031972', '2026-01-27 10:15:35', 0, '2026-01-27 10:00:35'),
(3, 1, '159641', '2026-01-27 10:15:55', 0, '2026-01-27 10:00:55'),
(4, 1, '118484', '2026-01-27 10:15:59', 0, '2026-01-27 10:00:59'),
(5, 1, '690081', '2026-01-27 10:24:15', 0, '2026-01-27 10:09:15'),
(6, 1, '556635', '2026-01-27 10:34:04', 1, '2026-01-27 10:19:04'),
(7, 1, '805007', '2026-01-27 10:44:41', 0, '2026-01-27 10:29:41'),
(8, 1, '820304', '2026-01-27 10:46:25', 1, '2026-01-27 10:31:25'),
(9, 1, '696518', '2026-01-27 16:49:50', 1, '2026-01-27 16:34:50'),
(10, 1, '291260', '2026-01-28 06:43:13', 1, '2026-01-28 06:28:13'),
(11, 1, '365009', '2026-01-28 20:02:36', 1, '2026-01-28 19:47:36'),
(12, 1, '125671', '2026-01-29 06:35:53', 1, '2026-01-29 06:20:53'),
(13, 1, '821990', '2026-02-25 06:44:13', 1, '2026-02-25 06:29:13');

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
(17, 9, 4, 'payment_recorded', 'Payment of $115.00 recorded via Check', 1, '2026-02-18 08:53:00'),
(18, 10, 5, 'payment_recorded', 'Payment of $1,000.00 recorded via Check', 1, '2026-02-25 06:43:23');

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
(16, 41, 2000.00, 'cash', '2026-02-25 12:28:19'),
(17, 43, 30000.00, NULL, '2026-03-06 20:42:21');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_wig_pieces`
--

CREATE TABLE `payment_wig_pieces` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `value` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(5, 'T shirt', 'SKU-788470', 'Tools', 'uojhgujoh', 'uploads/products/prod_1766131801_694508597f361.jpg', 3, NULL, 1, 50, 5, 100, 'pcs', 180.00, 2000.00, 0.00, 2.00, 'Active', '2025-12-19 14:10:01', '2026-02-06 14:07:40'),
(7, 'jahid 66', 'SKU-890051', 'Bundles', '[\'[\'', 'uploads/products/prod_1766188310_6945e51611444.jpg', 1, 'ffdd, pant', 1, 5, 5, 100, 'pcs', 1800.00, 2000.00, 0.00, 1.00, 'Active', '2025-12-20 05:51:50', '2026-02-06 12:51:07'),
(10, 'miraz', 'PROD-17847', 'Wigs & Lace', ' nm nn nmnm n', 'uploads/products/prod_1771996890_699e86da8768b.jpg', 6, 'HAIR', 1, 40, 5, 1000, 'pcs', 406.80, 500.00, 10.00, 2.00, 'Active', '2026-02-25 11:21:30', '2026-02-25 12:16:10'),
(11, 'miraz', 'PROD-17388', 'Wigs & Lace', 'hh', 'uploads/products/prod_1771997826_699e8a82b3652.jpg', 6, 'new miraz cap production, HAIR', 1, 10, 5, 100, 'pcs', 4073.00, 4500.00, 0.00, 5.00, 'Active', '2026-02-25 11:37:06', '2026-03-06 18:34:23'),
(12, 'mono', 'PROD-64323', 'Wigs & Lace', 'fdfdff', 'uploads/products/prod_1772797745_69aabf31116d3.jpg', NULL, NULL, 1, 500, 5, 100, 'pcs', 0.00, 1000.00, 0.00, 0.00, 'Active', '2026-03-06 17:49:05', '2026-03-06 17:49:05');

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
(41, 7, 'INV-000001', '2026-02-25', 5000.00, 'miraz', 3000.00, 'Pending', 'Partial', '', '2026-02-25 06:16:10'),
(42, 7, 'INV-000042', '2026-03-06', 135000.00, 'miraz', 100.00, 'Pending', 'Partial', '', '2026-03-06 12:33:34'),
(43, 7, 'INV-000043', '2026-03-06', 45000.00, 'miraz', 34500.00, 'Pending', 'Partial', '', '2026-03-06 12:34:23');

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
(32, 37, 1, 15, 800.00, 12000.00),
(33, 38, 1, 1, 800.00, 800.00),
(34, 39, 7, 5, 2000.00, 10000.00),
(35, 40, 5, 50, 2000.00, 100000.00),
(36, 41, 10, 10, 500.00, 5000.00),
(37, 42, 11, 30, 4500.00, 135000.00),
(38, 43, 11, 10, 4500.00, 45000.00);

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
  `nid_number` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
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

INSERT INTO `subcontractors` (`id`, `company_name`, `contact_person`, `bank_account_info`, `email`, `phone`, `address`, `specialization`, `tax_id`, `nid_number`, `photo`, `registration_date`, `contract_start`, `contract_end`, `project_rate`, `payment_terms`, `status`, `rating`, `notes`, `created_at`, `updated_at`) VALUES
(7, 'AR TECH SOLUTION', 'Md. Jahidul Hakim', NULL, 'mdjhk19@gmail.com', '(880) 183-7090', 'hngdfh', 'Other', '', NULL, NULL, '2025-12-30', NULL, NULL, 0.00, NULL, 'Active', 0.00, 'ghdgh', '2025-12-30 10:48:48', '2026-01-24 16:23:35'),
(8, 'ABC Construction Co.', 'John Smith', NULL, 'john@abcconstruction.com', '+1-555-123-4567', NULL, 'General Contracting', NULL, NULL, NULL, NULL, NULL, NULL, 85.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-25 05:32:23'),
(9, 'XYZ Electrical', 'Jane Doe', NULL, 'jane@xyzelectrical.com', '+1-555-987-6543', NULL, 'Electrical', NULL, NULL, NULL, NULL, NULL, NULL, 65.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(10, 'Best Plumbing Inc.', 'Robert Johnson', NULL, 'robert@bestplumbing.com', '+1-555-456-7890', NULL, 'Plumbing', NULL, NULL, NULL, NULL, NULL, NULL, 75.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(11, 'Quality Carpentry', 'Michael Brown', NULL, 'michael@qualitycarpentry.com', '+1-555-321-0987', NULL, 'Carpentry', NULL, NULL, NULL, NULL, NULL, NULL, 70.00, NULL, 'Active', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:42:48'),
(12, 'City Painting Services', 'Sarah Wilson', NULL, 'sarah@citypainting.com', '+1-555-789-0123', NULL, 'Painting', NULL, NULL, NULL, NULL, NULL, NULL, 55.00, NULL, 'Inactive', 0.00, NULL, '2026-01-21 15:42:48', '2026-01-21 15:44:27'),
(14, 'Hannan Fashion ltd', 'Saddam vus', NULL, 'saddamvu420@gmail.com', '(026) 569-8965', 'kolkata,soudi arab,malaysia,dhaka,bangladesh', 'Electrical', '1546756883', '', 'uploads/subcontractors/sub_69a16e94a7fe37.45360356.png', '2026-01-26', NULL, NULL, 80000.00, NULL, 'Active', 0.00, '', '2026-01-26 13:50:33', '2026-02-27 10:14:44'),
(15, 'রতন', 'Md. Jahidul Hakim', NULL, 'mdjhk1@gmail.com', '(880) 183-7090', '', 'Wig Styling', '', '', 'uploads/subcontractors/sub_69a16ad48a5e28.12747047.jpg', '2026-02-27', NULL, NULL, 0.00, NULL, 'Inactive', 0.00, '', '2026-02-27 09:58:44', '2026-02-27 10:26:20');

-- --------------------------------------------------------

--
-- Table structure for table `subcontractor_contacts`
--

CREATE TABLE `subcontractor_contacts` (
  `id` int(11) NOT NULL,
  `subcontractor_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subcontractor_documents`
--

CREATE TABLE `subcontractor_documents` (
  `id` int(11) NOT NULL,
  `subcontractor_id` int(11) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
(4, 8, 'WO-20260218-880', 'miraz cap wigs', 'rrtgsfg', '', '2026-02-18', '2026-02-25', 115.00, 115.00, 'Completed', 'Medium', '', NULL, '2026-02-18 08:51:09', '2026-02-18 08:53:00', '2026-02-18 08:52:42', 115.00, 'Fully Paid', '2026-02-18'),
(5, 7, 'WO-20260225-194', 'miraz', 'hdhdhdhddhd', 'hdhdfhdh', '2026-02-25', '2026-03-04', 10020.00, 20040.00, 'In Progress', 'Medium', 'cbxbb', NULL, '2026-02-25 06:40:48', '2026-02-25 06:43:23', NULL, 1000.00, 'Partially Paid', '2026-02-25');

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
(1, 'alihair26', 'ADMIN12345', 'admin', '2025-10-13 17:31:59', '2026-03-07 02:29:25', 'uploads/profile/profile_1_1766640193.jpg', 'Ali Hair Wigs', 'mdjhk19@gmail.com', '', '', 1, 1, '2026-03-06 20:29:25', NULL, NULL),
(2, 'manager1', 'manager', 'manager', '2025-11-12 17:31:59', '2026-01-25 19:29:07', NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-01-25 13:29:07', NULL, NULL),
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
(6, 'Emon Hair processing', 'Habibur', 'buisness@gmail.com', '01740323748', '', 'Raw Virgin Hair', 'Wholesaler', 'Bangladesh', 'uttara', 'uttara faydabad,Dhaka', 'Active', 'uploads/vendors/v_699e80540b24f.jpg', '2026-02-25 04:53:40'),
(7, 'Luknath Enterprise', 'Ripon', 'riponsutradhar00@gmail.com', '01928207061', '', 'Wig Tools & Accessories', 'Wholesaler', 'Bangladesh', 'uttara', 'uttara faydabad', 'Active', 'uploads/vendors/v_699e832475604.jpg', '2026-02-25 05:05:40');

-- --------------------------------------------------------

--
-- Table structure for table `wigs_batches`
--

CREATE TABLE `wigs_batches` (
  `id` int(11) NOT NULL,
  `lod_name` varchar(255) NOT NULL,
  `subcontractor_id` int(11) DEFAULT NULL,
  `production_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wigs_batches`
--

INSERT INTO `wigs_batches` (`id`, `lod_name`, `subcontractor_id`, `production_date`, `created_at`) VALUES
(1, 'New', 8, '2026-03-06', '2026-03-06 19:06:57');

-- --------------------------------------------------------

--
-- Table structure for table `wigs_batch_costs`
--

CREATE TABLE `wigs_batch_costs` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `size` enum('8','10','both') NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wigs_batch_costs`
--

INSERT INTO `wigs_batch_costs` (`id`, `batch_id`, `size`, `description`, `quantity`, `unit_price`) VALUES
(1, 1, '8', 'Hair', 5.00, 1000.00),
(2, 1, '10', 'Hair', 0.00, 0.00),
(3, 1, 'both', 'Base', 0.00, 0.00),
(4, 1, 'both', 'Noting', 0.00, 0.00),
(5, 1, 'both', 'Needle', 0.00, 0.00),
(6, 1, 'both', 'Courier', 0.00, 0.00),
(7, 1, 'both', 'Processing', 0.00, 0.00),
(8, 1, 'both', 'Other', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `wigs_batch_items`
--

CREATE TABLE `wigs_batch_items` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `type` enum('top8','iskin8','top10','iskin10') NOT NULL,
  `size` varchar(10) NOT NULL,
  `per_piece` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` enum('g','kg') NOT NULL DEFAULT 'g',
  `quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wigs_batch_items`
--

INSERT INTO `wigs_batch_items` (`id`, `batch_id`, `type`, `size`, `per_piece`, `unit`, `quantity`) VALUES
(1, 1, 'top8', '7x5', 10.00, 'g', 0);

-- --------------------------------------------------------

--
-- Table structure for table `work_orders`
--

CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL,
  `subcontractor_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `actual_hours` decimal(6,2) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('Unpaid','Partially Paid','Paid') DEFAULT 'Unpaid',
  `created_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(13, 4, 'suta', NULL, 10.000, 10.00, 100.00, 'Each', NULL, '', 'Pending', NULL, NULL, '2026-02-18 08:51:09', '2026-02-18 08:51:09'),
(14, 4, 'gam', NULL, 1.000, 15.00, 15.00, 'Each', NULL, '', 'Pending', NULL, NULL, '2026-02-18 08:51:09', '2026-02-18 08:51:09'),
(17, 5, '10 in', NULL, 1.000, 10000.00, 10000.00, 'Each', NULL, '', 'Pending', NULL, NULL, '2026-02-25 06:43:04', '2026-02-25 06:43:04'),
(18, 5, 'net', NULL, 1.000, 20.00, 20.00, 'Each', NULL, '', 'Pending', NULL, NULL, '2026-02-25 06:43:04', '2026-02-25 06:43:04');

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
(9, 4, 115.00, '2026-02-18', 'Check', '', 'partial', '', 'completed', 1, '2026-02-18 08:53:00', NULL),
(10, 5, 1000.00, '2026-02-25', 'Check', '', 'partial', '', 'completed', 1, '2026-02-25 06:43:23', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
,`duration_days` int(8)
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
(6, 4, NULL, 'Status Change', 'Assigned', 'Completed', 'Work order status changed from Assigned to Completed', NULL, '2026-02-18 08:52:42'),
(7, 5, NULL, 'Status Change', 'Assigned', 'In Progress', 'Work order status changed from Assigned to In Progress', NULL, '2026-02-25 06:43:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accessory_items`
--
ALTER TABLE `accessory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`);

--
-- Indexes for table `accessory_purchases`
--
ALTER TABLE `accessory_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

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
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user_entity` (`user_id`,`entity_type`,`entity_id`);

--
-- Indexes for table `backup_settings`
--
ALTER TABLE `backup_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `batch_returns`
--
ALTER TABLE `batch_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `sent_by` (`sent_by`);

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
-- Indexes for table `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
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
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `recorded_by` (`recorded_by`);

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
-- Indexes for table `subcontractor_contacts`
--
ALTER TABLE `subcontractor_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subcontractor_id` (`subcontractor_id`);

--
-- Indexes for table `subcontractor_documents`
--
ALTER TABLE `subcontractor_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subcontractor_id` (`subcontractor_id`);

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
-- Indexes for table `wigs_batches`
--
ALTER TABLE `wigs_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subcontractor_id` (`subcontractor_id`);

--
-- Indexes for table `wigs_batch_costs`
--
ALTER TABLE `wigs_batch_costs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `wigs_batch_items`
--
ALTER TABLE `wigs_batch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `subcontractor_id` (`subcontractor_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `assigned_to` (`assigned_to`);

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
-- AUTO_INCREMENT for table `accessory_items`
--
ALTER TABLE `accessory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accessory_purchases`
--
ALTER TABLE `accessory_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_settings`
--
ALTER TABLE `backup_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `batch_returns`
--
ALTER TABLE `batch_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=459;

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
-- AUTO_INCREMENT for table `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payment_activities`
--
ALTER TABLE `payment_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `production_costs`
--
ALTER TABLE `production_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `production_items`
--
ALTER TABLE `production_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `subcontractor_contacts`
--
ALTER TABLE `subcontractor_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcontractor_documents`
--
ALTER TABLE `subcontractor_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcontractor_work_orders`
--
ALTER TABLE `subcontractor_work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `wigs_batches`
--
ALTER TABLE `wigs_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wigs_batch_costs`
--
ALTER TABLE `wigs_batch_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `wigs_batch_items`
--
ALTER TABLE `wigs_batch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `work_orders`
--
ALTER TABLE `work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `work_order_notifications`
--
ALTER TABLE `work_order_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_payments`
--
ALTER TABLE `work_order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

-- --------------------------------------------------------

--
-- Structure for view `work_order_summary_view`
--
DROP TABLE IF EXISTS `work_order_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`alihairw`@`localhost` SQL SECURITY DEFINER VIEW `work_order_summary_view`  AS SELECT `wo`.`id` AS `id`, `wo`.`work_order_number` AS `work_order_number`, `wo`.`project_name` AS `project_name`, `wo`.`work_status` AS `work_status`, `wo`.`priority` AS `priority`, `wo`.`start_date` AS `start_date`, `wo`.`end_date` AS `end_date`, `wo`.`estimated_cost` AS `estimated_cost`, `wo`.`actual_cost` AS `actual_cost`, `wo`.`created_at` AS `created_at`, `s`.`company_name` AS `company_name`, `s`.`contact_person` AS `contact_person`, `s`.`email` AS `subcontractor_email`, `s`.`phone` AS `subcontractor_phone`, `s`.`specialization` AS `specialization`, (select count(0) from `work_order_materials` where `work_order_materials`.`work_order_id` = `wo`.`id`) AS `material_count`, (select sum(`work_order_materials`.`total_price`) from `work_order_materials` where `work_order_materials`.`work_order_id` = `wo`.`id`) AS `materials_cost`, (select count(0) from `work_order_payments` where `work_order_payments`.`work_order_id` = `wo`.`id`) AS `payment_count`, (select sum(`work_order_payments`.`amount`) from `work_order_payments` where `work_order_payments`.`work_order_id` = `wo`.`id`) AS `total_payments`, to_days(`wo`.`end_date`) - to_days(`wo`.`start_date`) AS `duration_days`, CASE WHEN `wo`.`actual_cost` > 0 THEN round((`wo`.`estimated_cost` - `wo`.`actual_cost`) / `wo`.`estimated_cost` * 100,2) ELSE 0 END AS `cost_variance_percent`, CASE WHEN `wo`.`work_status` = 'Completed' THEN 'Completed' WHEN `wo`.`work_status` = 'Cancelled' THEN 'Cancelled' WHEN `wo`.`end_date` < curdate() AND `wo`.`work_status` not in ('Completed','Cancelled') THEN 'Overdue' WHEN `wo`.`start_date` > curdate() THEN 'Scheduled' ELSE 'Active' END AS `project_status` FROM (`subcontractor_work_orders` `wo` left join `subcontractors` `s` on(`wo`.`subcontractor_id` = `s`.`id`)) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accessory_items`
--
ALTER TABLE `accessory_items`
  ADD CONSTRAINT `accessory_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `accessory_purchases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accessory_purchases`
--
ALTER TABLE `accessory_purchases`
  ADD CONSTRAINT `accessory_purchases_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `batch_returns`
--
ALTER TABLE `batch_returns`
  ADD CONSTRAINT `batch_returns_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `wigs_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `email_logs_ibfk_2` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`);

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
-- Constraints for table `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  ADD CONSTRAINT `password_reset_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `payment_transactions_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

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
-- Constraints for table `subcontractor_contacts`
--
ALTER TABLE `subcontractor_contacts`
  ADD CONSTRAINT `subcontractor_contacts_ibfk_1` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subcontractor_documents`
--
ALTER TABLE `subcontractor_documents`
  ADD CONSTRAINT `subcontractor_documents_ibfk_1` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subcontractor_work_orders`
--
ALTER TABLE `subcontractor_work_orders`
  ADD CONSTRAINT `subcontractor_work_orders_ibfk_1` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wigs_batches`
--
ALTER TABLE `wigs_batches`
  ADD CONSTRAINT `wigs_batches_ibfk_1` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wigs_batch_costs`
--
ALTER TABLE `wigs_batch_costs`
  ADD CONSTRAINT `wigs_batch_costs_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `wigs_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wigs_batch_items`
--
ALTER TABLE `wigs_batch_items`
  ADD CONSTRAINT `wigs_batch_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `wigs_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD CONSTRAINT `work_orders_ibfk_1` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `work_orders_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
