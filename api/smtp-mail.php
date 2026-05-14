<?php
/**
 * SMTP Email Sender via direct socket connection.
 * Uses SSL connection to smtp.gmail.com:465 (authenticated as leasing@eleanor.nyc).
 */

function smtpSend($to, $subject, $body, $replyTo = null, $isHtml = false, $extraHeaders = []) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from = SMTP_FROM;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'The Eleanor';

    $socket = @stream_socket_client(
        "ssl://$host:$port",
        $errno, $errstr, 30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    // Read greeting
    $greeting = smtpRead($socket);

    // EHLO
    fwrite($socket, "EHLO eleanor.nyc\r\n");
    smtpRead($socket); // Read full multi-line EHLO response

    // AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    smtpRead($socket);

    fwrite($socket, base64_encode($user) . "\r\n");
    smtpRead($socket);

    fwrite($socket, base64_encode($pass) . "\r\n");
    $authResponse = smtpRead($socket);
    if (strpos($authResponse, '235') === false) {
        error_log("SMTP auth failed: $authResponse");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<$from>\r\n");
    smtpRead($socket);

    // RCPT TO
    fwrite($socket, "RCPT TO:<$to>\r\n");
    smtpRead($socket);

    // DATA
    fwrite($socket, "DATA\r\n");
    smtpRead($socket);

    // Build message
    $message = "From: $fromName <$from>\r\n";
    $message .= "To: $to\r\n";
    $message .= "Subject: $subject\r\n";
    if ($replyTo) {
        $message .= "Reply-To: $replyTo\r\n";
    }
    $message .= "MIME-Version: 1.0\r\n";
    $contentType = $isHtml ? 'text/html' : 'text/plain';
    $message .= "Content-Type: $contentType; charset=UTF-8\r\n";
    $message .= "Date: " . date('r') . "\r\n";
    if (is_array($extraHeaders)) {
        foreach ($extraHeaders as $name => $value) {
            $name  = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $name);
            $value = str_replace(["\r", "\n"], '', (string) $value);
            if ($name !== '' && $value !== '') {
                $message .= "$name: $value\r\n";
            }
        }
    }
    $message .= "\r\n";
    // Escape dots at start of lines
    $message .= str_replace("\n.", "\n..", $body);
    $message .= "\r\n.\r\n";

    fwrite($socket, $message);
    $dataResponse = smtpRead($socket);
    $success = (strpos($dataResponse, '250') !== false);

    if (!$success) {
        error_log("SMTP send failed: $dataResponse");
    }

    fwrite($socket, "QUIT\r\n");
    @smtpRead($socket);
    fclose($socket);

    return $success;
}

/**
 * Read all available lines from SMTP socket.
 */
function smtpRead($socket) {
    $response = '';
    while ($line = @fgets($socket, 515)) {
        $response .= $line;
        // If 4th char is a space (not hyphen), this is the last line
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

/**
 * Read the owner-controlled automation toggles from the settings table.
 * Cached for 60 seconds. Missing keys default to 'on' so the system stays
 * safe when first deployed (before the owner has saved anything).
 *
 * Keys read:
 *   sms_enabled                  — gate for the welcome SMS step
 *   applicant_email_enabled      — gate for the applicant welcome email
 *   enrichment_email_enabled     — gate for the AI intelligence report
 *   owner_notification_enabled   — gate for the "new lead" email to owners
 *   applicant_email_instructions — optional Claude steer for the email opener
 */
function getAutomationSettings(): array {
    global $sb;
    static $cache = null;
    static $cachedAt = 0;

    if ($cache !== null && (time() - $cachedAt) < 60) {
        return $cache;
    }

    $defaults = [
        'sms_enabled'                  => 'on',
        'applicant_email_enabled'      => 'on',
        'enrichment_email_enabled'     => 'on',
        'owner_notification_enabled'   => 'on',
        'applicant_email_instructions' => '',
    ];

    $rows = $sb->select('settings', 'key,value', [
        'key=in.(' . implode(',', array_keys($defaults)) . ')',
    ]);
    $found = [];
    foreach ($rows as $r) {
        if (isset($r['key'])) $found[$r['key']] = $r['value'];
    }

    $cache = array_merge($defaults, $found);
    $cachedAt = time();
    return $cache;
}

function isAutomationEnabled(string $key): bool {
    $s = getAutomationSettings();
    return ($s[$key] ?? 'on') === 'on';
}

/**
 * Active owner email addresses — the recipients for all system notifications
 * (new leads, enrichment reports, tour scheduling). Replaces the legacy
 * settings.notification_emails field which predated owner/broker accounts.
 */
function getOwnerEmails(): array {
    global $sb;
    $rows = $sb->select('brokers', 'email,is_active,role', ['role=eq.owner']);
    $emails = [];
    foreach ($rows as $r) {
        if (($r['is_active'] ?? true) === false) continue;
        $e = trim($r['email'] ?? '');
        if ($e !== '') $emails[] = $e;
    }
    return array_values(array_unique($emails));
}

/**
 * Aggregate the tracking activity for a lead into a flat summary suitable for
 * embedding in an email. Joins waitlist_submissions / unit_inquiries /
 * mailing_list rows by email to collect their tracking_ids, then aggregates
 * activity_logs across all of those sessions.
 *
 * Always returns the full key set so callers don't need to defensively
 * isset()-check — keys map to empty values when no data exists.
 */
function getLeadEngagementSummary(string $email): array {
    global $sb;

    $summary = [
        'has_data'      => false,
        'session_count' => 0,
        'total_seconds' => 0,
        'first_seen'    => null,
        'last_seen'     => null,
        'device'        => null,
        'browser_os'    => null,
        'top_sections'  => [],
        'top_clicks'    => [],
    ];

    if ($email === '') return $summary;

    $trackingIds = [];
    foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
        $rows = $sb->select($table, 'tracking_id', ['email=eq.' . urlencode($email)]);
        foreach ($rows as $r) {
            if (!empty($r['tracking_id'])) $trackingIds[] = $r['tracking_id'];
        }
    }
    $trackingIds = array_values(array_unique($trackingIds));
    if (empty($trackingIds)) return $summary;

    $idList = '(' . implode(',', $trackingIds) . ')';
    $logs = $sb->select('activity_logs', 'event_type,event_name,event_data,created_at',
        ['session_id=in.' . $idList],
        'created_at.asc'
    );

    if (empty($logs)) return $summary;

    $sections = [];
    $clicks   = [];
    $totalSec = 0;
    $firstTs  = null;
    $lastTs   = null;

    foreach ($logs as $log) {
        $ts = $log['created_at'] ?? null;
        if ($ts) {
            if ($firstTs === null || $ts < $firstTs) $firstTs = $ts;
            if ($lastTs  === null || $ts > $lastTs)  $lastTs  = $ts;
        }

        $data = is_string($log['event_data']) ? json_decode($log['event_data'], true) : $log['event_data'];

        if ($log['event_type'] === 'visibility' && $log['event_name'] === 'section_leave' && is_array($data)) {
            $sec = trim((string) ($data['section'] ?? ''));
            $sec = $sec !== '' ? $sec : 'Unknown';
            $secs = (int) round((float) ($data['secondsSpent'] ?? 0));
            if ($secs > 0) {
                if (!isset($sections[$sec])) $sections[$sec] = 0;
                $sections[$sec] += $secs;
                $totalSec += $secs;
            }
        }

        if ($log['event_type'] === 'click' && $log['event_name'] === 'button_click' && is_array($data)) {
            $txt = trim((string) ($data['text'] ?? ''));
            if ($txt !== '') {
                if (!isset($clicks[$txt])) $clicks[$txt] = 0;
                $clicks[$txt]++;
            }
        }
    }

    arsort($sections);
    arsort($clicks);

    $topSections = [];
    foreach (array_slice($sections, 0, 5, true) as $name => $secs) {
        $topSections[] = ['name' => $name, 'seconds' => $secs];
    }
    $topClicks = [];
    foreach (array_slice($clicks, 0, 5, true) as $text => $count) {
        $topClicks[] = ['text' => $text, 'count' => $count];
    }

    $device = null;
    $browserOs = null;
    $sessionRows = $sb->select('tracking_sessions', 'id,user_agent', ['id=in.' . $idList]);
    if (!empty($sessionRows)) {
        $ua = $sessionRows[count($sessionRows) - 1]['user_agent'] ?? '';
        if ($ua !== '') {
            if (stripos($ua, 'iPhone') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'Mobile') !== false) {
                $device = 'Mobile';
            } elseif (stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false) {
                $device = 'Tablet';
            } else {
                $device = 'Desktop';
            }
            $browser = stripos($ua, 'Edg/')      !== false ? 'Edge'
                     : (stripos($ua, 'Chrome/')  !== false ? 'Chrome'
                     : (stripos($ua, 'Firefox/') !== false ? 'Firefox'
                     : (stripos($ua, 'Safari/')  !== false ? 'Safari' : null)));
            $os = stripos($ua, 'Mac OS X')   !== false ? 'macOS'
                : (stripos($ua, 'Windows')   !== false ? 'Windows'
                : (stripos($ua, 'iPhone OS') !== false ? 'iOS'
                : (stripos($ua, 'iPad')      !== false ? 'iPadOS'
                : (stripos($ua, 'Android')   !== false ? 'Android'
                : (stripos($ua, 'Linux')     !== false ? 'Linux' : null)))));
            if ($browser || $os) {
                $browserOs = trim(implode(' / ', array_filter([$browser, $os])));
            }
        }
    }

    $summary['has_data']      = true;
    $summary['session_count'] = count($trackingIds);
    $summary['total_seconds'] = $totalSec;
    $summary['first_seen']    = $firstTs;
    $summary['last_seen']     = $lastTs;
    $summary['device']        = $device;
    $summary['browser_os']    = $browserOs;
    $summary['top_sections']  = $topSections;
    $summary['top_clicks']    = $topClicks;
    return $summary;
}

/**
 * Email-notify owners + the assigned broker when a tour is scheduled,
 * rescheduled, or cancelled. Also logs each send into communications so the
 * lead's timeline reflects it.
 *
 * $tour expects keys: scheduled_at, lead_email, lead_phone, unit, broker_id, source
 * $eventType: 'scheduled' | 'rescheduled' | 'cancelled'
 */
function sendTourScheduledEmail(array $tour, string $eventType = 'scheduled'): void {
    global $sb;

    $leadEmail = $tour['lead_email'] ?? null;
    $leadPhone = $tour['lead_phone'] ?? null;
    $brokerId  = $tour['broker_id'] ?? null;
    $unit      = $tour['unit'] ?? '';
    $source    = $tour['source'] ?? 'manual';
    $scheduledAt = $tour['scheduled_at'] ?? '';

    if (!$scheduledAt) return;

    // Lead name lookup
    $leadName = '';
    if ($leadEmail) {
        foreach (['waitlist_submissions', 'unit_inquiries'] as $t) {
            $l = $sb->selectOne($t, 'first_name,last_name', ['email=eq.' . urlencode($leadEmail)]);
            if ($l) { $leadName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')); break; }
        }
    }
    if (!$leadName) $leadName = $leadEmail ?: ($leadPhone ?: 'Unknown lead');

    // Broker lookup
    $brokerName = '';
    $brokerEmail = '';
    if ($brokerId) {
        $b = $sb->selectOne('brokers', 'name,email', ['id=eq.' . intval($brokerId)]);
        if ($b) {
            $brokerName  = $b['name'] ?? '';
            $brokerEmail = $b['email'] ?? '';
        }
    }

    $tz = new DateTimeZone('America/New_York');
    $dt = new DateTime($scheduledAt, $tz);
    $when = $dt->format('l, F j, Y \\a\\t g:ia T');

    $verb = $eventType === 'rescheduled' ? 'Rescheduled' : ($eventType === 'cancelled' ? 'Cancelled' : 'Scheduled');
    $sourceLabel = $source === 'sms_ai' ? 'SMS AI conversation' : ($source === 'manual' ? 'Admin dashboard' : $source);

    $subject = "Tour {$verb}: {$leadName} — " . $dt->format('D, M j \\a\\t g:ia');

    $body = "<!DOCTYPE html><html><body style=\"font-family:Arial,Helvetica,sans-serif;color:#222;max-width:560px;margin:0 auto;padding:24px\">"
          . "<h2 style=\"margin:0 0 12px;font-weight:500\">Tour {$verb}</h2>"
          . "<p style=\"margin:0 0 16px;color:#666\">A tour was {$eventType} via {$sourceLabel}.</p>"
          . "<table cellpadding=\"6\" style=\"border-collapse:collapse;font-size:14px;width:100%\">"
          . "<tr><td style=\"color:#888;width:120px\">Lead</td><td><strong>" . htmlspecialchars($leadName) . "</strong></td></tr>"
          . ($leadEmail ? "<tr><td style=\"color:#888\">Email</td><td>" . htmlspecialchars($leadEmail) . "</td></tr>" : '')
          . ($leadPhone ? "<tr><td style=\"color:#888\">Phone</td><td>" . htmlspecialchars($leadPhone) . "</td></tr>" : '')
          . "<tr><td style=\"color:#888\">When</td><td><strong>" . htmlspecialchars($when) . "</strong></td></tr>"
          . ($unit ? "<tr><td style=\"color:#888\">Unit interest</td><td>" . htmlspecialchars($unit) . "</td></tr>" : '')
          . ($brokerName ? "<tr><td style=\"color:#888\">Broker</td><td>" . htmlspecialchars($brokerName) . "</td></tr>" : '')
          . "</table>"
          . "<p style=\"margin-top:24px\"><a href=\"https://eleanorbk.com/admin/calendar.php\" style=\"color:#5b9bf6\">Open Calendar →</a></p>"
          . "</body></html>";

    // Recipients: all active owners + assigned broker (deduped, broker may be an owner)
    $recipients = getOwnerEmails();
    if ($brokerEmail && !in_array($brokerEmail, $recipients, true)) {
        $recipients[] = $brokerEmail;
    }

    foreach ($recipients as $to) {
        $sent = smtpSend($to, $subject, $body, null, true);
        if (!$sent) {
            error_log("Tour {$eventType} email to {$to} failed (lead: {$leadName})");
        }
        if ($leadEmail) {
            $sb->insert('communications', [
                'lead_email' => $leadEmail,
                'direction'  => 'internal',
                'channel'    => 'email',
                'subject'    => $subject,
                'body'       => "Tour {$eventType} notification sent to {$to}.",
                'sender'     => 'System',
                'recipient'  => $to,
                'status'     => $sent ? 'sent' : 'failed',
            ]);
        }
    }
}
