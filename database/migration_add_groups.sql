-- Groups table
CREATE TABLE IF NOT EXISTS `groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_group_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group members table
CREATE TABLE IF NOT EXISTS `group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('admin', 'member') DEFAULT 'member',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_group_user` (`group_id`, `user_id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_group_member_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group messages table (extend messages table or create separate)
CREATE TABLE IF NOT EXISTS `group_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `message_text` TEXT DEFAULT NULL,
  `attachment_type` ENUM('none', 'image', 'video', 'file') DEFAULT 'none',
  `attachment_url` VARCHAR(255) DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_size` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_group_message_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

