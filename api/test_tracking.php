<?php
/**
 * Tracking System Verification Script
 */
header('Content-Type: application/json');
require_once 'db_config.php';

$report = [
    'database_connection' => 'OK',
    'tables' => [],
    'columns' => []
];

try {
    // 1. Check Tracking Tables
    $requiredTables = ['tracking_sessions', 'activity_logs'];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $report['tables'][$table] = ($stmt->rowCount() > 0) ? 'EXISTS' : 'MISSING';
    }

    // 2. Check for Tracking ID Columns in original tables
    $checkColumns = [
        'waitlist_submissions' => 'tracking_id',
        'unit_inquiries' => 'tracking_id',
        'mailing_list' => 'tracking_id'
    ];

    foreach ($checkColumns as $table => $column) {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        $report['columns']["$table.$column"] = ($stmt->rowCount() > 0) ? 'EXISTS' : 'MISSING';
    }

} catch (Exception $e) {
    $report['database_connection'] = 'FAILED: ' . $e->getMessage();
}

echo json_encode($report, JSON_PRETTY_PRINT);
