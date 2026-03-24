<?php
/**
 * Test script for Deep Enrichment (Tavily + Anthropic)
 */
require_once 'enrichment.php';

header('Content-Type: text/plain');

$email = $_GET['email'] ?? null;
$firstName = $_GET['firstName'] ?? null;
$lastName = $_GET['lastName'] ?? null;
$phone = $_GET['phone'] ?? null;

// HACK: Handle "mangled" or double-encoded URLs from copy-pasting
if (!$email && !empty($_SERVER['QUERY_STRING'])) {
    $rawQuery = urldecode($_SERVER['QUERY_STRING']);
    parse_str($rawQuery, $mangled);
    $email = $mangled['email'] ?? null;
    $firstName = $mangled['firstName'] ?? null;
    $lastName = $mangled['lastName'] ?? null;
    $phone = $mangled['phone'] ?? null;
}

if (!$email) {
    die("Error: No email provided. Use ?email=...&firstName=...&lastName=...");
}

$stateHint = getLikelyState($phone);
$emailParts = explode('@', $email);
$domain = end($emailParts);
$isPublicEmail = in_array($domain, ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'me.com']);

echo "--- Testing Direct LinkedIn Scraper (RapidAPI) ---\n";
echo "Target: $firstName $lastName ($email)\n";
echo "Phone: " . ($phone ?? "None") . "\n";
echo "State Hint: " . ($stateHint ?? "None") . "\n";
echo "Domain Hint: " . (!$isPublicEmail ? $domain : "None (Public)") . "\n";
echo "--- Strategies ---\n";
echo "1. Tavily for LinkedIn URL Discovery\n";
echo "2. RapidAPI for High-Fidelity Profile Scraping\n";
echo "3. Anthropic for Data Normalization\n\n";

$result = deepEnrichment($email, $firstName, $lastName, $phone);

if ($result && !isset($result['error'])) {
    echo "SUCCESS: Data enriched via " . ($result['_source'] ?? 'Unknown Source') . "\n";
    echo "Identity Confidence: " . ($result['identity_confidence'] ?? 'N/A') . "%\n";
    echo "Reasoning: " . ($result['reasoning'] ?? 'N/A') . "\n\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "FAILED: Could not extract data via Deep Search.\n";
    if (isset($result['error'])) {
        echo "Error Detail: " . $result['error'] . "\n";
        echo "Response Data: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
}
