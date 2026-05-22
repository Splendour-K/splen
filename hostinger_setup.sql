-- ============================================================
-- Splennet Complete Database Setup for Hostinger
-- Import this single file in phpMyAdmin to set up everything.
-- Safe to run on an empty database.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- CORE TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('brand', 'creator', 'admin') NOT NULL,
    status ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    brand_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NOT NULL,
    country VARCHAR(100),
    city VARCHAR(100),
    industry VARCHAR(100),
    website VARCHAR(255),
    phone VARCHAR(50),
    logo VARCHAR(255),
    subscription_tier ENUM('basic', 'pro', 'enterprise') DEFAULT 'basic',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS creators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    school VARCHAR(255) NOT NULL,
    country VARCHAR(100),
    city VARCHAR(100),
    phone VARCHAR(50),
    main_niche VARCHAR(100),
    bio TEXT,
    tiktok_handle VARCHAR(100),
    instagram_handle VARCHAR(100),
    profile_photo VARCHAR(255),
    sample_video_link VARCHAR(255),
    target_languages VARCHAR(255),
    equipment_list TEXT,
    availability ENUM('available_now', 'next_week', 'busy', 'away') DEFAULT 'available_now',
    bank_name VARCHAR(100),
    account_name VARCHAR(255),
    account_number VARCHAR(100),
    momo_number VARCHAR(100),
    verification_status ENUM('not_started', 'pending', 'verified', 'rejected') DEFAULT 'not_started',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS creator_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT UNIQUE NOT NULL,
    id_upload VARCHAR(255),
    letter_upload VARCHAR(255),
    school_email VARCHAR(255),
    status ENUM('pending', 'verified', 'rejected', 'more_info') DEFAULT 'pending',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    short_description VARCHAR(255),
    full_description TEXT,
    external_brief_link VARCHAR(255),
    product_name VARCHAR(255),
    order_type ENUM('direct_ugc', 'performance_campaign', 'contest') DEFAULT 'direct_ugc',
    opportunity_type ENUM('one_time_ugc', 'direct_ugc') DEFAULT 'direct_ugc',
    category VARCHAR(100),
    goal TEXT,
    location_country VARCHAR(100),
    location_city VARCHAR(100),
    preferred_university VARCHAR(255),
    target_platform VARCHAR(100),
    video_type VARCHAR(100),
    video_length VARCHAR(50),
    creator_count INT DEFAULT 1,
    budget_per_creator DECIMAL(10, 2),
    currency VARCHAR(10) DEFAULT 'USD',
    max_campaign_budget DECIMAL(10, 2) DEFAULT 0,
    deadline DATE,
    final_view_count_deadline DATE,
    main_message TEXT,
    required_shots TEXT,
    required_hashtags TEXT,
    required_caption TEXT,
    required_tags TEXT,
    words_to_say TEXT,
    words_to_avoid TEXT,
    call_to_action TEXT,
    tracking_instructions TEXT,
    posting_required TINYINT(1) DEFAULT 0,
    usage_rights_package ENUM('basic', 'ad', 'full') DEFAULT 'basic',
    product_shipping_details TEXT,
    revision_limit INT DEFAULT 1,
    status ENUM('draft', 'published', 'paused', 'completed', 'cancelled') DEFAULT 'published',
    pay_per_1000_views DECIMAL(10,2) DEFAULT 0,
    max_payable_views INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    creator_id INT NOT NULL,
    application_message TEXT,
    sample_video_link VARCHAR(255),
    estimated_delivery_date DATE,
    status ENUM('pending', 'shortlisted', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNIQUE NOT NULL,
    campaign_id INT NOT NULL,
    brand_id INT NOT NULL,
    creator_id INT NOT NULL,
    status ENUM('approved', 'waiting_product', 'in_progress', 'draft_submitted', 'revision_requested', 'awaiting_review', 'completed', 'disputed') DEFAULT 'approved',
    payment_status ENUM('in_escrow', 'awaiting_approval', 'approved', 'ready_payout', 'paid', 'disputed') DEFAULT 'in_escrow',
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    FOREIGN KEY (creator_id) REFERENCES creators(id)
);

CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    creator_id INT NOT NULL,
    campaign_id INT NOT NULL,
    video_file VARCHAR(255),
    video_link VARCHAR(255),
    watermarked_preview_file VARCHAR(255),
    clean_video_file VARCHAR(255),
    submission_note TEXT,
    status ENUM('submitted', 'revision_requested', 'approved', 'rejected', 'admin_review') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    creator_id INT NOT NULL,
    last_message TEXT,
    last_message_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT,
    job_id INT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('application', 'job', 'payment', 'system', 'message') DEFAULT 'system',
    target_url VARCHAR(255),
    target_type VARCHAR(50),
    target_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    creator_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    p_pay_per_1000_views DECIMAL(10,2) DEFAULT 0,
    p_max_payable_views INT DEFAULT 0,
    actual_views INT DEFAULT 0,
    approved_views INT DEFAULT 0,
    calculated_amount DECIMAL(10, 2) DEFAULT 0,
    commission_rate DECIMAL(5, 2) DEFAULT 0,
    commission_amount DECIMAL(10, 2) DEFAULT 0,
    net_amount DECIMAL(10, 2) DEFAULT 0,
    payment_type ENUM('fixed_ugc', 'performance_views', 'contest_reward', 'contest_cpm') DEFAULT 'fixed_ugc',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    contest_submission_id INT,
    payout_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    recipient_id INT NOT NULL,
    job_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    quality_score INT,
    on_time_score INT,
    communication_score INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- EXTENDED TABLES (from migration scripts)
-- ============================================================

CREATE TABLE IF NOT EXISTS revision_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    reason TEXT,
    required_changes TEXT,
    deadline DATE,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS usage_rights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNIQUE NOT NULL,
    campaign_id INT NOT NULL,
    brand_id INT NOT NULL,
    creator_id INT NOT NULL,
    rights_package ENUM('basic', 'ad', 'full'),
    allowed_usage TEXT,
    start_date DATE,
    end_date DATE,
    can_brand_edit TINYINT(1) DEFAULT 0,
    can_brand_ads TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS performance_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    job_id INT NOT NULL,
    creator_id INT NOT NULL,
    posted_video_link VARCHAR(255),
    platform VARCHAR(50),
    date_posted DATE,
    analytics_screenshot VARCHAR(255),
    view_count INT DEFAULT 0,
    engagement_count INT DEFAULT 0,
    final_view_count_date DATE,
    approved_view_count INT DEFAULT 0,
    calculated_payment DECIMAL(10, 2) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS campaign_reference_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    link_url VARCHAR(255) NOT NULL,
    link_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS creator_samples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    video_file VARCHAR(255),
    external_video_link VARCHAR(255),
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS usage_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    month_year VARCHAR(7),
    campaign_count INT DEFAULT 0,
    application_count INT DEFAULT 0,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS disputes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    attachment_path VARCHAR(255),
    status ENUM('open', 'under_review', 'resolved', 'closed') DEFAULT 'open',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- CONTEST SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS contests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    product_name VARCHAR(255),
    short_description TEXT,
    full_description TEXT,
    external_brief_link VARCHAR(255),
    category VARCHAR(100),
    video_type VARCHAR(100),
    video_length VARCHAR(50),
    currency VARCHAR(10) DEFAULT 'USD',
    submission_deadline DATE NOT NULL,
    winner_announcement_date DATE,
    required_shots TEXT,
    words_to_say TEXT,
    words_to_avoid TEXT,
    call_to_action TEXT,
    required_hashtags VARCHAR(255),
    required_caption TEXT,
    required_tag_or_mention VARCHAR(255),
    target_platform VARCHAR(100),
    usage_rights_package ENUM('basic', 'ad', 'full') DEFAULT 'basic',
    posting_required TINYINT(1) DEFAULT 0,
    total_contest_budget DECIMAL(10, 2),
    cpm_budget DECIMAL(10, 2),
    pay_per_1000_views DECIMAL(10, 2),
    max_payable_views_per_creator INT,
    product_delivery_details TEXT,
    rules TEXT,
    cpm_rate DECIMAL(10,4) DEFAULT 0,
    cpm_calculated_at TIMESTAMP NULL,
    status ENUM('draft', 'live', 'closed', 'completed', 'results_announced') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contest_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contest_id INT NOT NULL,
    position_name VARCHAR(50),
    position_number INT,
    reward_amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contest_id) REFERENCES contests(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contest_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contest_id INT NOT NULL,
    creator_id INT NOT NULL,
    video_file VARCHAR(255),
    video_link VARCHAR(255),
    thumbnail VARCHAR(255),
    watermarked_preview_file VARCHAR(255),
    clean_video_file VARCHAR(255),
    submission_note TEXT,
    posted_video_link VARCHAR(255),
    platform VARCHAR(100),
    view_count INT DEFAULT 0,
    engagement_count INT DEFAULT 0,
    analytics_screenshot VARCHAR(255),
    approved_views INT,
    calculated_cpm_payment DECIMAL(10, 2),
    views_verified TINYINT(1) DEFAULT 0,
    verified_view_count INT DEFAULT 0,
    views_verified_at TIMESTAMP NULL,
    payment_id INT DEFAULT NULL,
    flag_reason TEXT NULL,
    flagged_at TIMESTAMP NULL,
    view_count_rejected TINYINT(1) DEFAULT 0,
    rejection_reason TEXT NULL,
    views_rejected_at TIMESTAMP NULL,
    status ENUM('submitted', 'under_review', 'shortlisted', 'winner', 'not_selected', 'disqualified', 'payment_ready', 'paid') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contest_id) REFERENCES contests(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contest_reference_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contest_id INT NOT NULL,
    link_url VARCHAR(255) NOT NULL,
    link_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contest_id) REFERENCES contests(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    type ENUM('one_time_ugc', 'contest') NOT NULL,
    title VARCHAR(255) NOT NULL,
    related_id INT,
    currency VARCHAR(10) DEFAULT 'USD',
    status ENUM('draft', 'live', 'closed', 'completed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

-- ============================================================
-- UGC ORDERS
-- ============================================================

CREATE TABLE IF NOT EXISTS ugc_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    short_description TEXT,
    full_description TEXT,
    external_brief_link VARCHAR(255),
    reference_videos TEXT,
    category VARCHAR(100),
    video_type VARCHAR(100),
    video_length VARCHAR(50),
    creator_count INT DEFAULT 1,
    budget_per_creator DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    deadline DATE NOT NULL,
    required_shots TEXT,
    words_to_say TEXT,
    words_to_avoid TEXT,
    call_to_action TEXT,
    usage_rights_package ENUM('basic', 'ad', 'full') DEFAULT 'basic',
    posting_required TINYINT(1) DEFAULT 0,
    product_delivery_details TEXT,
    revision_limit INT DEFAULT 1,
    status ENUM('draft', 'published', 'paused', 'completed', 'cancelled') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ugc_orders_brand (brand_id),
    INDEX idx_ugc_orders_status (status),
    INDEX idx_ugc_orders_deadline (deadline),
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ugc_order_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ugc_order_id INT NOT NULL,
    creator_id INT NOT NULL,
    video_file VARCHAR(255),
    watermarked_preview_file VARCHAR(255),
    video_link VARCHAR(255),
    thumbnail VARCHAR(255),
    clean_video_file VARCHAR(255),
    submission_note TEXT,
    posted_video_link VARCHAR(255),
    platform VARCHAR(100),
    view_count INT DEFAULT 0,
    engagement_count INT DEFAULT 0,
    analytics_screenshot VARCHAR(255),
    quality_verified TINYINT(1) DEFAULT 0,
    flag_reason TEXT NULL,
    flagged_at TIMESTAMP NULL,
    watermark_approved TINYINT(1) DEFAULT 0,
    clean_file_unlocked_at TIMESTAMP NULL,
    status ENUM('submitted', 'under_review', 'revision_requested', 'approved', 'disqualified', 'payment_ready', 'paid') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ugc_submission_order (ugc_order_id),
    INDEX idx_ugc_submission_creator (creator_id),
    FOREIGN KEY (ugc_order_id) REFERENCES ugc_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA: Required platform settings
-- ============================================================

INSERT IGNORE INTO site_settings (setting_key, setting_value, description) VALUES
('basic_monthly_limit', '3', 'Monthly campaign limit for basic brands'),
('pro_monthly_limit', '15', 'Monthly campaign limit for pro brands'),
('platform_commission', '10', 'Platform commission percentage'),
('announcement_text', 'Welcome to Splennet!', 'Dashboard announcement bar text'),
('maintenance_mode', '0', 'Toggle maintenance mode (0/1)');
