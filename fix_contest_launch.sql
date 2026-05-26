-- ============================================================
-- Splennet — Contest Launch Fix Migration
-- Run this in phpMyAdmin → u922239638_splennet → Import
-- Safe to run on existing databases — uses IF NOT EXISTS / IF EXISTS guards.
-- Fixes "Unknown column" errors that block contest creation and editing.
-- ============================================================

-- 1. Add missing core columns to `contests` table
--    (from fix_contests_db.sql — safe to re-run, MySQL ignores duplicate columns)
ALTER TABLE `contests`
    ADD COLUMN IF NOT EXISTS `description` TEXT NULL AFTER `title`,
    ADD COLUMN IF NOT EXISTS `number_of_winners` INT NOT NULL DEFAULT 1 AFTER `total_contest_budget`,
    ADD COLUMN IF NOT EXISTS `terms_conditions` TEXT NULL AFTER `number_of_winners`;

-- 2. Add featured_image to contests, campaigns, ugc_orders
--    (from fix_featured_images.sql)
ALTER TABLE `contests`
    ADD COLUMN IF NOT EXISTS `featured_image` VARCHAR(255) NULL AFTER `terms_conditions`;
ALTER TABLE `campaigns`
    ADD COLUMN IF NOT EXISTS `featured_image` VARCHAR(255) NULL;
ALTER TABLE `ugc_orders`
    ADD COLUMN IF NOT EXISTS `featured_image` VARCHAR(255) NULL;

-- 3. Add CPM columns to `contests`
--    (from fix_contests_v2.sql — safe to re-run)
ALTER TABLE `contests`
    ADD COLUMN IF NOT EXISTS `cpm_budget` DECIMAL(10,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `pay_per_1000_views` DECIMAL(10,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `max_payable_views_per_creator` INT NULL DEFAULT NULL;

-- 4. Add moderation + winner columns to `contest_submissions`
--    (from fix_contests_v2.sql and fix_contests_v3.sql)
ALTER TABLE `contest_submissions`
    ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) NULL AFTER `creator_id`,
    ADD COLUMN IF NOT EXISTS `description` TEXT NULL AFTER `title`,
    ADD COLUMN IF NOT EXISTS `flag_reason` TEXT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `flagged_at` TIMESTAMP NULL AFTER `flag_reason`,
    ADD COLUMN IF NOT EXISTS `winner_position` INT NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `payment_released` TINYINT(1) NOT NULL DEFAULT 0;

-- 5. Add creator_count to ugc_orders (needed for admin stats)
ALTER TABLE `ugc_orders`
    ADD COLUMN IF NOT EXISTS `creator_count` INT NOT NULL DEFAULT 1 AFTER `budget_per_creator`;

-- 6. Brand Wallets (creates only if not yet migrated)
CREATE TABLE IF NOT EXISTS `brand_wallets` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `brand_id`          INT NOT NULL,
    `currency`          VARCHAR(10)  NOT NULL DEFAULT 'GHS',
    `available_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `reserved_balance`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_spent`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status`            ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_brand_wallet` (`brand_id`),
    FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `wallet_id`        INT NOT NULL,
    `brand_id`         INT NOT NULL,
    `admin_id`         INT NULL,
    `transaction_type` ENUM(
        'admin_credit','admin_debit','campaign_reserve','contest_reserve',
        'ugc_order_reserve','creator_payout','refund_unused_budget','manual_adjustment'
    ) NOT NULL,
    `amount`           DECIMAL(15,2) NOT NULL,
    `currency`         VARCHAR(10)  NOT NULL DEFAULT 'GHS',
    `balance_before`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `balance_after`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `reserved_before`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `reserved_after`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `description`      TEXT NULL,
    `reference_type`   VARCHAR(50) NULL,
    `reference_id`     INT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`wallet_id`) REFERENCES `brand_wallets`(`id`) ON DELETE CASCADE,
    KEY `idx_wallet_type` (`wallet_id`, `transaction_type`),
    KEY `idx_brand_created` (`brand_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Auto-create wallet rows for all existing brands (zero balance, GHS default)
INSERT IGNORE INTO `brand_wallets` (`brand_id`, `currency`)
SELECT `id`, 'GHS' FROM `brands`;

-- Done!
-- After running this migration:
-- • Brands can create contests, campaigns, and UGC orders immediately
-- • Admin can credit brand wallets from admin → Brand Wallets to enable wallet reservations
-- • All existing content continues to work without disruption
