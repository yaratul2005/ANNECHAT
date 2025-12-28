-- Friend requests table
CREATE TABLE IF NOT EXISTS `friend_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'accepted', 'rejected', 'blocked') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sender_receiver` (`sender_id`, `receiver_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_friend_request_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friend_request_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

