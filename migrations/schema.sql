-- phpMyAdmin SQL Dump
-- version 5.2.x
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 2026-01-29
-- Server version: 8.0.x
-- PHP Version: 8.1.x

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Database: `aset_sekolah`
-- (Pastikan Anda mengimpor ke database bernama aset_sekolah)
-- --------------------------------------------------------

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
(1, 'admin', CURRENT_TIMESTAMP),
(2, 'pegawai', CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `units`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` int NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `units` (`id`, `code`, `name`, `created_at`, `updated_at`) VALUES
(1, 'SKL001', 'Sekolah Dasar Negeri 1', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'SKL002', 'Sekolah Dasar Negeri 2', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, 'SKL003', 'Sekolah Menengah Pertama 1', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@sekolah.com', '$2y$10$8xI/6T6bYZx5i1Z4zwSVhefy7qRgVQLcguXAwJUDuoXTyymJ7LNNu', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'Pegawai', 'pegawai@sekolah.com', '$2y$10$gKor3vI2ASr1o6t5C4f0AeUiGz6wxrR03vjfv6Fpazbp5qbGTPkQq', 2, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `assets_monthly`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assets_monthly`;
CREATE TABLE `assets_monthly` (
  `id` int NOT NULL,
  `kib_type` enum('A','B','C','D','E','F') NOT NULL,
  `year` int NOT NULL,
  `month` int NOT NULL,
  `unit_id` int NOT NULL,
  `total` int NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `assets_monthly` (`id`, `kib_type`, `year`, `month`, `unit_id`, `total`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'A', YEAR(CURRENT_DATE), 1, 1, 150, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'A', YEAR(CURRENT_DATE), 2, 1, 155, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, 'B', YEAR(CURRENT_DATE), 1, 1, 100, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(4, 'C', YEAR(CURRENT_DATE), 1, 1, 200, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `condition` enum('layak_pakai','tidak_layak_pakai') NOT NULL,
  `photo_key` varchar(255) DEFAULT NULL,
  `photo_mime` varchar(100) DEFAULT NULL,
  `photo_size` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for table `documents` (opsional untuk unggahan dokumen)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `kib_type` enum('A','B','C','D','E','F') NOT NULL,
  `year` int NOT NULL,
  `month` int NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `object_key` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Indexes
-- --------------------------------------------------------
ALTER TABLE `assets_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kib_month_unit` (`kib_type`,`year`,`month`,`unit_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `idx_kib_year` (`kib_type`,`year`),
  ADD KEY `idx_month` (`month`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_condition` (`condition`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_photo_key` (`photo_key`);

ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_kib_year_month` (`kib_type`,`year`,`month`),
  ADD KEY `idx_object_key` (`object_key`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);

-- --------------------------------------------------------
-- AUTO_INCREMENT
-- --------------------------------------------------------
ALTER TABLE `assets_monthly`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1001;
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- --------------------------------------------------------
-- Constraints
-- --------------------------------------------------------
ALTER TABLE `assets_monthly`
  ADD CONSTRAINT `assets_monthly_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assets_monthly_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
