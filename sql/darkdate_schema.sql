-- DarkDate Phase 2: Database Schema
-- All tables use 'darkdate_' prefix to avoid conflicts
-- Compatible with ch709119_d2t2 database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Users table
CREATE TABLE IF NOT EXISTS `darkdate_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100),
  `fear_level` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_active` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_blocked` TINYINT(1) DEFAULT 0,
  `block_until` TIMESTAMP NULL,
  INDEX `idx_username` (`username`),
  INDEX `idx_fear_level` (`fear_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table for API authentication
CREATE TABLE IF NOT EXISTS `darkdate_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_token` (`token`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table for chat system
CREATE TABLE IF NOT EXISTS `darkdate_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `sender_type` ENUM('user', 'bot', 'system') NOT NULL DEFAULT 'user',
  `sender_id` VARCHAR(50),
  `message` TEXT NOT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_read` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_timestamp` (`user_id`, `timestamp`),
  INDEX `idx_sender_type` (`sender_type`),
  INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Story progress table
CREATE TABLE IF NOT EXISTS `darkdate_story_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `chapter` INT NOT NULL DEFAULT 1,
  `scene` INT NOT NULL DEFAULT 0,
  `choices_made` JSON,
  `completed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_chapter` (`user_id`, `chapter`),
  INDEX `idx_chapter` (`chapter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS `darkdate_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('story', 'chat', 'system', 'horror') DEFAULT 'system',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL,
  `priority` INT DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_read` (`user_id`, `is_read`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fear level log table
CREATE TABLE IF NOT EXISTS `darkdate_fear_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `fear_level` INT NOT NULL,
  `fear_change` INT NOT NULL,
  `reason` VARCHAR(255),
  `event_type` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_time` (`user_id`, `created_at`),
  INDEX `idx_fear_level` (`fear_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Achievements table
CREATE TABLE IF NOT EXISTS `darkdate_achievements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `achievement_key` VARCHAR(100) NOT NULL,
  `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `progress` INT DEFAULT 0,
  `is_completed` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_achievement` (`user_id`, `achievement_key`),
  INDEX `idx_is_completed` (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spam logs table
CREATE TABLE IF NOT EXISTS `darkdate_spam_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `spam_count` INT DEFAULT 0,
  `last_spam_time` TIMESTAMP NULL,
  `blocked_until` TIMESTAMP NULL,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `darkdate_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_blocked` (`user_id`, `blocked_until`),
  INDEX `idx_last_spam` (`last_spam_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bots configuration table
CREATE TABLE IF NOT EXISTS `darkdate_bots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bot_key` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `avatar_url` VARCHAR(255),
  `personality_type` ENUM('friendly', 'mysterious', 'aggressive', 'glitch', 'spammer') NOT NULL DEFAULT 'friendly',
  `message_frequency` INT DEFAULT 5, -- Messages per hour
  `active_hours_start` INT DEFAULT 0, -- Hour (0-23)
  `active_hours_end` INT DEFAULT 23, -- Hour (0-23)
  `response_delay_min` INT DEFAULT 2, -- Minimum delay in seconds
  `response_delay_max` INT DEFAULT 10, -- Maximum delay in seconds
  `fear_trigger_threshold` INT DEFAULT 50, -- Trigger special behavior at this fear level
  `is_active` TINYINT(1) DEFAULT 1,
  `script_data` JSON, -- Custom dialogue scripts and behaviors
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_personality` (`personality_type`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
