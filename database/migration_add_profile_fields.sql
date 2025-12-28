-- Migration: Add fullname and gender fields to users table
-- Date: 2024-12-19

ALTER TABLE `users` 
ADD COLUMN `fullname` VARCHAR(100) DEFAULT NULL AFTER `username`,
ADD COLUMN `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') DEFAULT NULL AFTER `age`;

