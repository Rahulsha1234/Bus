SET FOREIGN_KEY_CHECKS = 0;
-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql105.byetcluster.com
-- Generation Time: May 27, 2026 at 05:12 PM
-- Server version: 11.4.11-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `b10_40049115_bus`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `previous_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action_type`, `details`, `ip_address`, `created_at`, `previous_value`, `new_value`, `user_role`) VALUES
(1, 1, 'SYSTEM_INIT', 'System database initialized and seeded successfully.', '127.0.0.1', '2026-05-25 10:06:38', NULL, NULL, NULL),
(2, NULL, 'BOOKING_SUCCESS', 'Successful booking SB03C71455 for trip 2. Total: ₹2400. Seats: 1,2,5,6.', '::1', '2026-05-25 10:13:36', NULL, NULL, NULL),
(3, 5, 'AGENT_REGISTER', 'Agent signed up: aslitravels (8447695038)', '::1', '2026-05-25 10:20:33', NULL, NULL, NULL),
(4, 6, 'CUSTOMER_REGISTER', 'Customer signed up: jyoti', '::1', '2026-05-25 10:21:32', NULL, NULL, NULL),
(5, 6, 'LOGIN_SUCCESS', 'User logged in successfully.', '::1', '2026-05-25 10:26:41', NULL, NULL, NULL),
(6, 6, 'LOGOUT', 'User signed out manually.', '::1', '2026-05-25 10:26:44', NULL, NULL, NULL),
(7, 6, 'LOGIN_SUCCESS', 'User logged in successfully.', '::1', '2026-05-25 10:27:46', NULL, NULL, NULL),
(8, 6, 'LOGOUT', 'User signed out manually.', '::1', '2026-05-25 10:27:49', NULL, NULL, NULL),
(9, 1, 'LOGIN_SUCCESS', 'User logged in successfully.', '::1', '2026-05-25 10:32:53', NULL, NULL, NULL),
(10, 1, 'AGENT_STATUS_CHANGE', 'Updated status of agent user ID 5 to approved', '::1', '2026-05-25 10:33:20', NULL, NULL, NULL),
(11, 1, 'AGENT_STATUS_CHANGE', 'Updated status of agent user ID 3 to approved', '::1', '2026-05-25 10:33:25', NULL, NULL, NULL),
(12, 1, 'AGENT_STATUS_CHANGE', 'Updated status of agent user ID 3 to suspended', '::1', '2026-05-25 10:33:27', NULL, NULL, NULL),
(13, 5, 'LOGIN_SUCCESS', 'User logged in successfully.', '::1', '2026-05-25 10:34:23', NULL, NULL, NULL),
(14, 5, 'BUS_ADD', 'Added bus Manali Special (KA-01-F-0123)', '::1', '2026-05-25 10:35:12', NULL, NULL, NULL),
(15, 5, 'ROUTE_ADD', 'Added route Delhi to Manali (550 km)', '::1', '2026-05-25 10:37:07', NULL, NULL, NULL),
(16, 5, 'TRIP_ADD', 'Scheduled Trip ID: 4 (Bus ID 3, Route ID 3)', '::1', '2026-05-25 10:38:18', NULL, NULL, NULL),
(17, 5, 'SEAT_HOLD_MANUAL', 'Placed manual hold on Trip 4, Seat 6.', '::1', '2026-05-25 10:38:33', NULL, NULL, NULL),
(18, 5, 'SEAT_HOLD_MANUAL', 'Placed manual hold on Trip 4, Seat 10.', '::1', '2026-05-25 10:38:41', NULL, NULL, NULL),
(19, 1, 'LOGOUT', 'User signed out manually.', '::1', '2026-05-25 10:39:03', NULL, NULL, NULL),
(20, 6, 'LOGIN_SUCCESS', 'User logged in successfully.', '::1', '2026-05-25 10:39:15', NULL, NULL, NULL),
(21, 5, 'SEAT_RELEASE_MANUAL', 'Released manual hold on Trip 4, Seat 6.', '::1', '2026-05-25 10:39:37', NULL, NULL, NULL),
(22, 6, 'LOGOUT', 'User signed out manually.', '::1', '2026-05-25 10:40:06', NULL, NULL, NULL),
(23, NULL, 'BOOKING_SUCCESS', 'Successful booking SBE492D280 for trip 4. Total: ₹1000. Seats: 9,14.', '::1', '2026-05-25 10:41:50', NULL, NULL, NULL),
(24, 1, 'LOGIN_SUCCESS', 'User logged in successfully.', '::1', '2026-05-25 10:51:20', NULL, NULL, NULL),
(25, 1, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.87.57.77', '2026-05-27 15:43:33', NULL, NULL, NULL),
(26, 1, 'LOGOUT', 'User signed out manually.', '103.87.57.77', '2026-05-27 15:44:29', NULL, NULL, NULL),
(27, 6, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.87.57.77', '2026-05-27 15:44:37', NULL, NULL, NULL),
(28, 6, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.83.128.158', '2026-05-27 15:45:01', NULL, NULL, NULL),
(29, 6, 'LOGOUT', 'User signed out manually.', '103.87.57.77', '2026-05-27 15:45:32', NULL, NULL, NULL),
(30, 6, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.87.57.77', '2026-05-27 15:45:50', NULL, NULL, NULL),
(31, 5, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.87.57.77', '2026-05-27 15:47:14', NULL, NULL, NULL),
(32, NULL, 'LOGIN_FAILED', 'Failed Operator login attempt for: aslitravles', '103.87.57.77', '2026-05-27 15:48:11', NULL, NULL, NULL),
(33, 1, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.87.57.77', '2026-05-27 15:53:39', NULL, NULL, NULL),
(34, NULL, 'LOGIN_FAILED', 'Failed Agent login attempt for: asliagent', '103.83.128.158', '2026-05-27 15:55:47', NULL, NULL, NULL),
(35, NULL, 'LOGIN_FAILED', 'Failed Agent login attempt for: asliagent', '103.83.128.158', '2026-05-27 15:56:03', NULL, NULL, NULL),
(36, NULL, 'LOGIN_FAILED', 'Failed Agent login attempt for: asliagent', '103.83.128.158', '2026-05-27 15:56:52', NULL, NULL, NULL),
(37, 1, 'LOGOUT', 'User signed out manually.', '103.87.57.77', '2026-05-27 15:56:57', NULL, NULL, NULL),
(38, 5, 'LOGIN_SUCCESS', 'User logged in successfully as agent.', '103.87.57.77', '2026-05-27 15:57:06', NULL, NULL, NULL),
(39, NULL, 'LOGIN_FAILED', 'Failed Agent login attempt for: asliagent', '103.83.128.158', '2026-05-27 15:57:22', NULL, NULL, NULL),
(40, NULL, 'LOGIN_FAILED', 'Failed Agent login attempt for: asliagent', '103.83.128.158', '2026-05-27 15:57:35', NULL, NULL, NULL),
(41, 5, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.83.128.158', '2026-05-27 15:59:53', NULL, NULL, NULL),
(42, 6, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 16:00:26', NULL, NULL, NULL),
(43, 3, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.87.57.77', '2026-05-27 16:02:16', NULL, NULL, NULL),
(44, 3, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.83.128.158', '2026-05-27 16:02:20', NULL, NULL, NULL),
(45, 4, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.87.57.77', '2026-05-27 16:03:05', NULL, NULL, NULL),
(46, 4, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.83.128.158', '2026-05-27 16:03:30', NULL, NULL, NULL),
(47, 2, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.87.57.77', '2026-05-27 16:03:35', NULL, NULL, NULL),
(48, 1, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.87.57.77', '2026-05-27 16:08:41', NULL, NULL, NULL),
(49, 1, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.83.128.158', '2026-05-27 16:13:24', NULL, NULL, NULL),
(50, 2, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.83.128.158', '2026-05-27 16:14:29', NULL, NULL, NULL),
(51, 2, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '106.219.123.8', '2026-05-27 17:00:13', NULL, NULL, NULL),
(52, 2, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.87.57.77', '2026-05-27 17:15:04', NULL, NULL, NULL),
(53, 2, 'LOGOUT', 'User signed out manually.', '103.87.57.77', '2026-05-27 17:15:45', NULL, NULL, NULL),
(54, 1, 'LOGIN_SUCCESS', 'User logged in successfully as super_admin.', '103.87.57.77', '2026-05-27 17:15:51', NULL, NULL, NULL),
(55, 2, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.87.57.77', '2026-05-27 17:16:13', NULL, NULL, NULL),
(56, 2, 'BUS_ADD', 'Added bus BENGAL SPECAIL (WB-10-X-0932)', '103.87.57.77', '2026-05-27 17:46:09', NULL, NULL, NULL),
(57, 2, 'BUS_ADD', 'Added bus GOA SPEACIAL (GA 01 AB 1234)', '103.87.57.77', '2026-05-27 17:54:26', NULL, NULL, NULL),
(58, 2, 'BUS_OPERATOR_UPDATE', 'Updated operator info for bus ID: 7', '103.87.57.77', '2026-05-27 17:55:36', NULL, NULL, NULL),
(59, 2, 'BUS_OPERATOR_UPDATE', 'Updated operator info for bus ID: 6', '103.87.57.77', '2026-05-27 17:56:20', NULL, NULL, NULL),
(60, 4, 'LOGIN_SUCCESS', 'User logged in successfully as agent.', '103.83.128.158', '2026-05-27 18:03:21', NULL, NULL, NULL),
(61, 4, 'LOGIN_SUCCESS', 'User logged in successfully as agent.', '103.87.57.77', '2026-05-27 18:04:29', NULL, NULL, NULL),
(62, 5, 'CUSTOMER_REGISTER', 'Customer signed up: pradyut', '103.83.128.158', '2026-05-27 18:06:53', NULL, NULL, NULL),
(63, 5, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.83.128.158', '2026-05-27 18:07:10', NULL, NULL, NULL),
(64, 2, 'ROUTE_ADD', 'Added route DELHI to KOLKATA (1234 km, 32Hours)', '103.87.57.77', '2026-05-27 18:11:32', NULL, NULL, NULL),
(65, 5, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 18:11:42', NULL, NULL, NULL),
(66, NULL, 'LOGIN_FAILED', 'Failed login attempt for: pradyut', '103.83.128.158', '2026-05-27 18:12:18', NULL, NULL, NULL),
(67, 4, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.83.128.158', '2026-05-27 18:12:57', NULL, NULL, NULL),
(68, 4, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 18:13:31', NULL, NULL, NULL),
(69, 6, 'AGENT_REGISTER', 'Agent signed up: aslitravels (9813457901)', '103.83.128.158', '2026-05-27 18:18:46', NULL, NULL, NULL),
(70, 2, 'TRIP_ADD', 'Scheduled Trip ID: 5 (Bus ID 6, Route ID 6)', '103.87.57.77', '2026-05-27 18:22:45', NULL, NULL, NULL),
(71, NULL, 'LOGIN_FAILED', 'Failed Operator login attempt for: asliagent', '103.83.128.158', '2026-05-27 18:30:12', NULL, NULL, NULL),
(72, 2, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.83.128.158', '2026-05-27 18:30:56', NULL, NULL, NULL),
(73, 2, 'BUS_ADD', 'Added bus DANGA SPECIAL (TN-02-D-2355)', '103.83.128.158', '2026-05-27 18:33:34', NULL, NULL, NULL),
(74, 1, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.87.57.77', '2026-05-27 18:36:09', NULL, NULL, NULL),
(75, 2, 'LOGOUT', 'User signed out manually.', '103.87.57.77', '2026-05-27 18:38:02', NULL, NULL, NULL),
(76, 1, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.83.128.158', '2026-05-27 18:38:54', NULL, NULL, NULL),
(77, 1, 'AGENT_STATUS_CHANGE', 'Updated status of agent user ID 6 to approved', '103.83.128.158', '2026-05-27 18:39:02', NULL, NULL, NULL),
(78, 1, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 18:39:35', NULL, NULL, NULL),
(79, 6, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.83.128.158', '2026-05-27 18:40:08', NULL, NULL, NULL),
(80, 2, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.83.128.158', '2026-05-27 18:43:20', NULL, NULL, NULL),
(81, 6, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 18:43:46', NULL, NULL, NULL),
(82, 6, 'LOGIN_SUCCESS', 'User logged in successfully as agent.', '103.83.128.158', '2026-05-27 18:44:09', NULL, NULL, NULL),
(83, 6, 'LOGIN_SUCCESS', 'Agent logged in via Agent Partner portal.', '103.83.128.158', '2026-05-27 18:44:57', NULL, NULL, NULL),
(84, 6, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 18:45:10', NULL, NULL, NULL),
(85, 5, 'LOGIN_SUCCESS', 'User logged in successfully as customer.', '103.83.128.158', '2026-05-27 18:45:16', NULL, NULL, NULL),
(86, 2, 'PRICE_OVERRIDE_BULK', 'Override fares for seats (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40) on Trip: 5', '103.83.128.158', '2026-05-27 18:48:43', NULL, NULL, NULL),
(87, 2, 'PRICE_OVERRIDE_BULK', 'Override fares for seats (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40) on Trip: 5', '103.83.128.158', '2026-05-27 18:49:03', NULL, NULL, NULL),
(88, 2, 'PRICE_OVERRIDE_BULK', 'Override fares for seats (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40) on Trip: 5', '103.83.128.158', '2026-05-27 18:49:43', NULL, NULL, NULL),
(89, 5, 'LOGOUT', 'User signed out manually.', '103.83.128.158', '2026-05-27 18:53:50', NULL, NULL, NULL),
(90, 7, 'ADMIN_REGISTER', 'Operator/Admin signed up: abhi', '103.87.57.77', '2026-05-27 18:54:19', NULL, NULL, NULL),
(91, 8, 'ADMIN_REGISTER', 'Operator/Admin signed up: pradyutadmin', '103.83.128.158', '2026-05-27 18:54:45', NULL, NULL, NULL),
(92, 9, 'CUSTOMER_REGISTER', 'Customer signed up: pronay', '103.87.57.77', '2026-05-27 18:54:49', NULL, NULL, NULL),
(93, 8, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.83.128.158', '2026-05-27 18:56:08', NULL, NULL, NULL),
(94, 10, 'CUSTOMER_REGISTER', 'Customer signed up: maykun', '103.87.57.77', '2026-05-27 19:00:44', NULL, NULL, NULL),
(95, 1, 'OPERATOR_STATUS_CHANGE', 'Updated status of operator user ID 8 to approved', '103.87.57.77', '2026-05-27 19:01:18', NULL, NULL, NULL),
(96, 1, 'OPERATOR_STATUS_CHANGE', 'Updated status of operator user ID 7 to approved', '103.87.57.77', '2026-05-27 19:01:19', NULL, NULL, NULL),
(97, 7, 'LOGIN_SUCCESS', 'Operator logged in via Operator portal.', '103.87.57.77', '2026-05-27 19:03:35', NULL, NULL, NULL),
(98, 11, 'AGENT_REGISTER', 'Agent signed up: aslidalal agency under Admin ID: 7', '103.87.57.77', '2026-05-27 19:08:33', NULL, NULL, NULL),
(99, 7, 'AGENT_STATUS_CHANGE_BY_OPERATOR', 'Updated status of agent user ID 11 to approved', '103.87.57.77', '2026-05-27 19:08:40', NULL, NULL, NULL),
(100, NULL, 'LOGIN_FAILED', 'Failed login attempt for: aslidalal', '103.87.57.77', '2026-05-27 19:08:53', NULL, NULL, NULL),
(101, NULL, 'LOGIN_FAILED', 'Failed login attempt for: aslidalal', '103.87.57.77', '2026-05-27 19:09:00', NULL, NULL, NULL),
(102, 11, 'LOGIN_SUCCESS', 'User logged in successfully as agent.', '103.87.57.77', '2026-05-27 19:09:07', NULL, NULL, NULL),
(103, 2, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.87.57.77', '2026-05-27 19:45:54', NULL, NULL, NULL),
(104, 2, 'SEAT_RELEASE_BULK', 'Released seats (3,4,5) on Trip: 5', '103.87.57.77', '2026-05-27 19:47:50', NULL, NULL, NULL),
(105, 2, 'LOGIN_SUCCESS', 'User logged in successfully as admin.', '103.87.57.77', '2026-05-27 20:16:26', NULL, NULL, NULL),
(106, 2, 'BUS_DELETE', 'Soft deleted bus ID: 1', '103.87.57.77', '2026-05-27 20:18:00', NULL, NULL, NULL),
(107, 2, 'BUS_ADD', 'Added bus Panjab Highways (PB09HU1234)', '103.87.57.77', '2026-05-27 20:21:35', NULL, NULL, NULL),
(108, 2, 'ROUTE_ADD', 'Added route PUNJAB to DELHI (400 km, 8)', '103.87.57.77', '2026-05-27 20:23:47', NULL, NULL, NULL),
(109, 2, 'TRIP_ADD', 'Scheduled Trip ID: 6 (Bus ID 9, Route ID 7)', '103.87.57.77', '2026-05-27 20:26:00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `agent_profiles`
--

CREATE TABLE `agent_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `agency_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 2.00,
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_profiles`
--

INSERT INTO `agent_profiles` (`id`, `user_id`, `agency_name`, `phone`, `commission_rate`, `admin_id`) VALUES
(1, 4, 'Asli Agent Agency', '9876543210', '5.00', 2),
(2, 6, 'aslitravels', '9813457901', '2.00', NULL),
(3, 11, 'aslidalal agency', '2893728921', '2.00', 7);

-- --------------------------------------------------------

--
-- Table structure for table `boarding_points`
--

CREATE TABLE `boarding_points` (
  `id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `point_name` varchar(100) NOT NULL,
  `departure_time` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `boarding_points`
--

INSERT INTO `boarding_points` (`id`, `route_id`, `point_name`, `departure_time`) VALUES
(1, 6, 'Kashmeri gate', '12:00'),
(2, 7, 'KAHMERE GATE', '00:00');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(50) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `admin_commission` decimal(10,2) NOT NULL,
  `agent_net_earning` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_gateway` varchar(50) DEFAULT 'Razorpay',
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `boarding_point` varchar(255) DEFAULT NULL,
  `dropping_point` varchar(255) DEFAULT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `agent_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `booking_source` enum('customer','agent','admin') NOT NULL DEFAULT 'customer',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promo_code` varchar(50) DEFAULT NULL,
  `original_fare` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_fare` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `trip_id`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`, `total_amount`, `admin_commission`, `agent_net_earning`, `payment_status`, `payment_gateway`, `transaction_id`, `created_at`, `boarding_point`, `dropping_point`, `status`, `agent_id`, `admin_id`, `booking_source`, `discount_amount`, `promo_code`, `original_fare`, `discount_applied`, `final_fare`) VALUES
(1, 'TXN17797035981', 3, 4, 'John Doe', 'customer1@bus.com', '9876543219', '1100.00', '22.00', '1078.00', 'paid', 'Razorpay', 'pay_mock123456', '2026-05-24 04:36:38', NULL, NULL, 'active', NULL, NULL, 'customer', '0.00', NULL, '0.00', '0.00', '0.00'),
(2, 'TXN17797035982', 3, NULL, 'Alice Smith', 'alice@gmail.com', '9876543212', '550.00', '11.00', '539.00', 'paid', 'Razorpay', 'pay_mock789012', '2026-05-25 04:36:38', NULL, NULL, 'active', NULL, NULL, 'customer', '0.00', NULL, '0.00', '0.00', '0.00'),
(3, 'SB03C71455', 2, NULL, 'Keefe Miller', 'jyotirmaybiswas2419@gmail.com', '8447695038', '2400.00', '48.00', '2352.00', 'paid', 'Razorpay', 'pay_mock_0c113417ac99de6e', '2026-05-25 10:13:36', NULL, NULL, 'active', NULL, NULL, 'customer', '0.00', NULL, '0.00', '0.00', '0.00'),
(4, 'SBE492D280', 4, NULL, 'Theodore Hudson', 'diqo@mailinator.com', '+1 (293) 752-1939', '1000.00', '20.00', '980.00', 'paid', 'Razorpay', 'pay_mock_73883a3d0ceb4680', '2026-05-25 10:41:50', NULL, NULL, 'active', NULL, NULL, 'customer', '0.00', NULL, '0.00', '0.00', '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `booking_seats`
--

CREATE TABLE `booking_seats` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `passenger_name` varchar(100) NOT NULL,
  `passenger_age` int(11) NOT NULL,
  `passenger_gender` enum('Male','Female','Other') NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_seats`
--

INSERT INTO `booking_seats` (`id`, `booking_id`, `seat_number`, `passenger_name`, `passenger_age`, `passenger_gender`, `price`) VALUES
(1, 1, '1', 'John Doe', 30, 'Male', '550.00'),
(2, 1, '2', 'Jane Doe', 28, 'Female', '550.00'),
(3, 2, '3', 'Alice Smith', 24, 'Female', '550.00'),
(4, 3, '1', 'Dean Mcdaniel', 19, 'Male', '600.00'),
(5, 3, '2', 'Serena Kinney', 27, 'Female', '600.00'),
(6, 3, '5', 'Julian Singleton', 87, 'Female', '600.00'),
(7, 3, '6', 'Ariel Burnett', 72, 'Male', '600.00'),
(8, 4, '9', 'Odette Greer', 5, 'Other', '500.00'),
(9, 4, '14', 'Thomas Stark', 83, 'Other', '500.00');

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `bus_name` varchar(100) NOT NULL,
  `bus_number` varchar(50) NOT NULL,
  `bus_type` enum('AC Sleeper','Non-AC Sleeper','AC Seater','Non-AC Seater') NOT NULL,
  `total_seats` int(11) NOT NULL,
  `seat_layout_type` varchar(20) DEFAULT '2x2',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `admin_id` int(11) NOT NULL,
  `discount_type` enum('none','percentage','fixed') NOT NULL DEFAULT 'none',
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fixed` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`id`, `agent_id`, `bus_name`, `bus_number`, `bus_type`, `total_seats`, `seat_layout_type`, `created_at`, `status`, `admin_id`, `discount_type`, `percentage`, `fixed`) VALUES
(1, 2, 'Golden Deluxe AC Sleeper', 'KA-01-F-1234', 'AC Sleeper', 30, '2x1_sleeper', '2026-05-25 10:06:38', 'inactive', 2, 'none', '0.00', '0.00'),
(2, 2, 'Golden Express AC Seater', 'KA-01-F-5678', 'AC Seater', 40, '2x2_seater', '2026-05-25 10:06:38', 'active', 2, 'none', '0.00', '0.00'),
(3, 2, 'Manali Special', 'KA-01-F-0123', 'AC Seater', 40, '2x2_seater', '2026-05-25 10:35:12', 'active', 2, 'none', '0.00', '0.00'),
(6, 2, 'BENGAL SPECAIL', 'WB-10-X-0932', 'Non-AC Seater', 40, '2x2_seater', '2026-05-27 17:46:09', 'active', 2, 'none', '0.00', '0.00'),
(7, 2, 'GOA SPEACIAL', 'GA 01 AB 1234', 'AC Sleeper', 30, '2x1_sleeper', '2026-05-27 17:54:26', 'active', 2, 'none', '0.00', '0.00'),
(8, 2, 'DANGA SPECIAL', 'TN-02-D-2355', 'AC Sleeper', 30, '2x1_sleeper', '2026-05-27 18:33:34', 'active', 2, 'none', '0.00', '0.00'),
(9, 2, 'Panjab Highways', 'PB09HU1234', 'AC Sleeper', 30, '2x1_sleeper', '2026-05-27 20:21:35', 'active', 2, 'none', '0.00', '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `bus_layouts`
--

CREATE TABLE `bus_layouts` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `rows_count` int(11) NOT NULL DEFAULT 8,
  `cols_count` int(11) NOT NULL DEFAULT 5,
  `layout_type` varchar(50) NOT NULL DEFAULT 'Seater'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_seats`
--

CREATE TABLE `bus_seats` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `seat_number` varchar(50) NOT NULL,
  `row_pos` int(11) NOT NULL,
  `col_pos` int(11) NOT NULL,
  `seat_type` enum('Normal','Sleeper','Upper Sleeper','Lower Sleeper','Double Sleeper Upper','Double Sleeper Lower') NOT NULL DEFAULT 'Normal',
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `base_price` decimal(10,2) NOT NULL DEFAULT 500.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cancellation_requests`
--

CREATE TABLE `cancellation_requests` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dropping_points`
--

CREATE TABLE `dropping_points` (
  `id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `point_name` varchar(100) NOT NULL,
  `arrival_time` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dropping_points`
--

INSERT INTO `dropping_points` (`id`, `route_id`, `point_name`, `arrival_time`) VALUES
(1, 6, 'Sealdah', '12:00'),
(2, 7, 'AMRITSAR', '00:00');

-- --------------------------------------------------------

--
-- Table structure for table `layout_templates`
--

CREATE TABLE `layout_templates` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `rows_count` int(11) NOT NULL DEFAULT 8,
  `cols_count` int(11) NOT NULL DEFAULT 5,
  `layout_type` varchar(50) NOT NULL DEFAULT 'Seater',
  `seats_data` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operator_contacts`
--

CREATE TABLE `operator_contacts` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `operator_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `whatsapp_number` varchar(20) NOT NULL,
  `emergency_number` varchar(20) NOT NULL,
  `support_email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `operator_contacts`
--

INSERT INTO `operator_contacts` (`id`, `bus_id`, `operator_name`, `contact_number`, `whatsapp_number`, `emergency_number`, `support_email`) VALUES
(1, 7, 'Goons Buses', '9098324432', '9098324432', '9098324432', 'Goonsgoa@gmail.com'),
(2, 6, 'Dooie katla', '9098324432', '9098324432', '9098324432', 'dooiekatla@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `source` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `distance_km` int(11) NOT NULL,
  `pickup_points` text NOT NULL,
  `drop_points` text NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `duration` varchar(50) NOT NULL DEFAULT '6 hours',
  `admin_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`id`, `agent_id`, `source`, `destination`, `distance_km`, `pickup_points`, `drop_points`, `status`, `duration`, `admin_id`) VALUES
(1, 2, 'Bangalore', 'Mumbai', 1000, '[{\"name\":\"Majestic Bus Stand\",\"time\":\"20:00\"},{\"name\":\"Yeshwanthpur Tollgate\",\"time\":\"20:30\"}]', '[{\"name\":\"Pune Bypass\",\"time\":\"07:00\"},{\"name\":\"Mumbai Sion Circle\",\"time\":\"08:30\"}]', 'active', '6 hours', 2),
(2, 2, 'Bangalore', 'Chennai', 350, '[{\"name\":\"Majestic Bus Stand\",\"time\":\"22:00\"},{\"name\":\"Indiranagar Metro\",\"time\":\"22:30\"}]', '[{\"name\":\"Poonamallee Bypass\",\"time\":\"04:30\"},{\"name\":\"Koyambedu Bus Terminus\",\"time\":\"05:00\"}]', 'active', '6 hours', 2),
(3, 5, 'Delhi', 'Manali', 550, '[{\"name\":\"kashmere Gate\",\"time\":\"19:00\"}]', '[{\"name\":\"manali\",\"time\":\"09:06\"}]', 'active', '6 hours', 2),
(6, 2, 'DELHI', 'KOLKATA', 1234, '[{\"name\":\"Kashmeri gate\",\"time\":\"12:00\"}]', '[{\"name\":\"Sealdah\",\"time\":\"12:00\"}]', 'active', '32Hours', 2),
(7, 2, 'PUNJAB', 'DELHI', 400, '[{\"name\":\"KAHMERE GATE\",\"time\":\"00:00\"}]', '[{\"name\":\"AMRITSAR\",\"time\":\"00:00\"}]', 'active', '8', 2);

-- --------------------------------------------------------

--
-- Table structure for table `seat_blocks`
--

CREATE TABLE `seat_blocks` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `seat_number` varchar(20) NOT NULL,
  `blocked_by` int(11) NOT NULL,
  `blocked_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seat_holds`
--

CREATE TABLE `seat_holds` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `held_by_user_id` int(11) NOT NULL,
  `held_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seat_price_overrides`
--

CREATE TABLE `seat_price_overrides` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `seat_number` varchar(20) NOT NULL,
  `custom_price` decimal(10,2) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seat_pricing`
--

CREATE TABLE `seat_pricing` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `seat_number` varchar(50) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `current_price` decimal(10,2) NOT NULL,
  `offer_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_pricing`
--

INSERT INTO `seat_pricing` (`id`, `trip_id`, `seat_number`, `base_price`, `current_price`, `offer_price`) VALUES
(1, 5, '1', '1000.00', '800.00', '900.00'),
(2, 5, '2', '1000.00', '800.00', '900.00'),
(3, 5, '3', '1000.00', '800.00', '900.00'),
(4, 5, '4', '1000.00', '800.00', '900.00'),
(5, 5, '5', '1000.00', '800.00', '900.00'),
(6, 5, '6', '1000.00', '800.00', '900.00'),
(7, 5, '7', '1000.00', '800.00', '900.00'),
(8, 5, '8', '1000.00', '800.00', '900.00'),
(9, 5, '9', '1000.00', '800.00', '900.00'),
(10, 5, '10', '1000.00', '800.00', '900.00'),
(11, 5, '11', '1000.00', '800.00', '900.00'),
(12, 5, '12', '1000.00', '800.00', '900.00'),
(13, 5, '13', '1000.00', '800.00', '900.00'),
(14, 5, '14', '1000.00', '800.00', '900.00'),
(15, 5, '15', '1000.00', '800.00', '900.00'),
(16, 5, '16', '1000.00', '800.00', '900.00'),
(17, 5, '17', '1000.00', '800.00', '900.00'),
(18, 5, '18', '1000.00', '800.00', '900.00'),
(19, 5, '19', '1000.00', '800.00', '900.00'),
(20, 5, '20', '1000.00', '800.00', '900.00'),
(21, 5, '21', '1000.00', '800.00', '900.00'),
(22, 5, '22', '1000.00', '800.00', '900.00'),
(23, 5, '23', '1000.00', '800.00', '900.00'),
(24, 5, '24', '1000.00', '800.00', '900.00'),
(25, 5, '25', '1000.00', '800.00', '900.00'),
(26, 5, '26', '1000.00', '800.00', '900.00'),
(27, 5, '27', '1000.00', '800.00', '900.00'),
(28, 5, '28', '1000.00', '800.00', '900.00'),
(29, 5, '29', '1000.00', '800.00', '900.00'),
(30, 5, '30', '1000.00', '800.00', '900.00'),
(31, 5, '31', '1000.00', '800.00', '900.00'),
(32, 5, '32', '1000.00', '800.00', '900.00'),
(33, 5, '33', '1000.00', '800.00', '900.00'),
(34, 5, '34', '1000.00', '800.00', '900.00'),
(35, 5, '35', '1000.00', '800.00', '900.00'),
(36, 5, '36', '1000.00', '800.00', '900.00'),
(37, 5, '37', '1000.00', '800.00', '900.00'),
(38, 5, '38', '1000.00', '800.00', '900.00'),
(39, 5, '39', '1000.00', '800.00', '900.00'),
(40, 5, '40', '1000.00', '800.00', '900.00'),
(161, 6, 'L1', '0.00', '0.00', '0.00'),
(162, 6, 'U1', '100.00', '100.00', '100.00'),
(163, 6, 'L2', '0.00', '0.00', '0.00'),
(164, 6, 'U2', '100.00', '100.00', '100.00'),
(165, 6, 'L3', '0.00', '0.00', '0.00'),
(166, 6, 'U3', '100.00', '100.00', '100.00'),
(167, 6, 'L4', '0.00', '0.00', '0.00'),
(168, 6, 'U4', '100.00', '100.00', '100.00'),
(169, 6, 'L5', '0.00', '0.00', '0.00'),
(170, 6, 'U5', '100.00', '100.00', '100.00'),
(171, 6, 'L6', '0.00', '0.00', '0.00'),
(172, 6, 'U6', '100.00', '100.00', '100.00'),
(173, 6, 'L7', '0.00', '0.00', '0.00'),
(174, 6, 'U7', '100.00', '100.00', '100.00'),
(175, 6, 'L8', '0.00', '0.00', '0.00'),
(176, 6, 'U8', '100.00', '100.00', '100.00'),
(177, 6, 'L9', '0.00', '0.00', '0.00'),
(178, 6, 'U9', '100.00', '100.00', '100.00'),
(179, 6, 'L10', '0.00', '0.00', '0.00'),
(180, 6, 'U10', '100.00', '100.00', '100.00'),
(181, 6, 'L11', '0.00', '0.00', '0.00'),
(182, 6, 'U11', '100.00', '100.00', '100.00'),
(183, 6, 'L12', '0.00', '0.00', '0.00'),
(184, 6, 'U12', '100.00', '100.00', '100.00'),
(185, 6, 'L13', '0.00', '0.00', '0.00'),
(186, 6, 'U13', '100.00', '100.00', '100.00'),
(187, 6, 'L14', '0.00', '0.00', '0.00'),
(188, 6, 'U14', '100.00', '100.00', '100.00'),
(189, 6, 'L15', '0.00', '0.00', '0.00'),
(190, 6, 'U15', '100.00', '100.00', '100.00');

-- --------------------------------------------------------

--
-- Table structure for table `system_notifications`
--

CREATE TABLE `system_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_role` enum('admin','agent') NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'maintenance_mode', '0', '2026-05-25 10:06:38'),
(2, 'custom_notice', 'Welcome to the Bus Ticket Booking portal! Book your seats with premium ease.', '2026-05-25 10:06:38'),
(3, 'suspend_agent_panel', '0', '2026-05-25 10:06:38');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `departure_time` datetime NOT NULL,
  `arrival_time` datetime NOT NULL,
  `base_fare` decimal(10,2) NOT NULL,
  `seat_prices` text DEFAULT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `admin_id` int(11) NOT NULL,
  `discount_type` enum('none','percentage','fixed') NOT NULL DEFAULT 'none',
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fixed` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `bus_id`, `route_id`, `departure_time`, `arrival_time`, `base_fare`, `seat_prices`, `status`, `admin_id`, `discount_type`, `percentage`, `fixed`) VALUES
(1, 1, 1, '2026-05-26 20:00:00', '2026-05-27 08:00:00', '1200.00', NULL, 'active', 2, 'none', '0.00', '0.00'),
(2, 2, 2, '2026-05-26 22:00:00', '2026-05-27 05:00:00', '600.00', NULL, 'active', 2, 'none', '0.00', '0.00'),
(3, 2, 2, '2026-05-25 22:00:00', '2026-05-26 05:00:00', '550.00', NULL, 'active', 2, 'none', '0.00', '0.00'),
(4, 3, 3, '2026-05-26 19:00:00', '2026-05-27 09:08:00', '500.00', NULL, 'active', 2, 'none', '0.00', '0.00'),
(5, 6, 6, '2026-05-28 12:00:00', '2026-05-29 12:00:00', '0.00', NULL, 'active', 2, 'percentage', '20.00', '0.00'),
(6, 9, 7, '2026-05-28 12:00:00', '2026-05-29 20:00:00', '0.00', NULL, 'active', 2, 'percentage', '20.00', '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `trip_seats`
--

CREATE TABLE `trip_seats` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `seat_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('available','selected','booked','hold','cancelled','blocked','reserved','temp_locked','female_booked','female_protected') DEFAULT 'available',
  `hold_expires_at` timestamp NULL DEFAULT NULL,
  `locked_by_session` varchar(255) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `gender_restriction` enum('none','female_only','female_protected') DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_seats`
--

INSERT INTO `trip_seats` (`id`, `trip_id`, `seat_number`, `status`, `hold_expires_at`, `locked_by_session`, `locked_at`, `gender_restriction`) VALUES
(1, 1, 'L1', 'available', NULL, NULL, NULL, 'none'),
(2, 1, 'U1', 'available', NULL, NULL, NULL, 'none'),
(3, 1, 'L2', 'available', NULL, NULL, NULL, 'none'),
(4, 1, 'U2', 'available', NULL, NULL, NULL, 'none'),
(5, 1, 'L3', 'available', NULL, NULL, NULL, 'none'),
(6, 1, 'U3', 'available', NULL, NULL, NULL, 'none'),
(7, 1, 'L4', 'available', NULL, NULL, NULL, 'none'),
(8, 1, 'U4', 'available', NULL, NULL, NULL, 'none'),
(9, 1, 'L5', 'available', NULL, NULL, NULL, 'none'),
(10, 1, 'U5', 'available', NULL, NULL, NULL, 'none'),
(11, 1, 'L6', 'available', NULL, NULL, NULL, 'none'),
(12, 1, 'U6', 'available', NULL, NULL, NULL, 'none'),
(13, 1, 'L7', 'available', NULL, NULL, NULL, 'none'),
(14, 1, 'U7', 'available', NULL, NULL, NULL, 'none'),
(15, 1, 'L8', 'available', NULL, NULL, NULL, 'none'),
(16, 1, 'U8', 'available', NULL, NULL, NULL, 'none'),
(17, 1, 'L9', 'available', NULL, NULL, NULL, 'none'),
(18, 1, 'U9', 'available', NULL, NULL, NULL, 'none'),
(19, 1, 'L10', 'available', NULL, NULL, NULL, 'none'),
(20, 1, 'U10', 'available', NULL, NULL, NULL, 'none'),
(21, 1, 'L11', 'available', NULL, NULL, NULL, 'none'),
(22, 1, 'U11', 'available', NULL, NULL, NULL, 'none'),
(23, 1, 'L12', 'available', NULL, NULL, NULL, 'none'),
(24, 1, 'U12', 'available', NULL, NULL, NULL, 'none'),
(25, 1, 'L13', 'available', NULL, NULL, NULL, 'none'),
(26, 1, 'U13', 'available', NULL, NULL, NULL, 'none'),
(27, 1, 'L14', 'available', NULL, NULL, NULL, 'none'),
(28, 1, 'U14', 'available', NULL, NULL, NULL, 'none'),
(29, 1, 'L15', 'available', NULL, NULL, NULL, 'none'),
(30, 1, 'U15', 'available', NULL, NULL, NULL, 'none'),
(31, 2, '1', 'booked', NULL, NULL, NULL, 'none'),
(32, 2, '2', 'booked', NULL, NULL, NULL, 'none'),
(33, 2, '3', 'available', NULL, NULL, NULL, 'none'),
(34, 2, '4', 'available', NULL, NULL, NULL, 'none'),
(35, 2, '5', 'booked', NULL, NULL, NULL, 'none'),
(36, 2, '6', 'booked', NULL, NULL, NULL, 'none'),
(37, 2, '7', 'available', NULL, NULL, NULL, 'none'),
(38, 2, '8', 'available', NULL, NULL, NULL, 'none'),
(39, 2, '9', 'available', NULL, NULL, NULL, 'none'),
(40, 2, '10', 'available', NULL, NULL, NULL, 'none'),
(41, 2, '11', 'available', NULL, NULL, NULL, 'none'),
(42, 2, '12', 'available', NULL, NULL, NULL, 'none'),
(43, 2, '13', 'available', NULL, NULL, NULL, 'none'),
(44, 2, '14', 'available', NULL, NULL, NULL, 'none'),
(45, 2, '15', 'available', NULL, NULL, NULL, 'none'),
(46, 2, '16', 'available', NULL, NULL, NULL, 'none'),
(47, 2, '17', 'available', NULL, NULL, NULL, 'none'),
(48, 2, '18', 'available', NULL, NULL, NULL, 'none'),
(49, 2, '19', 'available', NULL, NULL, NULL, 'none'),
(50, 2, '20', 'available', NULL, NULL, NULL, 'none'),
(51, 2, '21', 'available', NULL, NULL, NULL, 'none'),
(52, 2, '22', 'available', NULL, NULL, NULL, 'none'),
(53, 2, '23', 'available', NULL, NULL, NULL, 'none'),
(54, 2, '24', 'available', NULL, NULL, NULL, 'none'),
(55, 2, '25', 'available', NULL, NULL, NULL, 'none'),
(56, 2, '26', 'available', NULL, NULL, NULL, 'none'),
(57, 2, '27', 'available', NULL, NULL, NULL, 'none'),
(58, 2, '28', 'available', NULL, NULL, NULL, 'none'),
(59, 2, '29', 'available', NULL, NULL, NULL, 'none'),
(60, 2, '30', 'available', NULL, NULL, NULL, 'none'),
(61, 2, '31', 'available', NULL, NULL, NULL, 'none'),
(62, 2, '32', 'available', NULL, NULL, NULL, 'none'),
(63, 2, '33', 'available', NULL, NULL, NULL, 'none'),
(64, 2, '34', 'available', NULL, NULL, NULL, 'none'),
(65, 2, '35', 'available', NULL, NULL, NULL, 'none'),
(66, 2, '36', 'available', NULL, NULL, NULL, 'none'),
(67, 2, '37', 'available', NULL, NULL, NULL, 'none'),
(68, 2, '38', 'available', NULL, NULL, NULL, 'none'),
(69, 2, '39', 'available', NULL, NULL, NULL, 'none'),
(70, 2, '40', 'available', NULL, NULL, NULL, 'none'),
(71, 3, '1', 'booked', NULL, NULL, NULL, 'none'),
(72, 3, '2', 'booked', NULL, NULL, NULL, 'none'),
(73, 3, '3', 'booked', NULL, NULL, NULL, 'none'),
(74, 3, '4', 'available', NULL, NULL, NULL, 'none'),
(75, 3, '5', 'available', NULL, NULL, NULL, 'none'),
(76, 3, '6', 'available', NULL, NULL, NULL, 'none'),
(77, 3, '7', 'available', NULL, NULL, NULL, 'none'),
(78, 3, '8', 'available', NULL, NULL, NULL, 'none'),
(79, 3, '9', 'available', NULL, NULL, NULL, 'none'),
(80, 3, '10', 'available', NULL, NULL, NULL, 'none'),
(81, 3, '11', 'available', NULL, NULL, NULL, 'none'),
(82, 3, '12', 'available', NULL, NULL, NULL, 'none'),
(83, 3, '13', 'available', NULL, NULL, NULL, 'none'),
(84, 3, '14', 'available', NULL, NULL, NULL, 'none'),
(85, 3, '15', 'available', NULL, NULL, NULL, 'none'),
(86, 3, '16', 'available', NULL, NULL, NULL, 'none'),
(87, 3, '17', 'available', NULL, NULL, NULL, 'none'),
(88, 3, '18', 'available', NULL, NULL, NULL, 'none'),
(89, 3, '19', 'available', NULL, NULL, NULL, 'none'),
(90, 3, '20', 'available', NULL, NULL, NULL, 'none'),
(91, 3, '21', 'available', NULL, NULL, NULL, 'none'),
(92, 3, '22', 'available', NULL, NULL, NULL, 'none'),
(93, 3, '23', 'available', NULL, NULL, NULL, 'none'),
(94, 3, '24', 'available', NULL, NULL, NULL, 'none'),
(95, 3, '25', 'available', NULL, NULL, NULL, 'none'),
(96, 3, '26', 'available', NULL, NULL, NULL, 'none'),
(97, 3, '27', 'available', NULL, NULL, NULL, 'none'),
(98, 3, '28', 'available', NULL, NULL, NULL, 'none'),
(99, 3, '29', 'available', NULL, NULL, NULL, 'none'),
(100, 3, '30', 'available', NULL, NULL, NULL, 'none'),
(101, 3, '31', 'available', NULL, NULL, NULL, 'none'),
(102, 3, '32', 'available', NULL, NULL, NULL, 'none'),
(103, 3, '33', 'available', NULL, NULL, NULL, 'none'),
(104, 3, '34', 'available', NULL, NULL, NULL, 'none'),
(105, 3, '35', 'available', NULL, NULL, NULL, 'none'),
(106, 3, '36', 'available', NULL, NULL, NULL, 'none'),
(107, 3, '37', 'available', NULL, NULL, NULL, 'none'),
(108, 3, '38', 'available', NULL, NULL, NULL, 'none'),
(109, 3, '39', 'available', NULL, NULL, NULL, 'none'),
(110, 3, '40', 'available', NULL, NULL, NULL, 'none'),
(119, 4, '1', 'available', NULL, NULL, NULL, 'none'),
(120, 4, '2', 'available', NULL, NULL, NULL, 'none'),
(121, 4, '3', 'available', NULL, NULL, NULL, 'none'),
(122, 4, '4', 'available', NULL, NULL, NULL, 'none'),
(123, 4, '5', 'available', NULL, NULL, NULL, 'none'),
(124, 4, '6', 'available', NULL, NULL, NULL, 'none'),
(125, 4, '7', 'available', NULL, NULL, NULL, 'none'),
(126, 4, '8', 'available', NULL, NULL, NULL, 'none'),
(127, 4, '9', 'booked', NULL, NULL, NULL, 'none'),
(128, 4, '10', 'hold', '2026-06-01 05:08:41', 'agent_hold_5', NULL, 'none'),
(129, 4, '11', 'available', NULL, NULL, NULL, 'none'),
(130, 4, '12', 'available', NULL, NULL, NULL, 'none'),
(131, 4, '13', 'available', NULL, NULL, NULL, 'none'),
(132, 4, '14', 'booked', NULL, NULL, NULL, 'none'),
(133, 4, '15', 'available', NULL, NULL, NULL, 'none'),
(134, 4, '16', 'available', NULL, NULL, NULL, 'none'),
(135, 4, '17', 'available', NULL, NULL, NULL, 'none'),
(136, 4, '18', 'available', NULL, NULL, NULL, 'none'),
(137, 4, '19', 'available', NULL, NULL, NULL, 'none'),
(138, 4, '20', 'available', NULL, NULL, NULL, 'none'),
(139, 4, '21', 'available', NULL, NULL, NULL, 'none'),
(140, 4, '22', 'available', NULL, NULL, NULL, 'none'),
(141, 4, '23', 'available', NULL, NULL, NULL, 'none'),
(142, 4, '24', 'available', NULL, NULL, NULL, 'none'),
(143, 4, '25', 'available', NULL, NULL, NULL, 'none'),
(144, 4, '26', 'available', NULL, NULL, NULL, 'none'),
(145, 4, '27', 'available', NULL, NULL, NULL, 'none'),
(146, 4, '28', 'available', NULL, NULL, NULL, 'none'),
(147, 4, '29', 'available', NULL, NULL, NULL, 'none'),
(148, 4, '30', 'available', NULL, NULL, NULL, 'none'),
(149, 4, '31', 'available', NULL, NULL, NULL, 'none'),
(150, 4, '32', 'available', NULL, NULL, NULL, 'none'),
(151, 4, '33', 'available', NULL, NULL, NULL, 'none'),
(152, 4, '34', 'available', NULL, NULL, NULL, 'none'),
(153, 4, '35', 'available', NULL, NULL, NULL, 'none'),
(154, 4, '36', 'available', NULL, NULL, NULL, 'none'),
(155, 4, '37', 'available', NULL, NULL, NULL, 'none'),
(156, 4, '38', 'available', NULL, NULL, NULL, 'none'),
(157, 4, '39', 'available', NULL, NULL, NULL, 'none'),
(158, 4, '40', 'available', NULL, NULL, NULL, 'none'),
(163, 5, '1', 'temp_locked', NULL, '38feadf82c9411f57b3ba371d4112b84', '2026-05-27 21:49:48', 'none'),
(164, 5, '2', 'temp_locked', NULL, '38feadf82c9411f57b3ba371d4112b84', '2026-05-27 21:49:49', 'none'),
(165, 5, '3', 'available', NULL, NULL, NULL, 'none'),
(166, 5, '4', 'available', NULL, NULL, NULL, 'none'),
(167, 5, '5', 'available', NULL, NULL, NULL, 'none'),
(168, 5, '6', 'available', NULL, NULL, NULL, 'none'),
(169, 5, '7', 'available', NULL, NULL, NULL, 'none'),
(170, 5, '8', 'available', NULL, NULL, NULL, 'none'),
(171, 5, '9', 'available', NULL, NULL, NULL, 'none'),
(172, 5, '10', 'available', NULL, NULL, NULL, 'none'),
(173, 5, '11', 'available', NULL, NULL, NULL, 'none'),
(174, 5, '12', 'available', NULL, NULL, NULL, 'none'),
(175, 5, '13', 'available', NULL, NULL, NULL, 'none'),
(176, 5, '14', 'available', NULL, NULL, NULL, 'none'),
(177, 5, '15', 'available', NULL, NULL, NULL, 'none'),
(178, 5, '16', 'available', NULL, NULL, NULL, 'none'),
(179, 5, '17', 'available', NULL, NULL, NULL, 'none'),
(180, 5, '18', 'available', NULL, NULL, NULL, 'none'),
(181, 5, '19', 'available', NULL, NULL, NULL, 'none'),
(182, 5, '20', 'available', NULL, NULL, NULL, 'none'),
(183, 5, '21', 'available', NULL, NULL, NULL, 'none'),
(184, 5, '22', 'available', NULL, NULL, NULL, 'none'),
(185, 5, '23', 'available', NULL, NULL, NULL, 'none'),
(186, 5, '24', 'available', NULL, NULL, NULL, 'none'),
(187, 5, '25', 'available', NULL, NULL, NULL, 'none'),
(188, 5, '26', 'available', NULL, NULL, NULL, 'none'),
(189, 5, '27', 'available', NULL, NULL, NULL, 'none'),
(190, 5, '28', 'available', NULL, NULL, NULL, 'none'),
(191, 5, '29', 'available', NULL, NULL, NULL, 'none'),
(192, 5, '30', 'available', NULL, NULL, NULL, 'none'),
(193, 5, '31', 'available', NULL, NULL, NULL, 'none'),
(194, 5, '32', 'available', NULL, NULL, NULL, 'none'),
(195, 5, '33', 'available', NULL, NULL, NULL, 'none'),
(196, 5, '34', 'available', NULL, NULL, NULL, 'none'),
(197, 5, '35', 'available', NULL, NULL, NULL, 'none'),
(198, 5, '36', 'available', NULL, NULL, NULL, 'none'),
(199, 5, '37', 'available', NULL, NULL, NULL, 'none'),
(200, 5, '38', 'available', NULL, NULL, NULL, 'none'),
(201, 5, '39', 'available', NULL, NULL, NULL, 'none'),
(202, 5, '40', 'available', NULL, NULL, NULL, 'none'),
(213, 6, 'L1', 'available', NULL, NULL, NULL, 'none'),
(214, 6, 'U1', 'available', NULL, NULL, NULL, 'none'),
(215, 6, 'L2', 'available', NULL, NULL, NULL, 'none'),
(216, 6, 'U2', 'available', NULL, NULL, NULL, 'none'),
(217, 6, 'L3', 'available', NULL, NULL, NULL, 'none'),
(218, 6, 'U3', 'available', NULL, NULL, NULL, 'none'),
(219, 6, 'L4', 'available', NULL, NULL, NULL, 'none'),
(220, 6, 'U4', 'available', NULL, NULL, NULL, 'none'),
(221, 6, 'L5', 'available', NULL, NULL, NULL, 'none'),
(222, 6, 'U5', 'available', NULL, NULL, NULL, 'none'),
(223, 6, 'L6', 'available', NULL, NULL, NULL, 'none'),
(224, 6, 'U6', 'available', NULL, NULL, NULL, 'none'),
(225, 6, 'L7', 'available', NULL, NULL, NULL, 'none'),
(226, 6, 'U7', 'available', NULL, NULL, NULL, 'none'),
(227, 6, 'L8', 'available', NULL, NULL, NULL, 'none'),
(228, 6, 'U8', 'available', NULL, NULL, NULL, 'none'),
(229, 6, 'L9', 'available', NULL, NULL, NULL, 'none'),
(230, 6, 'U9', 'available', NULL, NULL, NULL, 'none'),
(231, 6, 'L10', 'available', NULL, NULL, NULL, 'none'),
(232, 6, 'U10', 'available', NULL, NULL, NULL, 'none'),
(233, 6, 'L11', 'available', NULL, NULL, NULL, 'none'),
(234, 6, 'U11', 'available', NULL, NULL, NULL, 'none'),
(235, 6, 'L12', 'available', NULL, NULL, NULL, 'none'),
(236, 6, 'U12', 'available', NULL, NULL, NULL, 'none'),
(237, 6, 'L13', 'available', NULL, NULL, NULL, 'none'),
(238, 6, 'U13', 'available', NULL, NULL, NULL, 'none'),
(239, 6, 'L14', 'available', NULL, NULL, NULL, 'none'),
(240, 6, 'U14', 'available', NULL, NULL, NULL, 'none'),
(241, 6, 'L15', 'available', NULL, NULL, NULL, 'none'),
(242, 6, 'U15', 'available', NULL, NULL, NULL, 'none');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','agent','admin','super_admin') NOT NULL DEFAULT 'customer',
  `status` enum('pending','approved','suspended') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`, `created_at`, `admin_id`) VALUES
(1, 'admin', 'admin@bus.com', '$2y$10$wvncsQho7Y8VFBfgvJ2Nwu3Tk3ntWOFK6V8K1a18yljag/0LqDyoK', 'super_admin', 'approved', '2026-05-27 16:08:01', NULL),
(2, 'aslitravels', 'aslitravels@bus.com', '$2y$10$KnkfDlyXUhz55FaITiZ1NuZffzU0IhXseZFhuAVEYhXrrlPE3C2da', 'admin', 'approved', '2026-05-27 16:08:01', NULL),
(3, 'jyoti', 'jyoti@bus.com', '$2y$10$KnkfDlyXUhz55FaITiZ1NuZffzU0IhXseZFhuAVEYhXrrlPE3C2da', 'customer', 'approved', '2026-05-27 16:08:01', NULL),
(4, 'asliagent', 'asliagent@bus.com', '$2y$10$KnkfDlyXUhz55FaITiZ1NuZffzU0IhXseZFhuAVEYhXrrlPE3C2da', 'agent', 'approved', '2026-05-27 16:08:01', 2),
(5, 'pradyut', 'test@gmail.com', '$2y$10$YT.dMlS0HGQe9twVFlkKc.hsH.au1ZZ12fqAHIoTOWSSNNwY4.2LO', 'customer', 'approved', '2026-05-27 18:06:53', NULL),
(6, 'pradyutagent', 'test1@gmail.com', '$2y$10$9h3ceqyn3aT5By7/vWq3JuQBRFvNlax9bWsGigPB9AryVhTpJZmaK', 'agent', 'approved', '2026-05-27 18:18:46', NULL),
(7, 'abhi', 'abhi@gmail.com', '$2y$10$dAbo3XpadNFZ8UrwN9QyQ.WFbKovEklTlJAseSteP10Iooft83ji6', 'admin', 'approved', '2026-05-27 18:54:19', NULL),
(8, 'pradyutadmin', 'test2@gmail.com', '$2y$10$bo81okJM2DcQ851Kmstfkux/14r50iHlEWCH63aqz.T84CShFvCcG', 'admin', 'approved', '2026-05-27 18:54:45', NULL),
(9, 'pronay', 'pronay@gmail.com', '$2y$10$fa3Y8ReRga0DcgRkOdZcbuLBjzkKtCjh6XCilPCtJWSf8PG8AEMKa', 'customer', 'approved', '2026-05-27 18:54:49', NULL),
(10, 'maykun', 'maykun@gmail.com', '$2y$10$JmMf94VSzPKzZp3a5G7/Peg47bIznq2G96xfed4d.Ct4lT8kthEJG', 'customer', 'approved', '2026-05-27 19:00:44', NULL),
(11, 'aslidalal', 'aslidalal@gm', '$2y$10$mf4fmdAHOBo0YGPXkVUmWunx3Nd6L1H71vSFOFvaEnbAMhOuHCVeC', 'agent', 'approved', '2026-05-27 19:08:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `weekly_settlements`
--

CREATE TABLE `weekly_settlements` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `total_sales` decimal(10,2) NOT NULL,
  `commission_payable` decimal(10,2) NOT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `marked_paid_at` timestamp NULL DEFAULT NULL,
  `marked_paid_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weekly_settlements`
--

INSERT INTO `weekly_settlements` (`id`, `agent_id`, `week_start`, `week_end`, `total_sales`, `commission_payable`, `status`, `marked_paid_at`, `marked_paid_by`) VALUES
(1, 2, '2026-05-11', '2026-05-18', '5000.00', '100.00', 'pending', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `agent_profiles`
--
ALTER TABLE `agent_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `boarding_points`
--
ALTER TABLE `boarding_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `booking_seats`
--
ALTER TABLE `booking_seats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `bus_layouts`
--
ALTER TABLE `bus_layouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bus_id` (`bus_id`);

--
-- Indexes for table `bus_seats`
--
ALTER TABLE `bus_seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bus_seat_pos` (`bus_id`,`row_pos`,`col_pos`),
  ADD UNIQUE KEY `unique_bus_seat_num` (`bus_id`,`seat_number`);

--
-- Indexes for table `cancellation_requests`
--
ALTER TABLE `cancellation_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `dropping_points`
--
ALTER TABLE `dropping_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `layout_templates`
--
ALTER TABLE `layout_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `operator_contacts`
--
ALTER TABLE `operator_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bus_id` (`bus_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `seat_blocks`
--
ALTER TABLE `seat_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_seat_block` (`trip_id`,`seat_number`),
  ADD KEY `blocked_by` (`blocked_by`);

--
-- Indexes for table `seat_holds`
--
ALTER TABLE `seat_holds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_seat_hold` (`trip_id`,`seat_number`),
  ADD KEY `held_by_user_id` (`held_by_user_id`);

--
-- Indexes for table `seat_price_overrides`
--
ALTER TABLE `seat_price_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_seat_override` (`trip_id`,`seat_number`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `seat_pricing`
--
ALTER TABLE `seat_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_seat_price` (`trip_id`,`seat_number`);

--
-- Indexes for table `system_notifications`
--
ALTER TABLE `system_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `trip_seats`
--
ALTER TABLE `trip_seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_seat` (`trip_id`,`seat_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `weekly_settlements`
--
ALTER TABLE `weekly_settlements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `marked_paid_by` (`marked_paid_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `agent_profiles`
--
ALTER TABLE `agent_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `boarding_points`
--
ALTER TABLE `boarding_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `booking_seats`
--
ALTER TABLE `booking_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `bus_layouts`
--
ALTER TABLE `bus_layouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bus_seats`
--
ALTER TABLE `bus_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cancellation_requests`
--
ALTER TABLE `cancellation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dropping_points`
--
ALTER TABLE `dropping_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `layout_templates`
--
ALTER TABLE `layout_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operator_contacts`
--
ALTER TABLE `operator_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `seat_blocks`
--
ALTER TABLE `seat_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seat_holds`
--
ALTER TABLE `seat_holds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seat_price_overrides`
--
ALTER TABLE `seat_price_overrides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seat_pricing`
--
ALTER TABLE `seat_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT for table `system_notifications`
--
ALTER TABLE `system_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `trip_seats`
--
ALTER TABLE `trip_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=243;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `weekly_settlements`
--
ALTER TABLE `weekly_settlements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `agent_profiles`
--
ALTER TABLE `agent_profiles`
  ADD CONSTRAINT `agent_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `boarding_points`
--
ALTER TABLE `boarding_points`
  ADD CONSTRAINT `boarding_points_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_seats`
--
ALTER TABLE `booking_seats`
  ADD CONSTRAINT `booking_seats_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `buses`
--
ALTER TABLE `buses`
  ADD CONSTRAINT `buses_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bus_layouts`
--
ALTER TABLE `bus_layouts`
  ADD CONSTRAINT `bus_layouts_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bus_seats`
--
ALTER TABLE `bus_seats`
  ADD CONSTRAINT `bus_seats_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cancellation_requests`
--
ALTER TABLE `cancellation_requests`
  ADD CONSTRAINT `cancellation_requests_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cancellation_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dropping_points`
--
ALTER TABLE `dropping_points`
  ADD CONSTRAINT `dropping_points_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `layout_templates`
--
ALTER TABLE `layout_templates`
  ADD CONSTRAINT `layout_templates_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `operator_contacts`
--
ALTER TABLE `operator_contacts`
  ADD CONSTRAINT `operator_contacts_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `routes`
--
ALTER TABLE `routes`
  ADD CONSTRAINT `routes_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_blocks`
--
ALTER TABLE `seat_blocks`
  ADD CONSTRAINT `seat_blocks_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seat_blocks_ibfk_2` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_holds`
--
ALTER TABLE `seat_holds`
  ADD CONSTRAINT `seat_holds_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seat_holds_ibfk_2` FOREIGN KEY (`held_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_price_overrides`
--
ALTER TABLE `seat_price_overrides`
  ADD CONSTRAINT `seat_price_overrides_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seat_price_overrides_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_pricing`
--
ALTER TABLE `seat_pricing`
  ADD CONSTRAINT `seat_pricing_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trip_seats`
--
ALTER TABLE `trip_seats`
  ADD CONSTRAINT `trip_seats_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `weekly_settlements`
--
ALTER TABLE `weekly_settlements`
  ADD CONSTRAINT `weekly_settlements_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `weekly_settlements_ibfk_2` FOREIGN KEY (`marked_paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

SET FOREIGN_KEY_CHECKS = 1;
