# The Eleanor — System Audit & Improvement Plan

## Current State Summary

The platform works end-to-end: forms → database → enrichment → email → admin dashboard. But there are significant issues in security, performance, and code quality that need addressing before scale.

---

## CRITICAL FIXES (Do First)

### 1. Admin Dashboard N+1 Query Problem
**File**: `api/admin-api.php` → `getLeads()`
**Issue**: For 50 leads, makes 200+ separate Supabase API calls (enrichment lookup + activity count per lead)
**Fix**: Batch enrichment fetches — fetch all enrichment records in one call, then merge in PHP

### 2. XSS Vulnerabilities in Admin Dashboard
**File**: `admin/index.php`
**Issue**: Uses `innerHTML` with unescaped lead data (photo_url, email, names). A malicious lead could inject JavaScript.
**Fix**: Use `textContent` for text, validate URLs before inserting into `src` attributes

### 3. PostgREST Filter Syntax Bug
**Files**: `api/admin-api.php`, `api/enrichment.php`
**Issue**: `session_id=in.("id1","id2")` — PostgREST expects `session_id=in.(id1,id2)` without quotes
**Fix**: Remove quote wrapping in `$idList` construction

### 4. Analytics Traffic Trends Broken
**File**: `api/admin-api.php` → `getAnalytics()`
**Issue**: Hardcodes `'leads' => 0` for all days — never calculates actual leads per day
**Fix**: Query submission tables by date and merge

### 5. Supabase Token Refresh
**File**: `admin/auth.php`
**Issue**: Access tokens expire after ~1 hour. No refresh logic — admin gets silently logged out.
**Fix**: Check token on each request, use refresh_token to get new access_token when expired

---

## HIGH PRIORITY

### 6. Form Handler Code Duplication
**Files**: `form-handler.php`, `unit-interest.php`, `email-list.php`
**Issue**: ~80% duplicated code (validation, sanitization, enrichment call, SMTP send)
**Fix**: Create shared `FormProcessor` class or shared include with common logic

### 7. Enrichment Pipeline Error Handling
**File**: `api/enrichment.php`
**Issues**:
- No timeout on cURL calls (could hang indefinitely)
- No retry on 429 rate limits
- No circuit breaker for cascading API failures
**Fix**: Add `CURLOPT_TIMEOUT = 15`, retry with backoff on 429, fail gracefully

### 8. Rate Limiting on Form Endpoints
**Files**: All form handlers
**Issue**: No rate limiting — forms can be spammed
**Fix**: Track submissions per IP in Supabase, reject if >5 in 5 minutes

### 9. Webhook Signature Verification
**File**: `api/apollo-webhook.php`
**Issue**: Accepts any POST without verifying it came from Apollo
**Fix**: Verify `X-Apollo-Signature` header via HMAC-SHA256

### 10. Track.php Upsert Spam
**File**: `api/track.php`
**Issue**: Upserts tracking_sessions on EVERY event (100+ times per page view)
**Fix**: Check if session exists first, only insert on first event

---

## MEDIUM PRIORITY

### 11. Anthropic Model Outdated
**File**: `api/ai-summary.php`
**Issue**: Uses `claude-3-haiku-20240307` — outdated model
**Fix**: Update to `claude-haiku-4-5-20251001` (latest Haiku)

### 12. SMTP Blocking Performance
**File**: `api/smtp-mail.php`
**Issue**: Creates new socket per email, blocks 3+ seconds per send
**Impact**: Slows form submission response time
**Fix**: Send emails asynchronously (queue or background job)

### 13. Admin Dashboard Polling
**File**: `admin/index.php`
**Issue**: Polls API every 30 seconds, each poll = 200+ Supabase calls
**Fix**: Use Supabase Realtime subscriptions or increase poll interval to 60s+

### 14. Delete Lead Incomplete
**File**: `api/admin-api.php` → `deleteLead()`
**Issue**: Only deletes from submission table — leaves enrichment, activity logs, tracking session orphaned
**Fix**: Also delete from `lead_enrichment` and related `activity_logs`

### 15. Phone Validation
**Files**: Form handlers
**Issue**: No phone format validation — accepts any string
**Fix**: Validate format (10+ digits, optional +1 prefix)

---

## LOW PRIORITY / NICE TO HAVE

### 16. Pagination for Leads
**File**: `api/admin-api.php`
**Issue**: Hard-coded limit of 50 leads, no way to see older leads
**Fix**: Add offset/limit params, cursor-based pagination

### 17. Audit Logging
**Issue**: No record of admin actions (who viewed/deleted leads)
**Fix**: Create `audit_logs` table, log all admin API actions

### 18. Lead Deduplication
**Files**: Form handlers
**Issue**: Same email can submit multiple times
**Fix**: Check if email already exists before inserting

### 19. Enrichment Caching
**File**: `api/enrichment.php`
**Issue**: No caching of API responses — re-enriching same email makes duplicate API calls
**Fix**: Already deduplicates by email in lead_enrichment, but intermediate results not cached

### 20. Email Template System
**File**: `api/enrichment.php` → `sendEnrichmentEmail()`
**Issue**: HTML email template hardcoded in PHP string
**Fix**: Move to separate template file for easier editing

---

## Architecture V2 Vision

```
Frontend (index.php)
    ↓ forms + tracking
Shared FormProcessor
    ↓ validates, sanitizes, inserts
Supabase (tables)
    ↓ triggers
Background Enrichment Queue
    ↓ FullContact → Apollo → LinkedIn
Supabase (lead_enrichment)
    ↓ realtime
Admin Dashboard (WebSocket updates)
```

Key changes from current:
- **Async enrichment** — don't block form submission
- **Shared form processor** — eliminate duplication
- **Realtime dashboard** — no polling
- **Proper error handling** — retries, circuit breakers, user feedback
