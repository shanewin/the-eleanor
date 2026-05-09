<?php
/**
 * Admin Data API — Supabase REST
 */
header('Content-Type: application/json');
require_once 'db_config.php';
require_once '../admin/auth.php';
require_once 'google-calendar.php';

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
    case 'engage_ai_preview': engageAIPreview(); break;
    case 'engage_ai_send': engageAISend(); break;
    case 'update_lead_status': updateLeadStatus(); break;
    case 'get_my_profile': getMyProfile(); break;
    case 'update_my_profile': updateMyProfile(); break;
    case 'upload_profile_picture': uploadProfilePicture(); break;
    case 'get_unified_timeline': getUnifiedTimeline($_GET['email'] ?? ''); break;
    case 'get_notifications': getNotifications(); break;
    case 'mark_notifications_read': markNotificationsRead(); break;
    case 'mark_sms_read': markSMSRead(); break;
    case 'get_tour_requests': getTourRequests(); break;
    case 'add_tour_request': addTourRequest(); break;
    case 'update_tour_request': updateTourRequest(); break;
    case 'google_calendar_connect': googleCalendarConnect(); break;
    case 'google_calendar_disconnect': googleCalendarDisconnect(); break;
    case 'google_calendar_status': googleCalendarStatus(); break;
    case 'google_calendar_availability': googleCalendarAvailability(); break;
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

        // Deduplicate by email AND phone number, but collect all submissions per primary lead
        $seenEmails = [];
        $seenPhones = [];
        $unique = [];
        // Map primary email -> index in $unique (so we can attach later submissions to the right primary lead)
        $primaryByEmail = [];
        $primaryByPhone = [];

        foreach ($allLeads as $lead) {
            $email = strtolower($lead['email']);
            $phone = preg_replace('/\D/', '', $lead['phone'] ?? '');
            $source = $lead['source'] ?? '';

            // Capture the raw submission row for the submissions array (Waitlist + Unit Interest only)
            $isTrackedForm = ($source === 'Waitlist' || $source === 'Unit Interest');
            $submissionRow = $isTrackedForm ? $lead : null;

            // Find the primary lead this submission belongs to (if it's a duplicate)
            $primaryIdx = null;
            if (isset($seenEmails[$email])) {
                $primaryIdx = $primaryByEmail[$email];
            } elseif ($phone && strlen($phone) >= 10 && isset($seenPhones[$phone])) {
                $primaryIdx = $primaryByPhone[$phone];
            }

            // If this is a duplicate, attach as a submission to the primary lead and skip
            if ($primaryIdx !== null) {
                if ($submissionRow) {
                    $unique[$primaryIdx]['submissions'][] = $submissionRow;
                    $unique[$primaryIdx]['submission_count'] = count($unique[$primaryIdx]['submissions']);
                }
                continue;
            }

            // First time we've seen this email/phone — make it the primary
            $seenEmails[$email] = true;
            $primaryByEmail[$email] = count($unique);
            if ($phone && strlen($phone) >= 10) {
                $seenPhones[$phone] = true;
                $primaryByPhone[$phone] = count($unique);
            }

            // Merge enrichment from pre-fetched data
            if (isset($enrichmentByEmail[$email])) {
                $lead = array_merge($lead, $enrichmentByEmail[$email]);
            }

            // Attach broker name if assigned
            if (!empty($lead['assigned_to']) && isset($brokerLookup[$lead['assigned_to']])) {
                $lead['broker_name'] = $brokerLookup[$lead['assigned_to']];
            }

            $lead['event_count'] = 0;
            $lead['submissions'] = $submissionRow ? [$submissionRow] : [];
            $lead['submission_count'] = $submissionRow ? 1 : 0;

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

    // Gather all Waitlist + Unit Interest submissions for this lead (by email + phone tail)
    $primaryPhone = preg_replace('/\D/', '', $submission['phone'] ?? '');
    $phoneTail = $primaryPhone && strlen($primaryPhone) >= 10 ? substr($primaryPhone, -10) : null;
    $submissions = [];
    foreach ([
        ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'source' => 'Unit Interest']
    ] as $src) {
        $rows = $sb->select($src['table'], '*', ['email=eq.' . urlencode($email)], 'created_at.asc');
        foreach ($rows as $r) {
            $r['source'] = $src['source'];
            $submissions[] = $r;
        }
        if ($phoneTail) {
            $allRows = $sb->select($src['table'], '*');
            foreach ($allRows as $r) {
                $rPhone = preg_replace('/\D/', '', $r['phone'] ?? '');
                if ($rPhone && substr($rPhone, -10) === $phoneTail
                    && strtolower($r['email']) !== strtolower($email)) {
                    $r['source'] = $src['source'];
                    $submissions[] = $r;
                }
            }
        }
    }
    usort($submissions, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

    $merged = array_merge($submission, $enrichment);
    if (!isset($merged['phone_number']) && isset($merged['phone'])) {
        $merged['phone_number'] = $merged['phone'];
    }

    $merged['submissions'] = $submissions;
    $merged['submission_count'] = count($submissions);

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

    if (!isOwner()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Owner only']);
        return;
    }

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

    $email = $input['email'];

    // Invite broker via Supabase Auth — sends email with link to set password
    $authUserId = null;
    $ch = curl_init(SUPABASE_URL . '/auth/v1/invite');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Authorization: Bearer ' . SUPABASE_SECRET_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $authData = json_decode($response, true);
        $authUserId = $authData['id'] ?? null;
        error_log("Supabase invite sent to $email");
    } else {
        error_log("Failed to invite $email via Supabase: $response");
    }

    $brokerData = [
        'name'  => $input['name'],
        'email' => $email,
        'phone' => $input['phone'] ?? null,
        'role'  => $input['role'] ?? 'broker'
    ];
    if ($authUserId) {
        $brokerData['auth_user_id'] = $authUserId;
    }

    $result = $sb->insert('brokers', $brokerData);

    echo json_encode(['success' => true, 'broker' => $result, 'auth_created' => !!$authUserId]);
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

/**
 * Auto-update lead status based on system events.
 * Only upgrades — never moves backward in the pipeline.
 */
function autoUpdateLeadStatus($email, $newStatus) {
    global $sb;
    if (!$email) return;

    $hierarchy = ['New' => 0, 'Contacted' => 1, 'Showing Scheduled' => 2, 'Showed' => 3, 'Applied' => 4, 'Leased' => 5, 'Lost' => 6];
    $newRank = $hierarchy[$newStatus] ?? -1;
    if ($newRank < 0) return;

    foreach (['waitlist_submissions', 'unit_inquiries'] as $table) {
        $lead = $sb->selectOne($table, 'lead_status', ['email=eq.' . urlencode($email)]);
        if ($lead) {
            $currentStatus = $lead['lead_status'] ?? 'New';
            $currentRank = $hierarchy[$currentStatus] ?? 0;

            if ($newRank > $currentRank) {
                $sb->update($table, ['lead_status' => $newStatus], ['email=eq.' . urlencode($email)]);
                $sb->insert('communications', [
                    'lead_email' => $email,
                    'direction'  => 'internal',
                    'channel'    => 'note',
                    'subject'    => 'Status auto-updated to: ' . $newStatus,
                    'sender'     => 'System',
                    'status'     => 'system'
                ]);
            }
            return;
        }
    }
}

/* ── Notifications ── */

/**
 * Create a notification. Safe to call from any context.
 */
function createNotification($type, $title, $body, $leadEmail = null, $brokerId = null, $link = null) {
    global $sb;
    $sb->insert('notifications', [
        'type'       => $type,
        'title'      => $title,
        'body'       => $body,
        'lead_email' => $leadEmail,
        'broker_id'  => $brokerId,
        'is_read'    => false,
        'link'       => $link
    ]);
}

function getNotifications() {
    global $sb;

    $broker = getCurrentBroker();
    $isOwner = isOwner();

    if ($isOwner) {
        $notifications = $sb->select('notifications', '*', [], 'created_at.desc', 50);
    } else {
        $brokerId = $broker['id'] ?? 0;
        // Broker sees: their notifications + notifications with no broker_id (global)
        $own = $sb->select('notifications', '*', ['broker_id=eq.' . intval($brokerId)], 'created_at.desc', 50);
        $global = $sb->select('notifications', '*', ['broker_id=is.null'], 'created_at.desc', 25);
        $notifications = array_merge($own, $global);
        // Sort by created_at desc
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        $notifications = array_slice($notifications, 0, 50);
    }

    $unreadCount = 0;
    foreach ($notifications as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }

    echo json_encode([
        'notifications' => $notifications,
        'unread_count'   => $unreadCount
    ]);
}

function markNotificationsRead() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? null;

    $broker = getCurrentBroker();
    $isOwner = isOwner();

    if ($ids && is_array($ids)) {
        // Mark specific notifications
        foreach ($ids as $id) {
            $sb->update('notifications', ['is_read' => true], ['id=eq.' . $id]);
        }
    } else {
        // Mark all visible notifications as read
        if ($isOwner) {
            $sb->update('notifications', ['is_read' => true], ['is_read=eq.false']);
        } else {
            $brokerId = $broker['id'] ?? 0;
            $sb->update('notifications', ['is_read' => true], ['broker_id=eq.' . intval($brokerId), 'is_read=eq.false']);
            $sb->update('notifications', ['is_read' => true], ['broker_id=is.null', 'is_read=eq.false']);
        }
    }

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

/* ── Unified Timeline ── */

/**
 * Get a unified timeline merging communications + sms_messages for a lead.
 */
function getUnifiedTimeline($email) {
    global $sb;

    if (empty($email)) {
        echo json_encode(['error' => 'Email is required']);
        return;
    }

    // Look up lead info
    $lead = null;
    foreach ([
        ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'source' => 'Unit Interest'],
        ['table' => 'mailing_list', 'source' => 'Mailing List']
    ] as $src) {
        $row = $sb->selectOne($src['table'], 'first_name,last_name,email,phone,assigned_to,lead_status',
            ['email=eq.' . urlencode($email)]);
        if ($row) {
            $row['source'] = $src['source'];
            $lead = $row;
            break;
        }
    }

    if (!$lead) {
        echo json_encode(['error' => 'Lead not found']);
        return;
    }

    $leadName = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
    $leadPhone = $lead['phone'] ?? '';

    // Get assigned broker info
    $brokerName = null;
    if (!empty($lead['assigned_to'])) {
        $broker = $sb->selectOne('brokers', 'name,phone', ['id=eq.' . $lead['assigned_to']]);
        $brokerName = $broker['name'] ?? null;
    }

    // Fetch all form submissions for this lead (Waitlist + Unit Interest only)
    // Match by email OR phone (normalised, last 10 digits) so we catch entries with different emails
    $submissions = [];
    $phoneDigits = preg_replace('/\D/', '', $leadPhone);
    $phoneTail = $phoneDigits && strlen($phoneDigits) >= 10 ? substr($phoneDigits, -10) : null;

    foreach ([
        ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'source' => 'Unit Interest']
    ] as $src) {
        $rows = $sb->select($src['table'], '*',
            ['email=eq.' . urlencode($email)], 'created_at.asc');
        foreach ($rows as $r) {
            $r['source'] = $src['source'];
            $submissions[] = $r;
        }

        // Also pick up rows that share the phone but have a different email
        if ($phoneTail) {
            $allRows = $sb->select($src['table'], '*');
            foreach ($allRows as $r) {
                $rPhone = preg_replace('/\D/', '', $r['phone'] ?? '');
                if ($rPhone && substr($rPhone, -10) === $phoneTail
                    && strtolower($r['email']) !== strtolower($email)) {
                    $r['source'] = $src['source'];
                    $submissions[] = $r;
                }
            }
        }
    }

    // Sort submissions chronologically
    usort($submissions, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

    // Fetch manual communications
    $comms = $sb->select('communications', '*',
        ['lead_email=eq.' . urlencode($email)],
        'created_at.asc');

    // Fetch SMS messages (by email OR phone)
    $smsMessages = [];
    if ($email) {
        $smsMessages = $sb->select('sms_messages', '*',
            ['lead_email=eq.' . urlencode($email)],
            'created_at.asc');
    }
    // Also try by phone if we have one and got no results by email
    if (empty($smsMessages) && $leadPhone) {
        require_once __DIR__ . '/telnyx-sms.php';
        $normalizedPhone = normalizePhone($leadPhone);
        if ($normalizedPhone) {
            $smsMessages = $sb->select('sms_messages', '*',
                ['lead_phone=eq.' . urlencode($normalizedPhone)],
                'created_at.asc');
        }
    }

    // Normalize both into a common shape
    $timeline = [];

    foreach ($comms as $c) {
        $timeline[] = [
            'id'         => $c['id'] ?? null,
            'type'       => $c['channel'] ?? 'note',
            'direction'  => $c['direction'] ?? 'outbound',
            'sender'     => $c['sender'] ?? null,
            'body'       => $c['body'] ?? '',
            'subject'    => $c['subject'] ?? null,
            'source'     => 'manual',
            'status'     => $c['status'] ?? null,
            'created_at' => $c['created_at'],
            'raw_table'  => 'communications'
        ];
    }

    foreach ($smsMessages as $m) {
        $sender = 'Unknown';
        if ($m['direction'] === 'inbound') {
            $sender = $leadName ?: 'Lead';
        } else {
            $sender = $m['sender_name'] ?? ($m['sender_type'] === 'ai' ? 'Eleanor AI' : 'Broker');
        }

        $timeline[] = [
            'id'         => $m['id'] ?? null,
            'type'       => 'sms',
            'direction'  => $m['direction'],
            'sender'     => $sender,
            'body'       => $m['body'] ?? '',
            'subject'    => null,
            'source'     => $m['sender_type'] ?? 'unknown',
            'status'     => $m['status'] ?? null,
            'created_at' => $m['created_at'],
            'raw_table'  => 'sms_messages'
        ];
    }

    // Sort chronologically
    usort($timeline, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

    // Get AI automation status
    $aiStatus = ['status' => 'active', 'paused_by' => null, 'handoff_reason' => null];
    if ($leadPhone) {
        require_once __DIR__ . '/telnyx-sms.php';
        $normalizedPhone = normalizePhone($leadPhone);
        if ($normalizedPhone) {
            $record = $sb->selectOne('sms_automation', 'status,paused_by,handoff_reason',
                ['lead_phone=eq.' . urlencode($normalizedPhone)]);
            if ($record) $aiStatus = $record;
        }
    }

    echo json_encode([
        'lead' => [
            'name'        => $leadName,
            'email'       => $email,
            'phone'       => $leadPhone,
            'source'      => $lead['source'] ?? '',
            'lead_status' => $lead['lead_status'] ?? 'New',
            'assigned_to' => $lead['assigned_to'] ?? null,
            'broker_name' => $brokerName
        ],
        'ai_status'   => $aiStatus,
        'submissions' => $submissions,
        'timeline'    => $timeline
    ]);
}

/* ── Tour Requests ── */

function getTourRequests() {
    global $sb;
    $tours = $sb->select('tour_requests', '*', [], 'scheduled_at.asc', 100);

    // Enrich with lead names and broker names
    $brokerRows = $sb->select('brokers', 'id,name', []);
    $brokerLookup = [];
    foreach ($brokerRows as $b) $brokerLookup[$b['id']] = $b['name'];

    foreach ($tours as &$tour) {
        $tour['broker_name'] = isset($brokerLookup[$tour['broker_id']]) ? $brokerLookup[$tour['broker_id']] : 'Unassigned';

        // Find lead name
        $leadName = '';
        if (!empty($tour['lead_email'])) {
            $lead = findLeadByPhoneOrEmail(null, $tour['lead_email']);
            if ($lead) $leadName = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
        }
        if (!$leadName && !empty($tour['lead_phone'])) {
            $lead = findLeadByPhoneOrEmail($tour['lead_phone'], null);
            if ($lead) $leadName = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
        }
        $tour['lead_name'] = $leadName ?: 'Unknown';
    }
    unset($tour);

    echo json_encode($tours);
}

function addTourRequest() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    $email = $input['lead_email'] ?? '';
    $phone = $input['lead_phone'] ?? '';
    $unit = $input['unit'] ?? '';
    $brokerId = $input['broker_id'] ?? null;
    $scheduledAt = $input['scheduled_at'] ?? '';
    $notes = $input['notes'] ?? '';

    if (empty($scheduledAt)) {
        http_response_code(400);
        echo json_encode(['error' => 'Scheduled date/time is required']);
        return;
    }

    // Normalize phone if provided
    if ($phone) {
        require_once __DIR__ . '/telnyx-sms.php';
        $phone = normalizePhone($phone) ?: $phone;
    }

    $result = $sb->insert('tour_requests', [
        'lead_email'       => $email ?: null,
        'lead_phone'       => $phone ?: null,
        'unit'             => $unit ?: null,
        'broker_id'        => $brokerId ?: null,
        'scheduled_at'     => $scheduledAt,
        'duration_minutes' => 30,
        'status'           => 'confirmed',
        'source'           => 'manual',
        'notes'            => $notes ?: null
    ]);

    // Auto-update lead status
    if ($email) {
        autoUpdateLeadStatus($email, 'Showing Scheduled');
    }

    // Log in communications
    if ($email) {
        $tz = new DateTimeZone('America/New_York');
        $dt = new DateTime($scheduledAt, $tz);
        $sb->insert('communications', [
            'lead_email' => $email,
            'direction'  => 'internal',
            'channel'    => 'note',
            'subject'    => 'Tour Scheduled',
            'body'       => 'Tour manually scheduled for ' . $dt->format('l, F j \\a\\t g:ia') . ($unit ? " (Unit {$unit})" : ''),
            'sender'     => 'System',
            'status'     => 'system'
        ]);
    }

    // Create notification
    $leadName = '';
    if ($email) {
        $l = findLeadByPhoneOrEmail(null, $email);
        if ($l) $leadName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
    }
    $tz2 = new DateTimeZone('America/New_York');
    $dt2 = new DateTime($scheduledAt, $tz2);
    createNotification('tour_booked',
        'Tour Booked: ' . ($leadName ?: 'Lead') . ' — ' . $dt2->format('D \\a\\t g:ia'),
        $unit ? 'Unit ' . $unit : null,
        $email, $brokerId, '/admin/calendar.php');

    echo json_encode(['success' => true, 'tour' => $result]);
}

function updateTourRequest() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tour request id is required']);
        return;
    }

    $id = $input['id'];
    $data = [];
    foreach (['status', 'notes', 'scheduled_at'] as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = $input[$field];
        }
    }
    $data['updated_at'] = date('c');

    $sb->update('tour_requests', $data, ['id=eq.' . $id]);
    echo json_encode(['success' => true]);
}

/**
 * Mark all inbound SMS messages as read for a lead (by email or phone).
 */
function markSMSRead() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $phone = $input['phone'] ?? '';

    if ($email) {
        $sb->update('sms_messages', ['is_read' => true],
            ['lead_email=eq.' . urlencode($email), 'direction=eq.inbound', 'is_read=eq.false']);
    } elseif ($phone) {
        require_once __DIR__ . '/telnyx-sms.php';
        $normalized = normalizePhone($phone);
        if ($normalized) {
            $sb->update('sms_messages', ['is_read' => true],
                ['lead_phone=eq.' . urlencode($normalized), 'direction=eq.inbound', 'is_read=eq.false']);
        }
    }

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
        // Count unread inbound messages
        if ($msg['direction'] === 'inbound' && empty($msg['is_read'])) {
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

        // Get AI automation status + follow-up status
        $automation = $sb->selectOne('sms_automation', 'status,followup_status,followup_count',
            ['lead_phone=eq.' . urlencode($phone)]);
        $convo['ai_status'] = $automation['status'] ?? 'active';
        $convo['followup_status'] = $automation['followup_status'] ?? 'none';
        $convo['followup_count'] = $automation['followup_count'] ?? 0;

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

    // Auto-update lead status to Contacted
    if ($leadEmail) {
        autoUpdateLeadStatus($leadEmail, 'Contacted');
    }

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

/**
 * Generate an AI welcome message preview (does NOT send).
 */
function engageAIPreview() {
    global $sb;

    require_once __DIR__ . '/telnyx-sms.php';
    require_once __DIR__ . '/sms-ai.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        return;
    }

    $lead = findLeadByPhoneOrEmail(null, $email);
    if (!$lead || empty($lead['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Lead not found or no phone number on file']);
        return;
    }

    $phone = normalizePhone($lead['phone']);
    if (!$phone) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid phone number']);
        return;
    }

    if (!defined('TELNYX_FROM_NUMBER') || !TELNYX_FROM_NUMBER) {
        http_response_code(500);
        echo json_encode(['error' => 'Telnyx phone number not configured']);
        return;
    }

    // Check for existing conversation
    $existingMessages = $sb->select('sms_messages', 'id',
        ['lead_phone=eq.' . urlencode($phone)], null, 1);
    $hasConversation = !empty($existingMessages);

    // Generate the right type of message
    if ($hasConversation) {
        // Re-engagement message — contextual, based on conversation history
        $msg = generateFollowupMessage($phone, 1);
    } else {
        // Welcome message — first contact
        $msg = generateInitialMessage($phone, $email);
    }

    if (!$msg) {
        http_response_code(500);
        echo json_encode(['error' => 'AI failed to generate message. Try again.']);
        return;
    }

    $name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));

    echo json_encode([
        'success'        => true,
        'phone'          => $phone,
        'name'           => $name,
        'message'        => $msg,
        'is_reengagement' => $hasConversation
    ]);
}

/**
 * Send a (possibly edited) Auto Text message to a lead.
 */
function engageAISend() {
    global $sb;

    require_once __DIR__ . '/telnyx-sms.php';
    require_once __DIR__ . '/sms-ai.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $phone = $input['phone'] ?? '';
    $body  = $input['body'] ?? '';

    if (empty($email) || empty($phone) || empty($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email, phone, and message body are required']);
        return;
    }

    $normalizedPhone = normalizePhone($phone);

    $smsResult = sendSMS($normalizedPhone, $body);
    if (!$smsResult['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'SMS send failed: ' . $smsResult['error']]);
        return;
    }

    // Log the message
    $sb->insert('sms_messages', [
        'lead_phone'        => $normalizedPhone,
        'lead_email'        => $email,
        'direction'         => 'outbound',
        'sender_type'       => 'ai',
        'sender_name'       => 'Eleanor AI',
        'body'              => $body,
        'telnyx_message_id' => $smsResult['message_id'] ?? null,
        'status'            => 'sent'
    ]);

    // Create/update automation record — set to active so AI continues the conversation
    $sb->upsert('sms_automation', [
        'lead_phone' => $normalizedPhone,
        'lead_email' => $email,
        'status'     => 'active',
        'updated_at' => date('c')
    ], 'lead_phone');

    // Auto-update lead status to Contacted
    autoUpdateLeadStatus($email, 'Contacted');

    echo json_encode(['success' => true]);
}

// ── Profile Endpoints ──

function getCurrentUserEmail() {
    return $_SESSION['supabase_user']['email'] ?? '';
}

function getCurrentBroker() {
    global $sb;
    $email = getCurrentUserEmail();
    if (!$email) return null;
    return $sb->selectOne('brokers', '*', ['email=eq.' . $email]);
}

function getMyProfile() {
    $broker = getCurrentBroker();
    if (!$broker) {
        echo json_encode(['error' => 'Profile not found']);
        return;
    }
    // Parse JSONB fields
    if (is_string($broker['default_availability_days'])) {
        $broker['default_availability_days'] = json_decode($broker['default_availability_days'], true);
    }
    // Never expose tokens to frontend
    unset($broker['google_calendar_token']);
    echo json_encode($broker);
}

function updateMyProfile() {
    global $sb;
    $broker = getCurrentBroker();
    if (!$broker) {
        echo json_encode(['error' => 'Profile not found']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $allowed = ['name', 'title', 'phone', 'license_number', 'company', 'bio', 'preferred_contact', 'default_availability_start', 'default_availability_end', 'default_availability_days'];

    $data = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = $input[$field];
        }
    }

    if (empty($data)) {
        echo json_encode(['error' => 'No fields to update']);
        return;
    }

    // Encode JSON fields
    if (isset($data['default_availability_days']) && is_array($data['default_availability_days'])) {
        $data['default_availability_days'] = json_encode($data['default_availability_days']);
    }

    $sb->update('brokers', $data, ['id=eq.' . $broker['id']]);

    // Clear cached role in case name changed
    unset($_SESSION['user_role']);

    echo json_encode(['success' => true]);
}

function uploadProfilePicture() {
    global $sb;
    $broker = getCurrentBroker();
    if (!$broker) {
        echo json_encode(['error' => 'Profile not found']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $image = $input['image'] ?? null;

    // Validate base64 if provided
    if ($image !== null && !preg_match('/^data:image\/(png|jpeg|jpg|gif|webp);base64,/', $image)) {
        echo json_encode(['error' => 'Invalid image format']);
        return;
    }

    $sb->update('brokers', ['profile_picture' => $image], ['id=eq.' . $broker['id']]);
    echo json_encode(['success' => true]);
}

// ── Google Calendar Endpoints ──

function googleCalendarConnect() {
    $broker = getCurrentBroker();
    if (!$broker) {
        echo json_encode(['error' => 'Profile not found']);
        return;
    }

    $url = googleBuildAuthUrl($broker['id']);
    echo json_encode(['url' => $url]);
}

function googleCalendarDisconnect() {
    global $sb;
    $broker = getCurrentBroker();
    if (!$broker) {
        echo json_encode(['error' => 'Profile not found']);
        return;
    }

    // Optionally allow owner to disconnect another broker
    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = $input['broker_id'] ?? $broker['id'];

    // Only allow disconnecting others if owner
    if ($targetId != $broker['id'] && !isOwner()) {
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    // Revoke token (best-effort)
    $targetBroker = $sb->selectOne('brokers', 'google_calendar_token', ['id=eq.' . intval($targetId)]);
    if ($targetBroker && $targetBroker['google_calendar_token']) {
        $tokenData = is_string($targetBroker['google_calendar_token'])
            ? json_decode($targetBroker['google_calendar_token'], true)
            : $targetBroker['google_calendar_token'];
        if (!empty($tokenData['access_token'])) {
            googleRevokeToken($tokenData['access_token']);
        }
    }

    $sb->update('brokers', [
        'google_calendar_token'     => null,
        'google_calendar_email'     => null,
        'google_calendar_connected' => false
    ], ['id=eq.' . intval($targetId)]);

    echo json_encode(['success' => true]);
}

function googleCalendarStatus() {
    global $sb;
    $brokers = $sb->select('brokers', 'id,name,email,role,google_calendar_connected,google_calendar_email', [], 'name.asc');
    echo json_encode($brokers);
}

function googleCalendarAvailability() {
    global $sb;
    $input = json_decode(file_get_contents('php://input'), true);

    $brokerIds = $input['broker_ids'] ?? [];
    $timeMin = $input['time_min'] ?? '';
    $timeMax = $input['time_max'] ?? '';

    if (empty($brokerIds) || !$timeMin || !$timeMax) {
        echo json_encode(['error' => 'broker_ids, time_min, and time_max are required']);
        return;
    }

    // Limit date range to 31 days
    $minDate = strtotime($timeMin);
    $maxDate = strtotime($timeMax);
    if ($maxDate - $minDate > 31 * 86400) {
        echo json_encode(['error' => 'Date range cannot exceed 31 days']);
        return;
    }

    $result = [];
    foreach ($brokerIds as $brokerId) {
        $brokerId = intval($brokerId);
        $accessToken = googleGetValidToken($brokerId);

        if (!$accessToken) {
            // Broker not connected or token invalid — return default availability
            $broker = $sb->selectOne('brokers', 'id,name,google_calendar_connected,default_availability_start,default_availability_end,default_availability_days', ['id=eq.' . $brokerId]);
            $result[$brokerId] = [
                'name'      => $broker['name'] ?? 'Unknown',
                'connected' => false,
                'busy'      => [],
                'defaults'  => [
                    'start' => $broker['default_availability_start'] ?? '09:00',
                    'end'   => $broker['default_availability_end'] ?? '18:00',
                    'days'  => is_string($broker['default_availability_days'] ?? '')
                        ? json_decode($broker['default_availability_days'], true)
                        : ($broker['default_availability_days'] ?? [1,2,3,4,5])
                ]
            ];
            continue;
        }

        $busy = googleQueryFreeBusy($accessToken, $timeMin, $timeMax);
        $broker = $sb->selectOne('brokers', 'name', ['id=eq.' . $brokerId]);

        $result[$brokerId] = [
            'name'      => $broker['name'] ?? 'Unknown',
            'connected' => true,
            'busy'      => $busy ?: []
        ];
    }

    echo json_encode(['brokers' => $result]);
}
