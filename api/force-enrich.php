<?php
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
    die("Error: No email provided.");
}

echo "--- Forcing Real Enrichment ---\n";
echo "Target: $firstName $lastName ($email)\n\n";

// Clear existing enrichment first to allow re-enrichment with new logic
error_log("Force-Enrich: Clearing existing record for $email");
$pdo->prepare("DELETE FROM lead_enrichment WHERE TRIM(LOWER(email)) = ?")->execute([trim(strtolower($email))]);

$result = enrichLead($email, $firstName, $lastName, $phone);

echo "Status: " . ($result['status'] ?? 'Unknown') . "\n";
if (isset($result['message'])) echo "Message: " . $result['message'] . "\n";

if (($result['status'] ?? '') === 'success') {
    echo "\nSUCCESS! The database has been updated with the NYC profile.\n";
    echo "You can now refresh your Admin Dashboard to see the result.\n";
} else {
    echo "\nFailed to update database. Check logs.\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
