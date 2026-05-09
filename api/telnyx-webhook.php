<?php
/**
 * Telnyx Inbound SMS Webhook
 * Receives incoming SMS messages and triggers AI auto-response.
 *
 * Configure this URL in your Telnyx Messaging Profile:
 * https://eleanorbk.com/api/telnyx-webhook.php
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/telnyx-sms.php';
require_once __DIR__ . '/sms-ai.php';

// Telnyx sends POST with JSON body
$rawBody = file_get_contents('php://input');

// ── Webhook Signature Verification (Ed25519) ──
// Telnyx signs webhooks with Ed25519. The public key is in your Telnyx portal
// (Mission Control > API Keys > Public Key). Set it in config.php as TELNYX_PUBLIC_KEY.
if (defined('TELNYX_PUBLIC_KEY') && TELNYX_PUBLIC_KEY) {
    $signature = $_SERVER['HTTP_TELNYX_SIGNATURE_ED25519'] ?? '';
    $timestamp = $_SERVER['HTTP_TELNYX_TIMESTAMP'] ?? '';

    if (empty($signature) || empty($timestamp)) {
        error_log("Telnyx webhook: missing signature headers");
        http_response_code(403);
        echo json_encode(['error' => 'Missing signature']);
        exit;
    }

    // Reject if timestamp is older than 5 minutes (replay protection)
    if (abs(time() - intval($timestamp)) > 300) {
        error_log("Telnyx webhook: timestamp too old ($timestamp)");
        http_response_code(403);
        echo json_encode(['error' => 'Stale timestamp']);
        exit;
    }

    // Verify: the signed content is "{timestamp}|{payload}"
    $signedPayload = $timestamp . '|' . $rawBody;
    $decodedSig = base64_decode($signature);
    $publicKey = base64_decode(TELNYX_PUBLIC_KEY);

    $valid = sodium_crypto_sign_verify_detached($decodedSig, $signedPayload, $publicKey);

    if (!$valid) {
        error_log("Telnyx webhook: invalid signature");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

$event = json_decode($rawBody, true);

// Log raw webhook for debugging
error_log("Telnyx webhook received: " . substr($rawBody, 0, 500));

// Respond immediately with 200 (Telnyx requires < 2 seconds)
http_response_code(200);
echo json_encode(['status' => 'received']);

// Flush output so Telnyx gets the 200 before we process
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

// ── Process the webhook event ──

$eventType = $event['data']['event_type'] ?? '';

// Idempotency check: skip if we already processed this event
$eventId = $event['data']['id'] ?? '';
if ($eventType === 'message.received' && $eventId) {
    $messageId = $event['data']['payload']['id'] ?? '';
    if ($messageId) {
        $existing = $sb->selectOne('sms_messages', 'id',
            ['telnyx_message_id=eq.' . urlencode($messageId)]);
        if ($existing) {
            error_log("Telnyx webhook: duplicate message $messageId — skipping");
            exit;
        }
    }
}

if ($eventType === 'message.received') {
    handleInboundSMS($event['data']['payload']);
} elseif ($eventType === 'message.finalized') {
    handleMessageStatus($event['data']['payload']);
}

/**
 * Handle an inbound SMS from a lead.
 */
function handleInboundSMS($payload) {
    global $sb;

    $fromPhone = $payload['from']['phone_number'] ?? '';
    $toPhone   = $payload['to'][0]['phone_number'] ?? ($payload['to']['phone_number'] ?? '');
    $text      = $payload['text'] ?? '';
    $messageId = $payload['id'] ?? '';

    if (empty($fromPhone) || empty($text)) {
        error_log("Telnyx webhook: missing from or text");
        return;
    }

    $normalizedPhone = normalizePhone($fromPhone);

    // Check for STOP/opt-out keywords
    $stopWords = ['stop', 'unsubscribe', 'cancel', 'quit', 'end'];
    if (in_array(strtolower(trim($text)), $stopWords)) {
        handleOptOut($normalizedPhone);
        return;
    }

    // Look up lead email by phone
    $leadEmail = findLeadEmailByPhone($normalizedPhone);

    // Store the inbound message
    $sb->insert('sms_messages', [
        'lead_phone'       => $normalizedPhone,
        'lead_email'       => $leadEmail,
        'direction'        => 'inbound',
        'sender_type'      => 'lead',
        'sender_name'      => null,
        'body'             => $text,
        'telnyx_message_id'=> $messageId,
        'status'           => 'received',
        'is_read'          => false
    ]);

    // Track last lead message time + reset follow-up counter
    $existingAuto = $sb->selectOne('sms_automation', 'id', ['lead_phone=eq.' . urlencode($normalizedPhone)]);
    if ($existingAuto) {
        $sb->update('sms_automation', [
            'last_lead_message_at' => date('c'),
            'followup_count'       => 0,
            'followup_status'      => 'none',
            'updated_at'           => date('c')
        ], ['lead_phone=eq.' . urlencode($normalizedPhone)]);
    }

    // Check if SMS is intentionally enabled (master toggle + campaign dates)
    if (!isSMSMasterEnabled()) {
        error_log("SMS automation disabled (master off or outside campaign) — skipping for $normalizedPhone");
        return;
    }

    // Check if within send window — if not, queue for later
    if (!isWithinSendWindow()) {
        queueSMSResponse($normalizedPhone, $leadEmail, $text, $messageId);
        error_log("Outside send window — queued AI response for $normalizedPhone");
        return;
    }

    if (!isAIActiveForLead($normalizedPhone)) {
        error_log("AI paused for lead $normalizedPhone — skipping auto-response");
        return;
    }

    // Rate limit: skip AI response if we replied to this phone in the last 60 seconds
    $recentReply = $sb->selectOne('sms_messages', 'created_at', [
        'lead_phone=eq.' . urlencode($normalizedPhone),
        'direction=eq.outbound',
        'sender_type=eq.ai',
        'created_at=gte.' . date('c', time() - 60)
    ]);
    if ($recentReply) {
        error_log("Rate limit — AI replied to $normalizedPhone within last 60s, skipping");
        return;
    }

    // Generate and send AI response
    $reply = generateAIResponse($normalizedPhone, $text);

    if ($reply) {
        // Check for handoff/not-interested tags
        $handoff = false;
        $notInterested = false;
        $handoffReason = null;

        if (strpos($reply, '[HANDOFF]') !== false) {
            $handoff = true;
            $reply = trim(str_replace('[HANDOFF]', '', $reply));
            $handoffReason = 'AI detected handoff trigger';
        } elseif (strpos($reply, '[NOT_INTERESTED]') !== false) {
            $notInterested = true;
            $reply = trim(str_replace('[NOT_INTERESTED]', '', $reply));
            $handoffReason = 'Lead not interested';
        }

        $result = sendSMS($normalizedPhone, $reply);

        // Store the outbound AI message
        $sb->insert('sms_messages', [
            'lead_phone'        => $normalizedPhone,
            'lead_email'        => $leadEmail,
            'direction'         => 'outbound',
            'sender_type'       => 'ai',
            'sender_name'       => 'Eleanor AI',
            'body'              => $reply,
            'telnyx_message_id' => $result['message_id'] ?? null,
            'status'            => $result['success'] ? 'sent' : 'failed'
        ]);

        // Auto-update lead status to Contacted
        if ($result['success'] && $leadEmail) {
            autoUpdateLeadStatusFromWebhook($leadEmail, 'Contacted');
        }

        // Handle handoff or not-interested
        if ($handoff || $notInterested) {
            $newStatus = $handoff ? 'paused_handoff' : 'paused_manual';

            // Pause AI automation
            $existing = $sb->selectOne('sms_automation', 'id',
                ['lead_phone=eq.' . urlencode($normalizedPhone)]);
            if ($existing) {
                $sb->update('sms_automation', [
                    'status'         => $newStatus,
                    'paused_by'      => 'AI System',
                    'handoff_reason' => $handoffReason,
                    'updated_at'     => date('c')
                ], ['lead_phone=eq.' . urlencode($normalizedPhone)]);
            } else {
                $sb->insert('sms_automation', [
                    'lead_phone'     => $normalizedPhone,
                    'lead_email'     => $leadEmail,
                    'status'         => $newStatus,
                    'paused_by'      => 'AI System',
                    'handoff_reason' => $handoffReason
                ]);
            }

            // Log system message in communications
            $sb->insert('communications', [
                'lead_email' => $leadEmail,
                'direction'  => 'internal',
                'channel'    => 'note',
                'subject'    => $handoff ? 'AI Handoff' : 'Lead Not Interested',
                'body'       => $handoff
                    ? 'AI automation paused — lead needs broker follow-up. Last message: "' . substr($text, 0, 200) . '"'
                    : 'AI automation paused — lead indicated they are not interested.',
                'sender'     => 'Eleanor AI',
                'status'     => 'system'
            ]);

            // Notify assigned broker via SMS
            if ($handoff) {
                notifyBrokerOfHandoff($normalizedPhone, $leadEmail, $text);
            }

            // Create notification
            $leadName = '';
            $assignedBrokerId = null;
            if ($leadEmail) {
                foreach (['waitlist_submissions', 'unit_inquiries'] as $_t) {
                    $_l = $sb->selectOne($_t, 'first_name,last_name,assigned_to', ['email=eq.' . urlencode($leadEmail)]);
                    if ($_l) {
                        $leadName = trim(($_l['first_name'] ?? '') . ' ' . ($_l['last_name'] ?? ''));
                        $assignedBrokerId = $_l['assigned_to'] ?? null;
                        break;
                    }
                }
            }
            $notifType = $handoff ? 'handoff' : 'follow_up_cold';
            $notifTitle = $handoff
                ? 'Handoff: ' . ($leadName ?: 'Lead') . ' needs follow-up'
                : 'Not Interested: ' . ($leadName ?: 'Lead');
            createNotificationFromWebhook($notifType, $notifTitle, substr($text, 0, 100), $leadEmail, $assignedBrokerId,
                $leadEmail ? '/admin/communications.php?email=' . urlencode($leadEmail) : null);

            error_log("AI handoff triggered for $normalizedPhone — reason: $handoffReason");
        }
    } else {
        error_log("AI failed to generate response for $normalizedPhone");
    }
}

/**
 * Notify the assigned broker when AI triggers a handoff.
 */
function notifyBrokerOfHandoff($leadPhone, $leadEmail, $lastMessage) {
    global $sb;

    // Find the lead's assigned broker
    $lead = null;
    foreach (['waitlist_submissions', 'unit_inquiries'] as $table) {
        if ($leadEmail) {
            $lead = $sb->selectOne($table, 'first_name,last_name,assigned_to',
                ['email=eq.' . urlencode($leadEmail)]);
            if ($lead) break;
        }
    }

    if (!$lead || empty($lead['assigned_to'])) {
        error_log("Handoff notification: no assigned broker for $leadEmail");
        return;
    }

    $broker = $sb->selectOne('brokers', 'name,phone', ['id=eq.' . $lead['assigned_to']]);
    if (!$broker || empty($broker['phone'])) {
        error_log("Handoff notification: broker has no phone number");
        return;
    }

    $leadName = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
    $preview = substr($lastMessage, 0, 100);

    $notifyMsg = "Eleanor Alert: {$leadName} needs follow-up. Their last message: \"{$preview}\" — Open the dashboard to respond.";

    require_once __DIR__ . '/telnyx-sms.php';
    $brokerPhone = normalizePhone($broker['phone']);
    if ($brokerPhone) {
        sendSMS($brokerPhone, $notifyMsg);
        error_log("Handoff notification sent to broker {$broker['name']} at {$brokerPhone}");
    }
}

/**
 * Handle opt-out (STOP keyword).
 */
function handleOptOut($phone) {
    global $sb;

    // Update or create automation record
    $existing = $sb->selectOne('sms_automation', 'id', ['lead_phone=eq.' . urlencode($phone)]);

    if ($existing) {
        $sb->update('sms_automation', [
            'status'     => 'paused_optout',
            'updated_at' => date('c')
        ], ['lead_phone=eq.' . urlencode($phone)]);
    } else {
        $sb->insert('sms_automation', [
            'lead_phone' => $phone,
            'lead_email' => findLeadEmailByPhone($phone),
            'status'     => 'paused_optout'
        ]);
    }

    error_log("Lead $phone opted out of SMS");
}

/**
 * Handle delivery status updates.
 */
function handleMessageStatus($payload) {
    global $sb;

    $messageId = $payload['id'] ?? '';
    $status    = $payload['to'][0]['status'] ?? '';

    if ($messageId && $status) {
        $sb->update('sms_messages',
            ['status' => $status],
            ['telnyx_message_id=eq.' . urlencode($messageId)]
        );
    }
}

/**
 * Find a lead's email by their phone number.
 */
function findLeadEmailByPhone($phone) {
    global $sb;

    $phoneDigits = preg_replace('/\D/', '', $phone);

    foreach (['waitlist_submissions', 'unit_inquiries'] as $table) {
        $rows = $sb->select($table, 'email,phone');
        foreach ($rows as $row) {
            $rowPhone = preg_replace('/\D/', '', $row['phone'] ?? '');
            if ($rowPhone && $rowPhone === $phoneDigits) {
                return $row['email'];
            }
        }
    }

    return null;
}

/**
 * Auto-update lead status (webhook-safe — no auth dependency).
 * Only upgrades, never downgrades.
 */
function autoUpdateLeadStatusFromWebhook($email, $newStatus) {
    global $sb;
    if (!$email) return;

    $hierarchy = ['New' => 0, 'Contacted' => 1, 'Showing Scheduled' => 2, 'Showed' => 3, 'Applied' => 4, 'Leased' => 5, 'Lost' => 6];
    $newRank = $hierarchy[$newStatus] ?? -1;
    if ($newRank < 0) return;

    foreach (['waitlist_submissions', 'unit_inquiries'] as $table) {
        $lead = $sb->selectOne($table, 'lead_status', ['email=eq.' . urlencode($email)]);
        if ($lead) {
            $currentStatus = $lead['lead_status'] ?? 'New';
            $currentRank = $hierarchy[$currentStatus] ?? 0;
            if ($newRank > $currentRank) {
                $sb->update($table, ['lead_status' => $newStatus], ['email=eq.' . urlencode($email)]);
                $sb->insert('communications', [
                    'lead_email' => $email,
                    'direction'  => 'internal',
                    'channel'    => 'note',
                    'subject'    => 'Status auto-updated to: ' . $newStatus,
                    'sender'     => 'System',
                    'status'     => 'system'
                ]);
            }
            return;
        }
    }
}
