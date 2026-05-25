-- ============================================================
-- Splennet — Brand Wallet System Migration
-- Run once in phpMyAdmin on Hostinger
-- Skip any line that reports "Duplicate column/table"
-- ============================================================

-- 1. Brand Wallets table
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

-- 2. Wallet Transactions table
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `wallet_id`        INT NOT NULL,
    `brand_id`         INT NOT NULL,
    `admin_id`         INT NULL,
    `transaction_type` ENUM(
        'admin_credit',
        'admin_debit',
        'campaign_reserve',
        'contest_reserve',
        'ugc_order_reserve',
        'creator_payout',
        'refund_unused_budget',
        'manual_adjustment'
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

-- 3. Add creator_count to ugc_orders (for budget calculation)
ALTER TABLE `ugc_orders`
    ADD COLUMN IF NOT EXISTS `creator_count` INT NOT NULL DEFAULT 1 AFTER `budget_per_creator`;

-- 4. Auto-create wallets for all existing brands (default GHS, zero balance)
INSERT IGNORE INTO `brand_wallets` (`brand_id`, `currency`)
SELECT `id`, 'GHS' FROM `brands`;

-- Done!
-- After running this, set wallet currencies per brand in admin → Brand Wallets
-- then credit each brand wallet before they can publish campaigns.
