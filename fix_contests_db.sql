-- ============================================================
-- Add missing columns to contests table
-- Run in phpMyAdmin → u922239638_splennet → Import
-- ============================================================

ALTER TABLE contests ADD COLUMN description TEXT;
ALTER TABLE contests ADD COLUMN number_of_winners INT DEFAULT 1;
ALTER TABLE contests ADD COLUMN terms_conditions TEXT;
