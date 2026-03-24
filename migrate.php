<?php
require_once 'api/db_config.php';

try {
    $pdo->exec("ALTER TABLE tracking_sessions ADD COLUMN email VARCHAR(255) AFTER id;");
    echo "SUCCESS: Added email column to tracking_sessions\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
