<?php
require_once 'enrichment.php';
require_once 'config.php';

$testEmail = trim(strtolower($_GET['email'] ?? 'shanewin@gmail.com'));
$testPhone = $_GET['phone'] ?? '6317596760';
$firstName = $_GET['firstName'] ?? null;
$lastName = $_GET['lastName'] ?? null;
$force = isset($_GET['force']);
$verbose = isset($_GET['verbose']);

if ($force) {
    // Robust delete: handle casing and whitespace
    $stmt = $pdo->prepare("DELETE FROM lead_enrichment WHERE TRIM(LOWER(email)) = ?");
    $stmt->execute([$testEmail]);
    $count = $stmt->rowCount();
    $result_prefix = "--- Force Refresh: Cache Cleared ($count records deleted) ---\n";
}

$result = enrichLead($testEmail, $firstName, $lastName, $testPhone);

if ($verbose) {
    if (isset($result_prefix)) echo $result_prefix;
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>
