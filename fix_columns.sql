-- ============================================================
-- Splennet: Add Missing Columns to Existing Tables
-- Run this in phpMyAdmin → u922239638_splennet → Import
-- Tables already exist; this only ADDS the missing columns.
-- ============================================================

-- CRITICAL: These two cause the "Service Error" on every dashboard page
ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended', 'pending') DEFAULT 'active';
ALTER TABLE brands ADD COLUMN subscription_tier ENUM('basic', 'pro', 'enterprise') DEFAULT 'basic';

-- Creators: extra profile fields
ALTER TABLE creators ADD COLUMN sample_video_link VARCHAR(255);
ALTER TABLE creators ADD COLUMN target_languages VARCHAR(255);
ALTER TABLE creators ADD COLUMN equipment_list TEXT;
ALTER TABLE creators ADD COLUMN availability ENUM('available_now', 'next_week', 'busy', 'away') DEFAULT 'available_now';
ALTER TABLE creators ADD COLUMN bank_name VARCHAR(100);
ALTER TABLE creators ADD COLUMN account_name VARCHAR(255);
ALTER TABLE creators ADD COLUMN account_number VARCHAR(100);
ALTER TABLE creators ADD COLUMN momo_number VARCHAR(100);

-- Campaigns: extra fields from migrations
ALTER TABLE campaigns ADD COLUMN short_description VARCHAR(255);
ALTER TABLE campaigns ADD COLUMN full_description TEXT;
ALTER TABLE campaigns ADD COLUMN external_brief_link VARCHAR(255);
ALTER TABLE campaigns ADD COLUMN target_platform VARCHAR(100);
ALTER TABLE campaigns ADD COLUMN final_view_count_deadline DATE;
ALTER TABLE campaigns ADD COLUMN required_hashtags TEXT;
ALTER TABLE campaigns ADD COLUMN required_caption TEXT;
ALTER TABLE campaigns ADD COLUMN required_tags TEXT;
ALTER TABLE campaigns ADD COLUMN tracking_instructions TEXT;
ALTER TABLE campaigns ADD COLUMN max_campaign_budget DECIMAL(10,2) DEFAULT 0;
ALTER TABLE campaigns ADD COLUMN is_featured TINYINT(1) DEFAULT 0;
ALTER TABLE campaigns ADD COLUMN admin_note TEXT;
ALTER TABLE campaigns ADD COLUMN opportunity_type ENUM('one_time_ugc','direct_ugc') DEFAULT 'direct_ugc';

-- Payments: extra fields
ALTER TABLE payments ADD COLUMN currency VARCHAR(10) DEFAULT 'USD';
ALTER TABLE payments ADD COLUMN actual_views INT DEFAULT 0;
ALTER TABLE payments ADD COLUMN approved_views INT DEFAULT 0;
ALTER TABLE payments ADD COLUMN payout_date TIMESTAMP NULL;

-- Contests: CPM columns
ALTER TABLE contests ADD COLUMN cpm_rate DECIMAL(10,4) DEFAULT 0;
ALTER TABLE contests ADD COLUMN cpm_calculated_at TIMESTAMP NULL;

-- Contest submissions: verification + moderation columns
ALTER TABLE contest_submissions ADD COLUMN views_verified TINYINT(1) DEFAULT 0;
ALTER TABLE contest_submissions ADD COLUMN verified_view_count INT DEFAULT 0;
ALTER TABLE contest_submissions ADD COLUMN views_verified_at TIMESTAMP NULL;
ALTER TABLE contest_submissions ADD COLUMN payment_id INT DEFAULT NULL;
ALTER TABLE contest_submissions ADD COLUMN flag_reason TEXT NULL;
ALTER TABLE contest_submissions ADD COLUMN flagged_at TIMESTAMP NULL;
ALTER TABLE contest_submissions ADD COLUMN view_count_rejected TINYINT(1) DEFAULT 0;
ALTER TABLE contest_submissions ADD COLUMN rejection_reason TEXT NULL;
ALTER TABLE contest_submissions ADD COLUMN views_rejected_at TIMESTAMP NULL;

-- UGC order submissions: watermark + moderation columns
ALTER TABLE ugc_order_submissions ADD COLUMN watermarked_preview_file VARCHAR(255);
ALTER TABLE ugc_order_submissions ADD COLUMN quality_verified TINYINT(1) DEFAULT 0;
ALTER TABLE ugc_order_submissions ADD COLUMN flag_reason TEXT NULL;
ALTER TABLE ugc_order_submissions ADD COLUMN flagged_at TIMESTAMP NULL;
ALTER TABLE ugc_order_submissions ADD COLUMN watermark_approved TINYINT(1) DEFAULT 0;
ALTER TABLE ugc_order_submissions ADD COLUMN clean_file_unlocked_at TIMESTAMP NULL;

-- Campaign (UGC Order) submissions: brand feedback on rejection/revision
ALTER TABLE ugc_order_submissions ADD COLUMN brand_feedback TEXT NULL;
-- Add 'rejected' to status ENUM (alongside existing values)
ALTER TABLE ugc_order_submissions MODIFY COLUMN status ENUM('submitted','under_review','revision_requested','approved','rejected','disqualified','payment_ready','paid') DEFAULT 'submitted';
