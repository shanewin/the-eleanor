<?php
/**
 * Database Query Diagnostic
 */
header('Content-Type: application/json');
require_once 'db_config.php';

echo "--- DB Query Diagnostic ---\n";

try {
    $sql = "SELECT all_leads.source, all_leads.first_name, all_leads.last_name, all_leads.email, all_leads.created_at, all_leads.tracking_id, 
                   e.job_title, e.company
            FROM (
                SELECT 'Waitlist' as source, first_name, last_name, email, created_at, tracking_id FROM waitlist_submissions
                UNION ALL
                SELECT 'Unit Interest' as source, first_name, last_name, email, created_at, tracking_id FROM unit_inquiries
                UNION ALL
                SELECT 'Mailing List' as source, first_name, last_name, email, created_at, tracking_id FROM mailing_list
            ) all_leads
            LEFT JOIN lead_enrichment e ON all_leads.email = e.email
            ORDER BY all_leads.created_at DESC LIMIT 5";
            
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query Successful. Found " . count($results) . " leads.\n";
    echo json_encode($results, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo "QUERY FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
}
?>
