<?php
/**
 * Admin Data API — Supabase REST
 */
header('Content-Type: application/json');
require_once 'db_config.php';
require_once '../admin/auth.php';

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats': getStats(); break;
    case 'leads': getLeads(); break;
    case 'lead_detail': getLeadDetail($_GET['email'] ?? ''); break;
    case 'session_detail': getSessionDetail($_GET['sessionId'] ?? ''); break;
    case 'delete_lead': deleteLead($_POST['email'] ?? '', $_POST['source'] ?? ''); break;
    case 'lead_activity': getLeadActivity($_GET['email'] ?? ''); break;
    case 'analytics': getAnalytics(); break;
    case 'get_settings': getSettings(); break;
    case 'save_settings': saveSettings(); break;
    default: echo json_encode(['error' => 'Invalid action']);
}

function getStats() {
    global $sb;

    $sessions = $sb->select('tracking_sessions', 'id', [], null, null);
    $sessionCount = count($sessions);

    // Get unique emails across all 3 tables
    $emails = [];
    foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
        $rows = $sb->select($table, 'email');
        foreach ($rows as $r) $emails[strtolower($r['email'])] = true;
    }
    $leadCount = count($emails);

    $convRate = ($sessionCount > 0) ? round(($leadCount / $sessionCount) * 100, 1) : 0;

    // New today — count leads submitted today
    $today = date('Y-m-d');
    $newToday = 0;
    foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
        $rows = $sb->select($table, 'id', ['created_at=gte.' . $today . 'T00:00:00'], null, null);
        $newToday += count($rows);
    }

    echo json_encode([
        'totalSessions' => $sessionCount,
        'totalLeads' => $leadCount,
        'conversionRate' => $convRate . '%',
        'newToday' => $newToday
    ]);
}

function getLeads() {
    global $sb;

    try {
        // Fetch from all 3 tables
        $allLeads = [];
        foreach ([
            ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
            ['table' => 'unit_inquiries', 'source' => 'Unit Interest'],
            ['table' => 'mailing_list', 'source' => 'Mailing List']
        ] as $src) {
            $rows = $sb->select($src['table'], 'first_name,last_name,email,created_at,tracking_id',
                [], 'created_at.desc', 100);
            foreach ($rows as &$r) $r['source'] = $src['source'];
            $allLeads = array_merge($allLeads, $rows);
        }

        // Sort by created_at desc
        usort($allLeads, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Fetch ALL enrichment records in one call and index by email
        $allEnrichments = $sb->select('lead_enrichment',
            'email,job_title,company,photo_url,company_logo,annual_revenue,headline,raw_response',
            []);
        $enrichmentByEmail = [];
        foreach ($allEnrichments as $e) {
            $enrichmentByEmail[strtolower($e['email'])] = $e;
        }

        // Deduplicate by email and collect tracking IDs
        $seen = [];
        $unique = [];
        foreach ($allLeads as $lead) {
            $email = strtolower($lead['email']);
            if (isset($seen[$email])) continue;
            $seen[$email] = true;

            // Merge enrichment from pre-fetched data
            if (isset($enrichmentByEmail[$email])) {
                $lead = array_merge($lead, $enrichmentByEmail[$email]);
            }

            $lead['event_count'] = 0;
            $unique[] = $lead;
            if (count($unique) >= 50) break;
        }

        // Fetch activity counts for all tracking IDs in one call
        $trackingIds = array_filter(array_column($unique, 'tracking_id'));
        if (!empty($trackingIds)) {
            $idList = '(' . implode(',', array_unique($trackingIds)) . ')';
            $allEvents = $sb->select('activity_logs', 'session_id',
                ['session_id=in.' . $idList]);

            // Count events per session_id
            $eventCounts = [];
            foreach ($allEvents as $evt) {
                $sid = $evt['session_id'];
                $eventCounts[$sid] = ($eventCounts[$sid] ?? 0) + 1;
            }

            // Merge counts back into leads
            foreach ($unique as &$lead) {
                if (!empty($lead['tracking_id']) && isset($eventCounts[$lead['tracking_id']])) {
                    $lead['event_count'] = $eventCounts[$lead['tracking_id']];
                }
            }
            unset($lead);
        }

        echo json_encode($unique, JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getLeadDetail($email) {
    global $sb;

    $enrichment = $sb->selectOne('lead_enrichment', '*', ['email=eq.' . urlencode($email)]) ?: [];

    $submission = [];
    $sources = [
        ['table' => 'waitlist_submissions', 'label' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'label' => 'Unit Interest'],
        ['table' => 'mailing_list', 'label' => 'Mailing List']
    ];

    foreach ($sources as $src) {
        $row = $sb->selectOne($src['table'], '*', ['email=eq.' . urlencode($email)]);
        if ($row) {
            $submission = $row;
            $submission['submission_type'] = $src['label'];
            break;
        }
    }

    $merged = array_merge($submission, $enrichment);
    if (!isset($merged['phone_number']) && isset($merged['phone'])) {
        $merged['phone_number'] = $merged['phone'];
    }

    echo json_encode($merged);
}

function getSessionDetail($sessionId) {
    global $sb;

    if (empty($sessionId)) {
        echo json_encode(['error' => 'No session ID provided']);
        return;
    }

    $logs = $sb->select('activity_logs', '*',
        ['session_id=eq.' . urlencode($sessionId)],
        'created_at.asc');

    echo json_encode($logs);
}

function getLeadActivity($email) {
    global $sb;

    if (empty($email)) {
        echo json_encode(['error' => 'Email required']);
        return;
    }

    // Get tracking IDs for this email
    $trackingIds = [];
    foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
        $rows = $sb->select($table, 'tracking_id', ['email=eq.' . urlencode($email)]);
        foreach ($rows as $r) {
            if (!empty($r['tracking_id'])) $trackingIds[] = $r['tracking_id'];
        }
    }
    $trackingIds = array_unique($trackingIds);

    if (empty($trackingIds)) {
        echo json_encode([]);
        return;
    }

    $idList = '(' . implode(',', $trackingIds) . ')';
    $logs = $sb->select('activity_logs', '*',
        ['session_id=in.' . $idList],
        'created_at.asc');

    echo json_encode($logs);
}

function getAnalytics() {
    global $sb;

    try {
        // 1. Section Engagement — fetch raw data and aggregate in PHP
        $sectionLogs = $sb->select('activity_logs', 'event_data',
            ['event_type=eq.visibility', 'event_name=eq.section_leave'],
            null, 1000);

        $sections = [];
        foreach ($sectionLogs as $log) {
            $data = is_string($log['event_data']) ? json_decode($log['event_data'], true) : $log['event_data'];
            $sec = $data['section'] ?? null;
            $time = $data['secondsSpent'] ?? 0;
            if ($sec) {
                if (!isset($sections[$sec])) $sections[$sec] = ['total' => 0, 'count' => 0];
                $sections[$sec]['total'] += $time;
                $sections[$sec]['count']++;
            }
        }
        $sectionEngagement = [];
        foreach ($sections as $sec => $d) {
            $sectionEngagement[] = [
                'section' => $sec,
                'visit_count' => $d['count'],
                'avg_seconds' => $d['count'] > 0 ? round($d['total'] / $d['count']) : 0
            ];
        }
        usort($sectionEngagement, function($a, $b) { return $b['avg_seconds'] - $a['avg_seconds']; });

        // 2. Top Interactions
        $clickLogs = $sb->select('activity_logs', 'event_data',
            ['event_type=eq.click', 'event_name=eq.button_click'],
            null, 1000);

        $clicks = [];
        foreach ($clickLogs as $log) {
            $data = is_string($log['event_data']) ? json_decode($log['event_data'], true) : $log['event_data'];
            $text = $data['text'] ?? 'Unnamed Action';
            $clicks[$text] = ($clicks[$text] ?? 0) + 1;
        }
        arsort($clicks);
        $topInteractions = [];
        foreach (array_slice($clicks, 0, 12, true) as $text => $count) {
            $topInteractions[] = ['button_text' => $text, 'click_count' => $count];
        }

        // 3. Traffic Trends (last 14 days)
        $recentLogs = $sb->select('activity_logs', 'session_id,created_at',
            ['created_at=gte.' . date('Y-m-d', strtotime('-14 days'))],
            'created_at.asc', 5000);

        $dayData = [];
        foreach ($recentLogs as $log) {
            $date = substr($log['created_at'], 0, 10);
            if (!isset($dayData[$date])) $dayData[$date] = [];
            $dayData[$date][$log['session_id']] = true;
        }
        // Count leads per day from all 3 submission tables
        $leadsByDay = [];
        $cutoff = date('Y-m-d', strtotime('-14 days'));
        foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
            $rows = $sb->select($table, 'created_at',
                ['created_at=gte.' . $cutoff]);
            foreach ($rows as $r) {
                $date = substr($r['created_at'], 0, 10);
                $leadsByDay[$date] = ($leadsByDay[$date] ?? 0) + 1;
            }
        }

        $trafficTrends = [];
        foreach ($dayData as $date => $sessions) {
            $trafficTrends[] = [
                'date' => $date,
                'sessions' => count($sessions),
                'leads' => $leadsByDay[$date] ?? 0
            ];
        }

        // 4. Device Breakdown
        $allSessions = $sb->select('tracking_sessions', 'user_agent', [], null, 5000);
        $devices = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0];
        foreach ($allSessions as $s) {
            $ua = $s['user_agent'] ?? '';
            if (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false) {
                $devices['Mobile']++;
            } elseif (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false) {
                $devices['Tablet']++;
            } else {
                $devices['Desktop']++;
            }
        }
        $deviceBreakdown = [];
        foreach ($devices as $type => $count) {
            if ($count > 0) $deviceBreakdown[] = ['device_type' => $type, 'count' => $count];
        }

        echo json_encode([
            'sectionEngagement' => $sectionEngagement,
            'topInteractions' => $topInteractions,
            'trafficTrends' => $trafficTrends,
            'deviceBreakdown' => $deviceBreakdown
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteLead($email, $source) {
    global $sb;

    if (empty($email) || empty($source)) {
        echo json_encode(['success' => false, 'error' => 'Email and Source required']);
        return;
    }

    $table = '';
    switch ($source) {
        case 'Waitlist': $table = 'waitlist_submissions'; break;
        case 'Unit Interest': $table = 'unit_inquiries'; break;
        case 'Mailing List': $table = 'mailing_list'; break;
    }

    if ($table) {
        $sb->delete($table, ['email=eq.' . urlencode($email)]);
    }

    // Also delete orphaned enrichment data
    $sb->delete('lead_enrichment', ['email=eq.' . urlencode($email)]);

    echo json_encode(['success' => true]);
}

function getSettings() {
    global $sb;
    $rows = $sb->select('settings', '*');
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['key']] = $r['value'];
    }
    echo json_encode($settings);
}

function saveSettings() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input) || !is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'No settings provided']);
        return;
    }

    foreach ($input as $key => $value) {
        $sb->upsert('settings', [
            'key' => $key,
            'value' => $value,
            'updated_at' => date('c')
        ], 'key');
    }

    echo json_encode(['success' => true]);
}
