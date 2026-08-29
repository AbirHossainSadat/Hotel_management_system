-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Aug 28, 2026 at 05:36 PM
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
-- Database: `ahmani_hotel`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `average_income_per_service`
-- (See below for the actual view)
--
CREATE TABLE `average_income_per_service` (
`service_id` int(11)
,`service_name` varchar(100)
,`total_orders` bigint(21)
,`total_income` decimal(32,2)
,`avg_income_per_order` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `total_guests` int(11) DEFAULT 1,
  `booking_status` enum('Confirmed','CheckedIn','CheckedOut','Cancelled') DEFAULT 'Confirmed',
  `created_by_staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `guest_id`, `room_id`, `check_in_date`, `check_out_date`, `total_guests`, `booking_status`, `created_by_staff_id`, `created_at`) VALUES
(1, 2, 2, '2026-08-28', '2026-08-30', 2, 'Confirmed', 1, '2026-08-28 04:54:55'),
(2, 3, 3, '2026-08-28', '2026-08-31', 1, 'Confirmed', 1, '2026-08-28 05:07:58');

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `guest_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `id_card_type` enum('NID','Passport','Driving License') NOT NULL,
  `id_card_number` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`guest_id`, `first_name`, `last_name`, `email`, `phone`, `id_card_type`, `id_card_number`, `address`, `created_at`) VALUES
(1, 's', 'b', 'qw', '22', 'NID', '12', 'wef', '2026-08-27 16:39:10'),
(2, 'Nafis', 'Mihal', 'nf@gmail.com', '12345678911', 'NID', '12345', 'asdfg', '2026-08-28 04:54:55'),
(3, 'Abir', 'Hossain', 'qerw@gmail.com', '12345', 'NID', '123', '32324', '2026-08-28 05:07:58');

-- --------------------------------------------------------

--
-- Table structure for table `halls`
--

CREATE TABLE `halls` (
  `hall_id` int(11) NOT NULL,
  `hall_name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `halls`
--

INSERT INTO `halls` (`hall_id`, `hall_name`, `capacity`) VALUES
(1, 'Grand Ballroom', 500),
(2, 'Executive Boardroom', 50),
(3, 'Royal Celebration Lawn', 300);

-- --------------------------------------------------------

--
-- Table structure for table `hall_bookings`
--

CREATE TABLE `hall_bookings` (
  `event_id` int(11) NOT NULL,
  `hall_id` int(11) NOT NULL,
  `event_title` varchar(100) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `location_name`) VALUES
(1, 'Main Lobby'),
(2, 'Ocean Pool Deck'),
(3, 'Golden Palm Restaurant'),
(4, 'Grand Ballroom'),
(5, 'Executive Spa Center');

-- --------------------------------------------------------

--
-- Table structure for table `location_distances`
--

CREATE TABLE `location_distances` (
  `distance_id` int(11) NOT NULL,
  `from_location_id` int(11) NOT NULL,
  `to_location_id` int(11) NOT NULL,
  `distance_km` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `location_distances`
--

INSERT INTO `location_distances` (`distance_id`, `from_location_id`, `to_location_id`, `distance_km`) VALUES
(1, 1, 2, 0.15),
(2, 1, 3, 0.08),
(3, 2, 5, 0.20),
(4, 3, 4, 0.12),
(5, 4, 5, 0.25);

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_service_orders`
-- (See below for the actual view)
--
CREATE TABLE `pending_service_orders` (
`order_id` int(11)
,`guest_name` varchar(101)
,`guest_phone` varchar(20)
,`room_id` int(11)
,`room_number` varchar(10)
,`service_name` varchar(100)
,`quantity` int(11)
,`total_cost` decimal(10,2)
,`order_status` enum('Pending','In Progress','Completed','Cancelled')
,`order_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stars` tinyint(1) NOT NULL CHECK (`stars` >= 1 and `stars` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `user_id`, `stars`, `comment`, `created_at`) VALUES
(1, 1, 5, 'The ocean suite was absolutely breathtaking! Extremely clean, smooth room service, and top-tier hospitality.', '2026-08-27 19:54:46'),
(2, 2, 5, 'Exceptional experience from check-in to check-out. The banquet hall acoustics and buffet dinner were outstanding.', '2026-08-27 19:54:46'),
(3, 4, 4, 'Very comfortable stay with great high-speed internet and polite hotel staff. Will definitely visit again!', '2026-08-27 19:54:46'),
(4, 1, 5, 'The ocean suite was absolutely breathtaking! Extremely clean, smooth room service, and top-tier hospitality.', '2026-08-27 20:00:19'),
(5, 2, 5, 'Exceptional experience from check-in to check-out. The banquet hall acoustics and buffet dinner were outstanding.', '2026-08-27 20:00:19'),
(6, 4, 4, 'Very comfortable stay with great high-speed internet and polite hotel staff. Will definitely visit again!', '2026-08-27 20:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `room_type_id` int(11) NOT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `status` enum('Available','Occupied','Cleaning','Maintenance') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_number`, `room_type_id`, `floor_number`, `status`) VALUES
(1, '101', 1, 1, 'Available'),
(2, '102', 1, 1, 'Occupied'),
(3, '201', 2, 2, 'Occupied'),
(4, '202', 2, 2, 'Available'),
(5, '301', 3, 3, 'Available'),
(6, '404', 2, 4, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `room_type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `max_capacity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`room_type_id`, `type_name`, `description`, `base_price`, `max_capacity`, `created_at`) VALUES
(1, 'Deluxe Ocean Suite', 'Spacious king-bed suite with panoramic ocean views, private balcony, marble bath, and automated climate control.', 250.00, 2, '2026-08-27 20:00:19'),
(2, 'Executive Business Suite', 'Designed for modern travelers featuring an expansive work desk, high-speed fiber internet, and complimentary lounge access.', 350.00, 3, '2026-08-27 20:00:19'),
(3, 'Presidential Luxury Villa', 'Our flagship multi-room penthouse with a private infinity pool, dedicated 24/7 butler service, and VIP shuttle transfers.', 550.00, 4, '2026-08-27 20:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('Available','Unavailable') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `description`, `price`, `status`, `created_at`) VALUES
(1, '24/7 Gourmet Fine Dining', 'Enjoy world-class dishes prepared by certified master chefs delivered directly to your suite or served at the Golden Palm Restaurant.', 75.00, 'Available', '2026-08-27 20:00:19'),
(2, 'Luxury Spa & Wellness', 'Rejuvenate with organic aromatherapy, hydrotherapy baths, and specialized massage treatments designed for complete relaxation.', 120.00, 'Available', '2026-08-27 20:00:19'),
(3, 'Airport & Local Shuttles', 'Chauffeur-driven luxury sedans available for seamless airport pick-up, drop-off, and guided regional location tours.', 50.00, 'Available', '2026-08-27 20:00:19'),
(4, 'Express Laundry & Dry Cleaning', 'Fast, professional laundering, delicate garment press, and eco-friendly dry cleaning available at your convenience.', 30.00, 'Available', '2026-08-27 20:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `service_orders`
--

CREATE TABLE `service_orders` (
  `order_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `total_cost` decimal(10,2) NOT NULL,
  `order_status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('Manager','Receptionist','Housekeeping','Chef') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `first_name`, `last_name`, `role`, `phone`, `email`, `salary`, `hire_date`) VALUES
(1, 'a', 'b', 'Manager', '123', '2234', 234.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('Guest','Staff','Admin') NOT NULL DEFAULT 'Guest',
  `status` enum('Active','Inactive','Banned') DEFAULT 'Active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `guest_id`, `staff_id`, `username`, `email`, `password`, `user_type`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'abc', '123', '123', 'Guest', 'Active', '2026-08-28 15:10:35', '2026-08-27 16:40:18', '2026-08-28 15:10:35'),
(2, NULL, 1, 'ser', 'abc', '123', 'Guest', 'Active', NULL, '2026-08-27 16:42:04', '2026-08-27 16:42:04'),
(4, NULL, 1, 'abcd', 'qwe', '123', 'Staff', 'Active', '2026-08-28 05:06:24', '2026-08-27 16:44:09', '2026-08-28 05:06:24'),
(5, NULL, NULL, 'admin', 'admin@ahmani.com', 'admin', 'Admin', 'Active', '2026-08-28 05:10:22', '2026-08-27 20:38:16', '2026-08-28 05:10:22'),
(6, 2, NULL, 'nafis', 'nf@gmail.com', '12345', 'Guest', 'Active', NULL, '2026-08-28 04:54:55', '2026-08-28 04:54:55'),
(7, 3, NULL, 'abir', 'qerw@gmail.com', '123', 'Guest', 'Active', NULL, '2026-08-28 05:07:58', '2026-08-28 05:07:58');

-- --------------------------------------------------------

--
-- Structure for view `average_income_per_service`
--
DROP TABLE IF EXISTS `average_income_per_service`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `average_income_per_service`  AS SELECT `s`.`service_id` AS `service_id`, `s`.`service_name` AS `service_name`, count(`so`.`order_id`) AS `total_orders`, sum(`so`.`total_cost`) AS `total_income`, round(avg(`so`.`total_cost`),2) AS `avg_income_per_order` FROM (`services` `s` join `service_orders` `so` on(`s`.`service_id` = `so`.`service_id`)) WHERE `so`.`order_status` <> 'Cancelled' GROUP BY `s`.`service_id`, `s`.`service_name` ;

-- --------------------------------------------------------

--
-- Structure for view `pending_service_orders`
--
DROP TABLE IF EXISTS `pending_service_orders`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_service_orders`  AS SELECT `so`.`order_id` AS `order_id`, concat(`g`.`first_name`,' ',`g`.`last_name`) AS `guest_name`, `g`.`phone` AS `guest_phone`, `b`.`room_id` AS `room_id`, `r`.`room_number` AS `room_number`, `s`.`service_name` AS `service_name`, `so`.`quantity` AS `quantity`, `so`.`total_cost` AS `total_cost`, `so`.`order_status` AS `order_status`, `so`.`order_date` AS `order_date` FROM ((((`service_orders` `so` join `guests` `g` on(`so`.`guest_id` = `g`.`guest_id`)) join `services` `s` on(`so`.`service_id` = `s`.`service_id`)) left join `bookings` `b` on(`so`.`booking_id` = `b`.`booking_id`)) left join `rooms` `r` on(`b`.`room_id` = `r`.`room_id`)) WHERE `so`.`order_status` = 'Pending' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `created_by_staff_id` (`created_by_staff_id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`guest_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `halls`
--
ALTER TABLE `halls`
  ADD PRIMARY KEY (`hall_id`);

--
-- Indexes for table `hall_bookings`
--
ALTER TABLE `hall_bookings`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `hall_id` (`hall_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `location_distances`
--
ALTER TABLE `location_distances`
  ADD PRIMARY KEY (`distance_id`),
  ADD KEY `from_location_id` (`from_location_id`),
  ADD KEY `to_location_id` (`to_location_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `room_type_id` (`room_type_id`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`room_type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `service_orders`
--
ALTER TABLE `service_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `guest_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `halls`
--
ALTER TABLE `halls`
  MODIFY `hall_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hall_bookings`
--
ALTER TABLE `hall_bookings`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `location_distances`
--
ALTER TABLE `location_distances`
  MODIFY `distance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `room_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `service_orders`
--
ALTER TABLE `service_orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`created_by_staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL;

--
-- Constraints for table `hall_bookings`
--
ALTER TABLE `hall_bookings`
  ADD CONSTRAINT `hall_bookings_ibfk_1` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`hall_id`) ON DELETE CASCADE;

--
-- Constraints for table `location_distances`
--
ALTER TABLE `location_distances`
  ADD CONSTRAINT `location_distances_ibfk_1` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_distances_ibfk_2` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`room_type_id`);

--
-- Constraints for table `service_orders`
--
ALTER TABLE `service_orders`
  ADD CONSTRAINT `service_orders_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_orders_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `service_orders_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
