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
    global $sb;
    $row = $sb->selectOne('settings', 'value', ['key=eq.notification_emails']);
    $raw = $row['value'] ?? (defined('NOTIFICATION_EMAIL') ? NOTIFICATION_EMAIL : '');
    return array_filter(array_map('trim', explode(',', $raw)));
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

    // ── Database insert + enrichment ────────────────────────────────
    $dbData = ($config['db_map'])($fields, $ip);

    try {
        $sb->insert($table, $dbData);

        $enrichArgs = isset($config['enrich_args'])
            ? ($config['enrich_args'])($fields)
            : [$fields['email'], $fields['firstName'] ?? '', $fields['lastName'] ?? '', $fields['phone'] ?? ''];
        call_user_func_array('enrichLead', $enrichArgs);
    } catch (Exception $e) {
        error_log("Database insert failed ($table): " . $e->getMessage());
    }

    // ── SMTP notification ───────────────────────────────────────────
    $subject   = $config['subject'];
    // Replace {placeholder} tokens in subject
    foreach ($fields as $k => $v) {
        $subject = str_replace('{' . $k . '}', $v, $subject);
    }

    $body = ($config['email_body'])($fields);

    // Get notification emails from settings table (comma-separated)
    $notifyEmails = getNotificationEmails();
    foreach ($notifyEmails as $notifyTo) {
        $sent = smtpSend(trim($notifyTo), $subject, $body, $email);
        if (!$sent) {
            error_log("Failed to send notification email to $notifyTo for $table");
        }
        $sb->insert('communications', [
            'lead_email' => $email,
            'direction' => 'internal',
            'channel' => 'email',
            'subject' => $subject,
            'body' => $body,
            'sender' => 'System',
            'recipient' => trim($notifyTo),
            'status' => $sent ? 'sent' : 'failed'
        ]);
    }

    // ── CSRF token regeneration ─────────────────────────────────────
    if ($useCsrf) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // ── Response ────────────────────────────────────────────────────
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $config['success_message'] ?? ($emailSent ? 'Submission successful' : 'Submission saved (email notification failed)'),
    ]);
}
