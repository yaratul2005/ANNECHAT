-- Migration: Add Stories Feature
-- Created: 2024-12-21

-- Table: stories
CREATE TABLE IF NOT EXISTS `stories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `media_type` ENUM('image', 'video') NOT NULL,
  `media_url` VARCHAR(255) NOT NULL,
  `text` TEXT DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_story_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: story_reactions
CREATE TABLE IF NOT EXISTS `story_reactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `story_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction_type` ENUM('star', 'reply') NOT NULL,
  `content` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_story_id` (`story_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_reaction_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

