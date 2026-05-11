<?php
/**
 * Shared form processor for all lead-capture endpoints.
 *
 * Usage: each handler defines a config array and calls processForm($config).
 *
 * Config keys:
 *   'table'           => Supabase table name (required)
 *   'required'        => array of required POST field names (required)
 *   'subject'         => email subject line — may contain {placeholders} (required)
 *   'email_body'      => callable($fields): string  (required)
 *   'fields'          => callable(): array of sanitised field values (required)
 *   'db_map'          => callable($fields, $ip): array for Supabase insert (required)
 *   'success_message' => response message on success (optional)
 *   'use_csrf'        => bool, default true
 *   'use_cors'        => bool, default false
 *   'cors_origins'    => array of allowed origins when use_cors is true
 *   'has_phone'       => bool, default true — enable phone validation
 *   'enrich_args'     => callable($fields): array of args for enrichLead (optional)
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 0);

// Disable on-the-fly gzip so Content-Length is accurate when we flush early.
// Must be set before any output. Hostinger's LiteSpeed sometimes enables it.
@ini_set('zlib.output_compression', '0');

/**
 * Sanitise a single POST value.
 */
function clean(string $field): string {
    return htmlspecialchars(trim($_POST[$field] ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Get notification email addresses from settings table.
 * Falls back to NOTIFICATION_EMAIL constant if settings unavailable.
 */
function getNotificationEmails(): array {
    require_once __DIR__ . '/smtp-mail.php';
    $owners = getOwnerEmails();
    if (!empty($owners)) return $owners;
    // Fallback: NOTIFICATION_EMAIL constant if no owners exist yet
    if (defined('NOTIFICATION_EMAIL') && NOTIFICATION_EMAIL) {
        return array_filter(array_map('trim', explode(',', NOTIFICATION_EMAIL)));
    }
    return [];
}

/**
 * Main processor — called by each thin wrapper.
 */
function processForm(array $config): void {
    global $sb;

    $useCsrf  = $config['use_csrf']  ?? true;
    $useCors  = $config['use_cors']  ?? false;
    $hasPhone = $config['has_phone'] ?? true;

    // ── Session (only when CSRF is needed) ──────────────────────────
    if ($useCsrf) {
        session_start([
            'cookie_secure'   => isset($_SERVER['HTTPS']),
            'cookie_httponly'  => true,
            'cookie_samesite'  => 'Lax',
        ]);
    }

    // ── Includes ────────────────────────────────────────────────────
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/enrichment.php';
    require_once __DIR__ . '/smtp-mail.php';

    // ── Security headers ────────────────────────────────────────────
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Content-Type: application/json');

    // ── CORS (email-list style) ─────────────────────────────────────
    if ($useCors) {
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $config['cors_origins'] ?? [];
        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: POST');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    // ── Method check ────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }

    // ── CSRF validation ─────────────────────────────────────────────
    if ($useCsrf) {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }

    // ── Required fields ─────────────────────────────────────────────
    foreach ($config['required'] as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit;
        }
    }

    // ── Email validation ────────────────────────────────────────────
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }

    // ── Phone validation ────────────────────────────────────────────
    if ($hasPhone) {
        $rawPhone = $_POST['phone'] ?? '';
        $digits   = preg_replace('/\D/', '', $rawPhone);
        // Strip leading country code 1 for length check
        if (strlen($digits) > 10 && $digits[0] === '1') {
            $digitsForCheck = substr($digits, 1);
        } else {
            $digitsForCheck = $digits;
        }
        if (strlen($digitsForCheck) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Phone number must be at least 10 digits']);
            exit;
        }
    }

    // ── Custom validation (e.g. consent check) ───────────────────────
    if (isset($config['validate'])) {
        $error = ($config['validate'])();
        if ($error) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    }

    // ── Build field values via handler callback ─────────────────────
    $fields          = ($config['fields'])();
    $fields['email'] = $email;  // always use the validated email
    $ip              = $_SERVER['REMOTE_ADDR'];
    $table           = $config['table'];

    // Normalize phone to E.164 format (+16317596760) for consistent storage
    if (!empty($fields['phone'])) {
        require_once __DIR__ . '/telnyx-sms.php';
        $normalised = normalizePhone($fields['phone']);
        if ($normalised) {
            $fields['phone'] = $normalised;
        }
    }

    // ── Rate limiting (same IP within last 60 s) ────────────────────
    $cutoff  = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
    $recent  = $sb->select($table, 'id', [
        'ip_address=eq.' . $ip,
        'created_at=gte.' . $cutoff,
    ], null, 1);

    if (!empty($recent)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Please wait a moment and try again.']);
        exit;
    }

    // ── Build the notification email + enrichment args eagerly ──────
    // We need these now so they can be stored in the job payload and run
    // asynchronously after the response is flushed.
    $subject = $config['subject'];
    foreach ($fields as $k => $v) {
        $subject = str_replace('{' . $k . '}', $v, $subject);
    }
    $body         = ($config['email_body'])($fields);
    $notifyEmails = getNotificationEmails();
    $enrichArgs   = isset($config['enrich_args'])
        ? ($config['enrich_args'])($fields)
        : [$fields['email'], $fields['firstName'] ?? '', $fields['lastName'] ?? '', $fields['phone'] ?? ''];

    // ── Database insert ─────────────────────────────────────────────
    $dbData   = ($config['db_map'])($fields, $ip);
    $inserted = $sb->insert($table, $dbData);
    $sourceId = (is_array($inserted) && isset($inserted['id'])) ? (int) $inserted['id'] : 0;

    // ── Enqueue background job ──────────────────────────────────────
    // If the lead row insert succeeded, write a job row and run it inline after
    // the HTTP response is flushed. The cron-runner picks up anything that
    // doesn't finish here.
    $jobId = 0;
    if ($sourceId > 0) {
        $jobRow = $sb->insert('lead_processing_jobs', [
            'source_table' => $table,
            'source_id'    => $sourceId,
            'lead_email'   => $email,
            'lead_phone'   => $fields['phone'] ?? null,
            'payload'      => [
                'fields'          => $fields,
                'subject'         => $subject,
                'body'            => $body,
                'notify_emails'   => $notifyEmails,
                'enrich_args'     => $enrichArgs,
                'is_mailing_list' => $table === 'mailing_list',
            ],
        ]);
        if (is_array($jobRow) && isset($jobRow['id'])) {
            $jobId = (int) $jobRow['id'];
        }
    } else {
        error_log("Database insert failed ($table) — falling back to inline notification send");
    }

    // ── CSRF token regeneration (must happen before we close the session) ──
    if ($useCsrf) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // ── Build response body so Content-Length can be exact ──────────
    $responseBody = json_encode([
        'success' => true,
        'message' => $config['success_message'] ?? 'Submission successful',
    ]);

    // Release the session lock before flushing — keeps other requests from this
    // user from blocking on the session file while our tail keeps running.
    if ($useCsrf && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Tell the client exactly how many bytes are coming, and to close after.
    // Without Content-Length, LiteSpeed/keep-alive holds the connection open
    // until the PHP worker exits, which defeats the early flush.
    http_response_code(200);
    header('Content-Length: ' . strlen($responseBody));
    header('Connection: close');
    echo $responseBody;

    // Drain every output buffer layer (php.ini output_buffering, any gzip
    // handler, etc.) so the bytes actually leave PHP.
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();

    // Detach from the client. LiteSpeed has its own native function; PHP-FPM
    // uses fastcgi_finish_request(). Whichever exists, use it.
    if (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    ignore_user_abort(true);

    // ── Post-response work ──────────────────────────────────────────
    // Give the slow API cascade headroom; default max_execution_time is too tight.
    @set_time_limit(120);

    if ($jobId > 0) {
        require_once __DIR__ . '/job-runner.php';
        try {
            runLeadProcessingJob($jobId);
        } catch (Throwable $e) {
            error_log("Inline job run failed for $jobId: " . $e->getMessage());
            // The job row stays pending/running; cron will rescue it.
        }
    } else {
        // No job row (source insert failed). Best-effort inline notification so
        // the leasing team still hears about it.
        foreach ($notifyEmails as $notifyTo) {
            $notifyTo = trim($notifyTo);
            if ($notifyTo === '') continue;
            $sent = smtpSend($notifyTo, $subject, $body, $email);
            $sb->insert('communications', [
                'lead_email' => $email,
                'direction'  => 'internal',
                'channel'    => 'email',
                'subject'    => $subject,
                'body'       => $body,
                'sender'     => 'System',
                'recipient'  => $notifyTo,
                'status'     => $sent ? 'sent' : 'failed',
            ]);
        }
    }
}
