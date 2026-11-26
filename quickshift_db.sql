-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2026 at 09:43 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quickshift_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `apps`
--

CREATE TABLE `apps` (
  `id` varchar(32) NOT NULL,
  `job_id` varchar(32) NOT NULL,
  `title` varchar(100) NOT NULL,
  `applicant` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(25) NOT NULL DEFAULT '',
  `note` text DEFAULT NULL,
  `status` enum('Pending','Reviewed','Hired','Rejected') NOT NULL DEFAULT 'Pending',
  `ts` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `apps`
--

INSERT INTO `apps` (`id`, `job_id`, `title`, `applicant`, `name`, `phone`, `note`, `status`, `ts`) VALUES
('mwxb6po02ef486', 'mwxb6c582b79ef', 'rider', 'sabeth', 'sabeth coriba', '09855210979', '', 'Reviewed', 1795682817640);

-- --------------------------------------------------------

--
-- Table structure for table `ip_attempts`
--

CREATE TABLE `ip_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `ts` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ip_attempts`
--

INSERT INTO `ip_attempts` (`id`, `ip`, `ts`) VALUES
(1, '::1', 1795682461849);

-- --------------------------------------------------------

--
-- Table structure for table `ip_blocks`
--

CREATE TABLE `ip_blocks` (
  `ip` varchar(45) NOT NULL,
  `until_ts` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` varchar(32) NOT NULL,
  `title` varchar(100) NOT NULL,
  `cat` varchar(30) NOT NULL,
  `loc` varchar(60) NOT NULL,
  `pay` decimal(10,2) NOT NULL,
  `unit` enum('hour','day','week') NOT NULL DEFAULT 'day',
  `days_json` varchar(50) NOT NULL,
  `t1` varchar(10) NOT NULL,
  `t2` varchar(10) NOT NULL,
  `slots` int(11) NOT NULL DEFAULT 1,
  `descr` text DEFAULT NULL,
  `owner` varchar(50) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `ts` bigint(20) NOT NULL,
  `open` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `title`, `cat`, `loc`, `pay`, `unit`, `days_json`, `t1`, `t2`, `slots`, `descr`, `owner`, `owner_name`, `ts`, `open`) VALUES
('mwxb6c582b79ef', 'rider', 'Delivery', 'Carmen', 300.00, 'day', '[0,1,2,3,4]', '07:00', '19:00', 1, '', 'veronerz', 'veron erz', 1795682800118, 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_locks`
--

CREATE TABLE `login_locks` (
  `username` varchar(50) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  `s` int(11) NOT NULL DEFAULT 0,
  `t` int(11) NOT NULL DEFAULT 0,
  `until_ts` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `msgs`
--

CREATE TABLE `msgs` (
  `id` varchar(32) NOT NULL,
  `job_id` varchar(32) NOT NULL,
  `ap` varchar(50) NOT NULL,
  `sender` varchar(50) NOT NULL,
  `recipient` varchar(50) NOT NULL,
  `text` text NOT NULL,
  `ts` bigint(20) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_sys` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `msgs`
--

INSERT INTO `msgs` (`id`, `job_id`, `ap`, `sender`, `recipient`, `text`, `ts`, `is_read`, `is_sys`) VALUES
('mwxbd4wt8e6d9b', 'mwxb6c582b79ef', 'sabeth', 'veronerz', 'sabeth', 'Your application for rider is now Reviewed.', 1795683117329, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `otp_resets`
--

CREATE TABLE `otp_resets` (
  `username` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `exp` bigint(20) NOT NULL,
  `tries` int(11) NOT NULL DEFAULT 0,
  `rt` varchar(64) DEFAULT NULL,
  `rexp` bigint(20) NOT NULL DEFAULT 0,
  `rtries` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `token` varchar(64) NOT NULL,
  `email` varchar(50) NOT NULL,
  `last_active` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`token`, `email`, `last_active`) VALUES
('b47e731904798a197fbb4fa3a1cf7a18495f0bdc2e048b55', 'veronerz', 1795724299134);

-- --------------------------------------------------------

--
-- Table structure for table `signup_otps`
--

CREATE TABLE `signup_otps` (
  `username` varchar(30) NOT NULL,
  `gmail` varchar(120) NOT NULL,
  `code` varchar(6) NOT NULL,
  `exp` bigint(20) NOT NULL,
  `tries` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `gmail` varchar(120) DEFAULT NULL,
  `role` enum('owner','applicant') NOT NULL DEFAULT 'applicant',
  `is_verified` tinyint(1) NOT NULL DEFAULT 1,
  `phone` varchar(25) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `qa_json` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `gmail`, `role`, `is_verified`, `phone`, `password_hash`, `qa_json`, `created_at`) VALUES
(1, 'veron erz', 'veronerz', 'elizabeth.coriba@csucc.edu.ph', 'owner', 1, '09128503146', '$2y$12$ziMso/7p02Ej2N/0o8sjqOf1AE6jTSSJIwz5sgzwYeTAekr3Eqm.m', '[{\"i\":0,\"h\":\"$2y$12$EWCRnKx\\/aCfCcFHDE7Yxtuw8gjbSQLBQ9O0HE3EAK92f3iwQB7YuO\"},{\"i\":1,\"h\":\"$2y$12$.IuoxOrqIY7E7UNruiJi8.zQKAkE6mGfLHufHVO.pffu1wnrtctlO\"},{\"i\":2,\"h\":\"$2y$12$NaeZQoEWBj92gTDCQik9v.qnDI9GPvIGH7Ydj2CovCcuBujTjFNCO\"}]', '2026-11-26 08:42:35'),
(2, 'sabeth coriba', 'sabeth', 'dreyvelee@gmail.com', 'applicant', 1, '09855210979', '$2y$12$QNgyETZCeloZsQs18xMiYOdQHl9NT7ObV2vyF2dX1dYcFnLxmff8S', '[{\"i\":0,\"h\":\"$2y$12$Td5m3uB\\/z5TD2HozBZSGZeBrThu8oUvAp91DFsST3CitawP9JsqwK\"},{\"i\":1,\"h\":\"$2y$12$BPsT3TM\\/zA3sS8gt58f4aOLU3063\\/I297RZFCeUuozRMP9ov7ds9q\"},{\"i\":2,\"h\":\"$2y$12$UD5s7si4.5Jk3IxsTEjjT.gvbHoGib0DpknTyLGwC49RcHph\\/LdLa\"}]', '2026-11-26 08:43:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apps`
--
ALTER TABLE `apps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_applicant` (`applicant`);

--
-- Indexes for table `ip_attempts`
--
ALTER TABLE `ip_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_ts` (`ip`,`ts`);

--
-- Indexes for table `ip_blocks`
--
ALTER TABLE `ip_blocks`
  ADD PRIMARY KEY (`ip`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`owner`);

--
-- Indexes for table `login_locks`
--
ALTER TABLE `login_locks`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `msgs`
--
ALTER TABLE `msgs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread` (`job_id`,`ap`);

--
-- Indexes for table `otp_resets`
--
ALTER TABLE `otp_resets`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`token`),
  ADD KEY `idx_sess_email` (`email`);

--
-- Indexes for table `signup_otps`
--
ALTER TABLE `signup_otps`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ip_attempts`
--
ALTER TABLE `ip_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
