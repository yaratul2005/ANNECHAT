-- Migration: Add post_reactions table for post stars/reactions
-- Date: 2024-12-19

CREATE TABLE IF NOT EXISTS `post_reactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction_type` ENUM('star', 'like', 'love') DEFAULT 'star',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_post_user_reaction` (`post_id`, `user_id`, `reaction_type`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_reaction_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

