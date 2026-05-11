# The Eleanor | Luxury Residences in Boerum Hill, Brooklyn

The Eleanor is a luxury residential building at 52 4th Avenue, Brooklyn. This repository contains the marketing website, lead capture, enrichment pipeline, conversational SMS AI, and admin command center.

---

## Core Features

- **Marketing Website** — mobile-responsive landing page with video background, image sliders, neighborhood guide, and live unit availability.
- **Lead Capture** — waitlist form, unit interest popup, and mailing list, all with behavioral tracking (sections viewed, buttons clicked, time on page).
- **Enrichment Pipeline** — pairs Apollo.io and a LinkedIn scraper to build rich prospect profiles from a name + email + phone.
- **Conversational SMS AI** — Claude-powered two-way SMS over Telnyx that greets every new lead, qualifies them, and books tours autonomously. Tool use covers `check_tour_availability`, `book_tour`, `reschedule_tour`, and `cancel_tour`.
- **Tour Calendar** — integrates with each broker's Google Calendar (OAuth + FreeBusy + event creation). Falls back to default availability when a broker hasn't connected a calendar.
- **Admin Command Center** — owner/broker accounts (Supabase auth), unified CRM timeline, conversation panel with AI handoff, A+ to F lead grading, real-time notifications + unread badges, and a settings panel for SMS automation rules.
- **Notifications** — every owner-role account receives email alerts on new leads, enrichment reports, and tour scheduling events.

---

## Tech Stack

- **Backend**: PHP 8+ (Supabase REST via PHP cURL — no local SQL server)
- **Frontend**: Vanilla JS (ES6+), Bootstrap 5, Swiper.js
- **Data**: Supabase (Postgres + Auth)
- **External services**:
  - **Anthropic Claude** — SMS conversation engine (tool use) and on-demand prospect summaries
  - **Telnyx** — inbound + outbound SMS, signed webhooks (Ed25519)
  - **Google Calendar API** — broker calendar integration (OAuth 2.0)
  - **Apollo.io** — professional enrichment by email (job title, company, LinkedIn URL)
  - **Fresh LinkedIn Profile Data (RapidAPI)** — live LinkedIn scrape used as the source of truth for title, company, photo, location
  - **Hostinger SMTP** — transactional email delivery

---

## SMS AI Flow

```
Lead submits waitlist form
        │
        ▼
   Welcome SMS (Claude-generated, personalized)
        │
        ▼
   Lead replies ──→ telnyx-webhook.php
        │
        ▼
   Claude + tools  ←──┐
        │             │ (multi-loop tool use)
        ▼             │
   AI reply ──────────┘
        │
        ▼
   Books tour →  tour_requests + Google Calendar event +
                 SMS confirmation to lead +
                 email to owners & assigned broker
```

Messages received outside the configured send window are queued (`process-sms-queue.php`, cron every 5 min) and replied to when the window opens. If the AI detects frustration or an explicit ask for a human, it appends `[HANDOFF]`, pauses automation, and notifies the assigned broker.

---

## Project Structure

```
├── admin/                      # Admin command center (broker login)
│   ├── index.php               # Overview dashboard
│   ├── leads.php               # Lead list + filters
│   ├── lead.php                # Lead detail / unified timeline
│   ├── communications.php      # SMS + email conversation panel
│   ├── calendar.php            # Tour calendar (drag-to-schedule)
│   ├── brokers.php             # Broker/owner management (owner only)
│   ├── profile.php             # My profile + Google Calendar connect
│   ├── settings.php            # SMS window, AI prompts, jobs
│   ├── login.php               # Supabase email/password login
│   └── auth.php                # Session + Supabase auth helpers
├── api/
│   ├── config.php              # API keys & constants (gitignored)
│   ├── db_config.php           # Supabase REST client
│   ├── form-handler.php        # Waitlist submissions
│   ├── form-processor.php      # Async lead enrichment + welcome SMS
│   ├── unit-interest.php       # Unit inquiry popup
│   ├── email-list.php          # Mailing list signup
│   ├── track.php               # Behavioral tracking endpoint
│   ├── enrichment.php          # Apollo → LinkedIn pipeline + report email
│   ├── apollo-webhook.php      # Apollo webhook receiver
│   ├── telnyx-sms.php          # Outbound SMS helper
│   ├── telnyx-webhook.php      # Inbound SMS webhook (Ed25519 verified)
│   ├── sms-ai.php              # Claude conversation engine + calendar tools
│   ├── google-calendar.php     # OAuth tokens + FreeBusy + events
│   ├── google-calendar-auth.php# OAuth callback
│   ├── public-tours.php        # Public-facing tour booking page
│   ├── admin-api.php           # Admin dashboard JSON API
│   ├── ai-summary.php          # Claude prospect summaries
│   ├── smtp-mail.php           # SMTP sender + notification helpers
│   ├── job-runner.php          # Lead processing job pipeline
│   ├── process-lead-jobs.php   # Cron: lead enrichment jobs
│   ├── process-sms-queue.php   # Cron: replay queued inbound SMS
│   ├── process-followups.php   # Cron: proactive AI follow-ups
│   ├── process-tour-reminders.php # Cron: tour reminder SMS
│   ├── cron-runner.php         # Cron entry point
│   ├── get-csrf-token.php      # CSRF token issuance
│   └── .htaccess               # Blocks config files from public access
├── tours.php                   # Public tour booking page
└── index.php                   # Marketing site (password-gated)
```

---

## Setup

### 1. Supabase

Create a Supabase project and apply the schema (see your project's Supabase dashboard for the existing tables: `waitlist_submissions`, `unit_inquiries`, `mailing_list`, `tracking_sessions`, `lead_enrichment`, `brokers`, `tour_requests`, `sms_messages`, `sms_automation`, `sms_queue`, `communications`, `notifications`, `lead_processing_jobs`, `settings`).

Create at least one user via Supabase Auth and a matching row in `brokers` with `role = 'owner'`.

### 2. Configuration

```bash
cp api/config.example.php api/config.php
```

`api/config.php` requires:

| Constant | Purpose |
|---|---|
| `SUPABASE_URL`, `SUPABASE_SECRET_KEY`, `SUPABASE_ANON_KEY` | Supabase project |
| `ANTHROPIC_API_KEY` | Claude API (SMS AI + summaries) |
| `TELNYX_API_KEY`, `TELNYX_FROM_NUMBER`, `TELNYX_PUBLIC_KEY` | SMS send + webhook verification |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Broker calendar OAuth |
| `APOLLO_API_KEY`, `RAPIDAPI_KEY` | Enrichment |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | Hostinger SMTP |
| `PREVIEW_PASSWORD` | Marketing-site gate |
| `NOTIFICATION_EMAIL` | *Fallback only* — used if no `role='owner'` accounts exist yet |

### 3. Deployment (Hostinger)

1. Connect the repo via **Advanced → Git** in hPanel and deploy.
2. Create `api/config.php` on the server (gitignored).
3. In hPanel **PHP Configuration → Extensions**, enable `sodium` so Telnyx webhook signatures verify. The webhook auto-skips verification if the extension is missing, but enabling it closes the spoof window.
4. Point your Telnyx Messaging Profile webhook to `https://<domain>/api/telnyx-webhook.php` (API v2).
5. In Google Cloud Console, add `https://<domain>/api/google-calendar-auth.php` as an OAuth redirect URI.
6. Add cron jobs (every 5 min):
   ```
   php /home/.../api/process-lead-jobs.php
   php /home/.../api/process-sms-queue.php
   php /home/.../api/process-followups.php
   php /home/.../api/process-tour-reminders.php
   ```

### 4. Verify

- Visit `/admin/` → log in with a Supabase user
- Submit a test waitlist entry → verify enrichment email + welcome SMS
- Text the Telnyx number → AI should reply within ~5–15 seconds
- Book a tour through the SMS conversation → check `tour_requests` and email alerts

---

## Security

- `config.php` is gitignored and blocked via `.htaccess`
- Supabase Auth gates the admin dashboard (no shared password)
- CSRF tokens on every form submission
- Telnyx webhooks are Ed25519-signed and verified when the PHP `sodium` extension is available
- Owner-only routes server-side check `isOwner()` independent of the UI
- Public marketing site is gated by `PREVIEW_PASSWORD` while in pre-launch

---

© 2026 The Eleanor Brooklyn. All rights reserved.
