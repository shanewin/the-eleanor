<?php
header('Content-Type: application/json');
require_once 'db_config.php';

$results = [
    'connection' => false,
    'tables' => []
];

try {
    // Check connection
    if ($pdo) {
        $results['connection'] = true;
        
        // Check tables
        $tables = ['waitlist_submissions', 'unit_inquiries', 'mailing_list'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $results['tables'][$table] = ($stmt->rowCount() > 0);
        }
    }
} catch (Exception $e) {
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
