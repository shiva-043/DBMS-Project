-- ========================================================
-- Railway Reservation Management System
-- Database Schema & Sample Records
-- ========================================================

CREATE DATABASE IF NOT EXISTS `railway_reservation`;
USE `railway_reservation`;

-- --------------------------------------------------------
-- 1. Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(15) DEFAULT NULL,
    `role` ENUM('USER', 'ADMIN') NOT NULL DEFAULT 'USER'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Table structure for table `stations`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stations` (
    `station_id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_name` VARCHAR(100) NOT NULL,
    `station_code` VARCHAR(10) NOT NULL UNIQUE,
    `city` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Table structure for table `trains`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trains` (
    `train_id` INT AUTO_INCREMENT PRIMARY KEY,
    `train_number` VARCHAR(10) NOT NULL UNIQUE,
    `train_name` VARCHAR(100) NOT NULL,
    `source_station_id` INT NOT NULL,
    `destination_station_id` INT NOT NULL,
    `departure_time` TIME NOT NULL,
    `arrival_time` TIME NOT NULL,
    `total_seats` INT NOT NULL,
    `fare` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `chk_total_seats` CHECK (`total_seats` > 0),
    CONSTRAINT `chk_fare` CHECK (`fare` >= 0),
    CONSTRAINT `chk_stations_different` CHECK (`source_station_id` <> `destination_station_id`),
    CONSTRAINT `fk_trains_source` FOREIGN KEY (`source_station_id`) REFERENCES `stations` (`station_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_trains_dest` FOREIGN KEY (`destination_station_id`) REFERENCES `stations` (`station_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Table structure for table `bookings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
    `booking_id` INT AUTO_INCREMENT PRIMARY KEY,
    `pnr` VARCHAR(15) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `train_id` INT NOT NULL,
    `journey_date` DATE NOT NULL,
    `booking_date` DATETIME NOT NULL,
    `fare_per_passenger` DECIMAL(10,2) NOT NULL,
    `booking_status` ENUM('CONFIRMED', 'CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
    CONSTRAINT `chk_fare_per_passenger` CHECK (`fare_per_passenger` >= 0),
    CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_bookings_train` FOREIGN KEY (`train_id`) REFERENCES `trains` (`train_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table structure for table `passengers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `passengers` (
    `passenger_id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `passenger_name` VARCHAR(100) NOT NULL,
    `age` INT NOT NULL,
    `gender` ENUM('MALE', 'FEMALE', 'OTHER') NOT NULL,
    CONSTRAINT `chk_passenger_age` CHECK (`age` >= 0),
    CONSTRAINT `fk_passengers_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Indexes for query optimization
-- --------------------------------------------------------
CREATE INDEX `idx_trains_route` ON `trains` (`source_station_id`, `destination_station_id`);
CREATE INDEX `idx_bookings_train_date_status` ON `bookings` (`train_id`, `journey_date`, `booking_status`);
CREATE INDEX `idx_bookings_user` ON `bookings` (`user_id`);
CREATE INDEX `idx_passengers_booking` ON `passengers` (`booking_id`);

-- --------------------------------------------------------
-- Sample Data: Stations
-- --------------------------------------------------------
INSERT INTO `stations` (`station_id`, `station_name`, `station_code`, `city`) VALUES
(1, 'New Delhi Railway Station', 'NDLS', 'New Delhi'),
(2, 'Mumbai Central', 'MMCT', 'Mumbai'),
(3, 'Howrah Junction', 'HWH', 'Kolkata'),
(4, 'Chennai Central', 'MAS', 'Chennai'),
(5, 'KSR Bengaluru City', 'SBC', 'Bengaluru')
ON DUPLICATE KEY UPDATE `station_code` = VALUES(`station_code`);

-- --------------------------------------------------------
-- Sample Data: Trains
-- --------------------------------------------------------
INSERT INTO `trains` (`train_id`, `train_number`, `train_name`, `source_station_id`, `destination_station_id`, `departure_time`, `arrival_time`, `total_seats`, `fare`) VALUES
(1, '12952', 'Mumbai Rajdhani Express', 2, 1, '17:00:00', '08:35:00', 60, 1850.00),
(2, '12951', 'New Delhi Rajdhani Express', 1, 2, '16:55:00', '08:30:00', 60, 1850.00),
(3, '12841', 'Coromandel Express', 3, 4, '15:20:00', '17:00:00', 72, 820.00),
(4, '12842', 'Chennai Mail', 4, 3, '19:00:00', '23:45:00', 72, 820.00),
(5, '12658', 'Bengaluru Express', 4, 5, '23:20:00', '04:15:00', 80, 450.00)
ON DUPLICATE KEY UPDATE `train_number` = VALUES(`train_number`);

-- --------------------------------------------------------
-- Sample Data: Default Admin and Test User
-- Default credentials:
-- Admin: admin@railway.com / admin123
-- User:  user@railway.com  / user123
-- --------------------------------------------------------
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `role`) VALUES
(1, 'System Administrator', 'admin@railway.com', 'admin123', '9876543210', 'ADMIN'),
(2, 'Test Student User', 'user@railway.com', 'user123', '9123456780', 'USER')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);
