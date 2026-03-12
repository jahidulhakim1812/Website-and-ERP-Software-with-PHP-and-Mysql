-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 03, 2025 at 06:33 PM
-- Server version: 10.11.14-MariaDB-cll-lve
-- PHP Version: 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `alihairw_alihairwigs`
--

-- --------------------------------------------------------

--
-- Table structure for table `about`
--

CREATE TABLE `about` (
  `id` int(11) NOT NULL,
  `site_name` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `image_url`, `slug`) VALUES
(1, 'Men Wigs', 'images/men-category.jpg', 'men'),
(2, 'Women Wigs', 'images/women-category.jpg', 'women');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `visitor_name` varchar(150) DEFAULT NULL,
  `message` text NOT NULL,
  `direction` enum('user','agent') NOT NULL DEFAULT 'user',
  `status` enum('pending','delivered','read') NOT NULL DEFAULT 'pending',
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `session_id`, `visitor_name`, `message`, `direction`, `status`, `ip`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 'f6357e41b6b67c168f1447fc2e1a1e22', NULL, 'hi', 'user', 'pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 19:48:29', NULL),
(2, 'f6357e41b6b67c168f1447fc2e1a1e22', NULL, 'hello', 'user', 'pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 19:48:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_info`
--

CREATE TABLE `contact_info` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` char(32) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `business_name` varchar(190) DEFAULT NULL,
  `business_role` varchar(80) DEFAULT NULL,
  `subject` varchar(190) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `session_id`, `full_name`, `email`, `phone`, `country`, `business_name`, `business_role`, `subject`, `message`, `ip`, `user_agent`, `created_at`) VALUES
(5, '24846b29200c2fd81f4d0c43c65f2b09', 'Devon SEO Team', 'devon.walters12@gmail.com', '2102102101', 'USA', 'Devon SEO Team', 'Buyer', 'Website performance check', 'Hello,\r\nI spent some time checking your site and noticed a few points where speed, visibility, and performance can improve. Your mobile load time is over 7 seconds, and a few simple SEO fixes could help you reach more customers.\r\n\r\nI can send a free report showing what’s slowing the site down, missed keyword gaps, and clear steps to improve rankings. If you want, we can go over everything on a short Google Meet.\r\n\r\nWould you like the report, a proposal, or both?\r\n\r\nThanks & Regards,', 0x3130332e3136352e3130332e323030, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.4 Safari/605.1.15', '2025-11-25 09:25:19'),
(6, '54698cd6fd3fa1baa1d7ea02129d4160', 'Deepa Gupta', 'deepa.dgtlsolution@gmail.com', '7042514198', 'Netherlands', 'Deepa Gupta', 'Buyer', 'Get Your Website to Google 1st Page.', 'Hello, http://alihairwigs.com\r\n \r\nour Black Friday SEO Discount (20% OFF) is closing soon.\r\n \r\nIf you want more traffic, more leads, and higher Google rankings before 2025, this is the moment.\r\n \r\nIf you are interested, please share your target keywords, target locations  and I’ll share a audit + customize strategy.\r\n \r\nThank You,\r\nDeepa', 0x3132322e3136322e3134342e313232, 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', '2025-11-26 07:38:01');

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_name` varchar(191) NOT NULL,
  `customer_email` varchar(191) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_country` varchar(100) NOT NULL,
  `customer_address` text NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_usd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `display_currency` char(3) DEFAULT NULL,
  `display_subtotal` decimal(10,2) DEFAULT NULL,
  `display_shipping` decimal(10,2) DEFAULT NULL,
  `display_duty` decimal(10,2) DEFAULT NULL,
  `display_tax` decimal(10,2) DEFAULT NULL,
  `display_total` decimal(12,2) DEFAULT NULL,
  `order_status` enum('pending','complete') NOT NULL DEFAULT 'pending',
  `status_updated_at` datetime DEFAULT NULL,
  `created_at` datetime(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `customer_country`, `customer_address`, `currency`, `subtotal`, `shipping`, `duty`, `tax`, `total_usd`, `note`, `display_currency`, `display_subtotal`, `display_shipping`, `display_duty`, `display_tax`, `display_total`, `order_status`, `status_updated_at`, `created_at`) VALUES
(6, 'AHW-20251114-78347E', 'JAHID KHAN', 'mdjhk300@gmail.com', '01957288638', 'Bangladesh', 'Dhaka,Gazipur,Boardbazar,National university,\nsouth khailkur,38no woard,sohid siddik road, holding no:446', 'USD', 800.00, 3.00, 0.00, 0.00, 803.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'complete', '2025-11-18 19:47:32', '2025-11-14 17:50:01.000'),
(7, 'AHW-20251127-61949F', 'Anisur Rahman ziku', 'ahmeedrajon0@gmail.com', '01342866286', 'Bangladesh', 'Hazi barek vobon. Khailkur', 'USD', 6.00, 3.00, 0.00, 0.00, 9.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-27 19:00:10.000'),
(8, 'AHW-20251127-DD00F3', 'Anisur Rahman Ziko', 'ahmeedrajon0@gmail.com', '01342866286', 'Bangladesh', 'Uganda Bangladesh', 'USD', 82.00, 3.00, 0.00, 0.00, 85.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-27 19:01:01.000'),
(9, 'AHW-20251127-50469A', 'Faizul Chowdhury', 'ahmeedrajon0@gmail.com', '01342866286', 'Bangladesh', 'Mirpur10;diya bari uttara', 'USD', 82.00, 3.00, 0.00, 0.00, 85.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-27 19:05:32.000'),
(10, 'AHW-20251128-F905A3', 'JAHID KHAN', 'mdjhk300@gmail.com', '01957288638', 'Bangladesh', 'Dhaka,Gazipur,Boardbazar,National university,\nsouth khailkur,38no woard,sohid siddik road, holding no:446', 'USD', 276.00, 3.00, 0.00, 0.00, 279.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-28 18:41:41.000'),
(11, 'AHW-20251128-9E824E', 'JAHID KHAN', 'mdjhk300@gmail.com', '01957288638', 'Bangladesh', 'Dhaka,Gazipur,Boardbazar,National university,\nsouth khailkur,38no woard,sohid siddik road, holding no:446', 'USD', 826.00, 3.00, 0.00, 0.00, 829.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-28 18:42:26.000'),
(12, 'AHW-20251203-57DB37', 'Bulbul Ahmed', 'ahmmedroki8@gmail.com', '01342866286', 'Bangladesh', 'হাজি বারিক ভবন বোড বাজার, গাজিপুর', 'USD', 18.00, 3.00, 0.00, 0.00, 21.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-12-03 18:31:29.000');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) DEFAULT 0,
  `product_name` varchar(191) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty` int(11) NOT NULL DEFAULT 1,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_img` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `qty`, `line_total`, `product_img`) VALUES
(3, 6, 28, 'jahid khan sallu', 800.00, 1, 800.00, 'admin/admin/images/download_035eede66dc9.jpg'),
(4, 7, 18, 'kkkkkkkkkkk', 6.00, 1, 6.00, 'admin/admin/images/3.png'),
(5, 8, 32, 'Md Biplob Miah', 82.00, 1, 82.00, 'admin/admin/images/f584faa4-2f3b-4a47-9e14-983d3c76abf9_3e87fdc2891d.png'),
(6, 9, 32, 'Md Biplob Miah', 82.00, 1, 82.00, 'admin/admin/images/f584faa4-2f3b-4a47-9e14-983d3c76abf9_3e87fdc2891d.png'),
(7, 10, 23, 'jp0', 200.00, 1, 200.00, 'admin/admin/images/777.png'),
(8, 10, 30, 'JAHID KHAN', 66.00, 1, 66.00, 'admin/admin/images/222200_4993a3133ed5.jpg'),
(9, 10, 31, 'shakil', 10.00, 1, 10.00, 'admin/admin/images/222200_73d6eabf5a06.png'),
(10, 11, 18, 'kkkkkkkkkkk', 6.00, 1, 6.00, 'admin/admin/images/3.png'),
(11, 11, 28, 'jahid khan sallu', 800.00, 1, 800.00, 'admin/admin/images/download_035eede66dc9.jpg'),
(12, 11, 34, 'ddsssss', 20.00, 1, 20.00, 'admin/admin/images/Lace_70bfe5ab45bd.jpg'),
(13, 12, 18, 'kkkkkkkkkkk', 6.00, 3, 18.00, 'admin/admin/images/3.png');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_placeholder` longtext DEFAULT NULL,
  `product_category` varchar(255) DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image_placeholder`, `product_category`, `category_id`, `category`, `created_at`) VALUES
(18, 'kkkkkkkkkkk', 'ddd', 6.00, 'admin/admin/images/3.png', NULL, NULL, 'men', '2025-10-30 12:52:50'),
(20, 'htyyhtyfyjfjf', 'fgjjf', 100.00, 'admin/admin/images/Gemini_Generated_Image_3h59td3h59td3h59-removebg-preview__1_.png', NULL, NULL, 'women', '2025-10-31 17:29:09'),
(21, 'sakib', 'ghfrhfhfh', 200.00, 'admin/admin/images/pngwing.com.png', NULL, NULL, 'men', '2025-10-31 17:29:59'),
(22, 'OPI', '6tr uyyhftdgh', 10000.00, 'admin/admin/images/470659188_122130979754501073_34207520751072239_n.jpg', NULL, NULL, 'women', '2025-10-31 17:30:23'),
(23, 'jp0', 'cvcv', 200.00, 'admin/admin/images/777.png', NULL, NULL, 'men', '2025-10-31 17:34:53'),
(24, 'sakib', 'dfedfdfdf', 10.00, '[\"admin/admin/images/470659188_122130979754501073_34207520751072239_n_636198938311.jpg\",\"admin/admin/images/Gemini_Generated_Image_3h59td3h59td3h59_fd332e6d1d15.png\",\"admin/admin/images/pngwing_com_b3e5816e1883.png\"]', NULL, NULL, 'men', '2025-11-08 06:41:58'),
(25, 'OPI', 'sdsd', 50.00, 'admin/admin/images/pngwing.com.png', NULL, NULL, 'women', '2025-11-08 06:51:12'),
(26, 'OPI', 'sdsd', 50.00, '[\"admin/admin/images/470659188_122130979754501073_34207520751072239_n_68882050508d.jpg\",\"admin/admin/images/Gemini_Generated_Image_3h59td3h59td3h59_a16144e3346d.png\"]', NULL, NULL, 'women', '2025-11-08 06:51:53'),
(27, 'tgrtg', 'rtg', 20.00, '[\"admin/admin/images/download__1__f7e178b1f44d.jpg\",\"admin/admin/images/download_347af6a8823e.jpg\"]', NULL, NULL, 'women', '2025-11-08 06:55:00'),
(28, 'jahid khan sallu', 'sallu', 800.00, '[\"admin/admin/images/download_035eede66dc9.jpg\"]', NULL, NULL, 'men', '2025-11-08 07:01:31'),
(29, 'sakib', 'g', 55.00, '[\"admin/admin/images/download__1__97568311072e.jpg\"]', '33', NULL, 'women', '2025-11-08 08:07:55'),
(30, 'JAHID KHAN', 'dfdfddfdfd', 66.00, '[\"admin/admin/images/222200_4993a3133ed5.jpg\"]', NULL, NULL, 'men', '2025-11-14 13:26:08'),
(31, 'shakil', 'uii', 10.00, '[\"admin/admin/images/222200_73d6eabf5a06.png\",\"admin/admin/images/222200_79397c89a577.jpg\",\"admin/admin/images/IMG_20251109_083402-removebg-preview_176148518ac6.png\"]', '34', NULL, 'men', '2025-11-14 13:55:42'),
(32, 'Md Biplob Miah', 'dsfsd', 82.00, '[\"admin/admin/images/f584faa4-2f3b-4a47-9e14-983d3c76abf9_3e87fdc2891d.png\",\"admin/admin/images/548213871_728286826911820_1601145553244767146_n__1__848ca60ab85f.jpg\"]', '34', NULL, 'men', '2025-11-14 13:59:57'),
(33, 'sakib', 'gfg', 10.00, '[\"admin/admin/images/Poly-Fuse-1_cef42a718a3c.jpg\"]', '44', NULL, 'men', '2025-11-19 20:05:42'),
(34, 'ddsssss', 'sdd', 20.00, '[\"admin/admin/images/Lace_70bfe5ab45bd.jpg\"]', '43', NULL, 'men', '2025-11-25 07:56:51');

-- --------------------------------------------------------

--
-- Table structure for table `product_category`
--

CREATE TABLE `product_category` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_category`
--

INSERT INTO `product_category` (`category_id`, `name`, `description`, `image_url`, `sort_order`, `created_at`) VALUES
(43, 'mono', 'dd', 'uploads/cat_1763582272_531c256144a2.jpg', 3, '2025-11-19 19:57:52'),
(45, 'skin top', 'gng', 'uploads/cat_1763582320_202ca754e148.jpg', 2, '2025-11-19 19:58:40'),
(46, 'Lace', 'df', 'uploads/cat_1763582334_0ad4fbfc25c2.jpg', 4, '2025-11-19 19:58:54'),
(53, 'silk', 'dd', 'uploads/cat_1763583112_fce65d24a3df.jpg', 1, '2025-11-19 20:11:52'),
(54, 'mono top', 'gg', 'uploads/cat_1763996943_82556d6ede5b.png', 5, '2025-11-24 15:09:03');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL DEFAULT 'Anonymous',
  `email` varchar(191) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `name`, `email`, `rating`, `title`, `body`, `product_id`, `visible`, `created_at`) VALUES
(1, 'Fatima', 'fatima@example.com', 5, 'Beautiful hair', 'The lace is soft and the color matched my expectations. Great seller!', 12, 1, '2025-10-20 09:12:00'),
(2, 'Rita', 'rita@example.com', 4, 'Good quality', 'Nice texture and fast shipping. I will reorder.', 21, 1, '2025-10-18 13:04:00'),
(3, 'Sana', NULL, 5, 'Perfect fit', 'The wig fits well and looks natural. Very happy.', 33, 1, '2025-10-12 18:20:00'),
(4, 'Lina', 'lina@example.com', 3, 'OK, minor issues', 'Overall good but a slight smell initially; washed out quickly.', NULL, 1, '2025-09-30 11:00:00'),
(5, 'Arif', 'arif@example.com', 5, 'Excellent', 'Durable and soft. Customer support was helpful.', 12, 1, '2025-09-22 07:45:00'),
(6, 'MD.ASHIK BABU', 'mdjhk300@gmail.com', 5, 'hh', 'dhdfhdhdfhdfhdh', 28, 1, '2025-11-13 21:02:12'),
(7, 'জমজম প্রিমিয়াম', 'mdjhk19@gmail.com', 2, 'ghfghfhfh', 'fghfghfghfgh', 27, 1, '2025-11-13 21:03:33'),
(8, 'MD.ASHIK BABU', 'mdjhk19@gmail.com', 4, 'hdfhdfhdfhdf', 'fhdfhdhdh', 27, 1, '2025-11-14 17:53:56'),
(9, 'Jahid', '', 5, 'Nice wigs', 'Wonderful', 34, 1, '2025-11-30 20:03:40');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sliderimages`
--

CREATE TABLE `sliderimages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sliderimages`
--

INSERT INTO `sliderimages` (`id`, `title`, `subtitle`, `button_text`, `image_url`, `display_order`) VALUES
(12, 'Welcome To Ali Hair Wigs', 'Best quality wigs in affortable price.', 'Contact', 'uploads/slider_1763812493_f6e3c1428011.jpg', NULL),
(13, 'dfdf', 'dfdfd', 'dfdfd', 'uploads/slider_1764308498_d9e87d632cbc.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `slider_images`
--

CREATE TABLE `slider_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `slider_images`
--

INSERT INTO `slider_images` (`id`, `title`, `subtitle`, `button_text`, `image_url`, `display_order`, `created_at`) VALUES
(1, '8888888', 'wfsdfsf', '666', 'uploads/slider_1761829676_3784cf1108cb.png', 1, '2025-10-30 13:07:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `created_at`, `role`) VALUES
(2, 'admin@example.com', '$2y$10$7Fnrml0LxKhxeIPH3h48N.rJXeMXjPvWzPAe7P4TcnDal3sxcCjii', '2025-10-22 14:09:00', 'user'),
(3, 'alihairwig.bd@gmail.com', '$2y$10$iwagfLXGa06R4K/cvGcOMuV0mpsbfs0AmsZIhTULKVmwM34Xv/mve', '2025-11-27 12:53:51', 'user');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about`
--
ALTER TABLE `about`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `contact_info`
--
ALTER TABLE `contact_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `email` (`email`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_order_number` (`order_number`),
  ADD KEY `idx_customer_email` (`customer_email`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `category` (`category`);

--
-- Indexes for table `product_category`
--
ALTER TABLE `product_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_category_name` (`name`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `visible` (`visible`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sliderimages`
--
ALTER TABLE `sliderimages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `slider_images`
--
ALTER TABLE `slider_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about`
--
ALTER TABLE `about`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contact_info`
--
ALTER TABLE `contact_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `product_category`
--
ALTER TABLE `product_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sliderimages`
--
ALTER TABLE `sliderimages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `slider_images`
--
ALTER TABLE `slider_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD CONSTRAINT `verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
