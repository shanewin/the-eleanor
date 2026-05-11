<?php
/**
 * SMS Queue Processor — run via cron every 5 minutes.
 * Processes queued inbound messages that arrived outside the send window.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/sms-ai.php';
require_once __DIR__ . '/telnyx-sms.php';

// Only process if we're within the send window now
if (!isSMSMasterEnabled()) {
    return;
}
if (!isWithinSendWindow()) {
    return;
}

// Fetch pending messages whose scheduled_for has passed (limit 10 per run)
$now = date('c');
$pending = $sb->select(
    'sms_queue',
    '*',
    ['status=eq.pending', 'scheduled_for=lte.' . $now],
    'scheduled_for.asc',
    10
);

if (empty($pending)) {
    return;
}

foreach ($pending as $queued) {
    $phone = $queued['lead_phone'];
    $email = $queued['lead_email'];
    $inboundBody = $queued['inbound_body'];
    $queueId = $queued['id'];
    $attempts = ($queued['attempts'] ?? 0) + 1;

    // Mark as processing
    $sb->update('sms_queue', [
        'status' => 'processing',
        'attempts' => $attempts
    ], ['id=eq.' . $queueId]);

    // Check if AI is still active for this lead (broker may have taken over)
    if (!isAIActiveForLead($phone)) {
        $sb->update('sms_queue', [
            'status' => 'failed',
            'error' => 'AI paused for lead',
            'processed_at' => date('c')
        ], ['id=eq.' . $queueId]);
        error_log("SMS queue: skipping $phone — AI paused");
        continue;
    }

    // Generate AI response
    $reply = generateAIResponse($phone, $inboundBody);

    if (!$reply) {
        $sb->update('sms_queue', [
            'status' => $attempts >= 3 ? 'failed' : 'pending',
            'error' => 'AI generation failed',
            'processed_at' => $attempts >= 3 ? date('c') : null
        ], ['id=eq.' . $queueId]);
        error_log("SMS queue: AI failed for $phone (attempt $attempts)");
        continue;
    }

    // Send via Telnyx
    $result = sendSMS($phone, $reply);

    if ($result['success']) {
        // Log outbound message
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

        // Mark queue as sent
        $sb->update('sms_queue', [
            'status' => 'sent',
            'processed_at' => date('c')
        ], ['id=eq.' . $queueId]);

        markFirstResponseIfUnset($email, 'sms');

        error_log("SMS queue: sent delayed response to $phone");
    } else {
        $sb->update('sms_queue', [
            'status' => $attempts >= 3 ? 'failed' : 'pending',
            'error' => $result['error'] ?? 'Send failed',
            'processed_at' => $attempts >= 3 ? date('c') : null
        ], ['id=eq.' . $queueId]);
        error_log("SMS queue: send failed for $phone — " . ($result['error'] ?? 'unknown'));
    }
}
