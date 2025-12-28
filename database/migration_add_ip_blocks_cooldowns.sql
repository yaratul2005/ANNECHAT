-- Migration: Add IP blocks and cooldowns tables
-- Created: 2024-12-19

USE `anne_chat`;

-- Table: ip_blocks (IP blocking system)
CREATE TABLE IF NOT EXISTS `ip_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `blocked_by` INT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_permanent` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ip_address` (`ip_address`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_ip_block_blocked_by` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cooldowns (Rate limiting / cooldown system)
CREATE TABLE IF NOT EXISTS `cooldowns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `action_identifier` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempt_count` INT UNSIGNED DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_action_identifier` (`action_identifier`),
  KEY `idx_expires_at` (`expires_at`),
  UNIQUE KEY `idx_unique_cooldown` (`user_id`, `ip_address`, `action_type`, `action_identifier`),
  CONSTRAINT `fk_cooldown_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for reports table status field (if not exists)
-- Note: MySQL doesn't support IF NOT EXISTS for indexes, so check if it exists first
-- Or simply run: ALTER TABLE `reports` ADD INDEX `idx_status_created` (`status`, `created_at`);

