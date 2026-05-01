<?php
/**
 * Telnyx SMS Helper
 * Sends SMS via Telnyx REST API using cURL (matches existing project pattern).
 */
require_once __DIR__ . '/config.php';

/**
 * Send an SMS message via Telnyx.
 *
 * @param string $to   Recipient phone in E.164 format (+1XXXXXXXXXX)
 * @param string $text Message body
 * @param string $from Optional sender number (defaults to TELNYX_FROM_NUMBER)
 * @return array       ['success' => bool, 'message_id' => string|null, 'error' => string|null]
 */
function sendSMS($to, $text, $from = null) {
    $from = $from ?: (defined('TELNYX_FROM_NUMBER') ? TELNYX_FROM_NUMBER : '');

    if (empty($from)) {
        return ['success' => false, 'error' => 'TELNYX_FROM_NUMBER not configured'];
    }

    // Normalize phone to E.164
    $to = normalizePhone($to);
    if (!$to) {
        return ['success' => false, 'error' => 'Invalid phone number'];
    }

    $payload = [
        'from' => $from,
        'to'   => $to,
        'text' => $text
    ];

    // If messaging profile ID is set, include it
    if (defined('TELNYX_MESSAGING_PROFILE_ID') && TELNYX_MESSAGING_PROFILE_ID) {
        $payload['messaging_profile_id'] = TELNYX_MESSAGING_PROFILE_ID;
    }

    $ch = curl_init('https://api.telnyx.com/v2/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TELNYX_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Telnyx SMS cURL error: $curlError");
        return ['success' => false, 'error' => 'Connection error: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['data']['id'])) {
        return [
            'success'    => true,
            'message_id' => $data['data']['id'],
            'error'      => null
        ];
    }

    $errorMsg = $data['errors'][0]['detail'] ?? $data['errors'][0]['title'] ?? 'Unknown Telnyx error';
    error_log("Telnyx SMS failed ($httpCode): $response");
    return ['success' => false, 'message_id' => null, 'error' => $errorMsg];
}

/**
 * Normalize a phone number to E.164 format.
 */
function normalizePhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);

    // US number: 10 digits → prepend +1
    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }
    // Already has country code
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '+' . $digits;
    }
    // Already full E.164
    if (strlen($digits) >= 11) {
        return '+' . $digits;
    }

    return null;
}
