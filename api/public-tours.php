<?php
/**
 * Public Tour Booking API
 *
 * Two actions, both unauthenticated:
 *   GET  ?action=availability&from=YYYY-MM-DD&to=YYYY-MM-DD
 *        Returns 30-min slots that are open with at least one active broker.
 *
 *   POST ?action=book
 *        Body: { first_name, last_name, email, phone, unit?, notes?, scheduled_at (ISO), website }
 *        Creates a pending tour_requests row + lead row, fires admin notification,
 *        sends "we got your request" SMS to applicant. Broker assignment + final
 *        confirmation happens in the admin dashboard.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/google-calendar.php';
require_once __DIR__ . '/telnyx-sms.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'availability': publicAvailability(); break;
    case 'book':         publicBookTour(); break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Active brokers (excludes is_active=false). Used for slot generation and
 * availability of slots for an arbitrary date range.
 */
function getActiveBrokers() {
    global $sb;
    // Supabase REST: not.eq applied via filter; the project uses is_active=neq.false elsewhere
    return $sb->select(
        'brokers',
        'id,name,google_calendar_connected,default_availability_start,default_availability_end,default_availability_days,is_active',
        ['is_active=neq.false']
    );
}

/**
 * GET ?action=availability&from=YYYY-MM-DD&to=YYYY-MM-DD
 * Returns: { slots: ["2026-05-12T10:00:00-04:00", ...] }  (deduped, sorted)
 *
 * A slot is considered available if at least one active broker is free at that
 * time. We DO NOT expose broker identity — the public form doesn't pick a
 * broker; an admin assigns one at confirmation time.
 */
function publicAvailability() {
    $from = $_GET['from'] ?? date('Y-m-d');
    $to   = $_GET['to']   ?? date('Y-m-d', strtotime('+14 days'));

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.']);
        return;
    }

    $tz = new DateTimeZone('America/New_York');
    $fromDt = new DateTime($from, $tz);
    $toDt   = new DateTime($to, $tz);
    $maxDt  = (new DateTime('now', $tz))->modify('+14 days');

    // Hard cap: never look past 14 days from now
    if ($toDt > $maxDt) $toDt = $maxDt;
    if ($fromDt > $toDt) {
        echo json_encode(['slots' => []]);
        return;
    }

    $brokers = getActiveBrokers();
    if (empty($brokers)) {
        echo json_encode(['slots' => []]);
        return;
    }

    // Union slots across all active brokers, dedup by ISO timestamp.
    $unionSlots = [];
    foreach ($brokers as $broker) {
        $brokerSlots = getAvailableTourSlots($broker['id'], $fromDt->format('Y-m-d'), $toDt->format('Y-m-d'));
        foreach ($brokerSlots as $slot) {
            $unionSlots[$slot] = true;
        }
    }

    // Remove slots that already have non-cancelled tour_requests.
    // Using a single range query for efficiency.
    global $sb;
    $rangeStart = urlencode($fromDt->format('c'));
    $rangeEnd   = urlencode((clone $toDt)->modify('+1 day')->format('c'));
    $existing = $sb->select(
        'tour_requests',
        'scheduled_at,duration_minutes,status',
        [
            'scheduled_at=gte.' . $rangeStart,
            'scheduled_at=lt.'  . $rangeEnd,
            'status=in.(pending,confirmed,completed)'
        ]
    );

    $busyRanges = [];
    foreach ($existing as $t) {
        $start = strtotime($t['scheduled_at']);
        $dur   = (int) ($t['duration_minutes'] ?: 30);
        $busyRanges[] = ['start' => $start, 'end' => $start + ($dur * 60)];
    }

    $slots = [];
    foreach (array_keys($unionSlots) as $slotIso) {
        $slotStart = strtotime($slotIso);
        $slotEnd   = $slotStart + (30 * 60);
        $taken = false;
        foreach ($busyRanges as $r) {
            if ($slotStart < $r['end'] && $slotEnd > $r['start']) {
                $taken = true;
                break;
            }
        }
        if (!$taken) $slots[] = $slotIso;
    }

    sort($slots);
    echo json_encode(['slots' => $slots, 'timezone' => 'America/New_York']);
}

/**
 * POST ?action=book
 * Body JSON: { first_name, last_name, email, phone, unit?, notes?, scheduled_at, website }
 */
function publicBookTour() {
    global $sb;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        return;
    }

    // Honeypot — bots fill this; humans don't see it
    if (!empty($input['website'])) {
        // Pretend success so the bot moves on
        echo json_encode(['success' => true, 'pending' => true]);
        return;
    }

    $firstName   = trim($input['first_name'] ?? '');
    $lastName    = trim($input['last_name'] ?? '');
    $emailRaw    = trim($input['email'] ?? '');
    $phoneRaw    = trim($input['phone'] ?? '');
    $unit        = trim($input['unit'] ?? '');
    $notes       = trim($input['notes'] ?? '');
    $scheduledAt = trim($input['scheduled_at'] ?? '');

    // Required fields
    if (!$firstName || !$lastName || !$emailRaw || !$phoneRaw || !$scheduledAt) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required field']);
        return;
    }

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }

    $phone = normalizePhone($phoneRaw);
    if (!$phone) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone number must be at least 10 digits']);
        return;
    }

    // Validate scheduled_at — must be a real datetime, at least 1 hour ahead, within 14 days
    $tz = new DateTimeZone('America/New_York');
    try {
        $when = new DateTime($scheduledAt, $tz);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid scheduled_at']);
        return;
    }
    $now = new DateTime('now', $tz);
    $earliest = (clone $now)->modify('+1 hour');
    $latest   = (clone $now)->modify('+14 days');
    if ($when < $earliest) {
        http_response_code(400);
        echo json_encode(['error' => 'Tour must be scheduled at least one hour in advance']);
        return;
    }
    if ($when > $latest) {
        http_response_code(400);
        echo json_encode(['error' => 'Tours can only be booked up to 14 days in advance']);
        return;
    }

    // Rate limit by email (2 / day) against tour_requests
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $dayCutoff = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
    $byEmail = $sb->select('tour_requests', 'id',
        ['created_at=gte.' . $dayCutoff, 'lead_email=eq.' . urlencode($email)], null, 3);
    if (count($byEmail) >= 2) {
        http_response_code(429);
        echo json_encode(['error' => 'You already have a tour request pending. Please wait for a confirmation.']);
        return;
    }

    // Re-check availability at submission time (race protection):
    // the requested slot must still overlap an active broker's open window
    // and not collide with any existing pending/confirmed tour.
    if (!isSlotStillAvailable($when)) {
        http_response_code(409);
        echo json_encode(['error' => 'That time is no longer available. Please pick another slot.']);
        return;
    }

    // Insert lead into unit_inquiries (matches existing inbound public capture)
    // Dedup by email — if a row exists, skip the insert.
    $existingLead = $sb->selectOne('unit_inquiries', 'id,email', ['email=eq.' . urlencode($email)]);
    if (!$existingLead) {
        // Also check waitlist_submissions to avoid creating a duplicate lead identity
        $existingLead = $sb->selectOne('waitlist_submissions', 'id,email', ['email=eq.' . urlencode($email)]);
    }
    if (!$existingLead) {
        $sb->insert('unit_inquiries', [
            'first_name'    => substr($firstName, 0, 100),
            'last_name'     => substr($lastName, 0, 100),
            'email'         => $email,
            'phone'         => $phone,
            'unit'          => $unit ?: null,
            'message'       => $notes ? substr($notes, 0, 1000) : null,
            'ip_address'    => $ip,
            'lead_status'   => 'New',
            'hear_about_us' => 'tour_booking',
        ]);
    }

    // Insert pending tour_requests row
    $tour = $sb->insert('tour_requests', [
        'lead_email'       => $email,
        'lead_phone'       => $phone,
        'unit'             => $unit ?: null,
        'broker_id'        => null,
        'scheduled_at'     => $when->format('c'),
        'duration_minutes' => 30,
        'status'           => 'pending',
        'source'           => 'public_form',
        'notes'            => $notes ?: null,
    ]);

    if (!$tour) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save your tour request. Please try again.']);
        return;
    }

    // Auto-update lead status to Contacted (pending — not yet Showing Scheduled)
    publicAutoUpdateLeadStatus($email, 'Contacted');

    // Log to communications
    $sb->insert('communications', [
        'lead_email' => $email,
        'direction'  => 'inbound',
        'channel'    => 'web',
        'subject'    => 'Tour Request Submitted',
        'body'       => 'Public tour request for ' . $when->format('l, F j \\a\\t g:ia')
                       . ($unit ? " (Unit {$unit})" : '')
                       . ($notes ? "\n\nNotes: " . substr($notes, 0, 500) : ''),
        'sender'     => trim($firstName . ' ' . $lastName),
        'status'     => 'received',
    ]);

    // Admin notification
    $leadName = trim($firstName . ' ' . $lastName);
    $sb->insert('notifications', [
        'type'       => 'tour_requested',
        'title'      => 'Tour Requested: ' . $leadName . ' — ' . $when->format('D \\a\\t g:ia'),
        'body'       => $unit ? ('Unit ' . $unit) : 'Pending broker assignment',
        'lead_email' => $email,
        'broker_id'  => null,
        'is_read'    => false,
        'link'       => '/admin/calendar.php',
    ]);

    // Acknowledgement SMS to applicant (not a final confirmation)
    $ackMsg = "Thanks {$firstName}! We received your tour request for " . $when->format('l, F j \\a\\t g:ia')
            . ". A leasing agent will confirm shortly. — The Eleanor";
    $smsResult = sendSMS($phone, $ackMsg);

    echo json_encode([
        'success' => true,
        'pending' => true,
        'scheduled_at_display' => $when->format('l, F j \\a\\t g:ia'),
        'sms_sent' => !empty($smsResult['success']),
    ]);
}

/**
 * Check whether the requested datetime is still bookable: at least one active
 * broker is free, and no existing pending/confirmed tour overlaps the 30-min slot.
 */
function isSlotStillAvailable(DateTime $when) {
    global $sb;
    $brokers = getActiveBrokers();
    if (empty($brokers)) return false;

    $tz = new DateTimeZone('America/New_York');
    $dateStr = $when->format('Y-m-d');
    $whenIso = $when->format('c');

    // Conflict check against existing tour_requests (any status that counts as
    // "taken"). Pull a small window and check overlap server-side.
    $rangeStart = urlencode((clone $when)->modify('-1 hour')->format('c'));
    $rangeEnd   = urlencode((clone $when)->modify('+1 hour')->format('c'));
    $existing = $sb->select(
        'tour_requests',
        'scheduled_at,duration_minutes,status',
        [
            'scheduled_at=gte.' . $rangeStart,
            'scheduled_at=lte.' . $rangeEnd,
            'status=in.(pending,confirmed,completed)'
        ]
    );
    $whenStart = $when->getTimestamp();
    $whenEnd   = $whenStart + (30 * 60);
    foreach ($existing as $t) {
        $tStart = strtotime($t['scheduled_at']);
        $tEnd   = $tStart + ((int)($t['duration_minutes'] ?: 30) * 60);
        if ($whenStart < $tEnd && $whenEnd > $tStart) {
            return false;
        }
    }

    // At least one active broker must offer this slot.
    foreach ($brokers as $broker) {
        $brokerSlots = getAvailableTourSlots($broker['id'], $dateStr, $dateStr);
        foreach ($brokerSlots as $slotIso) {
            if (strtotime($slotIso) === $whenStart) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Localised copy of autoUpdateLeadStatus (admin-api.php) — needed here because
 * we don't require admin-api.php (which would force auth).
 */
function publicAutoUpdateLeadStatus($email, $newStatus) {
    global $sb;
    if (!$email) return;

    $hierarchy = ['New' => 0, 'Contacted' => 1, 'Showing Scheduled' => 2, 'Showed' => 3, 'Lost' => 4];
    $newRank = $hierarchy[$newStatus] ?? -1;
    if ($newRank < 0) return;

    foreach (['waitlist_submissions', 'unit_inquiries'] as $table) {
        $lead = $sb->selectOne($table, 'lead_status', ['email=eq.' . urlencode($email)]);
        if ($lead) {
            $currentStatus = $lead['lead_status'] ?? 'New';
            $currentRank = $hierarchy[$currentStatus] ?? 0;
            if ($newRank > $currentRank) {
                $sb->update($table, ['lead_status' => $newStatus], ['email=eq.' . urlencode($email)]);
            }
            return;
        }
    }
}
