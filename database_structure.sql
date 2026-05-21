-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2026 at 07:36 PM
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
-- Database: `jhyn`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `full_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$QiXlL2ZYuqdcy5vLTS/A5enOiRF12CxY68oRiF.ITJZ2FGgGbYUwW', 'CCS Administrator', '2026-05-13 16:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'C programming', 'hey wazzup', 'active', 'CCS Administrator', '2026-04-24 15:47:09', '2026-04-24 15:47:09'),
(2, 'JAVA PROGRAMMING', 'wazzupp', 'active', 'CCS Administrator', '2026-05-18 10:32:32', '2026-05-18 10:32:32');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `rating` int(1) NOT NULL,
  `message` text NOT NULL,
  `feedback_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_capacity`
--

CREATE TABLE `lab_capacity` (
  `id` int(11) NOT NULL,
  `laboratory` varchar(20) NOT NULL,
  `capacity` int(11) DEFAULT 30,
  `current_occupancy` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_capacity`
--

INSERT INTO `lab_capacity` (`id`, `laboratory`, `capacity`, `current_occupancy`, `last_updated`) VALUES
(1, 'Lab 1', 30, 1, '2026-05-21 15:29:05'),
(2, 'Lab 2', 30, 2, '2026-05-21 15:29:05'),
(3, 'Lab 3', 30, 0, '2026-05-21 15:29:05'),
(4, 'Lab 4', 30, 0, '2026-05-21 15:29:05'),
(5, 'Lab 530', 30, 0, '2026-05-21 15:29:05');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'announcement',
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Reservation Submitted', 'Your reservation for Lab 530 on May 18, 2026 at 18:09 has been submitted for approval.', 'reservation', 0, '2026-05-18 10:13:08');

-- --------------------------------------------------------

--
-- Table structure for table `points_history`
--

CREATE TABLE `points_history` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `points_change` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `admin_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `point_history`
--

CREATE TABLE `point_history` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `points_added` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `point_history`
--

INSERT INTO `point_history` (`id`, `student_id`, `points_added`, `reason`, `admin_name`, `created_at`) VALUES
(1, '21478755', 5, 'Sit-in Completion', 'CCS Administrator', '2026-05-21 17:28:24');

-- --------------------------------------------------------

--
-- Table structure for table `redemptions`
--

CREATE TABLE `redemptions` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `course` varchar(20) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `purpose` varchar(50) NOT NULL,
  `laboratory` varchar(20) NOT NULL,
  `reservation_date` date NOT NULL,
  `time_in` time NOT NULL,
  `sessions_used` int(11) DEFAULT 1,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `student_id`, `id_number`, `student_name`, `course`, `year_level`, `purpose`, `laboratory`, `reservation_date`, `time_in`, `sessions_used`, `status`, `created_at`) VALUES
(1, 1, '21478755', 'Jhyn Libaton Carampatana', 'BSIT', '3', 'C Programming', 'Lab 530', '2026-05-18', '18:09:00', 1, 'approved', '2026-05-18 10:13:08');

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `points_cost` int(11) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`id`, `name`, `points_cost`, `stock`, `created_at`) VALUES
(1, 'Coffee Voucher', 50, 10, '2026-05-20 14:33:53'),
(2, 'CCS T-Shirt', 100, 5, '2026-05-20 14:33:53'),
(3, 'USB Drive 16GB', 150, 3, '2026-05-20 14:33:53'),
(4, 'Power Bank', 300, 2, '2026-05-20 14:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_records`
--

CREATE TABLE `sit_in_records` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `purpose` varchar(100) NOT NULL,
  `lab` varchar(20) NOT NULL,
  `session_time` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `login_time` datetime DEFAULT NULL,
  `logout_time` datetime DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_sessions`
--

CREATE TABLE `sit_in_sessions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `purpose` varchar(50) NOT NULL,
  `laboratory` varchar(20) NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `ended_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_in_sessions`
--

INSERT INTO `sit_in_sessions` (`id`, `student_id`, `id_number`, `student_name`, `purpose`, `laboratory`, `time_in`, `time_out`, `duration_minutes`, `points_earned`, `status`, `ended_by`, `created_at`) VALUES
(1, 1, '21478755', 'Jhyn L Carampatana', 'Programming', 'Lab 544', '2026-04-24 08:58:37', NULL, 0, 0, 'active', NULL, '2026-04-24 06:58:37'),
(2, 2, '25116633', 'MARK CHESTER L VILLAMERO', 'Thesis', 'Lab 524', '2026-04-24 09:01:06', NULL, 0, 0, 'active', NULL, '2026-04-24 07:01:06'),
(3, 3, '21344758', 'Jayn R Carampatana', 'Programming', 'Lab 524', '2026-04-24 17:46:34', NULL, 0, 0, 'active', NULL, '2026-04-24 15:46:34');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT '',
  `middle_name` varchar(100) DEFAULT '',
  `year_level` varchar(20) DEFAULT 'Year 1',
  `course` varchar(20) DEFAULT 'BSIT',
  `email` varchar(100) DEFAULT '',
  `address` text DEFAULT '',
  `sessions` int(11) DEFAULT 30,
  `total_points` int(11) DEFAULT 0,
  `profile_pic` varchar(255) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_level` varchar(50) DEFAULT NULL,
  `sessions_used` int(11) DEFAULT 0,
  `sessions_remaining` int(11) DEFAULT 30,
  `total_hours` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `id_number`, `first_name`, `last_name`, `middle_name`, `year_level`, `course`, `email`, `address`, `sessions`, `total_points`, `profile_pic`, `password`, `created_at`, `course_level`, `sessions_used`, `sessions_remaining`, `total_hours`) VALUES
(1, '21478755', 'Jhyn', 'Carampatana', 'Libaton', 'Year 1', 'BSIS', 'carampatanajhyn491@gmail.com', 'San Roque Quiot Cebu City Quiot', 28, 5, '', '$2y$10$bWI5d5WVZ6/oRiMIn93Y5.3YjOHu2kfGcX06vtlrlTbQ7iSRde4Ey', '2026-04-23 17:29:42', '3', 1, 30, 0.00),
(2, '25116633', 'MARK CHESTER', 'VILLAMERO', 'L', 'Year 1', 'BSIT', 'markchestervillamero@gmail.com', 'San Roque Quiot Cebu City Quiot', 29, 0, '', '$2y$10$FbaGBhTGvHt0Yk6T/dc/TeCTd2SAmonQfLjMeMtuDtuSyCsApT/zu', '2026-04-24 05:31:02', NULL, 0, 30, 0.00),
(3, '21344758', 'Jayn', 'Carampatana', 'R', 'Year 2', 'BSIT', 'jhyncarampatana@gmail.com', 'Cebu City', 29, 0, '', '$2y$10$BeohiiKGdPLFrMxIUHvU0OOGPVVdn6qv14Z6tzPFoFBIOmE5hPFD2', '2026-04-24 15:44:41', NULL, 0, 30, 0.00),
(4, '21345678', 'jhyn', 'carampatana', 'libaton', 'Year 2', 'BSCS', 'carampatanajhyn123@gmail.com', 'San Roque Quiot Cebu City Quiot', 30, 0, '', '$2y$10$MAJQjL57A.7jfU/kYteSaOhWHZkBuzajSjZGI6UFwqsekKmOzFDNy', '2026-05-18 09:53:28', NULL, 0, 30, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('afternoon_points', '5', '2026-05-20 14:33:53'),
('bonus_hour', '2', '2026-05-20 14:33:53'),
('easy_points', '5', '2026-05-20 14:33:53'),
('evening_points', '8', '2026-05-20 14:33:53'),
('hard_multiplier', '2', '2026-05-20 14:33:53'),
('hard_points', '15', '2026-05-20 14:33:53'),
('medium_multiplier', '1.5', '2026-05-20 14:33:53'),
('medium_points', '10', '2026-05-20 14:33:53'),
('morning_points', '5', '2026-05-20 14:33:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `lab_capacity`
--
ALTER TABLE `lab_capacity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `laboratory` (`laboratory`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `points_history`
--
ALTER TABLE `points_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `point_history`
--
ALTER TABLE `point_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `redemptions`
--
ALTER TABLE `redemptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_reservation_date` (`reservation_date`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sit_in_sessions`
--
ALTER TABLE `sit_in_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_capacity`
--
ALTER TABLE `lab_capacity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `points_history`
--
ALTER TABLE `points_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `point_history`
--
ALTER TABLE `point_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `redemptions`
--
ALTER TABLE `redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sit_in_sessions`
--
ALTER TABLE `sit_in_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sit_in_sessions`
--
ALTER TABLE `sit_in_sessions`
  ADD CONSTRAINT `sit_in_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
