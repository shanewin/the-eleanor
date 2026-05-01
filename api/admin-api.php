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
    case 'get_brokers': getBrokers(); break;
    case 'add_broker': addBroker(); break;
    case 'update_broker': updateBroker(); break;
    case 'delete_broker': deleteBroker(); break;
    case 'assign_lead': assignLead(); break;
    case 'respond_lead': respondLead(); break;
    case 'get_communications': getCommunications($_GET['email'] ?? ''); break;
    case 'add_communication': addCommunication(); break;
    case 'delete_communication': deleteCommunication(); break;
    case 'sms_conversations': getSMSConversations(); break;
    case 'sms_thread': getSMSThread($_GET['phone'] ?? ''); break;
    case 'sms_send': sendSMSFromDashboard(); break;
    case 'sms_toggle_ai': toggleAIForLead(); break;
    case 'sms_ai_status': getAIStatus($_GET['phone'] ?? ''); break;
    case 'update_lead_status': updateLeadStatus(); break;
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

    $convRate = ($sessionCount > 0) ? min(100, round(($leadCount / $sessionCount) * 100, 1)) : 0;

    // New today — count UNIQUE emails submitted today
    $today = date('Y-m-d');
    $todayEmails = [];
    foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
        $rows = $sb->select($table, 'email', ['created_at=gte.' . $today . 'T00:00:00'], null, null);
        foreach ($rows as $r) $todayEmails[strtolower($r['email'])] = true;
    }
    $newToday = count($todayEmails);

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
        // Fetch from all 3 tables (include phone, budget, move_in_date, unit for display)
        // Fetch all brokers for lookup
        $brokerRows = $sb->select('brokers', 'id,name', []);
        $brokerLookup = [];
        foreach ($brokerRows as $b) {
            $brokerLookup[$b['id']] = $b['name'];
        }

        $allLeads = [];
        foreach ([
            ['table' => 'waitlist_submissions', 'source' => 'Waitlist', 'fields' => 'first_name,last_name,email,phone,budget,move_in_date,unit,unit_type,created_at,tracking_id,assigned_to,first_response_at,response_method,lead_status'],
            ['table' => 'unit_inquiries', 'source' => 'Unit Interest', 'fields' => 'first_name,last_name,email,phone,budget,move_in_date,unit,created_at,tracking_id,assigned_to,first_response_at,response_method,lead_status'],
            ['table' => 'mailing_list', 'source' => 'Mailing List', 'fields' => 'first_name,last_name,email,created_at,tracking_id']
        ] as $src) {
            $rows = $sb->select($src['table'], $src['fields'],
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
            'email,job_title,company,photo_url,company_logo,annual_revenue,headline,inferred_salary,linkedin_url,raw_response',
            []);
        $enrichmentByEmail = [];
        foreach ($allEnrichments as $e) {
            $enrichmentByEmail[strtolower($e['email'])] = $e;
        }

        // Deduplicate by email AND phone number
        $seenEmails = [];
        $seenPhones = [];
        $unique = [];
        foreach ($allLeads as $lead) {
            $email = strtolower($lead['email']);
            $phone = preg_replace('/\D/', '', $lead['phone'] ?? '');

            // Skip if we've seen this email
            if (isset($seenEmails[$email])) continue;

            // Skip if we've seen this phone number (and it's not empty)
            if ($phone && strlen($phone) >= 10 && isset($seenPhones[$phone])) continue;

            $seenEmails[$email] = true;
            if ($phone && strlen($phone) >= 10) $seenPhones[$phone] = true;

            // Merge enrichment from pre-fetched data
            if (isset($enrichmentByEmail[$email])) {
                $lead = array_merge($lead, $enrichmentByEmail[$email]);
            }

            // Attach broker name if assigned
            if (!empty($lead['assigned_to']) && isset($brokerLookup[$lead['assigned_to']])) {
                $lead['broker_name'] = $brokerLookup[$lead['assigned_to']];
            }

            $lead['event_count'] = 0;
            $unique[] = $lead;
            if (count($unique) >= 50) break;
        }

        // Fetch all communications and build per-lead lookup
        $allComms = $sb->select('communications', 'lead_email,subject,created_at', [], 'created_at.desc', 500);
        $lastComm = [];
        $commCount = [];
        foreach ($allComms as $comm) {
            $ce = strtolower($comm['lead_email'] ?? '');
            if (!$ce) continue;
            $commCount[$ce] = ($commCount[$ce] ?? 0) + 1;
            if (!isset($lastComm[$ce])) {
                $lastComm[$ce] = $comm;
            }
        }
        foreach ($unique as &$lead) {
            $le = strtolower($lead['email']);
            $lead['last_comm_subject'] = isset($lastComm[$le]) ? $lastComm[$le]['subject'] : null;
            $lead['last_comm_at'] = isset($lastComm[$le]) ? $lastComm[$le]['created_at'] : null;
            $lead['comm_count'] = $commCount[$le] ?? 0;
        }
        unset($lead);

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

    // Resolve broker name
    if (!empty($merged['assigned_to'])) {
        $broker = $sb->selectOne('brokers', 'name', ['id=eq.' . $merged['assigned_to']]);
        $merged['broker_name'] = $broker['name'] ?? null;
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

/* ── Broker Management ── */

function getBrokers() {
    global $sb;
    $brokers = $sb->select('brokers', '*', [], 'name.asc');
    echo json_encode($brokers);
}

function addBroker() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name']) || empty($input['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and email are required']);
        return;
    }

    $result = $sb->insert('brokers', [
        'name'  => $input['name'],
        'email' => $input['email'],
        'phone' => $input['phone'] ?? null,
        'role'  => $input['role'] ?? 'broker'
    ]);

    echo json_encode(['success' => true, 'broker' => $result]);
}

function updateBroker() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Broker id is required']);
        return;
    }

    $id = $input['id'];
    $data = [];
    foreach (['name', 'email', 'phone', 'role', 'is_active'] as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = $input[$field];
        }
    }

    $sb->update('brokers', $data, ['id=eq.' . $id]);
    echo json_encode(['success' => true]);
}

function deleteBroker() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Broker id is required']);
        return;
    }

    $id = $input['id'];

    // Unassign leads in both tables
    $sb->update('waitlist_submissions', ['assigned_to' => null], ['assigned_to=eq.' . $id]);
    $sb->update('unit_inquiries', ['assigned_to' => null], ['assigned_to=eq.' . $id]);

    // Delete the broker
    $sb->delete('brokers', ['id=eq.' . $id]);

    echo json_encode(['success' => true]);
}

/* ── Lead Assignment & Response ── */

function assignLead() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    $email = $input['email'] ?? '';
    $source = $input['source'] ?? '';
    $brokerId = $input['broker_id'] ?? null;

    if (empty($email) || empty($source)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and source are required']);
        return;
    }

    $table = '';
    switch ($source) {
        case 'Waitlist':      $table = 'waitlist_submissions'; break;
        case 'Unit Interest':  $table = 'unit_inquiries'; break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid source for assignment']);
            return;
    }

    $assignValue = (!empty($brokerId) && $brokerId != 0) ? $brokerId : null;

    $sb->update($table, ['assigned_to' => $assignValue], ['email=eq.' . urlencode($email)]);
    echo json_encode(['success' => true]);
}

function respondLead() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    $email  = $input['email'] ?? '';
    $source = $input['source'] ?? '';
    $method = $input['method'] ?? '';

    if (empty($email) || empty($source) || empty($method)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email, source, and method are required']);
        return;
    }

    if (!in_array($method, ['sms', 'email', 'phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Method must be sms, email, or phone']);
        return;
    }

    $table = '';
    switch ($source) {
        case 'Waitlist':      $table = 'waitlist_submissions'; break;
        case 'Unit Interest':  $table = 'unit_inquiries'; break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid source for response tracking']);
            return;
    }

    $sb->update($table, [
        'first_response_at' => date('c'),
        'response_method'   => $method
    ], ['email=eq.' . urlencode($email)]);

    echo json_encode(['success' => true]);
}

/* ── Lead Status ── */

function updateLeadStatus() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $source = $input['source'] ?? '';
    $status = $input['status'] ?? '';

    if (empty($email) || empty($status)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and status required']);
        return;
    }

    $table = '';
    switch ($source) {
        case 'Waitlist': $table = 'waitlist_submissions'; break;
        case 'Unit Interest': $table = 'unit_inquiries'; break;
        default: $table = 'waitlist_submissions'; break;
    }

    $sb->update($table, ['lead_status' => $status], ['email=eq.' . urlencode($email)]);

    // Log status change as communication
    $sb->insert('communications', [
        'lead_email' => $email,
        'direction' => 'internal',
        'channel' => 'note',
        'subject' => 'Status changed to: ' . $status,
        'sender' => 'System',
        'status' => 'sent'
    ]);

    echo json_encode(['success' => true]);
}

/* ── Communications ── */

function getCommunications($email) {
    global $sb;

    if (empty($email)) {
        // Return all recent communications across all leads
        $comms = $sb->select('communications', '*', [],
            'created_at.desc', 100);
        echo json_encode($comms);
        return;
    }

    $comms = $sb->select('communications', '*',
        ['lead_email=eq.' . urlencode($email)],
        'created_at.desc');

    echo json_encode($comms);
}

function addCommunication() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    $leadEmail = $input['lead_email'] ?? '';
    $direction = $input['direction'] ?? '';
    $channel   = $input['channel'] ?? '';

    if (empty($leadEmail) || empty($direction) || empty($channel)) {
        http_response_code(400);
        echo json_encode(['error' => 'lead_email, direction, and channel are required']);
        return;
    }

    $result = $sb->insert('communications', [
        'lead_email' => $leadEmail,
        'direction'  => $direction,
        'channel'    => $channel,
        'subject'    => $input['subject'] ?? null,
        'body'       => $input['body'] ?? null,
        'sender'     => $input['sender'] ?? null,
        'recipient'  => $input['recipient'] ?? null,
        'status'     => $input['status'] ?? null,
        'metadata'   => $input['metadata'] ?? null,
    ]);

    echo json_encode(['success' => true, 'communication' => $result]);
}

function deleteCommunication() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Communication id is required']);
        return;
    }

    $sb->delete('communications', ['id=eq.' . $input['id']]);
    echo json_encode(['success' => true]);
}

/* ── SMS Conversations ── */

/**
 * Get all SMS conversations (grouped by phone number) with lead info.
 */
function getSMSConversations() {
    global $sb;

    // Get all SMS messages ordered by most recent
    $messages = $sb->select('sms_messages', '*', [], 'created_at.desc', 500);

    // Group by phone and get latest message + count
    $convos = [];
    foreach ($messages as $msg) {
        $phone = $msg['lead_phone'];
        if (!isset($convos[$phone])) {
            $convos[$phone] = [
                'lead_phone'    => $phone,
                'lead_email'    => $msg['lead_email'],
                'last_message'  => $msg['body'],
                'last_direction'=> $msg['direction'],
                'last_sender'   => $msg['sender_type'],
                'last_at'       => $msg['created_at'],
                'message_count' => 0,
                'unread'        => 0
            ];
        }
        $convos[$phone]['message_count']++;
        // Count inbound messages as "unread" (simplified — no read tracking yet)
        if ($msg['direction'] === 'inbound') {
            $convos[$phone]['unread']++;
        }
    }

    // Enrich with lead names from submission tables
    $result = [];
    foreach ($convos as $phone => $convo) {
        // Try to find lead name
        $lead = findLeadByPhoneOrEmail($phone, $convo['lead_email']);
        $convo['lead_name'] = $lead ? trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')) : '';
        $convo['lead_source'] = $lead['source'] ?? '';

        // Get AI automation status
        $automation = $sb->selectOne('sms_automation', 'status',
            ['lead_phone=eq.' . urlencode($phone)]);
        $convo['ai_status'] = $automation['status'] ?? 'active';

        $result[] = $convo;
    }

    // Sort by most recent message
    usort($result, function($a, $b) {
        return strtotime($b['last_at']) - strtotime($a['last_at']);
    });

    echo json_encode($result);
}

/**
 * Get full SMS thread for a specific phone number.
 */
function getSMSThread($phone) {
    global $sb;

    if (empty($phone)) {
        echo json_encode(['error' => 'Phone number required']);
        return;
    }

    $messages = $sb->select('sms_messages', '*',
        ['lead_phone=eq.' . urlencode($phone)],
        'created_at.asc');

    echo json_encode($messages);
}

/**
 * Send an SMS from the admin dashboard (broker takeover).
 */
function sendSMSFromDashboard() {
    global $sb;

    require_once __DIR__ . '/telnyx-sms.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $phone = $input['phone'] ?? '';
    $body  = $input['body'] ?? '';
    $senderName = $input['sender_name'] ?? 'Broker';

    if (empty($phone) || empty($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone and body are required']);
        return;
    }

    $normalizedPhone = normalizePhone($phone);

    // Send via Telnyx
    $result = sendSMS($normalizedPhone, $body);

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'SMS send failed: ' . $result['error']]);
        return;
    }

    // Find lead email
    $leadEmail = null;
    $lead = findLeadByPhoneOrEmail($normalizedPhone, null);
    if ($lead) $leadEmail = $lead['email'] ?? null;

    // Store the message
    $sb->insert('sms_messages', [
        'lead_phone'        => $normalizedPhone,
        'lead_email'        => $leadEmail,
        'direction'         => 'outbound',
        'sender_type'       => 'broker',
        'sender_name'       => $senderName,
        'body'              => $body,
        'telnyx_message_id' => $result['message_id'],
        'status'            => 'sent'
    ]);

    // Pause AI automation for this lead (broker took over)
    $existing = $sb->selectOne('sms_automation', 'id',
        ['lead_phone=eq.' . urlencode($normalizedPhone)]);

    if ($existing) {
        $sb->update('sms_automation', [
            'status'     => 'paused_manual',
            'paused_by'  => $senderName,
            'updated_at' => date('c')
        ], ['lead_phone=eq.' . urlencode($normalizedPhone)]);
    } else {
        $sb->insert('sms_automation', [
            'lead_phone' => $normalizedPhone,
            'lead_email' => $leadEmail,
            'status'     => 'paused_manual',
            'paused_by'  => $senderName
        ]);
    }

    echo json_encode(['success' => true, 'message_id' => $result['message_id']]);
}

/**
 * Toggle AI automation on/off for a specific lead.
 */
function toggleAIForLead() {
    global $sb;

    $input = json_decode(file_get_contents('php://input'), true);
    $phone  = $input['phone'] ?? '';
    $status = $input['status'] ?? '';  // 'active' or 'paused_manual'

    if (empty($phone) || !in_array($status, ['active', 'paused_manual'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone and valid status required']);
        return;
    }

    require_once __DIR__ . '/telnyx-sms.php';
    $normalizedPhone = normalizePhone($phone);

    $existing = $sb->selectOne('sms_automation', 'id',
        ['lead_phone=eq.' . urlencode($normalizedPhone)]);

    if ($existing) {
        $sb->update('sms_automation', [
            'status'     => $status,
            'paused_by'  => $status === 'paused_manual' ? 'Admin' : null,
            'updated_at' => date('c')
        ], ['lead_phone=eq.' . urlencode($normalizedPhone)]);
    } else {
        $leadEmail = null;
        $lead = findLeadByPhoneOrEmail($normalizedPhone, null);
        if ($lead) $leadEmail = $lead['email'] ?? null;

        $sb->insert('sms_automation', [
            'lead_phone' => $normalizedPhone,
            'lead_email' => $leadEmail,
            'status'     => $status,
            'paused_by'  => $status === 'paused_manual' ? 'Admin' : null
        ]);
    }

    echo json_encode(['success' => true, 'status' => $status]);
}

/**
 * Get AI automation status for a lead.
 */
function getAIStatus($phone) {
    global $sb;

    if (empty($phone)) {
        echo json_encode(['status' => 'active']);
        return;
    }

    $record = $sb->selectOne('sms_automation', 'status,paused_by,updated_at',
        ['lead_phone=eq.' . urlencode($phone)]);

    echo json_encode($record ?: ['status' => 'active', 'paused_by' => null]);
}

/**
 * Helper: find lead by phone or email across submission tables.
 */
function findLeadByPhoneOrEmail($phone, $email) {
    global $sb;

    $phoneDigits = preg_replace('/\D/', '', $phone ?? '');

    foreach ([
        ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'source' => 'Unit Interest']
    ] as $src) {
        // Try email first (faster via index)
        if ($email) {
            $row = $sb->selectOne($src['table'], 'first_name,last_name,email,phone',
                ['email=eq.' . urlencode($email)]);
            if ($row) {
                $row['source'] = $src['source'];
                return $row;
            }
        }

        // Try phone match
        if ($phoneDigits) {
            $rows = $sb->select($src['table'], 'first_name,last_name,email,phone');
            foreach ($rows as $row) {
                $rowPhone = preg_replace('/\D/', '', $row['phone'] ?? '');
                if ($rowPhone && $rowPhone === $phoneDigits) {
                    $row['source'] = $src['source'];
                    return $row;
                }
            }
        }
    }

    return null;
}
