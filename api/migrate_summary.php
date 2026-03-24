<?php
/**
 * Database Migration: Add ai_summary column
 * Run this by visiting: your-site.com/api/migrate_summary.php
 */
require_once 'db_config.php';
require_once '../admin/auth.php';

// Security: Only allow logged-in admins to run this
if (!isAdmin()) {
    die("Unauthorized. Please log in as admin first.");
}

try {
    $sql = "ALTER TABLE lead_enrichment ADD COLUMN IF NOT EXISTS ai_summary TEXT AFTER photo_url";
    $pdo->exec($sql);
    echo "<h3>Migration Successful!</h3>";
    echo "<p>Column 'ai_summary' added to 'lead_enrichment' table.</p>";
    echo "<a href='../admin/index.php'>Back to Dashboard</a>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "<h3>Migration already completed.</h3>";
        echo "<p>Column 'ai_summary' already exists.</p>";
        echo "<a href='../admin/index.php'>Back to Dashboard</a>";
    } else {
        echo "<h3>Migration Failed!</h3>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }
}
?>
