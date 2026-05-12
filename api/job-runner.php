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

    // Step 1: notification email(s) to the leasing team
    if (!in_array('notify_email', $done, true)) {
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

    $subject = $payload['subject'] ?? 'New Lead Submission';
    $body    = $payload['body']    ?? '';
    $to      = $payload['notify_emails'] ?? [];

    foreach ($to as $notifyTo) {
        $notifyTo = trim($notifyTo);
        if ($notifyTo === '') continue;

        $sent = smtpSend($notifyTo, $subject, $body, $leadEmail);
        if (!$sent) {
            error_log("Lead notification email to $notifyTo failed (subject: $subject)");
        }
        $sb->insert('communications', [
            'lead_email' => $leadEmail,
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
