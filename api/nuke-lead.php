<?php
require_once 'db_config.php';
header('Content-Type: text/plain');

$email = $_GET['email'] ?? 'shanewin@gmail.com';
$cleanEmail = trim($email);

echo "--- Lead Nuke Tool ---\n";
echo "Targeting Email: $cleanEmail\n";

try {
    // Broad delete to catch any weird formatting
    $stmt = $pdo->prepare("DELETE FROM lead_enrichment WHERE email LIKE ?");
    $stmt->execute(['%' . $cleanEmail . '%']);
    $count = $stmt->rowCount();
    
    echo "SUCCESS: Deleted $count record(s).\n";
    
    // Final check
    $check = $pdo->prepare("SELECT COUNT(*) FROM lead_enrichment WHERE email LIKE ?");
    $check->execute(['%' . $cleanEmail . '%']);
    $remaining = $check->fetchColumn();
    echo "REMAINING RECORDS: $remaining\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
