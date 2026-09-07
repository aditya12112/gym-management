-- =======================================================
-- GymPro — Database Schema & Initial Seed Data
-- Turnkey installation file for MySQL / MariaDB
-- =======================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `gym_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gym_db`;

-- --------------------------------------------------------
-- Table: system_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('gym_name', 'GymPro'),
('gym_tagline', 'Smart Gym Management System'),
('gym_currency', '₹'),
('gym_email', 'admin@gympro.com'),
('gym_phone', '+91 98765 43210'),
('cron_secret', 'gymcron2024'),
('mail_host', 'smtp.gmail.com'),
('mail_port', '587'),
('mail_user', ''),
('mail_pass', ''),
('mail_from', ''),
('mail_from_name', 'GymPro')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','trainer','member') NOT NULL DEFAULT 'member',
  `age` int(11) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `membership_status` enum('active','inactive','expired','pending') DEFAULT 'inactive',
  `membership_start` date DEFAULT NULL,
  `membership_end` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Seed Accounts
-- Default Admin: admin@gympro.com / admin123
-- Default Trainer: trainer@gympro.com / trainer123
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `age`, `gender`, `phone`, `membership_status`, `created_at`) VALUES
(1, 'Super Admin', 'admin@gympro.com', '$2y$10$vPlhFxdUxRhvgo3wRJNDfOgetn3uXYLI73OPtdDXnTel7yDge/imW', 'admin', 30, 'Male', '+91 98765 43210', 'active', NOW()),
(2, 'Demo Trainer', 'trainer@gympro.com', '$2y$10$N8aY2rZG8MNqivb0fx39quaBNCbC73iq..KUxyUUcnHiJI2QHJH3.', 'trainer', 28, 'Male', '+91 98765 43211', 'active', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: trainers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trainers` (
  `id` int(11) NOT NULL,
  `speciality` varchar(150) DEFAULT 'General Fitness',
  `experience_years` int(11) DEFAULT 3,
  `bio` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_trainers_user` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `trainers` (`id`, `speciality`, `experience_years`, `bio`) VALUES
(2, 'Strength & Conditioning', 5, 'Certified strength specialist passionate about functional movement and bodybuilding.')
ON DUPLICATE KEY UPDATE `speciality` = VALUES(`speciality`);

-- --------------------------------------------------------
-- Table: time_slots
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `time_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `day_of_week` varchar(15) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 20,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slots_trainer` (`trainer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Time Slots
INSERT INTO `time_slots` (`id`, `trainer_id`, `day_of_week`, `start_time`, `end_time`, `capacity`) VALUES
(1, 2, 'Mon', '06:00:00', '07:30:00', 20),
(2, 2, 'Mon', '18:00:00', '19:30:00', 20),
(3, 2, 'Tue', '06:00:00', '07:30:00', 20),
(4, 2, 'Wed', '06:00:00', '07:30:00', 20),
(5, 2, 'Thu', '06:00:00', '07:30:00', 20),
(6, 2, 'Fri', '06:00:00', '07:30:00', 20),
(7, 2, 'Sat', '07:00:00', '09:00:00', 20)
ON DUPLICATE KEY UPDATE `capacity` = VALUES(`capacity`);

-- --------------------------------------------------------
-- Table: bookings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `status` enum('booked','cancelled','completed') NOT NULL DEFAULT 'booked',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bookings_user` (`user_id`),
  KEY `idx_bookings_slot` (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: payments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) NOT NULL DEFAULT 'Cash',
  `txn_ref` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','failed','rejected') NOT NULL DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: complaints
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_complaints_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: exercises
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exercises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `body_part` varchar(100) DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Standard Exercises
INSERT INTO `exercises` (`id`, `name`, `body_part`, `difficulty`, `description`) VALUES
(1, 'Push-up', 'Chest', 'Easy', '3 sets x 10-15 reps. Hands shoulder-width, body straight. Control descent.'),
(2, 'Squat', 'Legs', 'Easy', '3 sets x 12-15 reps. Sit back into heels, knees track toes.'),
(3, 'Plank', 'Core', 'Easy', 'Hold 30-60 seconds. Core tight, hips level, no lower back arch.'),
(4, 'Lunges', 'Legs', 'Medium', '3 sets x 10 reps per leg. Step forward and lower under control.'),
(5, 'Bench Press', 'Chest', 'Medium', '4 sets x 8-10 reps. Full ROM, controlled descent, do not bounce.'),
(6, 'Deadlift', 'Back', 'Hard', '4 sets x 5 reps. Neutral spine, keep bar close to body throughout.'),
(7, 'Pull-up', 'Back', 'Hard', '3 sets to failure. Full hang at bottom, chin clears bar at top.'),
(8, 'Shoulder Press', 'Shoulders', 'Medium', '3 sets x 8-12 reps. Press straight up, do not arch lower back.'),
(9, 'Bicycle Crunch', 'Core', 'Easy', '3 sets x 20 reps (10 each side). Slow and controlled movement.'),
(10, 'Calf Raise', 'Legs', 'Easy', '3 sets x 15-20 reps. Pause 1 second at top of each rep.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: diet_plans
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `diet_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `diet_text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_diet_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: trainer_leaves
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trainer_leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `leave_start` date DEFAULT NULL,
  `leave_end` date DEFAULT NULL,
  `leave_date` date DEFAULT NULL,
  `total_days` int(11) NOT NULL DEFAULT 1,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leaves_trainer` (`trainer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_plan_assignments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_plan_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `plan_type` enum('diet','exercise') NOT NULL,
  `plan_id` int(11) NOT NULL DEFAULT 0,
  `plan_text` text DEFAULT NULL,
  `custom_notes` text DEFAULT NULL,
  `status` enum('assigned','approved','completed','cancelled','rejected') NOT NULL DEFAULT 'assigned',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assign_user` (`user_id`),
  KEY `idx_assign_trainer` (`trainer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_saved_exercises
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_saved_exercises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exercise_id` int(11) NOT NULL,
  `saved_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_saved_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: bmi_records
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bmi_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `height_cm` decimal(5,2) NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `bmi` decimal(5,2) NOT NULL,
  `category` varchar(30) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bmi_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
