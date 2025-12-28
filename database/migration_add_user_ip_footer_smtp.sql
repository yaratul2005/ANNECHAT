-- Migration: Add user IP tracking, footer settings, and SMTP settings
-- Created: 2024-12-19

USE `anne_chat`;

-- Add IP address column to users table (for tracking last known IP)
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `last_ip_address` VARCHAR(45) DEFAULT NULL AFTER `updated_at`,
ADD INDEX IF NOT EXISTS `idx_last_ip` (`last_ip_address`);

-- Add footer settings to site_settings table
ALTER TABLE `site_settings`
ADD COLUMN IF NOT EXISTS `footer_text` TEXT DEFAULT NULL AFTER `favicon_url`,
ADD COLUMN IF NOT EXISTS `footer_enabled` BOOLEAN DEFAULT TRUE AFTER `footer_text`,
ADD COLUMN IF NOT EXISTS `footer_copyright` VARCHAR(255) DEFAULT NULL AFTER `footer_enabled`;

-- Create SMTP settings table
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `host` VARCHAR(255) NOT NULL,
  `port` INT UNSIGNED NOT NULL DEFAULT 587,
  `encryption` ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
  `username` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `from_email` VARCHAR(255) NOT NULL,
  `from_name` VARCHAR(255) DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT FALSE,
  `test_status` ENUM('pending', 'success', 'failed') DEFAULT 'pending',
  `test_message` TEXT DEFAULT NULL,
  `tested_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email logs table (for tracking sent emails)
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_email` VARCHAR(255) NOT NULL,
  `recipient_name` VARCHAR(255) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `error_message` TEXT DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_email`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

