<?php
/**
 * Background worker for lead_processing_jobs.
 *
 * A job row is written by form-processor.php after the lead is saved. Each job
 * has three steps (notify_email, enrich, sms_welcome) tracked in steps_done so
 * retries skip work that already succeeded.
 *
 * Two entry points run jobs:
 *   - form-processor.php calls runLeadProcessingJob() inline, after the HTTP
 *     response has been flushed via fastcgi_finish_request().
 *   - cron-runner.php → process-lead-jobs.php drains anything still pending.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp-mail.php';
require_once __DIR__ . '/enrichment.php';
require_once __DIR__ . '/applicant-email.php';

const LEAD_JOB_MAX_ATTEMPTS = 3;
const LEAD_JOB_STALE_LOCK_SECONDS = 300; // 5 min — cron rescue cutoff

/**
 * Atomically claim a pending (or stale-locked) job and run all remaining steps.
 * Returns true if this caller claimed and processed the job.
 */
function runLeadProcessingJob(int $jobId): bool {
    global $sb;

    $job = $sb->selectOne('lead_processing_jobs', '*', ['id=eq.' . $jobId]);
    if (!$job) {
        return false;
    }
    if ($job['status'] === 'done' || $job['status'] === 'failed') {
        return false;
    }

    // Only claim if pending, or running with a stale lock.
    $isStale = $job['status'] === 'running'
        && !empty($job['locked_at'])
        && (time() - strtotime($job['locked_at'])) > LEAD_JOB_STALE_LOCK_SECONDS;

    if ($job['status'] !== 'pending' && !$isStale) {
        return false;
    }

    $statusFilter = $isStale ? 'status=eq.running' : 'status=eq.pending';
    $claim = $sb->update('lead_processing_jobs', [
        'status'     => 'running',
        'locked_at'  => date('c'),
        'attempts'   => ($job['attempts'] ?? 0) + 1,
        'updated_at' => date('c'),
    ], ['id=eq.' . $jobId, $statusFilter]);

    // PostgREST returns the patched rows; empty array = we lost the race.
    if (empty($claim) || !is_array($claim)) {
        return false;
    }

    $payload = is_string($job['payload']) ? json_decode($job['payload'], true) : $job['payload'];
    $done    = $job['steps_done'] ?? [];
    $errors  = [];

    // Step 1: notification email(s) to the leasing team (owner master toggle)
    $skipOwnerNotify = !isAutomationEnabled('owner_notification_enabled');
    if (!$skipOwnerNotify && !in_array('notify_email', $done, true)) {
        try {
            sendLeadNotificationEmails($payload, $job['lead_email']);
            $done[] = 'notify_email';
            $sb->update('lead_processing_jobs',
                ['steps_done' => $done, 'updated_at' => date('c')],
                ['id=eq.' . $jobId]);
        } catch (Throwable $e) {
            $errors[] = 'notify_email: ' . $e->getMessage();
            error_log("Lead job $jobId notify_email failed: " . $e->getMessage());
        }
    } elseif ($skipOwnerNotify && !in_array('notify_email', $done, true)) {
        $done[] = 'notify_email';
    }

    // Step 2: enrichment (PDL / Apollo / LinkedIn / Anthropic cascade)
    if (!in_array('enrich', $done, true)) {
        try {
            $args = $payload['enrich_args'] ?? [
                $job['lead_email'],
                $payload['fields']['firstName'] ?? '',
                $payload['fields']['lastName'] ?? '',
                $payload['fields']['phone'] ?? '',
            ];
            call_user_func_array('enrichLead', $args);
            $done[] = 'enrich';
            $sb->update('lead_processing_jobs',
                ['steps_done' => $done, 'updated_at' => date('c')],
                ['id=eq.' . $jobId]);
        } catch (Throwable $e) {
            $errors[] = 'enrich: ' . $e->getMessage();
            error_log("Lead job $jobId enrich failed: " . $e->getMessage());
        }
    }

    // Step 3: welcome SMS via Telnyx + Claude (skip mailing list, skip if no phone)
    $skipSms = empty($job['lead_phone']) || !empty($payload['is_mailing_list']);
    if (!$skipSms && !in_array('sms_welcome', $done, true)) {
        try {
            sendWelcomeSms($job['lead_phone'], $job['lead_email']);
            $done[] = 'sms_welcome';
            $sb->update('lead_processing_jobs',
                ['steps_done' => $done, 'updated_at' => date('c')],
                ['id=eq.' . $jobId]);
        } catch (Throwable $e) {
            $errors[] = 'sms_welcome: ' . $e->getMessage();
            error_log("Lead job $jobId sms_welcome failed: " . $e->getMessage());
        }
    } elseif ($skipSms && !in_array('sms_welcome', $done, true)) {
        $done[] = 'sms_welcome';
    }

    // Step 4: applicant welcome email (parallel with SMS — independent send).
    // Skip for mailing-list signups; applicant-email.php also enforces this and
    // the email_suppressions list internally.
    $skipApplicantEmail = !empty($payload['is_mailing_list']);
    if (!$skipApplicantEmail && !in_array('applicant_email', $done, true)) {
        try {
            sendApplicantEmail($payload, $job['lead_email']);
            $done[] = 'applicant_email';
            $sb->update('lead_processing_jobs',
                ['steps_done' => $done, 'updated_at' => date('c')],
                ['id=eq.' . $jobId]);
        } catch (Throwable $e) {
            $errors[] = 'applicant_email: ' . $e->getMessage();
            error_log("Lead job $jobId applicant_email failed: " . $e->getMessage());
        }
    } elseif ($skipApplicantEmail && !in_array('applicant_email', $done, true)) {
        $done[] = 'applicant_email';
    }

    $allDone = in_array('notify_email', $done, true)
        && in_array('enrich', $done, true)
        && in_array('sms_welcome', $done, true)
        && in_array('applicant_email', $done, true);

    if ($allDone) {
        $sb->update('lead_processing_jobs', [
            'status'     => 'done',
            'steps_done' => $done,
            'locked_at'  => null,
            'last_error' => empty($errors) ? null : implode(' | ', $errors),
            'updated_at' => date('c'),
        ], ['id=eq.' . $jobId]);
        return true;
    }

    // Partial — release the lock so cron can retry, or mark failed if exhausted.
    $exhausted = ($job['attempts'] ?? 0) + 1 >= LEAD_JOB_MAX_ATTEMPTS;
    $sb->update('lead_processing_jobs', [
        'status'     => $exhausted ? 'failed' : 'pending',
        'steps_done' => $done,
        'locked_at'  => null,
        'last_error' => implode(' | ', $errors),
        'updated_at' => date('c'),
    ], ['id=eq.' . $jobId]);

    return true;
}

/**
 * Send notification email(s) to the leasing team and log each as a
 * communications row. Extracted from form-processor.php.
 */
function sendLeadNotificationEmails(array $payload, string $leadEmail): void {
    global $sb;

    $subject     = $payload['subject'] ?? 'New Lead Submission';
    $plaintext   = $payload['body']    ?? '';
    $to          = $payload['notify_emails'] ?? [];

    $engagement = function_exists('getLeadEngagementSummary')
        ? getLeadEngagementSummary($leadEmail)
        : ['has_data' => false];
    $htmlBody = buildLeadNotificationHtml($payload, $leadEmail, $engagement);

    foreach ($to as $notifyTo) {
        $notifyTo = trim($notifyTo);
        if ($notifyTo === '') continue;

        $sent = smtpSend($notifyTo, $subject, $htmlBody, $leadEmail, true);
        if (!$sent) {
            error_log("Lead notification email to $notifyTo failed (subject: $subject)");
        }
        $sb->insert('communications', [
            'lead_email' => $leadEmail,
            'direction'  => 'internal',
            'channel'    => 'email',
            'subject'    => $subject,
            'body'       => $plaintext,
            'sender'     => 'System',
            'recipient'  => $notifyTo,
            'status'     => $sent ? 'sent' : 'failed',
        ]);
    }
}

/**
 * Render the HTML body for a new-lead notification email. Utility-dashboard
 * styling: white card, near-black text, monospace data, table layout for
 * email-client compatibility.
 */
function buildLeadNotificationHtml(array $payload, string $leadEmail, array $engagement): string {
    $subject = $payload['subject'] ?? 'New Lead Submission';

    $displayFields = $payload['display_fields'] ?? [];
    if (empty($displayFields)) {
        // Backward-compat: parse the plaintext body line by line if a job
        // pre-dates the display_fields payload key.
        $displayFields = parsePlaintextBodyToFields($payload['body'] ?? '');
    }

    $rowsHtml = '';
    foreach ($displayFields as $label => $value) {
        $val = trim((string) $value);
        if ($val === '') $val = '—';
        $rowsHtml .= '<tr>'
            . '<td style="padding:6px 16px 6px 0;color:#57534e;font-size:13px;vertical-align:top;white-space:nowrap">'
            . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
            . '</td>'
            . '<td style="padding:6px 0;color:#1c1917;font-size:13px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;word-break:break-word">'
            . nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'))
            . '</td>'
            . '</tr>';
    }

    $engagementHtml = buildEngagementCardHtml($engagement);

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f5f4;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f5f4;padding:32px 16px">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e7e5e4;border-radius:8px;overflow:hidden">'

        // Header
        . '<tr><td style="padding:20px 24px;border-bottom:1px solid #e7e5e4">'
        . '<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#78716c;font-weight:600">The Eleanor</div>'
        . '<div style="margin-top:4px;font-size:17px;font-weight:600;color:#1c1917">'
        . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
        . '</div>'
        . '</td></tr>'

        // Lead details
        . '<tr><td style="padding:20px 24px">'
        . '<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#78716c;font-weight:600;margin-bottom:12px">Lead details</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">'
        . $rowsHtml
        . '</table>'
        . '</td></tr>'

        // Engagement
        . '<tr><td style="padding:0 24px 24px 24px">'
        . $engagementHtml
        . '</td></tr>'

        . '</table>'
        . '<div style="margin-top:16px;font-size:11px;color:#a8a29e">Sent by The Eleanor Command Center</div>'
        . '</td></tr></table></body></html>';
}

/**
 * Render the "Website behavior" card inside the notification email. Falls
 * back to a one-liner when no tracking data is linked to this lead.
 */
function buildEngagementCardHtml(array $e): string {
    $cardOpen  = '<div style="border-top:1px solid #e7e5e4;padding-top:16px">'
               . '<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#78716c;font-weight:600;margin-bottom:12px">Website behavior</div>';
    $cardClose = '</div>';

    if (empty($e['has_data'])) {
        return $cardOpen
            . '<div style="font-size:13px;color:#78716c;font-style:italic">No on-site activity recorded for this email yet.</div>'
            . $cardClose;
    }

    $rows = '';
    $rows .= engagementRow('Time on site', formatDurationShort((int) $e['total_seconds']));
    $rows .= engagementRow('Visits', $e['session_count'] . ($e['session_count'] === 1 ? ' session' : ' sessions'));
    if (!empty($e['first_seen'])) $rows .= engagementRow('First seen', formatTimestamp($e['first_seen']));
    if (!empty($e['last_seen']))  $rows .= engagementRow('Most recent', formatTimestamp($e['last_seen']));
    $deviceLabel = trim(($e['device'] ?? '') . ($e['browser_os'] ? ' · ' . $e['browser_os'] : ''));
    if ($deviceLabel !== '') $rows .= engagementRow('Device', $deviceLabel);

    $headlineTable = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:14px">' . $rows . '</table>';

    $sectionsList = '';
    if (!empty($e['top_sections'])) {
        $sectionsList .= '<div style="font-size:12px;color:#57534e;font-weight:600;margin:14px 0 6px 0">Most-viewed sections</div>';
        $sectionsList .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">';
        foreach ($e['top_sections'] as $s) {
            $sectionsList .= '<tr>'
                . '<td style="padding:3px 0;font-size:13px;color:#1c1917;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">'
                . htmlspecialchars((string) $s['name'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:3px 0;font-size:13px;color:#57534e;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;text-align:right">'
                . htmlspecialchars(formatDurationShort((int) $s['seconds']), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
        $sectionsList .= '</table>';
    }

    $clicksList = '';
    if (!empty($e['top_clicks'])) {
        $clicksList .= '<div style="font-size:12px;color:#57534e;font-weight:600;margin:14px 0 6px 0">Notable clicks</div>';
        $clicksList .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">';
        foreach ($e['top_clicks'] as $c) {
            $clicksList .= '<tr>'
                . '<td style="padding:3px 0;font-size:13px;color:#1c1917;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">"'
                . htmlspecialchars((string) $c['text'], ENT_QUOTES, 'UTF-8') . '"</td>'
                . '<td style="padding:3px 0;font-size:13px;color:#57534e;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;text-align:right">'
                . (int) $c['count'] . '×</td>'
                . '</tr>';
        }
        $clicksList .= '</table>';
    }

    return $cardOpen . $headlineTable . $sectionsList . $clicksList . $cardClose;
}

function engagementRow(string $label, string $value): string {
    return '<tr>'
        . '<td style="padding:4px 16px 4px 0;color:#57534e;font-size:13px;vertical-align:top;white-space:nowrap">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="padding:4px 0;color:#1c1917;font-size:13px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">'
        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}

function formatDurationShort(int $seconds): string {
    if ($seconds <= 0) return '0s';
    if ($seconds < 60) return $seconds . 's';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) return $h . 'h ' . $m . 'm';
    if ($m > 0 && $s > 0) return $m . 'm ' . $s . 's';
    return $m . 'm';
}

function formatTimestamp(string $iso): string {
    $ts = strtotime($iso);
    if ($ts === false) return $iso;
    return date('M j, g:i A', $ts);
}

function parsePlaintextBodyToFields(string $body): array {
    $out = [];
    foreach (explode("\n", $body) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') === false) continue;
        [$k, $v] = array_map('trim', explode(':', $line, 2));
        if ($k === '' || stripos($k, 'Inquiry details') !== false || stripos($k, 'Wait List Inquiry') !== false) continue;
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Generate and send the Claude-authored welcome SMS, and set up the automation
 * record. Extracted from form-processor.php.
 */
function sendWelcomeSms(string $leadPhone, string $leadEmail): void {
    global $sb;

    require_once __DIR__ . '/telnyx-sms.php';
    require_once __DIR__ . '/sms-ai.php';

    if (!isSMSAutomationAllowed() || !defined('TELNYX_FROM_NUMBER') || !TELNYX_FROM_NUMBER) {
        return;
    }

    $normalized = normalizePhone($leadPhone);
    if (!$normalized) {
        return;
    }

    $welcome = generateInitialMessage($normalized, $leadEmail);
    if (!$welcome) {
        throw new RuntimeException('generateInitialMessage returned empty');
    }

    $smsResult = sendSMS($normalized, $welcome);

    $sb->insert('sms_messages', [
        'lead_phone'        => $normalized,
        'lead_email'        => $leadEmail,
        'direction'         => 'outbound',
        'sender_type'       => 'ai',
        'sender_name'       => 'Eleanor AI',
        'body'              => $welcome,
        'telnyx_message_id' => $smsResult['message_id'] ?? null,
        'status'            => !empty($smsResult['success']) ? 'sent' : 'failed',
    ]);

    $sb->upsert('sms_automation', [
        'lead_phone' => $normalized,
        'lead_email' => $leadEmail,
        'status'     => 'active',
        'updated_at' => date('c'),
    ], 'lead_phone');

    if (empty($smsResult['success'])) {
        throw new RuntimeException('sendSMS failed: ' . ($smsResult['error'] ?? 'unknown'));
    }

    markFirstResponseIfUnset($leadEmail, 'sms');
}
