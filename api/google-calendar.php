<?php
/**
 * Google Calendar Helper Library
 * Handles OAuth flow, token management, and FreeBusy queries.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';

define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_REVOKE_URL', 'https://oauth2.googleapis.com/revoke');
define('GOOGLE_FREEBUSY_URL', 'https://www.googleapis.com/calendar/v3/freeBusy');
define('GOOGLE_CALENDAR_SCOPE', 'https://www.googleapis.com/auth/calendar.freebusy https://www.googleapis.com/auth/calendar.events');

/**
 * Build the Google OAuth consent URL for a broker.
 * Stores a nonce in the session for CSRF validation.
 */
function googleBuildAuthUrl($brokerId) {
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_nonce'] = $nonce;
    $_SESSION['google_oauth_broker_id'] = $brokerId;

    // HMAC-sign the state to prevent tampering
    $stateData = json_encode(['broker_id' => $brokerId, 'nonce' => $nonce]);
    $hmac = hash_hmac('sha256', $stateData, GOOGLE_CLIENT_SECRET);
    $state = base64_encode(json_encode(['data' => $stateData, 'hmac' => $hmac]));

    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => GOOGLE_CALENDAR_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state
    ];

    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Validate the OAuth state parameter.
 * Returns ['broker_id' => ..., 'nonce' => ...] on success, false on failure.
 */
function googleValidateState($stateParam) {
    $decoded = json_decode(base64_decode($stateParam), true);
    if (!$decoded || empty($decoded['data']) || empty($decoded['hmac'])) {
        return false;
    }

    // Verify HMAC
    $expectedHmac = hash_hmac('sha256', $decoded['data'], GOOGLE_CLIENT_SECRET);
    if (!hash_equals($expectedHmac, $decoded['hmac'])) {
        return false;
    }

    $stateData = json_decode($decoded['data'], true);
    if (!$stateData || empty($stateData['nonce']) || empty($stateData['broker_id'])) {
        return false;
    }

    // Verify nonce matches session
    if (($stateData['nonce'] ?? '') !== ($_SESSION['google_oauth_nonce'] ?? '')) {
        return false;
    }

    return $stateData;
}

/**
 * Exchange an authorization code for access + refresh tokens.
 * Returns token data array or false on failure.
 */
function googleExchangeCode($code) {
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google token exchange failed ($httpCode): $response");
        return false;
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        error_log("Google token exchange missing access_token: $response");
        return false;
    }

    // Convert expires_in to absolute timestamp (with 60s safety margin)
    return [
        'access_token'  => $data['access_token'],
        'refresh_token' => $data['refresh_token'] ?? null,
        'token_type'    => $data['token_type'] ?? 'Bearer',
        'expires_at'    => time() + ($data['expires_in'] ?? 3600) - 60
    ];
}

/**
 * Refresh an expired access token using the refresh token.
 * Returns updated token data or false on failure.
 */
function googleRefreshAccessToken($tokenData) {
    if (empty($tokenData['refresh_token'])) {
        return false;
    }

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'refresh_token' => $tokenData['refresh_token'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'grant_type'    => 'refresh_token'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google token refresh failed ($httpCode): $response");
        return false;
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        return false;
    }

    // Google does NOT return refresh_token on refresh — keep the existing one
    return [
        'access_token'  => $data['access_token'],
        'refresh_token' => $tokenData['refresh_token'],
        'token_type'    => $data['token_type'] ?? 'Bearer',
        'expires_at'    => time() + ($data['expires_in'] ?? 3600) - 60
    ];
}

/**
 * Get a valid access token for a broker, refreshing if expired.
 * Updates stored token in Supabase if refreshed.
 * Returns access_token string or false on failure.
 */
function googleGetValidToken($brokerId) {
    global $sb;

    $broker = $sb->selectOne('brokers', 'id,google_calendar_token,google_calendar_connected', ['id=eq.' . intval($brokerId)]);
    if (!$broker || !$broker['google_calendar_connected']) {
        return false;
    }

    $tokenData = $broker['google_calendar_token'];
    if (is_string($tokenData)) {
        $tokenData = json_decode($tokenData, true);
    }
    if (!$tokenData || empty($tokenData['access_token'])) {
        return false;
    }

    // Check if token is expired
    if (time() >= ($tokenData['expires_at'] ?? 0)) {
        $refreshed = googleRefreshAccessToken($tokenData);
        if (!$refreshed) {
            // Refresh failed — token revoked or invalid. Mark as disconnected.
            $sb->update('brokers', [
                'google_calendar_connected' => false,
                'google_calendar_token'     => null,
                'google_calendar_email'     => null
            ], ['id=eq.' . intval($brokerId)]);
            return false;
        }

        // Store refreshed token
        $sb->update('brokers', [
            'google_calendar_token' => json_encode($refreshed)
        ], ['id=eq.' . intval($brokerId)]);

        return $refreshed['access_token'];
    }

    return $tokenData['access_token'];
}

/**
 * Get the Google account email for the connected calendar.
 * Returns email string or false on failure.
 */
function googleGetCalendarEmail($accessToken) {
    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google calendar primary fetch failed ($httpCode): $response");
        return false;
    }

    $data = json_decode($response, true);
    return $data['id'] ?? false;
}

/**
 * Query Google Calendar FreeBusy API.
 * Returns array of busy blocks [{start, end}, ...] or false on failure.
 */
function googleQueryFreeBusy($accessToken, $timeMin, $timeMax, $calendarIds = ['primary']) {
    $items = array_map(function($id) { return ['id' => $id]; }, $calendarIds);

    $body = json_encode([
        'timeMin'  => $timeMin,
        'timeMax'  => $timeMax,
        'timeZone' => 'America/New_York',
        'items'    => $items
    ]);

    $ch = curl_init(GOOGLE_FREEBUSY_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google FreeBusy query failed ($httpCode): $response");
        return false;
    }

    $data = json_decode($response, true);

    // Collect all busy blocks across all calendars
    $busy = [];
    if (!empty($data['calendars'])) {
        foreach ($data['calendars'] as $calId => $calData) {
            if (!empty($calData['busy'])) {
                foreach ($calData['busy'] as $block) {
                    $busy[] = $block;
                }
            }
        }
    }

    return $busy;
}

/**
 * Create a Google Calendar event.
 * Returns the event ID string on success, or false on failure.
 */
function googleCreateEvent($accessToken, $summary, $startTime, $endTime, $description = '', $attendeeEmail = null) {
    $event = [
        'summary'     => $summary,
        'description' => $description,
        'start'       => ['dateTime' => $startTime, 'timeZone' => 'America/New_York'],
        'end'         => ['dateTime' => $endTime, 'timeZone' => 'America/New_York'],
    ];

    if ($attendeeEmail) {
        $event['attendees'] = [['email' => $attendeeEmail]];
    }

    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Google Calendar create event failed ($httpCode): $response");
        return false;
    }

    $data = json_decode($response, true);
    return $data['id'] ?? false;
}

/**
 * Compute available 30-minute tour slots from FreeBusy data.
 * Returns array of ISO datetime strings for open slots within tour hours.
 */
function computeAvailableSlots($busyBlocks, $startDate, $endDate, $tourHoursStart = '10:00', $tourHoursEnd = '18:00', $activeDays = [1,2,3,4,5,6]) {
    $tz = new DateTimeZone('America/New_York');
    $start = new DateTime($startDate, $tz);
    $end = new DateTime($endDate, $tz);
    $slotDuration = 30; // minutes

    $slots = [];

    // Iterate day by day
    $current = clone $start;
    while ($current <= $end) {
        $dayOfWeek = (int) $current->format('w');

        // Skip days not in active days
        if (!in_array($dayOfWeek, $activeDays)) {
            $current->modify('+1 day');
            continue;
        }

        // Generate 30-min slots within tour hours
        list($startH, $startM) = explode(':', $tourHoursStart);
        list($endH, $endM) = explode(':', $tourHoursEnd);

        $slotTime = clone $current;
        $slotTime->setTime((int)$startH, (int)$startM);

        $dayEnd = clone $current;
        $dayEnd->setTime((int)$endH, (int)$endM);

        while ($slotTime < $dayEnd) {
            $slotEnd = clone $slotTime;
            $slotEnd->modify("+{$slotDuration} minutes");

            // Check if this slot overlaps any busy block
            $isBusy = false;
            foreach ($busyBlocks as $block) {
                $busyStart = new DateTime($block['start'], $tz);
                $busyEnd = new DateTime($block['end'], $tz);

                // Overlap if slot starts before busy ends AND slot ends after busy starts
                if ($slotTime < $busyEnd && $slotEnd > $busyStart) {
                    $isBusy = true;
                    break;
                }
            }

            // Enforce the 1-hour-ahead booking cutoff. publicBookTour rejects
            // anything closer than this, the admin Openings UI hides the same
            // window, and the AI SMS scheduler shouldn't offer "in 20 minutes"
            // slots either — so the filter belongs here, in shared logic.
            $earliest = (new DateTime('now', $tz))->modify('+1 hour');
            if ($slotTime < $earliest) {
                $isBusy = true;
            }

            if (!$isBusy) {
                $slots[] = $slotTime->format('c');
            }

            $slotTime->modify("+{$slotDuration} minutes");
        }

        $current->modify('+1 day');
    }

    return $slots;
}

/**
 * Get available tour slots for a broker over a date range.
 * Uses Google Calendar if connected, falls back to default availability.
 * Cached for 5 minutes per broker.
 */
function getAvailableTourSlots($brokerId, $startDate, $endDate) {
    global $sb;

    // Check cache (key includes date range — different windows must not share results)
    $cacheKey = $brokerId . '_' . md5($startDate . '|' . $endDate);
    $cacheFile = sys_get_temp_dir() . '/eleanor_tour_slots_' . $cacheKey . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }

    $broker = $sb->selectOne('brokers', 'id,google_calendar_connected,default_availability_start,default_availability_end,default_availability_days',
        ['id=eq.' . intval($brokerId)]);

    if (!$broker) return [];

    // Use the broker's stored default availability as the working window in
    // both paths. The admin calendar's Openings mode does the same — so the
    // public tour picker and the admin agree on which hours are bookable.
    $tourStart = substr($broker['default_availability_start'] ?? '09:00', 0, 5);
    $tourEnd   = substr($broker['default_availability_end']   ?? '18:00', 0, 5);
    $tourDays  = $broker['default_availability_days'] ?? [1, 2, 3, 4, 5];
    if (is_string($tourDays)) $tourDays = json_decode($tourDays, true);
    if (!is_array($tourDays) || empty($tourDays)) $tourDays = [1, 2, 3, 4, 5];

    if ($broker['google_calendar_connected']) {
        $accessToken = googleGetValidToken($brokerId);
        if ($accessToken) {
            $tz = new DateTimeZone('America/New_York');
            $timeMin = (new DateTime($startDate, $tz))->format('c');
            $timeMax = (new DateTime($endDate, $tz))->modify('+1 day')->format('c');

            $busyBlocks = googleQueryFreeBusy($accessToken, $timeMin, $timeMax);
            if ($busyBlocks !== false) {
                $slots = computeAvailableSlots($busyBlocks, $startDate, $endDate, $tourStart, $tourEnd, $tourDays);
                file_put_contents($cacheFile, json_encode($slots));
                return $slots;
            }
        }
    }

    // Fallback (no Google Calendar): same working window, no busy blocks.
    $slots = computeAvailableSlots([], $startDate, $endDate, $tourStart, $tourEnd, $tourDays);
    file_put_contents($cacheFile, json_encode($slots));
    return $slots;
}

/**
 * Revoke a Google OAuth token (best-effort).
 */
function googleRevokeToken($token) {
    $ch = curl_init(GOOGLE_REVOKE_URL . '?token=' . urlencode($token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_exec($ch);
    curl_close($ch);
}
