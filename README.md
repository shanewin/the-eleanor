# The Eleanor | Luxury Residences in Boerum Hill, Brooklyn

The Eleanor is a luxury residential building at 52 4th Avenue, Brooklyn. This repository contains the marketing website, lead capture system, enrichment pipeline, and admin dashboard.

---

## Core Features

- **Marketing Website**: Mobile-responsive landing page with smooth animations, video background, image sliders, neighborhood guide, and unit availability browser.
- **Lead Capture**: Waitlist form, unit interest popup, and mailing list — all with real-time behavioral tracking (sections viewed, buttons clicked, time spent).
- **Lead Enrichment Pipeline**: Chains FullContact, Apollo.io, and a LinkedIn scraper to build rich prospect profiles from just a name, personal email, and phone number.
- **Admin Dashboard**: Lead management with A+ to F grading, enrichment data, behavioral journey timeline, and AI-generated prospect summaries.
- **Email Notifications**: SMTP delivery via Hostinger for form submissions and enrichment profiles.

---

## Tech Stack

- **Backend**: PHP 8+, MySQL (PDO)
- **Frontend**: Vanilla JS (ES6+), CSS3 (Glassmorphism), Bootstrap 5, Swiper.js
- **APIs**:
  - **FullContact**: Identity resolution from email + phone + name. Discovers work emails for personal email leads.
  - **Apollo.io**: Professional enrichment by email. Returns job title, company, firmographics, LinkedIn URL.
  - **Fresh LinkedIn Profile Data (RapidAPI)**: Scrapes live LinkedIn data. Used as the final source of truth for title, company, photo, and location.
  - **Anthropic Claude**: On-demand AI prospect summaries in the admin dashboard.

---

## Enrichment Pipeline

The system chains three services to accurately identify leads, even when they submit personal emails:

```
Form Submission (name + email + phone)
        │
        ▼
   FullContact ──→ Identifies person, discovers work email
        │
        ▼
     Apollo ──→ Matches on work email for professional profile + LinkedIn URL
        │
        ▼
  LinkedIn Scraper ──→ Fetches live profile data (source of truth)
        │
        ▼
   Database ──→ Stores enriched data, triggers email notification
```

- **Corporate email** (e.g. `name@company.com`): Apollo matches directly. FullContact and LinkedIn scraper enhance the data.
- **Personal email** (e.g. `name@gmail.com`): FullContact resolves identity via phone + name, finds work email. Apollo matches on work email. LinkedIn scraper provides fresh data.
- **No match found**: Lead is saved with submitted form data only. No incorrect guesses.

---

## Project Structure

```
├── admin/                # Admin dashboard
│   ├── index.php         # Lead management interface
│   ├── login.php         # Password login
│   ├── auth.php          # Session authentication
│   └── admin.css         # Dashboard styles
├── api/                  # Backend API
│   ├── config.php        # API keys and constants (not in git)
│   ├── config.example.php# Template for config.php
│   ├── db_config.php     # Database connection (not in git)
│   ├── db_config.example.php
│   ├── enrichment.php    # Enrichment pipeline (FullContact → Apollo → LinkedIn)
│   ├── smtp-mail.php     # SMTP email sender
│   ├── form-handler.php  # Waitlist form handler
│   ├── unit-interest.php # Unit inquiry handler
│   ├── email-list.php    # Mailing list handler
│   ├── track.php         # Behavioral tracking endpoint
│   ├── admin-api.php     # Admin dashboard API
│   ├── ai-summary.php    # Claude AI prospect summaries
│   ├── apollo-webhook.php# Apollo webhook receiver
│   ├── setup_db.sql      # Database schema
│   └── .htaccess         # Protects config files from public access
├── assets/floor-plans/   # Unit floor plan images
├── css/                  # Stylesheets
├── js/                   # Frontend scripts
├── img/                  # Site images
├── video/                # Background video
└── index.php             # Main site (password-gated)
```

---

## Setup

### 1. Database

Create a MySQL database and import the schema:

```sql
mysql -u username -p database_name < api/setup_db.sql
```

This creates 6 tables: `waitlist_submissions`, `unit_inquiries`, `mailing_list`, `tracking_sessions`, `activity_logs`, `lead_enrichment`.

### 2. Configuration

Copy the example files and fill in your credentials:

```bash
cp api/config.example.php api/config.php
cp api/db_config.example.php api/db_config.php
```

**`api/config.php`** requires:
| Constant | Description |
|---|---|
| `FULLCONTACT_API_KEY` | FullContact API key for identity resolution |
| `APOLLO_API_KEY` | Apollo.io API key for professional enrichment |
| `RAPIDAPI_KEY` | RapidAPI key for LinkedIn scraper |
| `ANTHROPIC_API_KEY` | Anthropic API key for AI summaries |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | SMTP credentials for email delivery |
| `NOTIFICATION_EMAIL` | Email address to receive lead notifications |
| `ADMIN_PASSWORD_HASH` | Admin dashboard password |
| `PREVIEW_PASSWORD` | Frontend preview gate password |

**`api/db_config.php`** requires: `$db_host`, `$db_name`, `$db_user`, `$db_pass`

### 3. Deployment (Hostinger)

1. Connect the GitHub repo via **Advanced → Git** in hPanel
2. Click **Deploy** to pull files to `public_html`
3. Create `api/config.php` and `api/db_config.php` manually on the server (they are gitignored)
4. Import `api/setup_db.sql` via phpMyAdmin

### 4. Verify

- Visit the site URL — should show the password gate
- Submit a test waitlist entry — check email delivery and admin dashboard
- Visit `/admin/` — log in and verify lead data

---

## Security

- **Config files** (`config.php`, `db_config.php`) are gitignored and protected by `.htaccess`
- **CSRF tokens** protect all form submissions
- **Password gate** on the public site and admin dashboard
- **No test/debug endpoints** in production
- **SQL files** blocked from public access

---

© 2026 The Eleanor Brooklyn. All rights reserved.
