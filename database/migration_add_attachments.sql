-- Migration: Add attachment support to messages table
-- Run this if messages table already exists

ALTER TABLE `messages` 
ADD COLUMN `attachment_type` ENUM('image', 'video', 'file', 'none') DEFAULT 'none' AFTER `message_text`,
ADD COLUMN `attachment_url` VARCHAR(255) DEFAULT NULL AFTER `attachment_type`,
ADD COLUMN `attachment_name` VARCHAR(255) DEFAULT NULL AFTER `attachment_url`,
ADD COLUMN `attachment_size` INT UNSIGNED DEFAULT NULL AFTER `attachment_name`;

