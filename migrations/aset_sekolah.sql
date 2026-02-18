-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 15, 2026 at 01:55 AM
-- Server version: 8.0.32
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aset_sekolah`
--
DROP DATABASE IF EXISTS `aset_sekolah`;
CREATE DATABASE `aset_sekolah` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `aset_sekolah`;

-- --------------------------------------------------------

--
-- Table structure for table `assets_monthly`
--

CREATE TABLE `assets_monthly` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kib_type` enum('A','B','C','D','E','F') NOT NULL,
  `year` int NOT NULL,
  `month` int NOT NULL,
  `unit_id` int NOT NULL,
  `total` bigint NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_kib_month_unit` (`kib_type`,`year`,`month`,`unit_id`),
  KEY `created_by` (`created_by`),
  KEY `unit_id` (`unit_id`),
  KEY `idx_kib_year` (`kib_type`,`year`),
  KEY `idx_month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `kib_type` enum('A','B','C','D','E','F') NOT NULL,
  `year` int NOT NULL,
  `month` int NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `object_key` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_unit_id` (`unit_id`),
  KEY `idx_kib_year_month` (`kib_type`,`year`,`month`),
  KEY `idx_object_key` (`object_key`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `condition` enum('layak_pakai','tidak_layak_pakai') NOT NULL,
  `photo_key` varchar(255) DEFAULT NULL,
  `photo_mime` varchar(100) DEFAULT NULL,
  `photo_size` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_unit_id` (`unit_id`),
  KEY `idx_condition` (`condition`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_photo_key` (`photo_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
(1, 'admin', '2026-01-29 11:55:12'),
(2, 'pegawai', '2026-01-29 11:55:12'),
(3, 'supervisor', '2026-01-30 22:34:36'),
(4, 'pengembang', '2026-02-16 07:54:00');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users` (admin & pegawai only)
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@sekolah.com', '$2y$10$8xI/6T6bYZx5i1Z4zwSVhefy7qRgVQLcguXAwJUDuoXTyymJ7LNNu', 1, 1, '2026-01-29 11:55:12', '2026-01-29 11:55:12'),
(2, 'Pegawai', 'pegawai@sekolah.com', '$2y$10$gKor3vI2ASr1o6t5C4f0AeUiGz6wxrR03vjfv6Fpazbp5qbGTPkQq', 2, 1, '2026-01-29 11:55:12', '2026-01-31 12:16:15'),
(3, 'Pengembang', 'dev@sekolah.com', '$2y$10$zcZWndPFwbbZ38Gkwd9A0OPay60orkaWWAab1iGLUQuFknrByJGT.', 4, 1, '2026-02-16 07:54:00', '2026-02-16 07:54:00');

-- --------------------------------------------------------

--
-- Constraints for dumped tables
--

ALTER TABLE `assets_monthly`
  ADD CONSTRAINT `assets_monthly_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assets_monthly_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
