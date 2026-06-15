-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 15, 2026 at 02:09 AM
-- Server version: 10.11.16-MariaDB-cll-lve
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `talk_to_excel`
--

-- --------------------------------------------------------

--
-- Table structure for table `ip_usage`
--

CREATE TABLE `ip_usage` (
  `ip_hash` binary(32) NOT NULL,
  `upload_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `question_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `window_started_at` datetime DEFAULT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_upload_at` datetime DEFAULT NULL,
  `last_question_at` datetime DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `upload_id` bigint(20) UNSIGNED NOT NULL,
  `ip_hash` binary(32) NOT NULL,
  `question_hash` binary(32) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `input_tokens` int(10) UNSIGNED DEFAULT NULL,
  `output_tokens` int(10) UNSIGNED DEFAULT NULL,
  `error_code` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ip_hash` binary(32) NOT NULL,
  `session_token_hash` binary(32) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_extension` varchar(10) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `row_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sheet_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `context_bytes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_truncated` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('processing','ready','failed') NOT NULL DEFAULT 'processing',
  `error_code` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ip_usage`
--
ALTER TABLE `ip_usage`
  ADD PRIMARY KEY (`ip_hash`),
  ADD KEY `idx_ip_usage_window_started_at` (`window_started_at`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_ip_created` (`ip_hash`,`created_at`),
  ADD KEY `idx_question_upload` (`upload_id`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_upload_session_token` (`session_token_hash`),
  ADD KEY `idx_upload_ip_created` (`ip_hash`,`created_at`),
  ADD KEY `idx_upload_expiry` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_question_ip_usage` FOREIGN KEY (`ip_hash`) REFERENCES `ip_usage` (`ip_hash`),
  ADD CONSTRAINT `fk_question_upload` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `fk_upload_ip_usage` FOREIGN KEY (`ip_hash`) REFERENCES `ip_usage` (`ip_hash`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
