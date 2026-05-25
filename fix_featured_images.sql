-- ============================================================
-- Splennet: Add featured_image to campaigns, contests, ugc_orders
-- Run once in phpMyAdmin on Hostinger.
-- MySQL 8.0+ : "IF NOT EXISTS" is supported — safe to re-run.
-- MySQL 5.7   : If you see "Duplicate column name" errors, the
--               column already exists — ignore and continue.
-- ============================================================

ALTER TABLE campaigns  ADD COLUMN IF NOT EXISTS featured_image VARCHAR(255) DEFAULT NULL;
ALTER TABLE contests   ADD COLUMN IF NOT EXISTS featured_image VARCHAR(255) DEFAULT NULL;
ALTER TABLE ugc_orders ADD COLUMN IF NOT EXISTS featured_image VARCHAR(255) DEFAULT NULL;
