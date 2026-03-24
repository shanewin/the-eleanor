<?php
/**
 * Apollo.io Webhook Handler
 * Receives asynchronous enrichment data (Waterfall/Reveal results)
 */
require_once 'db_config.php';
require_once 'config.php';

// Log incoming request for debugging
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
    // Update existing lead record with revealed info
    // We use COALESCE to keep existing data if the webhook payload is partial
    $stmt = $pdo->prepare("UPDATE lead_enrichment SET 
        full_name = COALESCE(?, full_name),
        job_title = COALESCE(?, job_title),
        linkedin_url = COALESCE(?, linkedin_url),
        city = COALESCE(?, city),
        state = COALESCE(?, state),
        country = COALESCE(?, country),
        photo_url = COALESCE(?, photo_url),
        raw_response = ? 
        WHERE email = ?");

    $stmt->execute([
        $person['name'] ?? null,
        $person['title'] ?? null,
        $person['linkedin_url'] ?? null,
        $person['city'] ?? null,
        $person['state'] ?? null,
        $person['country'] ?? null,
        $person['photo_url'] ?? null,
        $rawInput, // Store the latest raw response
        $email
    ]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Webhook database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'db_error']);
}
