-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 13, 2026 at 11:53 AM
-- Server version: 8.0.30
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

-- --------------------------------------------------------

--
-- Table structure for table `assets_monthly`
--

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

--
-- Dumping data for table `assets_monthly`
--

INSERT INTO `assets_monthly` (`id`, `kib_type`, `year`, `month`, `unit_id`, `total`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'A', 2025, 1, 1, 150, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(2, 'A', 2025, 2, 1, 155, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(3, 'A', 2025, 3, 1, 160, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(4, 'A', 2025, 4, 1, 165, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(5, 'A', 2025, 5, 1, 170, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(6, 'A', 2025, 6, 1, 175, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(7, 'B', 2025, 1, 1, 100, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(8, 'B', 2025, 2, 1, 105, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(9, 'B', 2025, 3, 1, 110, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(10, 'C', 2025, 1, 1, 200, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(11, 'C', 2025, 2, 1, 205, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(12, 'C', 2025, 3, 1, 210, 1, '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(13, 'A', 2026, 1, 2, 1500000, 1, '2026-01-13 10:03:05', '2026-01-13 10:03:05'),
(14, 'A', 2026, 1, 3, 4556889, 1, '2026-01-13 10:39:24', '2026-01-13 10:39:24'),
(15, 'B', 2026, 1, 1, 1200000, 2, '2026-01-13 10:50:09', '2026-01-13 10:50:09');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
(1, 'admin', '2026-01-13 10:02:09'),
(2, 'pegawai', '2026-01-13 10:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `code`, `name`, `created_at`, `updated_at`) VALUES
(1, 'SKL001', 'Sekolah Dasar Negeri 1', '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(2, 'SKL002', 'Sekolah Dasar Negeri 2', '2026-01-13 10:02:09', '2026-01-13 10:02:09'),
(3, 'SKL003', 'Sekolah Menengah Pertama 1', '2026-01-13 10:02:09', '2026-01-13 10:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

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

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@sekolah.com', '$2y$10$8xI/6T6bYZx5i1Z4zwSVhefy7qRgVQLcguXAwJUDuoXTyymJ7LNNu', 1, 1, '2026-01-13 10:02:09', '2026-01-13 10:51:45'),
(2, 'Pegawai', 'pegawai@sekolah.com', '$2y$10$gKor3vI2ASr1o6t5C4f0AeUiGz6wxrR03vjfv6Fpazbp5qbGTPkQq', 2, 1, '2026-01-13 10:02:09', '2026-01-13 10:50:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets_monthly`
--
ALTER TABLE `assets_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kib_month_unit` (`kib_type`,`year`,`month`,`unit_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `idx_kib_year` (`kib_type`,`year`),
  ADD KEY `idx_month` (`month`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets_monthly`
--
ALTER TABLE `assets_monthly`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets_monthly`
--
ALTER TABLE `assets_monthly`
  ADD CONSTRAINT `assets_monthly_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assets_monthly_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
