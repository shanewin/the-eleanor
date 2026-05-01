# Telnyx SMS Integration — The Eleanor

## Overview

AI-powered conversational SMS system that automatically engages leads after form submission, with the goal of booking in-person tours. Uses Telnyx for SMS delivery and Claude (Sonnet) for generating natural, contextual responses.

---

## Architecture

```
Lead submits form (Waitlist / Unit Interest)
        |
        v
form-processor.php
        |
        ├── Inserts lead into Supabase
        ├── Runs enrichment pipeline (PDL, Apollo, LinkedIn, etc.)
        ├── Sends SMTP notification to admin
        └── If SMS automation is ON:
              ├── Claude generates personalized welcome text
              ├── Telnyx sends the SMS from +1 (718) 675-5803
              └── Message logged to sms_messages table
```

```
Lead replies via SMS
        |
        v
Telnyx receives it → POSTs to /api/telnyx-webhook.php
        |
        ├── Signature verification (Ed25519)
        ├── Idempotency check (skip duplicates)
        ├── STOP keyword → opt-out handling
        ├── Store inbound message in sms_messages
        ├── Check: global SMS settings ON? AI active for this lead?
        ├── Load lead context (name, budget, unit interest, enrichment)
        ├── Fetch live unit inventory from Google Sheet (cached 5 min)
        ├── Load conversation history (last 20 messages)
        ├── Claude generates contextual reply
        ├── Telnyx sends response
        └── Store outbound message in sms_messages
```

```
Broker takes over
        |
        v
Admin dashboard → Communications tab → selects lead → types message
        |
        ├── Message sent via Telnyx (same +718 number)
        ├── AI automation auto-paused for this lead
        ├── Broker continues conversation manually
        └── Can click "Resume AI" to hand back to automation
```

---

## New Files

### `api/telnyx-sms.php`
Telnyx SMS send helper. Wraps the Telnyx v2 Messages API via cURL.
- `sendSMS($to, $text, $from)` — sends an SMS, returns success/message_id/error
- `normalizePhone($phone)` — converts US phone numbers to E.164 format
- 10s timeout, 5s connect timeout on API calls

### `api/sms-ai.php`
Claude-powered conversation engine. The brain of the system.
- `generateAIResponse($leadPhone, $inboundText)` — generates a reply to an inbound SMS using full conversation history and lead context
- `generateInitialMessage($leadPhone, $leadEmail)` — generates the first welcome text after form submission
- `getLeadContext($phone, $email)` — looks up lead info across waitlist_submissions, unit_inquiries, and lead_enrichment tables
- `buildSystemPrompt($lead)` — assembles the full system prompt with:
  - Static property details (address, amenities, transit, neighborhood)
  - Live unit inventory from Google Sheet (cached 5 min via `fetchAvailableUnits()`)
  - Lead-specific context (name, budget, unit preference, enrichment data)
  - Conversation rules for SMS tone and behavior
- `fetchAvailableUnits()` — fetches live unit data from the same Google Sheet endpoint the website uses, caches to temp file for 5 minutes
- `isSMSAutomationAllowed()` — checks global settings: master toggle, active days, send window, campaign date range
- `isAIActiveForLead($phone)` — checks if AI is paused for a specific lead (broker takeover or opt-out)

### `api/telnyx-webhook.php`
Inbound webhook endpoint. Telnyx POSTs here when an SMS is received or a message status updates.
- Webhook URL: `https://eleanorbk.com/api/telnyx-webhook.php`
- Responds with 200 immediately (Telnyx requires < 2 seconds), then processes async
- Ed25519 signature verification (when TELNYX_PUBLIC_KEY is configured)
- Idempotency: skips processing if telnyx_message_id already exists in DB
- Handles `message.received` (inbound SMS) and `message.finalized` (delivery status)
- STOP/UNSUBSCRIBE/CANCEL/QUIT/END keywords trigger opt-out
- `findLeadEmailByPhone($phone)` — resolves lead email from phone number

---

## Modified Files

### `api/config.php` (gitignored)
Added Telnyx configuration:
```php
define('TELNYX_API_KEY', '...');
define('TELNYX_FROM_NUMBER', '+17186755803');
define('TELNYX_MESSAGING_PROFILE_ID', '40019de4-32dd-46c8-a171-3b5fdaf37688');
define('TELNYX_PUBLIC_KEY', '');  // TODO: add from Telnyx portal
```

### `api/form-processor.php`
Added SMS auto follow-up block after the SMTP notification section. When a lead submits a waitlist or unit interest form:
1. Checks if SMS automation is enabled globally
2. Checks if TELNYX_FROM_NUMBER is configured
3. Generates a personalized welcome message via Claude
4. Sends it via Telnyx
5. Logs the message to sms_messages
6. Creates/updates sms_automation record (upsert on lead_phone)

### `api/admin-api.php`
Added 5 new endpoints:
- `sms_conversations` — lists all SMS threads grouped by phone, with lead info and AI status
- `sms_thread` — returns full message history for a specific phone number
- `sms_send` — sends SMS from dashboard (broker takeover), auto-pauses AI for that lead
- `sms_toggle_ai` — toggles AI automation on/off for a specific lead
- `sms_ai_status` — returns AI automation status for a lead

### `admin/index.php`
- **Communications tab redesigned** — split into conversation list (left panel) + message thread (right panel) + compose box
- Conversation list shows lead name, phone, last message preview, AI status indicator, message count
- Thread view shows messages in chat bubble format with sender labels (AI/Broker/Lead) and delivery status
- Compose box with character counter, sends via sms_send endpoint
- Pause AI / Resume AI toggle button in thread header
- Auto-refreshes active thread every 10 seconds
- **Settings view** — added SMS Automation card with:
  - Master enable/disable toggle
  - Send window (start time / end time)
  - Active days (day-of-week checkboxes)
  - Campaign start/end date pickers (optional)

---

## Database Tables (Supabase)

### `sms_messages`
Stores every SMS message (inbound and outbound).

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| lead_phone | TEXT | Phone in E.164 format |
| lead_email | TEXT | Lead's email (nullable) |
| direction | TEXT | `inbound` or `outbound` |
| sender_type | TEXT | `ai`, `broker`, or `lead` |
| sender_name | TEXT | Display name (nullable) |
| body | TEXT | Message content |
| telnyx_message_id | TEXT | Telnyx message ID for status tracking |
| status | TEXT | `sent`, `delivered`, `failed`, `received` |
| created_at | TIMESTAMPTZ | Auto-set |

Indexes: `lead_phone`, `lead_email`, `created_at`

### `sms_automation`
Tracks AI automation state per lead.

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| lead_phone | TEXT | UNIQUE, E.164 format |
| lead_email | TEXT | Lead's email (nullable) |
| status | TEXT | `active`, `paused_manual`, `paused_optout`, `completed` |
| paused_by | TEXT | Who paused it (broker name or "Admin") |
| created_at | TIMESTAMPTZ | Auto-set |
| updated_at | TIMESTAMPTZ | Auto-set |

---

## Telnyx Configuration

| Setting | Value |
|---------|-------|
| Phone Number | +1 (718) 675-5803 |
| Type | Local (Brooklyn 718 area code) |
| Messaging Profile | Eleanor Enrichment |
| Profile ID | 40019de4-32dd-46c8-a171-3b5fdaf37688 |
| Webhook URL | https://eleanorbk.com/api/telnyx-webhook.php |
| API Version | V2 |

### Keyword Auto-Responses (configured in Telnyx portal)
- **STOP** → opt-out confirmation
- **START** → opt-in confirmation
- **HELP** → leasing team contact info

---

## System Prompt (Claude)

The AI operates as a leasing agent with three layers of context:

### 1. Static Property Details
- Address: 52 4th Avenue, Brooklyn, NY 11217 (Boerum Hill)
- Unit features: in-unit W/D, central air, oak flooring, marble counters, open-concept
- Amenities: rooftop terrace, fitness center, co-working lounge, library, courtyard, bike storage, package room
- Transit: Atlantic Ave-Barclays Center (2/3/4/5/B/D/N/Q/R/W + LIRR), under 15 min to Manhattan
- Neighborhood: Barclays Center (3 min), BAM (8 min), Smith St restaurants (5 min), DeKalb Market (7 min)
- Pet-friendly

### 2. Live Unit Inventory (dynamic)
Fetched from the same Google Sheet endpoint the website uses. Cached 5 minutes. Includes unit number, bed/bath, sqft, rent, outdoor space, view. Leased units excluded.

### 3. Lead-Specific Context
Name, budget, unit interest, move-in date, form source, enrichment data (job title, company). For unknown callers, the AI introduces itself and asks how it can help.

### AI Behavior Rules
- 1-3 sentences max (SMS, not email)
- Sound human, no emojis, no corporate speak
- Never reveal it's an AI
- CAN discuss specific pricing, units, sqft, availability
- Only punts on: lease terms, guarantor requirements, app fees, broker fees, move-in costs, pet deposits
- When recommending units, picks 2-3 matches rather than listing everything
- Tour scheduling: suggests weekday 10am-6pm or Saturday 11am-4pm windows, team confirms

---

## Security

- **Webhook signature verification**: Ed25519 via `telnyx-signature-ed25519` and `telnyx-timestamp` headers (requires TELNYX_PUBLIC_KEY)
- **Replay protection**: rejects webhook timestamps older than 5 minutes
- **Idempotency**: checks telnyx_message_id before processing to prevent duplicate responses from Telnyx retries
- **STOP handling**: both at Telnyx carrier level and in webhook code
- **Config protection**: api/config.php is gitignored and blocked by .htaccess

---

## Settings (Admin Dashboard)

All stored in Supabase `settings` table:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| sms_enabled | on/off | off | Master toggle |
| sms_window_start | HH:MM | 09:00 | Earliest send time |
| sms_window_end | HH:MM | 19:00 | Latest send time |
| sms_active_days | comma-separated | 1,2,3,4,5 | 0=Sun through 6=Sat |
| sms_campaign_start | YYYY-MM-DD | (empty) | Optional campaign start |
| sms_campaign_end | YYYY-MM-DD | (empty) | Optional campaign end |

---

## TODO / Not Yet Implemented

- [ ] **10DLC Registration** — required for US A2P messaging. Register brand + campaign in Telnyx portal under 10DLC section
- [ ] **TELNYX_PUBLIC_KEY** — add from Mission Control > API Keys > Public Key for webhook signature verification
- [ ] **Re-engagement nudge** — if lead goes quiet for 24 hours, send one follow-up (needs lightweight cron)
- [ ] **Per-lead AI pause from lead profile** — button on lead profile view to pause/resume AI (currently only from Communications tab)
- [ ] **Read receipts / unread tracking** — currently all inbound messages show as "unread" in conversation count
- [ ] **Broker identification** — currently all dashboard-sent messages show as "Broker", could use logged-in admin name
