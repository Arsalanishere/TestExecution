-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2025 at 08:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `my_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(2, 'om', 'om123'),
(3, 'ritik', 'ritik123'),
(4, 'mariam', 'mariam123');

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `alert_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `alert_message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `alert_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Seen','Resolved') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user` varchar(100) DEFAULT NULL,
  `pickup` varchar(255) DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `distance_km` float DEFAULT NULL,
  `total_price` float DEFAULT NULL,
  `num_labor` int(11) DEFAULT NULL,
  `vehicle` varchar(50) DEFAULT NULL,
  `labour_required` varchar(10) DEFAULT NULL,
  `labour_count` int(11) DEFAULT NULL,
  `tracking_id` varchar(50) DEFAULT NULL,
  `assigned_driver_id` int(11) DEFAULT NULL,
  `sender_name` varchar(100) DEFAULT NULL,
  `sender_phone` varchar(20) DEFAULT NULL,
  `receiver_name` varchar(100) DEFAULT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Delivered','Declined') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cargo_type` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user`, `pickup`, `destination`, `distance_km`, `total_price`, `num_labor`, `vehicle`, `labour_required`, `labour_count`, `tracking_id`, `assigned_driver_id`, `sender_name`, `sender_phone`, `receiver_name`, `receiver_phone`, `price`, `status`, `created_at`, `cargo_type`, `user_id`) VALUES
(125, NULL, 'Karachi Cantt Station, Karachi, Pakistan', 'Jinnah International Airport, Karachi, Pakistan', 16.4943, 3319.54, 4, 'Shehzor', 'Yes', 4, 'TRK71336', 21, 'kaviya', '0988886', 'akeel', '999888888', 1319.54, 'Delivered', '2025-10-13 18:38:08', 'Furniture', 20),
(126, NULL, 'Karachi Cantt Station, PK', 'Jinnah International Airport, PK', 16.4943, 2819.54, 3, 'Shehzor', 'Yes', 3, 'TRK71455', 23, 'name1', '098888', 'name2', '9998888885', 1319.54, 'Delivered', '2025-10-13 18:50:02', 'Furniture', 22);

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `vehicle_details` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `email`, `username`, `phone_number`, `password`, `vehicle_details`, `address`, `status`, `created_at`) VALUES
(21, 'driver khan', 'driver123@gmail.com', 'driver123', '03443594013', '', 'truck', 'flat: 15, floor no: 4, AR tower main road qasimabad hyderabad', 'active', '2025-10-13 16:45:51'),
(23, 'alikhan', 'alikhan123@gmail.com', 'alikhan123', '03443594013', '', 'suzuki', 'flat: 15, floor no: 4, AR tower main road qasimabad hyderabad', 'active', '2025-10-13 16:51:51');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `driver_id`, `message_text`, `is_read`, `created_at`, `user_id`) VALUES
(1, 21, '{\"driver_name\":\"driver khan\",\"driver_email\":\"driver123@gmail.com\",\"driver_contact\":\"03443594013\",\"driver_vehicle\":\"truck\",\"driver_address\":\"flat: 15, floor no: 4, AR tower main road qasimabad hyderabad\",\"booking_id\":\"125\",\"driver_id\":21}', 1, '2025-10-13 16:45:51', 20),
(2, 23, '{\"driver_name\":\"alikhan\",\"driver_email\":\"alikhan123@gmail.com\",\"driver_contact\":\"03443594013\",\"driver_vehicle\":\"suzuki\",\"driver_address\":\"flat: 15, floor no: 4, AR tower main road qasimabad hyderabad\",\"booking_id\":\"126\",\"driver_id\":23}', 1, '2025-10-13 16:51:51', 22);

-- --------------------------------------------------------

--
-- Table structure for table `proofs`
--

CREATE TABLE `proofs` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proofs`
--

INSERT INTO `proofs` (`id`, `booking_id`, `driver_id`, `photo_path`, `uploaded_at`) VALUES
(1, 125, 21, 'uploads/proofs/1760373973_c3d6b084e2.jpeg', '2025-10-13 16:46:13'),
(2, 126, 23, 'uploads/proofs/1760374331_daf2df94c2.jpeg', '2025-10-13 16:52:11');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `updated` date DEFAULT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `receiver_name` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `survey`
--

CREATE TABLE `survey` (
  `id` int(11) NOT NULL,
  `product_type` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','user','driver') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `role`) VALUES
(20, 'pooja123', 'pooja123@gmail.com', '$2y$10$7rDvoz6en5deFQM529NvQePPKhF/nMNbYXpHaB5Rr1Xy5vCrqwJVC', '2025-10-13 16:36:59', 'user'),
(21, 'driver123', 'driver123@gmail.com', '$2y$10$7VOusFBG0NNu7KRsgLlSTuE7uQlMCj7aRN7jyoSxlN6YN2Lu/UK7.', '2025-10-13 16:38:57', 'driver'),
(22, 'kaviya123', 'kaviya123@gmail.com', '$2y$10$Zod43cWUvVAfSCFIvS87QuyMDGI6PuPscjmV3yGvlZ.xH.u7yLbSu', '2025-10-13 16:49:00', 'user'),
(23, 'alikhan123', 'alikhan123@gmail.com', '$2y$10$5.SzdlN2rJj3yBn1rbSgrOcyTiLwP.dKA3XOgBUTdI5T3ZWERzK.m', '2025-10-13 16:51:06', 'driver');

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
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`alert_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_id` (`tracking_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proofs`
--
ALTER TABLE `proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `proofs_ibfk_1` (`booking_id`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `survey`
--
ALTER TABLE `survey`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `proofs`
--
ALTER TABLE `proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `survey`
--
ALTER TABLE `survey`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `proofs`
--
ALTER TABLE `proofs`
  ADD CONSTRAINT `proofs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proofs_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
