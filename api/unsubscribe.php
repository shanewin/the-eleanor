<?php
/**
 * Applicant unsubscribe endpoint.
 *
 * URL: /api/unsubscribe.php?e={url-encoded email}&t={hmac-sha256}
 * Token is hash_hmac('sha256', strtolower($email), UNSUBSCRIBE_SECRET).
 *
 * Honors RFC 8058 one-click unsubscribe — Gmail/Yahoo will POST here with
 * `List-Unsubscribe=One-Click` in the body when the user hits the in-inbox
 * unsubscribe button.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';

function unsubExpectedToken(string $email): string {
    return hash_hmac('sha256', strtolower(trim($email)), UNSUBSCRIBE_SECRET);
}

function unsubBuildLink(string $email): string {
    $token = unsubExpectedToken($email);
    return rtrim(APP_PUBLIC_URL, '/') . '/api/unsubscribe.php?e=' . urlencode($email) . '&t=' . $token;
}

// Allow these helpers to be loaded by applicant-email.php without rendering a page.
if (defined('UNSUBSCRIBE_INCLUDE_ONLY')) {
    return;
}

$email = isset($_GET['e']) ? trim((string) $_GET['e']) : '';
$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';

$ok = false;
if ($email !== '' && $token !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if (hash_equals(unsubExpectedToken($email), $token)) {
        $ok = true;
    }
}

if ($ok) {
    global $sb;
    $existing = $sb->selectOne('email_suppressions', 'email', ['email=eq.' . urlencode(strtolower($email))]);
    if (!$existing) {
        $sb->insert('email_suppressions', [
            'email'      => strtolower($email),
            'source'     => 'applicant_email',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
    // Also log it to communications so it shows up in the lead's timeline.
    $sb->insert('communications', [
        'lead_email' => strtolower($email),
        'direction'  => 'inbound',
        'channel'    => 'note',
        'subject'    => 'Unsubscribed from emails',
        'body'       => 'Applicant clicked the unsubscribe link in The Eleanor email.',
        'sender'     => $email,
        'status'     => 'system',
    ]);
}

// One-click POST (RFC 8058) — return 200 with no body.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code($ok ? 200 : 400);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$displayEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$title = $ok ? 'You\'re unsubscribed' : 'Link expired or invalid';
$message = $ok
    ? "We won't send <strong>$displayEmail</strong> any more emails from The Eleanor."
    : "This unsubscribe link couldn't be verified. If you got here by mistake, just close this window — nothing has changed.";

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= $title ?> · The Eleanor</title>
<style>
    body { margin:0; padding:0; background:#111; color:#eee; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; }
    .wrap { max-width:480px; margin:80px auto; padding:40px 32px; background:#1a1a1a; border-radius:8px; text-align:center; }
    .eyebrow { font-size:10px; text-transform:uppercase; letter-spacing:4px; color:#888; }
    h1 { font-weight:300; font-size:24px; letter-spacing:1px; margin:18px 0 12px; color:#fff; }
    .accent { width:40px; height:2px; background:#5b9bf6; margin:14px auto; }
    p { font-size:15px; color:#bbb; line-height:1.6; margin:14px 0; }
    .footer { margin-top:32px; padding-top:20px; border-top:1px solid #2a2a2a; font-size:12px; color:#666; }
    a { color:#5b9bf6; text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
    <div class="eyebrow">The Eleanor</div>
    <h1><?= $title ?></h1>
    <div class="accent"></div>
    <p><?= $message ?></p>
    <?php if ($ok): ?>
        <p style="font-size:13px;color:#888">You'll still hear from us if you book a tour or reach out directly.</p>
    <?php endif; ?>
    <div class="footer">
        The Eleanor · <?= htmlspecialchars(THE_ELEANOR_ADDRESS) ?><br>
        <a href="https://eleanor.nyc">eleanor.nyc</a>
    </div>
</div>
</body>
</html>
