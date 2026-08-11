-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2026 at 01:44 AM
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
-- Database: `delta_census3`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'create_enumerator', 'Created enumerator: Obinna Enenya (ID: DEL-00001)', '::1', '2026-07-12 23:04:13'),
(2, 1, 'update_status', 'Updated user status to: inactive for user ID: 2', '::1', '2026-07-12 23:04:54'),
(3, 1, 'update_status', 'Updated user status to: active for user ID: 2', '::1', '2026-07-12 23:05:13'),
(4, 1, 'create_enumerator', 'Created enumerator: Ihgbdna Enerry (ID: DEL-00002)', '::1', '2026-07-12 23:24:29'),
(5, 1, 'update_enumerator', 'Updated enumerator: Obinna Enenya (ID: 2)', '::1', '2026-07-13 09:19:50'),
(6, 1, 'verify_household', 'Verified household ID: 5', '::1', '2026-07-13 09:58:31');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `user_role` enum('admin','enumerator') NOT NULL,
  `action` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `action`, `category`, `description`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 1, 'admin', 'admin', 'update_profile', 'user_management', 'Updated admin profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 12:15:41'),
(2, 1, 'admin', 'admin', 'update_profile', 'user_management', 'Updated admin profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 12:16:59'),
(3, 1, 'admin', 'admin', 'update_profile', 'user_management', 'Updated admin profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 12:22:15'),
(4, 1, 'admin', 'admin', 'change_password', 'user_management', 'Changed admin password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 12:27:32'),
(5, 1, 'admin', 'admin', 'login', 'authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 12:53:11'),
(6, 1, 'admin', 'admin', 'update_profile', 'user_management', 'Updated admin profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 13:31:52'),
(7, 1, 'admin', 'admin', 'logout', 'authentication', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 14:08:02'),
(8, 1, 'admin', 'admin', 'login', 'authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-13 15:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(11) NOT NULL,
  `household_code` varchar(50) NOT NULL,
  `lga` varchar(100) NOT NULL,
  `ward` varchar(100) NOT NULL,
  `community` varchar(100) DEFAULT NULL,
  `enumeration_area` varchar(50) DEFAULT NULL,
  `head_of_household` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `total_members` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('draft','submitted','verified','rejected') DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `household_number` varchar(20) DEFAULT NULL,
  `household_head` varchar(100) DEFAULT NULL,
  `house_number` varchar(50) DEFAULT NULL,
  `street_name` varchar(200) DEFAULT NULL,
  `landmark` varchar(200) DEFAULT NULL,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` varchar(20) DEFAULT NULL,
  `building_type` varchar(50) DEFAULT NULL,
  `house_ownership` varchar(50) DEFAULT NULL,
  `number_of_households` int(11) DEFAULT NULL,
  `number_of_rooms` int(11) DEFAULT NULL,
  `enumerator_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `households`
--

INSERT INTO `households` (`id`, `household_code`, `lga`, `ward`, `community`, `enumeration_area`, `head_of_household`, `address`, `phone`, `total_members`, `created_by`, `created_at`, `updated_at`, `status`, `submitted_at`, `verified_at`, `device_info`, `ip_address`, `supervisor_id`, `household_number`, `household_head`, `house_number`, `street_name`, `landmark`, `gps_latitude`, `gps_longitude`, `gps_accuracy`, `building_type`, `house_ownership`, `number_of_households`, `number_of_rooms`, `enumerator_id`) VALUES
(1, 'HH-000001', 'Burutu', 'Obotebe', 'eleme', 'EA-2', 'Sunny John', '12 Bode Sodiya', '08011112222', 0, 3, '2026-07-13 01:07:53', NULL, 'draft', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '::1', NULL, NULL, 'Sunny John', '12', 'Bode Sodiya', 'Razeva School', NULL, NULL, NULL, 'Bungalow', 'Owned', 1, 1, 3),
(2, 'HH-000002', 'Burutu', 'Obotebe', 'umomo', 'EA-2', 'Regina Paul', '2 St. Francis', '09032713457', 0, 3, '2026-07-13 01:14:47', NULL, 'draft', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '::1', NULL, NULL, 'Regina Paul', '2', 'St. Francis', 'Greenland', NULL, NULL, NULL, 'Storey Building', 'Owned', 4, 8, 3),
(3, 'HH-000003', 'Burutu', 'Obotebe', 'eleme', 'EA-2', 'Anlanba Chris', '1 Freddy moore', '08021235678', 0, 3, '2026-07-13 01:47:55', NULL, 'draft', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '::1', NULL, NULL, 'Anlanba Chris', '1', 'Freddy moore', 'MAxland', NULL, NULL, NULL, 'Flat', 'Rented', 2, 8, 3),
(4, 'HH-000004', 'Burutu', 'Obotebe', 'Ejigbo', 'Bucknor', 'Suyyny Johnn', '27 Bode Sodiya', '07037208799', 1, 3, '2026-07-13 03:12:19', '2026-07-13 06:46:36', 'submitted', '2026-07-13 06:46:36', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '::1', NULL, NULL, 'Suyyny Johnn', '27', 'Bode Sodiya', 'Market', NULL, NULL, NULL, 'Duplex', 'Rented', 2, 4, 3),
(5, 'HH-000005', 'Burutu', 'Obotebe', 'Ogombo', 'EA-12', 'Anna Manu', '6 Bruno Lane', '08059876543', 3, 3, '2026-07-13 06:58:05', '2026-07-13 09:58:31', 'verified', '2026-07-13 07:38:45', '2026-07-13 09:58:31', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '::1', NULL, NULL, 'Anna Manu', '6', 'Bruno Lane', 'Shoprite', NULL, NULL, NULL, 'Storey Building', 'Rented', 4, 12, 3);

--
-- Triggers `households`
--
DELIMITER $$
CREATE TRIGGER `audit_household_update` AFTER UPDATE ON `households` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (user_id, username, user_role, action, category, description, ip_address, details)
    SELECT 
        IFNULL(@audit_user_id, 0),
        IFNULL(@audit_username, 'system'),
        IFNULL(@audit_user_role, 'system'),
        'UPDATE',
        'household',
        CONCAT('Household ', OLD.household_code, ' was updated'),
        @audit_ip,
        JSON_OBJECT(
            'old', JSON_OBJECT(
                'status', OLD.status,
                'head', OLD.head_of_household,
                'community', OLD.community
            ),
            'new', JSON_OBJECT(
                'status', NEW.status,
                'head', NEW.head_of_household,
                'community', NEW.community
            )
        );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `household_members`
--

CREATE TABLE `household_members` (
  `id` int(11) NOT NULL,
  `household_id` int(11) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `other_name` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(3) DEFAULT NULL,
  `relationship` varchar(50) NOT NULL,
  `is_head` tinyint(1) NOT NULL DEFAULT 0,
  `marital_status` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) NOT NULL DEFAULT 'Nigerian',
  `state_of_origin` varchar(50) NOT NULL DEFAULT '',
  `lga_of_origin` varchar(50) NOT NULL DEFAULT '',
  `state_of_birth` varchar(50) NOT NULL DEFAULT '',
  `lga_of_birth` varchar(50) NOT NULL DEFAULT '',
  `ethnicity` varchar(50) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `language_spoken` varchar(100) DEFAULT NULL,
  `currently_in_school` enum('Yes','No') NOT NULL DEFAULT 'No',
  `highest_qualification` varchar(50) NOT NULL DEFAULT 'No Formal Education',
  `literacy_read` enum('Yes','No') NOT NULL DEFAULT 'No',
  `literacy_write` enum('Yes','No') NOT NULL DEFAULT 'No',
  `employment_status` varchar(50) NOT NULL DEFAULT 'Unemployed',
  `occupation` varchar(100) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `place_of_work` varchar(200) DEFAULT NULL,
  `disability` enum('Yes','No') NOT NULL DEFAULT 'No',
  `disability_type` varchar(50) DEFAULT NULL,
  `health_insurance` enum('Yes','No') NOT NULL DEFAULT 'No',
  `nin` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `household_members`
--

INSERT INTO `household_members` (`id`, `household_id`, `surname`, `first_name`, `other_name`, `gender`, `date_of_birth`, `age`, `relationship`, `is_head`, `marital_status`, `nationality`, `state_of_origin`, `lga_of_origin`, `state_of_birth`, `lga_of_birth`, `ethnicity`, `religion`, `language_spoken`, `currently_in_school`, `highest_qualification`, `literacy_read`, `literacy_write`, `employment_status`, `occupation`, `industry`, `place_of_work`, `disability`, `disability_type`, `health_insurance`, `nin`, `phone`, `email`, `education_level`, `created_at`, `updated_at`) VALUES
(1, 4, 'Enenya', 'Obinna', '', 'Male', '1993-07-13', 33, 'Head', 1, 'Single', 'Nigerian', 'lagos', 'Lagos Island', 'enugu', 'Nsukka', 'Igbo', 'Christianity', 'Igbo', 'Yes', 'Bachelor\'s Degree', 'Yes', 'Yes', 'Employed', 'Banker', 'Finance', 'UBA', 'No', NULL, 'No', '1234567891', '07037208799', 'enenyaobinna@gmail.com', NULL, '2026-07-13 03:15:44', NULL),
(2, 5, 'Anna', 'Manu', '', 'Female', '1954-02-15', 72, 'Head', 1, 'Widowed', 'Nigerian', 'edo', 'Esan North-East', 'lagos', 'Ikeja', '', 'Christianity', '', 'Yes', 'Master\'s Degree', 'Yes', 'Yes', 'Retired', 'Retired', '', '', 'Yes', 'Visual', 'No', '1932867891', '07037870000', 'annao@me.com', NULL, '2026-07-13 07:02:27', NULL),
(3, 5, 'Bruno', 'Manu', '', 'Male', '1985-08-19', 40, 'Son', 0, 'Married', 'Nigerian', 'edo', 'Esan North-East', 'lagos', 'Ikeja', '', '', '', 'Yes', 'Bachelor\'s Degree', 'Yes', 'Yes', 'Employed', 'Engineer', 'Oil and Gas', 'Snepco', 'No', NULL, 'Yes', '44228678910', '07056218749', 'brunomanu@xyz.com', NULL, '2026-07-13 07:11:43', NULL),
(4, 5, 'Manu', 'Uche', '', 'Female', '1990-08-14', 35, 'Daughter', 0, 'Single', 'Nigerian', 'edo', 'Etsako Central', 'lagos', 'Eti-Osa', '', 'Christianity', '', 'No', 'Master\'s Degree', 'Yes', 'Yes', 'Self-employed', 'Business Owner', 'Information Technology', '', 'No', NULL, 'No', '55229878910', '07038907733', 'uchemanu@xyz.com', NULL, '2026-07-13 07:46:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `user_id`, `ip_address`, `attempt_time`, `success`) VALUES
(1, 1, '::1', '2026-07-12 21:38:20', 1),
(2, 3, '::1', '2026-07-12 23:25:25', 1),
(3, 1, '::1', '2026-07-13 00:18:24', 1),
(4, 3, '::1', '2026-07-13 00:19:27', 1),
(5, 3, '::1', '2026-07-13 00:46:30', 0),
(6, 3, '::1', '2026-07-13 00:46:45', 1),
(7, 1, '::1', '2026-07-13 08:26:51', 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `user_role` enum('admin','enumerator') NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `failure_reason` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `username`, `user_role`, `login_time`, `logout_time`, `ip_address`, `user_agent`, `session_id`, `success`, `failure_reason`) VALUES
(1, 1, 'admin', 'admin', '2026-07-13 12:53:11', '2026-07-13 14:08:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'ecukk1r053c962ov0q1mptii78', 1, NULL),
(2, 1, 'admin', 'admin', '2026-07-13 15:12:00', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'ecukk1r053c962ov0q1mptii78', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `uploads`
--

INSERT INTO `uploads` (`id`, `user_id`, `file_name`, `file_path`, `file_type`, `file_size`, `uploaded_at`) VALUES
(1, 2, 'WIN_20260420_09_48_13_Pro.jpg', 'uploads/passports/passport_2_1783897453.jpg', 'image/jpeg', 115410, '2026-07-12 23:04:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `other_name` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `passport_photo` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','enumerator') DEFAULT 'enumerator',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `employee_id`, `surname`, `first_name`, `other_name`, `gender`, `date_of_birth`, `phone`, `email`, `passport_photo`, `password_hash`, `role`, `status`, `created_at`, `last_login`, `created_by`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'admin', 'ADMIN001', 'Onochie', 'Daniel', 'Oganiru', 'Male', '1990-01-01', '08012345678', 'onochiedaniel99@gmail.com', 'uploads/passports/passport_1_1783945335.jpeg', '$2y$10$ENr8MqKMpl2nEEVUTZ3zRuGKAbGd3pN6kC9YBCvz5CdIhZJkF.lUq', 'admin', 'active', '2026-07-12 21:35:08', '2026-07-13 15:12:00', NULL, NULL, NULL),
(2, 'obinna.enenya', 'DEL-00001', 'Enenya', 'Obinna', '', 'Male', '1982-03-12', '07037204562', 'enenyaobinna@gmail.com', 'uploads/passports/passport_2_1783934390.png', '$2y$10$2vLYxEj4DtAD.DnDHYX93ebzs/ZjRqAcSG4dhkU4vBGqK5NnMfrPS', 'enumerator', 'active', '2026-07-12 23:04:13', NULL, 1, NULL, NULL),
(3, 'ihgbdna.enerry', 'DEL-00002', 'Enerry', 'Ihgbdna', '', 'Male', '1996-07-13', '070373273711', 'nice@me.com', 'uploads/passports/passport_3_1783898669.jpg', '$2y$10$7bMZaiaA1u5p8Ow9o1N53eEVRofB8gwYLc3XbjuUE/Zgfbewk9mxq', 'enumerator', 'active', '2026-07-12 23:24:29', '2026-07-13 00:46:45', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_locations`
--

CREATE TABLE `user_locations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lga` varchar(100) NOT NULL,
  `ward` varchar(100) NOT NULL,
  `community` varchar(100) DEFAULT NULL,
  `enumeration_area` varchar(50) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_locations`
--

INSERT INTO `user_locations` (`id`, `user_id`, `lga`, `ward`, `community`, `enumeration_area`, `assigned_at`, `assigned_by`) VALUES
(1, 1, 'Delta North', 'Ward 1', 'Community A', 'EA001', '2026-07-12 21:35:08', 1),
(2, 2, 'Burutu', 'Ngbilebiri I', '', '', '2026-07-12 23:04:13', 1),
(3, 3, 'Burutu', 'Obotebe', '', '', '2026-07-12 23:24:29', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `household_code` (`household_code`),
  ADD UNIQUE KEY `household_number` (`household_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_lga_ward` (`lga`,`ward`),
  ADD KEY `idx_enumeration_area` (`enumeration_area`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_household_number` (`household_number`),
  ADD KEY `idx_lga` (`lga`),
  ADD KEY `idx_ward` (`ward`),
  ADD KEY `idx_enumerator` (`enumerator_id`);

--
-- Indexes for table `household_members`
--
ALTER TABLE `household_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_household` (`household_id`),
  ADD KEY `idx_is_head` (`is_head`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_time` (`login_time`),
  ADD KEY `idx_success` (`success`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `user_locations`
--
ALTER TABLE `user_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_location` (`user_id`,`lga`,`ward`,`community`,`enumeration_area`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_lga_ward` (`lga`,`ward`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `household_members`
--
ALTER TABLE `household_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_locations`
--
ALTER TABLE `user_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `households_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `household_members`
--
ALTER TABLE `household_members`
  ADD CONSTRAINT `household_members_ibfk_1` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `uploads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_locations`
--
ALTER TABLE `user_locations`
  ADD CONSTRAINT `user_locations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_locations_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
