-- Migration: Add is_banned field to users table
-- Date: 2024-12-19

ALTER TABLE `users` 
ADD COLUMN `is_banned` BOOLEAN DEFAULT FALSE AFTER `is_guest`,
ADD KEY `idx_is_banned` (`is_banned`);

