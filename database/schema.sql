-- Anne Chat Application Database Schema
-- Version: 1.0
-- Created: 2024-12-19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Create Database
CREATE DATABASE IF NOT EXISTS `anne_chat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `anne_chat`;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `age` INT UNSIGNED DEFAULT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `is_verified` BOOLEAN DEFAULT FALSE,
  `is_admin` BOOLEAN DEFAULT FALSE,
  `is_guest` BOOLEAN DEFAULT FALSE,
  `verification_token` VARCHAR(64) DEFAULT NULL,
  `verification_token_expires` DATETIME DEFAULT NULL,
  `password_reset_token` VARCHAR(64) DEFAULT NULL,
  `password_reset_expires` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  UNIQUE KEY `idx_email` (`email`),
  UNIQUE KEY `idx_verification_token` (`verification_token`),
  KEY `idx_is_verified` (`is_verified`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: messages
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `message_text` LONGTEXT DEFAULT NULL,
  `attachment_type` ENUM('image', 'video', 'file', 'none') DEFAULT 'none',
  `attachment_url` VARCHAR(255) DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_size` INT UNSIGNED DEFAULT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_conversation` (`sender_id`, `recipient_id`, `created_at`),
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: online_status
CREATE TABLE IF NOT EXISTS `online_status` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('online', 'away', 'offline') DEFAULT 'offline',
  `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_seen` (`last_seen`),
  CONSTRAINT `fk_online_status_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: site_settings
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_name` VARCHAR(100) DEFAULT 'Anne Chat',
  `site_description` TEXT DEFAULT NULL,
  `meta_title` VARCHAR(60) DEFAULT NULL,
  `meta_description` VARCHAR(160) DEFAULT NULL,
  `meta_keywords` VARCHAR(255) DEFAULT NULL,
  `custom_head_tags` TEXT DEFAULT NULL,
  `custom_css` TEXT DEFAULT NULL,
  `primary_color` VARCHAR(7) DEFAULT '#1a73e8',
  `secondary_color` VARCHAR(7) DEFAULT '#e91e8c',
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `favicon_url` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(128) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `data` TEXT DEFAULT NULL,
  `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default site settings
INSERT INTO `site_settings` (`site_name`, `site_description`, `meta_title`, `meta_description`) 
VALUES ('Anne Chat', 'Real-time chat application', 'Anne Chat - Real-Time Messaging', 'Connect with friends in real-time using Anne Chat');

-- Create default admin user (password: admin123 - CHANGE THIS!)
-- Password hash for 'admin123' with cost 12
INSERT INTO `users` (`username`, `email`, `password_hash`, `age`, `is_verified`, `is_admin`, `is_guest`) 
VALUES ('admin', 'admin@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyYqJZ5Q5Q5m', 25, TRUE, TRUE, FALSE);

