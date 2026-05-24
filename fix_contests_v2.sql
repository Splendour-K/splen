-- ============================================================
-- Splennet — Contest System V2 Migration
-- Run in phpMyAdmin on Hostinger (skip any line that already succeeded)
-- ============================================================

-- 1. Add title + description to contest_submissions
ALTER TABLE `contest_submissions` ADD COLUMN `title` VARCHAR(255) NULL AFTER `creator_id`;
ALTER TABLE `contest_submissions` ADD COLUMN `description` TEXT NULL AFTER `title`;

-- 2. Add flag_reason + flagged_at for admin moderation
ALTER TABLE `contest_submissions` ADD COLUMN `flag_reason` TEXT NULL AFTER `status`;
ALTER TABLE `contest_submissions` ADD COLUMN `flagged_at` TIMESTAMP NULL AFTER `flag_reason`;

-- 3. Add CPM columns to contests (optional pay-per-view system)
ALTER TABLE `contests` ADD COLUMN `cpm_budget` DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE `contests` ADD COLUMN `pay_per_1000_views` DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE `contests` ADD COLUMN `max_payable_views_per_creator` INT NULL DEFAULT NULL;

-- 4. If you haven't run fix_contests_db.sql yet, also run:
-- ALTER TABLE `contests` ADD COLUMN `description` TEXT NULL;
-- ALTER TABLE `contests` ADD COLUMN `number_of_winners` INT DEFAULT 1;
-- ALTER TABLE `contests` ADD COLUMN `terms_conditions` TEXT NULL;

-- Done!
