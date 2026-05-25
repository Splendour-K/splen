-- ============================================================
-- Splennet — Contest System V3 Migration
-- Run in phpMyAdmin on Hostinger after fix_contests_v2.sql
-- Skip any ALTER that reports "Duplicate column name"
-- ============================================================

-- 1. winner_position: which prize position a submission won (1 = 1st, 2 = 2nd, etc.)
ALTER TABLE `contest_submissions`
    ADD COLUMN `winner_position` INT NULL DEFAULT NULL AFTER `status`;

-- 2. payment_released: admin marks 1 after releasing the prize payment
ALTER TABLE `contest_submissions`
    ADD COLUMN `payment_released` TINYINT(1) NOT NULL DEFAULT 0 AFTER `approved_views`;

-- Done!
-- After running this, admins can select winners and release payments
-- from the Contest Submissions page in the admin panel.
