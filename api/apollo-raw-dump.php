<?php
/**
 * Apollo Raw Data Dump
 * Use this to see every single field Apollo returns for a profile.
 */
require_once 'config.php';

header('Content-Type: application/json');

$email = $_GET['email'] ?? 's.nadella@microsoft.com'; // Using a known high-profile person for a full dump

function getRawApolloData($email) {
    $url = "https://api.apollo.io/api/v1/people/match";
    $payload = [
        'email' => $email,
        'reveal_personal_emails' => true,
        'reveal_phone_number' => true,
        'webhook_url' => APOLLO_WEBHOOK_URL,
        'run_waterfall_email' => true,
        'run_waterfall_phone' => true
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cache-Control: no-cache',
        'X-Api-Key: ' . APOLLO_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'raw_apollo_json' => json_decode($response, true)
    ];
}

echo json_encode(getRawApolloData($email), JSON_PRETTY_PRINT);
?>
