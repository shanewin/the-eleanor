# The Eleanor | Luxury Residences in Boerum Hill, Brooklyn

The Eleanor is a high-end residential project located at 52 4th Avenue, Brooklyn. This repository contains the source code for the project's web application, which includes a private preview landing page, availability tracking, and a sophisticated lead enrichment and management system.

---

## 🚀 Core Features

- **Luxury Landing Page**: A premium, mobile-responsive design featuring smooth animations, video background, and interactive carousels.
- **Lead Capture & Tracking**: Real-time monitoring of visitor activity (`api/track.php`) and seamless capture of inquiries through multiple entry points (waitlist, unit inquiries, mailing list).
- **Intelligent Lead Enrichment**: A multi-tier fallback engine that hydrates prospects with professional data using Apollo.io, Tavily, and LinkedIn scraping, verified by Claude 3 AI.
- **Admin Intelligence Dashboard**: A powerful backend for managing leads, featuring a real-time grading algorithm and on-demand AI-generated prospect summaries.
- **Unit Availability Management**: Real-time filtering and status tracking for residential units.

---

## 🛠 Tech Stack

- **Backend**: PHP 8+
- **Frontend**: Vanilla JS (ES6+), Modern CSS3 (with Glassmorphism), Bootstrap 5, Swiper.js, FontAwesome
- **Database**: MySQL (PDO)
- **AI/APIs**: 
  - **Anthropic Claude 3 Haiku**: Synthesis of professional summaries and data normalization.
  - **Apollo.io**: Primary professional person/company search.
  - **Tavily AI**: Search engine for identity discovery.
  - **LinkedIn Scraper API**: Professional profile extraction.

---

## 📂 Project Structure

```text
├── admin/               # Administrative dashboard source
│   ├── index.php        # Main lead management interface
│   └── auth.php         # Authentication gate
├── api/                 # Backend API handlers and logic
│   ├── config.php       # API keys and global constants
│   ├── db_config.php    # Database connection parameters
│   ├── enrichment.php   # Core enrichment engine logic
│   └── setup_db.sql     # Database schema and initial seed
├── assets/              # Reusable design assets
├── css/                 # Modern, modular CSS system
├── js/                  # Frontend logic & interactions
├── img/                 # Optimized image assets
└── index.php            # Main public entry point (password-gated)
```

---

## ⚙️ Setup & Configuration

### 1. Database Initialization
Run the SQL script found in `api/setup_db.sql` to create the necessary tables:
- `visitor_activity`: Behavioral logs.
- `lead_enrichment`: Enriched professional data.
- `waitlist_submissions`: General inquiries.
- `unit_inquiries`: Unit-specific leads.

### 2. Environment Configuration
Update the following files with your credentials:

- **`api/db_config.php`**: Set your MySQL database host, name, user, and password.
- **`api/config.php`**: Add your API keys for Apollo, Anthropic, and Tavily. You can also update the `ADMIN_PASSWORD_HASH` here.

---

## 🧠 Prospect Enrichment Flow

The system employs a multi-tiered approach to ensure every lead is deeply understood:

1. **Identity Capture**: Visitor interaction triggers an immediate `tracking_id` link.
2. **Tier 1 (Apollo Match)**: Instant match by email for professional data.
3. **Tier 2 (Apollo Search)**: Fallback search by name and location (parsed from phone number).
4. **Tier 3 (Deep Search)**: Tavily finds the LinkedIn URL → Scraper extracts profile → Claude 3 normalizes the data.
5. **Final Intelligence**: Data is presented in the Admin Dash with a dynamic **A+ to F** grade based on seniority, company revenue, and elite signals.

For an indepth look at the data flow, see [PROSPECT_ENRICHMENT_FLOW.md](PROSPECT_ENRICHMENT_FLOW.md).

---

## 🔒 Security
- **Password Gate**: The public site is protected by a session-based password gate.
- **CSRF Protection**: All form submissions are protected via CSRF tokens.
- **Credential Safety**: Sensitive keys are centralized in `api/config.php`. Ensure this file is never publicly accessible (e.g., via `.htaccess` rules).

---

&copy; 2026 The Eleanor Brooklyn. All rights reserved.
