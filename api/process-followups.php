<?php
/**
 * Stale Lead Follow-Up Processor
 * Run via cron every 30 minutes:
 *   Every 30 min: php /home/u161465571/domains/eleanorbk.com/public_html/api/process-followups.php
 *
 * Finds leads who haven't responded in 3+ days and sends contextual follow-ups.
 * Max 2 attempts per lead (day 3 and day 6), then marks as cold.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/sms-ai.php';
require_once __DIR__ . '/telnyx-sms.php';
require_once __DIR__ . '/cron-helpers.php';

cronRunStart('process-followups');

// Only process if within send window
if (!isSMSMasterEnabled() || !isWithinSendWindow()) {
    return;
}

$now = time();
$threeDaysAgo = urlencode(date('c', $now - (3 * 86400)));
$sixDaysAgo   = urlencode(date('c', $now - (6 * 86400)));

// Find leads due for first follow-up (3+ days silent, no follow-ups sent yet)
$dueFirst = $sb->select('sms_automation', '*', [
    'status=eq.active',
    'followup_status=eq.none',
    'last_lead_message_at=lte.' . $threeDaysAgo,
    'last_lead_message_at=neq.'  // not null — they must have messaged at least once
], 'last_lead_message_at.asc', 5);

// Find leads due for second follow-up (6+ days silent, first follow-up already sent)
$dueSecond = $sb->select('sms_automation', '*', [
    'status=eq.active',
    'followup_status=eq.sent_1',
    'last_lead_message_at=lte.' . $sixDaysAgo
], 'last_lead_message_at.asc', 5);

// Combine and process
$toProcess = [];
foreach ($dueFirst as $r) $toProcess[] = ['record' => $r, 'attempt' => 1];
foreach ($dueSecond as $r) $toProcess[] = ['record' => $r, 'attempt' => 2];

// Limit total per run
$toProcess = array_slice($toProcess, 0, 5);

if (empty($toProcess)) {
    return;
}

foreach ($toProcess as $item) {
    $record = $item['record'];
    $attempt = $item['attempt'];
    $phone = $record['lead_phone'];
    $email = $record['lead_email'];

    // Skip if a tour is already booked for this lead
    if ($email) {
        $tour = $sb->selectOne('tour_requests', 'id', [
            'lead_email=eq.' . urlencode($email),
            'status=eq.confirmed'
        ]);
        if ($tour) {
            error_log("Follow-up: skipping $phone — tour already booked");
            continue;
        }
    }

    // Generate follow-up message
    $reply = generateFollowupMessage($phone, $attempt);

    if (!$reply) {
        error_log("Follow-up: AI failed to generate message for $phone (attempt $attempt)");
        continue;
    }

    // Send via Telnyx
    $result = sendSMS($phone, $reply);

    if ($result['success']) {
        cronRunIncItems(1);
        // Log the message
        $sb->insert('sms_messages', [
            'lead_phone'        => $phone,
            'lead_email'        => $email,
            'direction'         => 'outbound',
            'sender_type'       => 'ai',
            'sender_name'       => 'Eleanor AI',
            'body'              => $reply,
            'telnyx_message_id' => $result['message_id'] ?? null,
            'status'            => 'sent'
        ]);

        // Update follow-up status
        $newStatus = $attempt === 1 ? 'sent_1' : 'sent_2';
        $sb->update('sms_automation', [
            'followup_count'  => $attempt,
            'followup_status' => $newStatus,
            'updated_at'      => date('c')
        ], ['lead_phone=eq.' . urlencode($phone)]);

        // If second attempt, mark as cold and notify
        if ($attempt === 2) {
            $sb->update('sms_automation', [
                'followup_status' => 'cold'
            ], ['lead_phone=eq.' . urlencode($phone)]);

            // Find lead name and assigned broker for notification
            $coldLeadName = '';
            $coldBrokerId = null;
            if ($email) {
                foreach (['waitlist_submissions', 'unit_inquiries'] as $t) {
                    $l = $sb->selectOne($t, 'first_name,last_name,assigned_to', ['email=eq.' . urlencode($email)]);
                    if ($l) {
                        $coldLeadName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
                        $coldBrokerId = $l['assigned_to'] ?? null;
                        break;
                    }
                }
            }
            createNotificationFromWebhook('follow_up_cold',
                'Lead Gone Cold: ' . ($coldLeadName ?: 'Lead'),
                'No response after 2 follow-ups',
                $email, $coldBrokerId,
                $email ? '/admin/communications.php?email=' . urlencode($email) : null);
        }

        error_log("Follow-up $attempt sent to $phone");
    } else {
        error_log("Follow-up: send failed for $phone — " . ($result['error'] ?? 'unknown'));
    }
}
