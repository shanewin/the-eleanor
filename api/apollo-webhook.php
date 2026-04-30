<?php
/**
 * Apollo.io Webhook Handler
 * Receives asynchronous enrichment data (Waterfall/Reveal results)
 */
require_once 'db_config.php';

$rawInput = file_get_contents('php://input');
error_log("Apollo Webhook received: " . $rawInput);

$data = json_decode($rawInput, true);
$person = $data['person'] ?? null;

if (!$person || !isset($person['email'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$email = $person['email'];

try {
    // Build update with only non-null fields
    $update = ['raw_response' => json_decode($rawInput, true)];
    if (!empty($person['name'])) $update['full_name'] = $person['name'];
    if (!empty($person['title'])) $update['job_title'] = $person['title'];
    if (!empty($person['linkedin_url'])) $update['linkedin_url'] = $person['linkedin_url'];
    if (!empty($person['city'])) $update['city'] = $person['city'];
    if (!empty($person['state'])) $update['state'] = $person['state'];
    if (!empty($person['country'])) $update['country'] = $person['country'];
    if (!empty($person['photo_url'])) $update['photo_url'] = $person['photo_url'];

    $sb->update('lead_enrichment', $update, ['email=eq.' . urlencode($email)]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("Webhook database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'db_error']);
}
