-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2026 at 08:23 PM
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
-- Database: `bhconnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `move_in` date NOT NULL,
  `move_out` date DEFAULT NULL,
  `rental_rate` decimal(10,2) DEFAULT NULL,
  `deposit` decimal(10,2) DEFAULT NULL,
  `billing_schedule` varchar(50) DEFAULT 'monthly',
  `status` enum('active','ended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `tenant_id`, `room_id`, `move_in`, `move_out`, `rental_rate`, `deposit`, `billing_schedule`, `status`) VALUES
(1, 1, 2, '2026-09-01', NULL, 3000.00, 0.00, 'monthly', 'active'),
(2, 2, 3, '2026-09-01', NULL, 3000.00, 0.00, 'monthly', 'active'),
(3, 3, 4, '2026-09-01', NULL, 3000.00, NULL, 'monthly', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `correction_requests`
--

CREATE TABLE `correction_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `log_id` int(11) DEFAULT NULL,
  `requested_event` enum('check_in','check_out') DEFAULT NULL,
  `requested_time` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `landlord_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `correction_requests`
--

INSERT INTO `correction_requests` (`id`, `tenant_id`, `log_id`, `requested_event`, `requested_time`, `reason`, `status`, `landlord_remarks`, `created_at`) VALUES
(1, 1, 5, 'check_out', '2026-09-30 23:46:00', 'i forgot to scan', 'approved', '', '2026-10-03 15:46:52');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `urgency` enum('low','medium','high') DEFAULT 'low',
  `status` enum('pending','in_progress','resolved','closed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`id`, `tenant_id`, `category`, `description`, `urgency`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'Door/Lock', 'nasira ang door knob ng 101', 'high', 'resolved', '2026-10-03 16:07:33', '2026-10-03 16:08:06');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `ref_key` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `type`, `ref_key`, `is_read`, `created_at`) VALUES
(1, 4, 'Overdue Payment: ₱3,000.00', 'Rent was due on 2026-10-01. Please settle your balance.', '/bhconnect/tenant/billing.php', 'danger', 'overdue_payment_2', 1, '2026-10-03 16:32:04');

-- --------------------------------------------------------

--
-- Table structure for table `occupancy_logs`
--

CREATE TABLE `occupancy_logs` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `event_type` enum('check_in','check_out') NOT NULL,
  `event_time` datetime NOT NULL,
  `qr_session_id` int(11) DEFAULT NULL,
  `status` enum('valid','flagged','approved','rejected') DEFAULT 'valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `occupancy_logs`
--

INSERT INTO `occupancy_logs` (`id`, `tenant_id`, `event_type`, `event_time`, `qr_session_id`, `status`) VALUES
(1, 1, 'check_in', '2026-10-01 19:26:25', 1, 'valid'),
(2, 1, 'check_out', '2026-10-01 19:27:07', 1, 'valid'),
(3, 1, 'check_in', '2026-10-01 19:38:51', 2, 'valid'),
(4, 1, 'check_out', '2026-10-03 17:12:12', 3, 'valid'),
(5, 1, 'check_out', '2026-09-30 23:46:00', NULL, 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `allocation_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('rent','utility') NOT NULL,
  `payment_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `period_label` varchar(50) DEFAULT NULL,
  `status` enum('paid','partial','unpaid') DEFAULT 'unpaid',
  `receipt_no` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `allocation_id`, `amount`, `payment_type`, `payment_date`, `due_date`, `period_label`, `status`, `receipt_no`, `notes`) VALUES
(1, 1, NULL, 3000.00, 'rent', '2026-09-01', '2026-10-01', '', 'paid', 'RCPT-20261003-5240', 'cash'),
(2, 3, NULL, 3000.00, 'rent', '2026-09-01', '2026-10-02', '', 'unpaid', 'RCPT-20261003-5918', '');

-- --------------------------------------------------------

--
-- Table structure for table `qr_sessions`
--

CREATE TABLE `qr_sessions` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_sessions`
--

INSERT INTO `qr_sessions` (`id`, `token`, `expires_at`, `created_at`) VALUES
(1, '78fe1ea9a79567e6', '2026-10-01 19:29:53', '2026-10-01 17:24:53'),
(2, '50c1be13f3fbd3ae', '2026-10-01 19:42:46', '2026-10-01 17:37:46'),
(3, 'f145fe7753ec0f21', '2026-10-03 17:16:39', '2026-10-03 15:11:39'),
(4, '1385a63736c40b6a', '2026-10-03 17:40:26', '2026-10-03 15:35:26'),
(5, 'e4341f67b7d935f6', '2026-10-03 19:37:17', '2026-10-03 17:32:17'),
(6, '63f80599baeb7637', '2026-10-03 19:45:02', '2026-10-03 17:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 1,
  `rental_rate` decimal(10,2) DEFAULT 0.00,
  `status` enum('vacant','occupied','reserved','maintenance') DEFAULT 'vacant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `capacity`, `rental_rate`, `status`) VALUES
(2, '101', 'single', 1, 3000.00, 'occupied'),
(3, '102', 'single', 1, 3000.00, 'occupied'),
(4, '103', 'single', 1, 3000.00, 'occupied'),
(5, '104', 'single', 1, 3000.00, 'occupied');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `full_name`, `contact`, `email`, `emergency_contact`, `room_id`, `status`, `created_at`) VALUES
(1, 'juan dela cruz', '', '', '', 2, 'active', '2026-09-30 19:05:39'),
(2, 'maria santos', '', '', '', 3, 'active', '2026-09-30 19:05:52'),
(3, 'jhay Montesir', '', 'smontesir12@gmail.com', '', 4, 'active', '2026-10-01 16:37:27'),
(6, 'cj', '', '', '', 5, 'active', '2026-10-03 15:57:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('landlord','tenant') NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `avatar`, `tenant_id`, `created_at`) VALUES
(1, 'landlord', '$2y$10$h8hd68vJ3ZfD/gfase8IUeOvs9.jdUosWWb/L7SqmBwEj/702RZQa', 'landlord', 'user_1_1791049921.jpg', NULL, '2026-09-30 18:00:11'),
(4, 'jhay', '$2y$10$jUlq1Pii8G/GTVTTvKG9uuumcErid1bo.BfoYPZRqMNm9vwPzT6/u', 'tenant', NULL, 3, '2026-10-01 17:10:13'),
(5, 'juan', '$2y$10$uE2M6O8eZXoy5xZOfIJvL.7naM1NVqrdjo/5WC8frBOiT/TKbJQQ6', 'tenant', NULL, 1, '2026-10-01 17:11:33'),
(6, 'maria', '$2y$10$Bp0D47jIpp4dwi0Pky2n1ePDqIvS4Nj1SE4/biYXNIg1A4t3GpncG', 'tenant', NULL, 2, '2026-10-01 17:11:48');

-- --------------------------------------------------------

--
-- Table structure for table `utility_allocations`
--

CREATE TABLE `utility_allocations` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `approved_days` int(11) NOT NULL,
  `total_days` int(11) NOT NULL,
  `share_amount` decimal(10,2) NOT NULL,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utility_bills`
--

CREATE TABLE `utility_bills` (
  `id` int(11) NOT NULL,
  `billing_start` date NOT NULL,
  `billing_end` date NOT NULL,
  `utility_type` enum('water','electricity') NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `correction_requests`
--
ALTER TABLE `correction_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ref` (`user_id`,`ref_key`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `occupancy_logs`
--
ALTER TABLE `occupancy_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `qr_sessions`
--
ALTER TABLE `qr_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `utility_allocations`
--
ALTER TABLE `utility_allocations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `utility_bills`
--
ALTER TABLE `utility_bills`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `correction_requests`
--
ALTER TABLE `correction_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `occupancy_logs`
--
ALTER TABLE `occupancy_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `qr_sessions`
--
ALTER TABLE `qr_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `utility_allocations`
--
ALTER TABLE `utility_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `utility_bills`
--
ALTER TABLE `utility_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
