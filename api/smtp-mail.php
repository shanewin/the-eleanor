<?php
/**
 * SMTP Email Sender via direct socket connection.
 * Uses SSL connection to smtp.hostinger.com:465.
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
    fwrite($socket, "EHLO eleanorbk.com\r\n");
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
