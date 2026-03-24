<?php
require_once 'db_config.php';

$email = $_GET['email'] ?? null;

if (!$email) {
    die("Error: No email provided. Usage: ?email=example@domain.com");
}

try {
    // Delete from lead_enrichment
    $stmt1 = $pdo->prepare("DELETE FROM lead_enrichment WHERE TRIM(LOWER(email)) = ?");
    $stmt1->execute([trim(strtolower($email))]);
    $count1 = $stmt1->rowCount();

    echo "--- Cleanup Success ---\n";
    echo "Removed $count1 record(s) from lead_enrichment for: $email\n";
    echo "\nYou can now re-test enrichment at: https://eleanorbk.com/api/test-deep-enrichment.php?email=" . urlencode($email) . "&firstName=Shane&lastName=Winter&phone=6317596760\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
