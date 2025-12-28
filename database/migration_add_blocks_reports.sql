-- Migration: Add blocks and reports tables
-- Created: 2024-12-19

USE `anne_chat`;

-- Table: blocks (user blocking system)
CREATE TABLE IF NOT EXISTS `blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_id` INT UNSIGNED NOT NULL,
  `blocked_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_blocker_blocked` (`blocker_id`, `blocked_id`),
  KEY `idx_blocker` (`blocker_id`),
  KEY `idx_blocked` (`blocked_id`),
  CONSTRAINT `fk_block_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: reports (user reporting system)
CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT UNSIGNED NOT NULL,
  `reported_id` INT UNSIGNED NOT NULL,
  `reason` ENUM('spam', 'harassment', 'inappropriate', 'fake_account', 'other') DEFAULT 'other',
  `description` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reporter` (`reporter_id`),
  KEY `idx_reported` (`reported_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_report_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_reported` FOREIGN KEY (`reported_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

