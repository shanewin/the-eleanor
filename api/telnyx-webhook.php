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
        'status'           => 'received'
    ]);

    // Check if AI automation is allowed globally AND for this lead
    if (!isSMSAutomationAllowed()) {
        error_log("SMS automation disabled globally — skipping AI response for $normalizedPhone");
        return;
    }

    if (!isAIActiveForLead($normalizedPhone)) {
        error_log("AI paused for lead $normalizedPhone — skipping auto-response");
        return;
    }

    // Generate and send AI response
    $reply = generateAIResponse($normalizedPhone, $text);

    if ($reply) {
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
    } else {
        error_log("AI failed to generate response for $normalizedPhone");
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
