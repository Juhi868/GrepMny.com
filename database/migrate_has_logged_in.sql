-- Migration: Add has_logged_in column to login table
-- Run this on existing databases to enable first-login front page display
ALTER TABLE `login`
  ADD COLUMN `has_logged_in` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`;

-- Mark all existing users as having logged in already,
-- so only brand-new users will see the front page on first login.
UPDATE `login` SET `has_logged_in` = 1;
