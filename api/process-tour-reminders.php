<?php
/**
 * Tour Reminder Processor
 * Runs hourly via cron-runner.php.
 * Sends a reminder SMS to leads with confirmed tours tomorrow.
 * Only sends one reminder per tour (tracks via notes field).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/telnyx-sms.php';

// Calculate tomorrow's date range (Eastern time)
$tz = new DateTimeZone('America/New_York');
$tomorrow = new DateTime('tomorrow', $tz);
$tomorrowStart = $tomorrow->format('Y-m-d') . 'T00:00:00';
$tomorrowEnd = $tomorrow->format('Y-m-d') . 'T23:59:59';

// Find confirmed tours scheduled for tomorrow
$tours = $sb->select('tour_requests', '*', [
    'status=eq.confirmed',
    'scheduled_at=gte.' . $tomorrowStart,
    'scheduled_at=lte.' . $tomorrowEnd
]);

if (empty($tours)) {
    return;
}

foreach ($tours as $tour) {
    // Skip if reminder already sent (check notes field)
    if (!empty($tour['notes']) && strpos($tour['notes'], '[REMINDER_SENT]') !== false) {
        continue;
    }

    $phone = $tour['lead_phone'] ?? '';
    if (!$phone) continue;

    // Format the tour time
    $tourTime = new DateTime($tour['scheduled_at'], $tz);
    $timeFormatted = $tourTime->format('g:ia');
    $dayFormatted = $tourTime->format('l');

    // Find lead name
    $leadName = 'there';
    if (!empty($tour['lead_email'])) {
        foreach (['waitlist_submissions', 'unit_inquiries'] as $table) {
            $lead = $sb->selectOne($table, 'first_name', ['email=eq.' . urlencode($tour['lead_email'])]);
            if ($lead && !empty($lead['first_name'])) {
                $leadName = $lead['first_name'];
                break;
            }
        }
    }

    // Build reminder message
    $message = "Hi {$leadName}! Just a reminder about your tour at The Eleanor tomorrow ({$dayFormatted}) at {$timeFormatted}. "
        . "We're at 52 4th Avenue, Brooklyn. Looking forward to seeing you! Reply if you need to reschedule.";

    // Send
    $result = sendSMS($phone, $message);

    if ($result['success']) {
        // Log the message
        $sb->insert('sms_messages', [
            'lead_phone'        => $phone,
            'lead_email'        => $tour['lead_email'],
            'direction'         => 'outbound',
            'sender_type'       => 'ai',
            'sender_name'       => 'Eleanor AI',
            'body'              => $message,
            'telnyx_message_id' => $result['message_id'] ?? null,
            'status'            => 'sent'
        ]);

        // Mark reminder as sent (append to notes so we don't send again)
        $existingNotes = $tour['notes'] ?? '';
        $sb->update('tour_requests', [
            'notes' => trim($existingNotes . ' [REMINDER_SENT]'),
            'updated_at' => date('c')
        ], ['id=eq.' . $tour['id']]);

        error_log("Tour reminder sent to {$phone} for tour on {$tourTime->format('Y-m-d H:i')}");
    } else {
        error_log("Tour reminder failed for {$phone}: " . ($result['error'] ?? 'unknown'));
    }
}
