-- Database setup for The Eleanor Form Submissions

-- 1. Waitlist Submissions
CREATE TABLE IF NOT EXISTS waitlist_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    move_in_date VARCHAR(50),
    budget VARCHAR(100),
    unit VARCHAR(100),
    unit_type VARCHAR(100),
    hear_about_us VARCHAR(255),
    message TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Unit Inquiries (Specific Units)
CREATE TABLE IF NOT EXISTS unit_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    move_in_date VARCHAR(50),
    budget VARCHAR(100),
    hear_about_us VARCHAR(255),
    message TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Mailing List Signups
CREATE TABLE IF NOT EXISTS mailing_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    interests TEXT,
    consent VARCHAR(10) DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. User Tracking Sessions
CREATE TABLE IF NOT EXISTS tracking_sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_agent TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. User Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64),
    event_type VARCHAR(100), -- 'visibility', 'click', 'modal', 'time'
    event_name VARCHAR(255), -- 'about-section', 'waitlist-submit', etc.
    event_data JSON,         -- Additional context (e.g., filter values, time spent)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES tracking_sessions(id) ON DELETE CASCADE
);

-- Add tracking_session_id to existing tables
ALTER TABLE waitlist_submissions ADD COLUMN IF NOT EXISTS tracking_id VARCHAR(64);
ALTER TABLE unit_inquiries ADD COLUMN IF NOT EXISTS tracking_id VARCHAR(64);
ALTER TABLE mailing_list ADD COLUMN IF NOT EXISTS tracking_id VARCHAR(64);

-- 6. Lead Enrichment Data (Apollo.io)
CREATE TABLE IF NOT EXISTS lead_enrichment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255),
    job_title VARCHAR(255),
    company VARCHAR(255),
    company_domain VARCHAR(255),
    seniority VARCHAR(100),
    linkedin_url VARCHAR(255),
    twitter_url VARCHAR(255),
    github_url VARCHAR(255),
    facebook_url VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    employee_count VARCHAR(50),
    industry VARCHAR(255),
    annual_revenue VARCHAR(100),
    company_logo TEXT,
    company_description TEXT,
    headline TEXT,
    photo_url TEXT,
    ai_summary TEXT,
    raw_response JSON,
    enriched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
