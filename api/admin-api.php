<?php
/**
 * Admin Data API
 */
header('Content-Type: application/json');
require_once 'db_config.php';
require_once '../admin/auth.php';

// Security: Only allow logged-in admins
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        getStats();
        break;
    case 'leads':
        getLeads();
        break;
    case 'lead_detail':
        getLeadDetail($_GET['email'] ?? '');
        break;
    case 'session_detail':
        getSessionDetail($_GET['sessionId'] ?? '');
        break;
    case 'delete_lead':
        deleteLead($_POST['email'] ?? '', $_POST['source'] ?? '');
        break;
    case 'lead_activity':
        getLeadActivity($_GET['email'] ?? '');
        break;
    case 'analytics':
        getAnalytics();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getStats() {
    global $pdo;
    
    // Total Unique Visitors (based on sessions)
    $sessions = $pdo->query("SELECT COUNT(*) FROM tracking_sessions")->fetchColumn();
    
    // Total Unique Leads across all 3 sources
    $leadsSql = "
        SELECT COUNT(DISTINCT email) FROM (
            SELECT email FROM waitlist_submissions
            UNION ALL SELECT email FROM unit_inquiries
            UNION ALL SELECT email FROM mailing_list
        ) as all_emails";
    $leads = $pdo->query($leadsSql)->fetchColumn();
    
    // Conversion Rate
    $convRate = ($sessions > 0) ? round(($leads / $sessions) * 100, 1) : 0;
    
    // Top Interest (Most inquired unit)
    $topUnitSql = "SELECT unit FROM unit_inquiries GROUP BY unit ORDER BY COUNT(*) DESC LIMIT 1";
    $topUnit = $pdo->query($topUnitSql)->fetchColumn();

    echo json_encode([
        'totalSessions' => $sessions,
        'totalLeads' => $leads,
        'conversionRate' => $convRate . '%',
        'hottestSection' => $topUnit ?: 'None'
    ]);
}

function getLeads() {
    global $pdo;
    
    try {
        // Fetch all recent submissions from all sources with activity counts
        $sql = "SELECT all_leads.source, all_leads.first_name, all_leads.last_name, all_leads.email, all_leads.created_at, all_leads.tracking_id, 
                       e.job_title, e.company, e.photo_url, e.company_logo, e.annual_revenue, e.headline, e.raw_response,
                       (SELECT COUNT(*) FROM activity_logs WHERE session_id = all_leads.tracking_id) as event_count
                FROM (
                    SELECT 'Waitlist' as source, first_name, last_name, email, created_at, tracking_id FROM waitlist_submissions
                    UNION ALL
                    SELECT 'Unit Interest' as source, first_name, last_name, email, created_at, tracking_id FROM unit_inquiries
                    UNION ALL
                    SELECT 'Mailing List' as source, first_name, last_name, email, created_at, tracking_id FROM mailing_list
                ) all_leads
                LEFT JOIN lead_enrichment e ON all_leads.email = e.email 
                ORDER BY all_leads.created_at DESC LIMIT 100";
                
        $stmt = $pdo->query($sql);
        $rawLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Deduplicate by email in PHP (keep the first/most recent one found)
        $uniqueLeads = [];
        $seenEmails = [];
        foreach ($rawLeads as $lead) {
            $email = strtolower($lead['email']);
            if (!isset($seenEmails[$email])) {
                $seenEmails[$email] = true;
                $uniqueLeads[] = $lead;
            }
            if (count($uniqueLeads) >= 50) break;
        }
        
        echo json_encode($uniqueLeads, JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Query Error: ' . $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'General API Error: ' . $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

function getLeadDetail($email) {
    global $pdo;
    
    // 1. Fetch Enrichment Data
    $stmt = $pdo->prepare("SELECT * FROM lead_enrichment WHERE email = ?");
    $stmt->execute([$email]);
    $enrichment = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 2. Fetch Submission Data (Check all source tables)
    $submission = [];
    $sources = [
        ['table' => 'waitlist_submissions', 'label' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'label' => 'Unit Interest'],
        ['table' => 'mailing_list', 'label' => 'Mailing List']
    ];

    foreach ($sources as $src) {
        $st = $pdo->prepare("SELECT * FROM {$src['table']} WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $submission = $row;
            $submission['submission_type'] = $src['label'];
            break;
        }
    }

    // Merge them: Enrichment takes priority for job details, Submission for intent details
    $merged = array_merge($submission, $enrichment);
    
    // Normalize phone number field for frontend
    if (!isset($merged['phone_number']) && isset($merged['phone'])) {
        $merged['phone_number'] = $merged['phone'];
    }
    
    echo json_encode($merged);
}

function getSessionDetail($sessionId) {
    global $pdo;
    
    if (empty($sessionId)) {
        echo json_encode(['error' => 'No session ID provided']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$sessionId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($logs);
}

function getLeadActivity($email) {
    global $pdo;

    if (empty($email)) {
        echo json_encode(['error' => 'Email required']);
        return;
    }

    // Join sessions and logs to get all activity for this lead by email
    $sql = "SELECT l.*, s.id as session_id 
            FROM activity_logs l
            JOIN tracking_sessions s ON l.session_id = s.id
            WHERE s.email = ? OR s.id IN (
                SELECT tracking_id FROM waitlist_submissions WHERE email = ?
                UNION
                SELECT tracking_id FROM unit_inquiries WHERE email = ?
                UNION
                SELECT tracking_id FROM mailing_list WHERE email = ?
            )
            ORDER BY l.created_at ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email, $email, $email, $email]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($logs);
}

function getAnalytics() {
    global $pdo;
    
    try {
        // 1. Section Engagement (Avg Time Spent)
        // Group by the actual section ID inside the data payload
        $sectionEngagementSql = "
            SELECT
                event_data->>'section' as section,
                COUNT(*) as visit_count,
                AVG(CAST(event_data->>'secondsSpent' AS INTEGER)) as avg_seconds
            FROM activity_logs
            WHERE event_type = 'visibility' AND event_name = 'section_leave'
            GROUP BY event_data->>'section'
            ORDER BY avg_seconds DESC";
        $sectionEngagement = $pdo->query($sectionEngagementSql)->fetchAll(PDO::FETCH_ASSOC);

        // 2. Top Interactions (Button Clicks)
        $topInteractionsSql = "
            SELECT
                COALESCE(NULLIF(event_data->>'text', ''), 'Unnamed Action') as button_text,
                COUNT(*) as click_count
            FROM activity_logs
            WHERE event_type = 'click' AND event_name = 'button_click'
            GROUP BY button_text
            ORDER BY click_count DESC
            LIMIT 12";
        $topInteractions = $pdo->query($topInteractionsSql)->fetchAll(PDO::FETCH_ASSOC);

        // 3. Traffic Trends (Last 10 Days)
        $trafficTrendsSql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(DISTINCT session_id) as sessions,
                (SELECT COUNT(*) FROM (
                    SELECT email, DATE(created_at) as d FROM waitlist_submissions
                    UNION ALL SELECT email, DATE(created_at) as d FROM unit_inquiries
                    UNION ALL SELECT email, DATE(created_at) as d FROM mailing_list
                ) all_leads WHERE all_leads.d = DATE(activity_logs.created_at)) as leads
            FROM activity_logs
            WHERE created_at >= NOW() - INTERVAL '14 days'
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
        $trafficTrends = $pdo->query($trafficTrendsSql)->fetchAll(PDO::FETCH_ASSOC);

        // 4. Device Breakdown (Simplified from User Agent)
        $deviceBreakdownSql = "
            SELECT 
                CASE 
                    WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'Mobile'
                    WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device_type,
                COUNT(*) as count
            FROM tracking_sessions
            GROUP BY 1";
        $deviceBreakdown = $pdo->query($deviceBreakdownSql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sectionEngagement' => $sectionEngagement,
            'topInteractions' => $topInteractions,
            'trafficTrends' => $trafficTrends,
            'deviceBreakdown' => $deviceBreakdown
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteLead($email, $source) {
    global $pdo;
    
    if (empty($email) || empty($source)) {
        echo json_encode(['success' => false, 'error' => 'Email and Source required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $table = '';
        switch ($source) {
            case 'Waitlist': $table = 'waitlist_submissions'; break;
            case 'Unit Interest': $table = 'unit_inquiries'; break;
            case 'Mailing List': $table = 'mailing_list'; break;
        }

        if ($table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE email = ?");
            $stmt->execute([$email]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
