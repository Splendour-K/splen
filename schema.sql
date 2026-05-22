-- Splennet Database Schema

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('brand', 'creator', 'admin') NOT NULL,
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
    product_name VARCHAR(255),
    order_type ENUM('direct_ugc', 'performance_campaign', 'contest') DEFAULT 'direct_ugc',
    category VARCHAR(100),
    goal TEXT,
    location_country VARCHAR(100),
    location_city VARCHAR(100),
    preferred_university VARCHAR(255),
    video_type VARCHAR(100),
    video_length VARCHAR(50),
    creator_count INT DEFAULT 1,
    budget_per_creator DECIMAL(10, 2),
    currency VARCHAR(10) DEFAULT 'USD',
    deadline DATE,
    main_message TEXT,
    required_shots TEXT,
    words_to_say TEXT,
    words_to_avoid TEXT,
    call_to_action TEXT,
    posting_required TINYINT(1) DEFAULT 0,
    usage_rights_package ENUM('basic', 'ad', 'full') DEFAULT 'basic',
    product_shipping_details TEXT,
    revision_limit INT DEFAULT 1,
    status ENUM('draft', 'published', 'paused', 'completed', 'cancelled') DEFAULT 'published',
    pay_per_1000_views DECIMAL(10,2) DEFAULT 0,
    max_payable_views INT DEFAULT 0,
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
    p_pay_per_1000_views DECIMAL(10,2) DEFAULT 0,
    p_max_payable_views INT DEFAULT 0,
    calculated_amount DECIMAL(10, 2) DEFAULT 0,
    commission_rate DECIMAL(5, 2) DEFAULT 0,
    commission_amount DECIMAL(10, 2) DEFAULT 0,
    net_amount DECIMAL(10, 2) DEFAULT 0,
    payment_type ENUM('fixed_ugc', 'performance_views', 'contest_reward', 'contest_cpm') DEFAULT 'fixed_ugc',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    contest_submission_id INT,
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
