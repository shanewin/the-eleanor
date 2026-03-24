<?php
/**
 * Database Configuration (TEMPLATE)
 * 
 * IMPORTANT: Copy this to db_config.php and replace the values with your actual database credentials.
 */

$db_host = '127.0.0.1'; // Usually localhost or 127.0.0.1 on Hostinger
$db_name = 'YOUR_DB_NAME';
$db_user = 'YOUR_DB_USER';
$db_pass = 'YOUR_DB_PASSWORD';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // In production, don't reveal connection details
    error_log("Connection failed: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed. Please contact support.']));
}
