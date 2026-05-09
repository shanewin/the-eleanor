<?php
/**
 * SMS AI Conversation Handler
 * Uses Claude API to generate contextual, conversational responses for leads.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';

/**
 * Load all admin settings with 60-second cache.
 * Shared by buildSystemPrompt() and generateInitialMessage().
 */
function getAISettings() {
    global $sb;
    static $cache = null;
    static $cacheTime = 0;

    if ($cache === null || (time() - $cacheTime) > 60) {
        $rows = $sb->select('settings', '*');
        $cache = [];
        foreach ($rows as $r) {
            $cache[$r['key']] = $r['value'];
        }
        $cacheTime = time();
    }

    return $cache;
}

/**
 * Generate an AI response for an inbound SMS from a lead.
 *
 * @param string $leadPhone  The lead's phone number (E.164)
 * @param string $inboundText The message the lead just sent
 * @return string|null        The AI-generated reply, or null on failure
 */
function generateAIResponse($leadPhone, $inboundText) {
    global $sb;

    // 1. Look up lead info by phone number
    $leadContext = getLeadContext($leadPhone);

    // 2. Load conversation history (last 20 messages)
    $history = $sb->select('sms_messages', 'direction,sender_type,body,created_at',
        ['lead_phone=eq.' . urlencode($leadPhone)],
        'created_at.asc', 20);

    // 3. Build Claude messages array from conversation history
    // Note: the inbound message was already inserted into sms_messages by telnyx-webhook.php
    // before this function is called, so it's already included in $history. Do NOT append it again.
    $messages = [];
    foreach ($history as $msg) {
        if ($msg['direction'] === 'inbound') {
            $messages[] = ['role' => 'user', 'content' => $msg['body']];
        } else {
            $messages[] = ['role' => 'assistant', 'content' => $msg['body']];
        }
    }

    // 4. Build the system prompt
    $systemPrompt = buildSystemPrompt($leadContext);

    // 5. Build tool definitions
    $tools = getCalendarToolDefinitions();

    // 6. Call Claude API with tool use loop
    $payload = [
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 300,
        'system'     => $systemPrompt,
        'tools'      => $tools,
        'messages'   => $messages
    ];

    $reply = null;
    $maxLoops = 3;

    for ($i = 0; $i < $maxLoops; $i++) {
        $response = callClaudeAPI($payload);

        if (!$response) {
            error_log("Claude AI SMS response failed on loop $i");
            return null;
        }

        // Extract text from response
        $textParts = [];
        $toolCalls = [];
        foreach ($response['content'] as $block) {
            if ($block['type'] === 'text') {
                $textParts[] = $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = $block;
            }
        }

        // If no tool calls, we're done
        if ($response['stop_reason'] !== 'tool_use' || empty($toolCalls)) {
            $reply = implode(' ', $textParts);
            break;
        }

        // Execute each tool call
        $toolResults = [];
        foreach ($toolCalls as $call) {
            $result = executeCalendarTool($call['name'], $call['input'], $leadPhone, $leadContext);
            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $call['id'],
                'content'     => json_encode($result)
            ];
        }

        // Append assistant response + tool results to messages for next loop
        $payload['messages'][] = ['role' => 'assistant', 'content' => $response['content']];
        $payload['messages'][] = ['role' => 'user', 'content' => $toolResults];
    }

    // Trim to SMS-friendly length
    if ($reply && strlen($reply) > 480) {
        $truncated = substr($reply, 0, 480);
        $lastPeriod = strrpos($truncated, '.');
        $lastQuestion = strrpos($truncated, '?');
        $lastExclaim = strrpos($truncated, '!');
        $cutAt = max($lastPeriod ?: 0, $lastQuestion ?: 0, $lastExclaim ?: 0);
        if ($cutAt > 100) {
            $reply = substr($reply, 0, $cutAt + 1);
        } else {
            $lastSpace = strrpos($truncated, ' ');
            $reply = substr($reply, 0, $lastSpace ?: 477) . '...';
        }
    }

    return $reply;
}

/**
 * Call the Claude Messages API and return parsed response.
 */
function callClaudeAPI($payload) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Claude API failed ($httpCode): $response");
        return null;
    }

    return json_decode($response, true);
}

/**
 * Define the calendar tools available to Claude.
 */
function getCalendarToolDefinitions() {
    return [
        [
            'name' => 'check_tour_availability',
            'description' => 'Check available tour time slots for The Eleanor. Returns open 30-minute slots within the assigned broker\'s calendar for the given date range. Only call this when the conversation is heading toward scheduling a tour.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start_date' => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD format'],
                    'end_date'   => ['type' => 'string', 'description' => 'End date in YYYY-MM-DD format (max 14 days from start)']
                ],
                'required' => ['start_date', 'end_date']
            ]
        ],
        [
            'name' => 'book_tour',
            'description' => 'Book a confirmed tour at The Eleanor. Creates a calendar event and notifies the broker. Only call this after the lead has explicitly confirmed a specific date and time.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'datetime'      => ['type' => 'string', 'description' => 'Confirmed tour datetime in ISO 8601 format (e.g. 2026-05-13T14:00:00)'],
                    'lead_name'     => ['type' => 'string', 'description' => 'The lead\'s full name'],
                    'unit_interest' => ['type' => 'string', 'description' => 'Unit or unit type they\'re interested in, if known']
                ],
                'required' => ['datetime', 'lead_name']
            ]
        ],
        [
            'name' => 'get_tour_hours',
            'description' => 'Get the building\'s general tour availability hours and days. Use this when the lead asks about tour hours in general, before checking specific availability.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => []
            ]
        ],
        [
            'name' => 'cancel_tour',
            'description' => 'Cancel an existing tour for a lead. Only call this when the lead explicitly says they want to cancel and NOT reschedule.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'description' => 'Brief reason for cancellation, if given']
                ],
                'required' => []
            ]
        ],
        [
            'name' => 'reschedule_tour',
            'description' => 'Reschedule an existing tour to a new date/time. Cancels the old tour and books the new one. Only call this after the lead has confirmed the new time.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'new_datetime' => ['type' => 'string', 'description' => 'New confirmed tour datetime in ISO 8601 format'],
                    'lead_name'    => ['type' => 'string', 'description' => 'The lead\'s full name']
                ],
                'required' => ['new_datetime', 'lead_name']
            ]
        ]
    ];
}

/**
 * Execute a calendar tool call and return the result.
 */
function executeCalendarTool($toolName, $input, $leadPhone, $leadContext) {
    global $sb;

    require_once __DIR__ . '/google-calendar.php';

    switch ($toolName) {
        case 'get_tour_hours':
            $settings = getSMSSettings();
            $tourHours = $settings['ai_tour_hours'] ?? 'Weekdays 10am-6pm, Saturdays 11am-4pm';
            return ['tour_hours' => $tourHours];

        case 'check_tour_availability':
            $startDate = $input['start_date'] ?? date('Y-m-d');
            $endDate = $input['end_date'] ?? date('Y-m-d', strtotime('+7 days'));

            // Limit range to 14 days
            if (strtotime($endDate) - strtotime($startDate) > 14 * 86400) {
                $endDate = date('Y-m-d', strtotime($startDate . ' +14 days'));
            }

            $brokerId = $leadContext['assigned_to'] ?? null;
            if (!$brokerId) {
                // No broker assigned — try to find any active broker
                $anyBroker = $sb->selectOne('brokers', 'id', ['is_active=neq.false'], 'id.asc');
                $brokerId = $anyBroker['id'] ?? null;
            }

            if (!$brokerId) {
                return ['available_slots' => [], 'note' => 'No broker available. Suggest the lead contact the leasing office directly.'];
            }

            $slots = getAvailableTourSlots($brokerId, $startDate, $endDate);

            // Format slots for readability
            $formatted = [];
            $tz = new DateTimeZone('America/New_York');
            foreach ($slots as $slot) {
                $dt = new DateTime($slot, $tz);
                $day = $dt->format('l, F j');
                $time = $dt->format('g:ia');
                $formatted[$day][] = $time;
            }

            $readable = [];
            foreach ($formatted as $day => $times) {
                $readable[] = $day . ': ' . implode(', ', array_slice($times, 0, 6));
            }

            return [
                'available_slots' => array_slice($slots, 0, 30),
                'formatted' => $readable,
                'note' => empty($slots) ? 'No available slots in this range. Try a different date range.' : null
            ];

        case 'book_tour':
            $datetime = $input['datetime'] ?? '';
            $leadName = $input['lead_name'] ?? 'Lead';
            $unitInterest = $input['unit_interest'] ?? '';

            if (!$datetime) {
                return ['success' => false, 'error' => 'Datetime is required'];
            }

            // Validate datetime is in the future
            $tz = new DateTimeZone('America/New_York');
            $tourTime = new DateTime($datetime, $tz);
            $now = new DateTime('now', $tz);
            if ($tourTime <= $now) {
                return ['success' => false, 'error' => 'Cannot book a tour in the past'];
            }

            $brokerId = $leadContext['assigned_to'] ?? null;
            if (!$brokerId) {
                $anyBroker = $sb->selectOne('brokers', 'id', ['is_active=neq.false'], 'id.asc');
                $brokerId = $anyBroker['id'] ?? null;
            }

            // Create Google Calendar event
            $eventId = null;
            if ($brokerId) {
                $accessToken = googleGetValidToken($brokerId);
                if ($accessToken) {
                    $startISO = $tourTime->format('c');
                    $endTime = clone $tourTime;
                    $endTime->modify('+30 minutes');
                    $endISO = $endTime->format('c');

                    $summary = "Tour — {$leadName} — The Eleanor";
                    $description = "Tour with {$leadName}\nPhone: {$leadPhone}\nUnit Interest: {$unitInterest}";
                    $attendeeEmail = $leadContext['email'] ?? null;

                    $eventId = googleCreateEvent($accessToken, $summary, $startISO, $endISO, $description, $attendeeEmail);
                }
            }

            // Create tour_requests record
            $sb->insert('tour_requests', [
                'lead_email'       => $leadContext['email'] ?? null,
                'lead_phone'       => $leadPhone,
                'unit'             => $unitInterest,
                'broker_id'        => $brokerId,
                'scheduled_at'     => $tourTime->format('c'),
                'duration_minutes' => 30,
                'status'           => 'confirmed',
                'google_event_id'  => $eventId,
                'source'           => 'sms_ai',
                'notes'            => "Booked via SMS AI conversation"
            ]);

            // Log in communications
            $sb->insert('communications', [
                'lead_email' => $leadContext['email'] ?? null,
                'direction'  => 'internal',
                'channel'    => 'note',
                'subject'    => 'Tour Booked',
                'body'       => "Tour booked for {$tourTime->format('l, F j \\a\\t g:ia')} — {$leadName}" . ($unitInterest ? " (interested in {$unitInterest})" : ''),
                'sender'     => 'Eleanor AI',
                'status'     => 'system'
            ]);

            // Auto-update lead status to Showing Scheduled
            if (!empty($leadContext['email'])) {
                autoUpdateLeadStatusFromWebhook($leadContext['email'], 'Showing Scheduled');
            }

            // Notify broker
            if ($brokerId) {
                $broker = $sb->selectOne('brokers', 'name,phone', ['id=eq.' . intval($brokerId)]);
                if ($broker && !empty($broker['phone'])) {
                    require_once __DIR__ . '/telnyx-sms.php';
                    $brokerPhone = normalizePhone($broker['phone']);
                    if ($brokerPhone) {
                        $notifyMsg = "Eleanor Alert: Tour booked with {$leadName} on {$tourTime->format('l, F j \\a\\t g:ia')}." . ($unitInterest ? " Interest: {$unitInterest}." : '') . " Check the dashboard for details.";
                        sendSMS($brokerPhone, $notifyMsg);
                    }
                }
            }

            // Create notification
            createNotificationFromWebhook('tour_booked',
                'Tour Booked: ' . $leadName . ' — ' . $tourTime->format('D \\a\\t g:ia'),
                $unitInterest ? 'Interested in ' . $unitInterest : null,
                $leadContext['email'] ?? null, $brokerId,
                $leadContext['email'] ? '/admin/calendar.php' : null);

            return [
                'success'    => true,
                'event_time' => $tourTime->format('l, F j \\a\\t g:ia'),
                'calendar_event_created' => !!$eventId
            ];

        case 'cancel_tour':
            $reason = $input['reason'] ?? 'Lead requested cancellation';
            $leadEmail = $leadContext['email'] ?? '';

            // Find their confirmed tour
            $tour = null;
            if ($leadEmail) {
                $tour = $sb->selectOne('tour_requests', 'id,scheduled_at,google_event_id,broker_id',
                    ['lead_email=eq.' . urlencode($leadEmail), 'status=eq.confirmed']);
            }
            if (!$tour && $leadPhone) {
                $tour = $sb->selectOne('tour_requests', 'id,scheduled_at,google_event_id,broker_id',
                    ['lead_phone=eq.' . urlencode($leadPhone), 'status=eq.confirmed']);
            }

            if (!$tour) {
                return ['success' => false, 'error' => 'No confirmed tour found for this lead'];
            }

            // Cancel the tour
            $sb->update('tour_requests', [
                'status' => 'cancelled',
                'notes' => $reason,
                'updated_at' => date('c')
            ], ['id=eq.' . $tour['id']]);

            // Delete Google Calendar event if exists
            if (!empty($tour['google_event_id']) && !empty($tour['broker_id'])) {
                $accessToken = googleGetValidToken($tour['broker_id']);
                if ($accessToken) {
                    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events/' . urlencode($tour['google_event_id']));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }

            // Log
            $tz = new DateTimeZone('America/New_York');
            $tourTime = new DateTime($tour['scheduled_at'], $tz);
            $sb->insert('communications', [
                'lead_email' => $leadEmail,
                'direction'  => 'internal',
                'channel'    => 'note',
                'subject'    => 'Tour Cancelled',
                'body'       => 'Tour on ' . $tourTime->format('l, F j \\a\\t g:ia') . ' cancelled. Reason: ' . $reason,
                'sender'     => 'Eleanor AI',
                'status'     => 'system'
            ]);

            // Find lead name for notification
            $cancelLeadName = '';
            if ($leadEmail) {
                foreach (['waitlist_submissions', 'unit_inquiries'] as $t) {
                    $l = $sb->selectOne($t, 'first_name,last_name', ['email=eq.' . urlencode($leadEmail)]);
                    if ($l) { $cancelLeadName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')); break; }
                }
            }
            createNotificationFromWebhook('tour_cancelled',
                'Tour Cancelled: ' . ($cancelLeadName ?: 'Lead'),
                'Was scheduled for ' . $tourTime->format('l, F j \\a\\t g:ia') . '. Reason: ' . $reason,
                $leadEmail, $tour['broker_id'] ?? null, '/admin/calendar.php');

            return ['success' => true, 'cancelled_time' => $tourTime->format('l, F j \\a\\t g:ia')];

        case 'reschedule_tour':
            $newDatetime = $input['new_datetime'] ?? '';
            $leadName = $input['lead_name'] ?? 'Lead';
            $leadEmail = $leadContext['email'] ?? '';

            if (!$newDatetime) {
                return ['success' => false, 'error' => 'New datetime is required'];
            }

            // Find and cancel existing tour
            $oldTour = null;
            if ($leadEmail) {
                $oldTour = $sb->selectOne('tour_requests', 'id,scheduled_at,google_event_id,broker_id,unit',
                    ['lead_email=eq.' . urlencode($leadEmail), 'status=eq.confirmed']);
            }
            if (!$oldTour && $leadPhone) {
                $oldTour = $sb->selectOne('tour_requests', 'id,scheduled_at,google_event_id,broker_id,unit',
                    ['lead_phone=eq.' . urlencode($leadPhone), 'status=eq.confirmed']);
            }

            $brokerId = $oldTour['broker_id'] ?? ($leadContext['assigned_to'] ?? null);
            $unit = $oldTour['unit'] ?? ($input['unit_interest'] ?? '');

            // Cancel old tour if exists
            if ($oldTour) {
                $sb->update('tour_requests', [
                    'status' => 'cancelled',
                    'notes' => 'Rescheduled',
                    'updated_at' => date('c')
                ], ['id=eq.' . $oldTour['id']]);

                // Delete old Google Calendar event
                if (!empty($oldTour['google_event_id']) && $brokerId) {
                    $accessToken = googleGetValidToken($brokerId);
                    if ($accessToken) {
                        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events/' . urlencode($oldTour['google_event_id']));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }

            // Book new tour (reuse book_tour logic)
            $tz = new DateTimeZone('America/New_York');
            $tourTime = new DateTime($newDatetime, $tz);
            $now = new DateTime('now', $tz);
            if ($tourTime <= $now) {
                return ['success' => false, 'error' => 'Cannot schedule a tour in the past'];
            }

            // Create Google Calendar event
            $eventId = null;
            if ($brokerId) {
                $accessToken = googleGetValidToken($brokerId);
                if ($accessToken) {
                    $startISO = $tourTime->format('c');
                    $endTime = clone $tourTime;
                    $endTime->modify('+30 minutes');
                    $summary = "Tour — {$leadName} — The Eleanor";
                    $description = "Rescheduled tour with {$leadName}\nPhone: {$leadPhone}\nUnit: {$unit}";
                    $eventId = googleCreateEvent($accessToken, $summary, $startISO, $endTime->format('c'), $description, $leadEmail ?: null);
                }
            }

            // Create new tour request
            $sb->insert('tour_requests', [
                'lead_email'       => $leadEmail ?: null,
                'lead_phone'       => $leadPhone,
                'unit'             => $unit ?: null,
                'broker_id'        => $brokerId,
                'scheduled_at'     => $tourTime->format('c'),
                'duration_minutes' => 30,
                'status'           => 'confirmed',
                'google_event_id'  => $eventId,
                'source'           => 'sms_ai',
                'notes'            => 'Rescheduled via SMS'
            ]);

            // Log
            $oldTimeStr = $oldTour ? (new DateTime($oldTour['scheduled_at'], $tz))->format('l, F j \\a\\t g:ia') : 'N/A';
            $sb->insert('communications', [
                'lead_email' => $leadEmail,
                'direction'  => 'internal',
                'channel'    => 'note',
                'subject'    => 'Tour Rescheduled',
                'body'       => "Tour rescheduled from {$oldTimeStr} to {$tourTime->format('l, F j \\a\\t g:ia')} — {$leadName}",
                'sender'     => 'Eleanor AI',
                'status'     => 'system'
            ]);

            // Notify broker
            if ($brokerId) {
                $broker = $sb->selectOne('brokers', 'name,phone', ['id=eq.' . intval($brokerId)]);
                if ($broker && !empty($broker['phone'])) {
                    require_once __DIR__ . '/telnyx-sms.php';
                    $brokerPhone = normalizePhone($broker['phone']);
                    if ($brokerPhone) {
                        sendSMS($brokerPhone, "Eleanor Alert: {$leadName} rescheduled their tour to {$tourTime->format('l, F j \\a\\t g:ia')}.");
                    }
                }
            }

            createNotificationFromWebhook('tour_rescheduled',
                'Tour Rescheduled: ' . $leadName . ' — ' . $tourTime->format('D \\a\\t g:ia'),
                'Moved from ' . $oldTimeStr,
                $leadEmail ?: null, $brokerId, '/admin/calendar.php');

            return [
                'success'    => true,
                'old_time'   => $oldTimeStr,
                'new_time'   => $tourTime->format('l, F j \\a\\t g:ia'),
                'calendar_event_created' => !!$eventId
            ];

        default:
            return ['error' => 'Unknown tool: ' . $toolName];
    }
}

/**
 * Generate the initial outreach message for a new lead.
 */
function generateInitialMessage($leadPhone, $leadEmail) {
    global $sb;

    $leadContext = getLeadContext($leadPhone, $leadEmail);

    $systemPrompt = buildSystemPrompt($leadContext);

    // Check for custom welcome instructions
    $aiSettings = getAISettings();
    $customWelcome = trim($aiSettings['ai_welcome_instructions'] ?? '');

    if ($customWelcome) {
        $userPrompt = "This person just submitted a form expressing interest in The Eleanor. "
            . "Send them a warm, brief welcome text. Mention their name if you know it. "
            . "INSTRUCTIONS FROM THE LEASING TEAM: {$customWelcome} "
            . "Keep it under 3 sentences. End with a soft question to start a conversation. "
            . "Do NOT use emojis. Do NOT sound robotic or corporate. Sound like a real person.";
    } else {
        $userPrompt = "This person just submitted a form expressing interest in The Eleanor. "
            . "Send them a warm, brief welcome text. Mention their name. "
            . "If they mentioned a specific unit or budget, reference it naturally. "
            . "Keep it under 3 sentences. End with a soft question to start a conversation — "
            . "something like asking what's most important to them in their next home, or if they'd like to schedule a tour. "
            . "Do NOT use emojis. Do NOT sound robotic or corporate. Sound like a real person.";
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ]);

    $payload = [
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 250,
        'system'     => $systemPrompt,
        'messages'   => [
            ['role' => 'user', 'content' => $userPrompt]
        ]
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Claude AI initial SMS failed ($httpCode): $response");
        return null;
    }

    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? null;
}

/**
 * Generate a follow-up message for a stale lead.
 * $attemptNumber: 1 = first follow-up (day 3), 2 = final follow-up (day 6)
 */
function generateFollowupMessage($leadPhone, $attemptNumber = 1) {
    global $sb;

    $leadContext = getLeadContext($leadPhone);

    // Load conversation history
    $history = $sb->select('sms_messages', 'direction,sender_type,body,created_at',
        ['lead_phone=eq.' . urlencode($leadPhone)],
        'created_at.asc', 20);

    $messages = [];
    foreach ($history as $msg) {
        if ($msg['direction'] === 'inbound') {
            $messages[] = ['role' => 'user', 'content' => $msg['body']];
        } else {
            $messages[] = ['role' => 'assistant', 'content' => $msg['body']];
        }
    }

    $systemPrompt = buildSystemPrompt($leadContext);

    if ($attemptNumber === 1) {
        $messages[] = ['role' => 'user', 'content' =>
            "[SYSTEM: It's been a few days since this person last responded. Send a brief, natural check-in. "
            . "Reference something specific from the conversation if possible — a unit they liked, a question they asked, "
            . "or the tour they mentioned. Keep it casual, 1-2 sentences. Don't be pushy. "
            . "Do NOT reveal that this is an automated follow-up. Sound like you just thought of them.]"
        ];
    } else {
        $messages[] = ['role' => 'user', 'content' =>
            "[SYSTEM: This is your final follow-up with this person. They haven't responded in almost a week. "
            . "Send a warm, low-pressure message — let them know you're here if they need anything, "
            . "and maybe mention something timely like a unit getting interest or availability changing. "
            . "1 sentence max. This is your last message unless they respond. "
            . "Do NOT reveal that this is an automated follow-up.]"
        ];
    }

    $payload = [
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 200,
        'system'     => $systemPrompt,
        'messages'   => $messages
    ];

    $response = callClaudeAPI($payload);
    if (!$response) return null;

    $reply = null;
    foreach ($response['content'] as $block) {
        if ($block['type'] === 'text') {
            $reply = $block['text'];
            break;
        }
    }

    // Truncate if needed
    if ($reply && strlen($reply) > 320) {
        $truncated = substr($reply, 0, 320);
        $lastPeriod = strrpos($truncated, '.');
        $lastQuestion = strrpos($truncated, '?');
        $cutAt = max($lastPeriod ?: 0, $lastQuestion ?: 0);
        if ($cutAt > 50) {
            $reply = substr($reply, 0, $cutAt + 1);
        }
    }

    return $reply;
}

/**
 * Look up everything we know about a lead by phone (or email).
 */
function getLeadContext($phone, $email = null) {
    global $sb;

    $phoneDigits = preg_replace('/\D/', '', $phone);
    $lead = null;
    $source = '';

    // Try to find by phone across submission tables
    foreach ([
        ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
        ['table' => 'unit_inquiries', 'source' => 'Unit Interest']
    ] as $src) {
        // Try phone match
        $rows = $sb->select($src['table'], '*');
        foreach ($rows as $row) {
            $rowPhone = preg_replace('/\D/', '', $row['phone'] ?? '');
            if ($rowPhone && $rowPhone === $phoneDigits) {
                $lead = $row;
                $source = $src['source'];
                break 2;
            }
        }
    }

    // Fallback: try email if provided
    if (!$lead && $email) {
        foreach ([
            ['table' => 'waitlist_submissions', 'source' => 'Waitlist'],
            ['table' => 'unit_inquiries', 'source' => 'Unit Interest']
        ] as $src) {
            $row = $sb->selectOne($src['table'], '*', ['email=eq.' . urlencode($email)]);
            if ($row) {
                $lead = $row;
                $source = $src['source'];
                break;
            }
        }
    }

    if (!$lead) {
        return ['name' => 'there', 'source' => 'Unknown'];
    }

    // Get enrichment data
    $enrichment = [];
    if (!empty($lead['email'])) {
        $enrichment = $sb->selectOne('lead_enrichment', '*',
            ['email=eq.' . urlencode($lead['email'])]) ?: [];
    }

    return [
        'first_name'   => $lead['first_name'] ?? '',
        'last_name'    => $lead['last_name'] ?? '',
        'name'         => trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')),
        'email'        => $lead['email'] ?? '',
        'phone'        => $lead['phone'] ?? '',
        'budget'       => $lead['budget'] ?? '',
        'move_in_date' => $lead['move_in_date'] ?? '',
        'unit'         => $lead['unit'] ?? '',
        'unit_type'    => $lead['unit_type'] ?? '',
        'message'      => $lead['message'] ?? '',
        'source'       => $source,
        'assigned_to'  => $lead['assigned_to'] ?? null,
        'job_title'    => $enrichment['job_title'] ?? '',
        'company'      => $enrichment['company'] ?? '',
        'inferred_salary' => $enrichment['inferred_salary'] ?? '',
    ];
}

/**
 * Build the system prompt for Claude with lead context + live unit inventory.
 */
function buildSystemPrompt($lead) {
    // Load admin-configured AI settings
    $aiSettings = getAISettings();

    $tone = $aiSettings['ai_tone'] ?? 'friendly';
    $tourHours = $aiSettings['ai_tour_hours'] ?? 'weekdays 10am-6pm, Saturdays 11am-4pm';
    $talkingPoints = trim($aiSettings['ai_talking_points'] ?? '');
    $extraPropertyInfo = trim($aiSettings['ai_extra_property_info'] ?? '');
    $offLimits = trim($aiSettings['ai_off_limits'] ?? '');

    // ── Role & Goal with tone ──
    $toneMap = [
        'friendly'     => 'You are warm, approachable, and personable — like a helpful neighbor who happens to know everything about the building.',
        'professional' => 'You are polished and knowledgeable — confident but never pushy. Think luxury concierge.',
        'casual'       => 'You are relaxed and easygoing — like texting with a friend who works at the building.',
        'enthusiastic' => 'You are upbeat and genuinely excited about the building — your energy is contagious but never fake.',
    ];
    $toneDesc = $toneMap[$tone] ?? $toneMap['friendly'];

    $prompt = "You are a leasing agent for The Eleanor, a brand-new luxury rental building in Boerum Hill, Brooklyn. "
        . "You are texting with a prospective renter via SMS. Your goal is to be helpful and conversational — "
        . "and ultimately to book an in-person tour of the building.\n"
        . "PERSONALITY: " . $toneDesc . "\n\n";

    // ── Conversation Rules ──
    $prompt .= "RULES:\n"
        . "- Keep responses SHORT. This is SMS, not email. 1-3 sentences max.\n"
        . "- Sound like a real person, not a chatbot. No emojis. No corporate speak.\n"
        . "- Never say you are an AI, assistant, or bot. You represent The Eleanor leasing team.\n"
        . "- You KNOW unit pricing, availability, square footage, and views — use this info confidently when asked. "
        . "Reference specific units that match what they're looking for.\n"
        . "- For things you do NOT know — exact lease terms, guarantor requirements, application fees, "
        . "broker fees, move-in costs, pet deposit amounts — say you'll have someone from the team follow up, "
        . "or suggest they come see the building to discuss details in person.\n"
        . "- You have access to tour scheduling tools. When the conversation naturally leads to scheduling "
        . "a tour, use check_tour_availability to find open slots, then offer 2-3 specific times. "
        . "When the lead confirms a time, use book_tour to finalize it. Only book when they've clearly confirmed.\n"
        . "- If no slots are available in their preferred window, suggest alternatives.\n"
        . "- If the lead asks to cancel their tour, use cancel_tour.\n"
        . "- If the lead asks to reschedule, use check_tour_availability to find new slots, "
        . "then once they confirm, use reschedule_tour to cancel the old one and book the new one in one step.\n"
        . "- If they say STOP or ask to stop texting, acknowledge it gracefully and end the conversation.\n"
        . "- Match their energy. If they're brief, be brief. If they have questions, answer them.\n"
        . "- Don't overwhelm them with info they didn't ask for. If they ask about a 1-bed, don't list every 1-bed — "
        . "pick 2-3 that fit their budget or preference and mention those.\n"
        . "- HANDOFF RULES: If ANY of these apply, append the exact tag [HANDOFF] at the very end of your message "
        . "(after your normal response text):\n"
        . "  1. The person explicitly asks to speak with a real person or a broker.\n"
        . "  2. The person seems frustrated, upset, or unhappy with the conversation.\n"
        . "  3. The conversation topic requires detailed lease terms, fees, or legal info you can't provide.\n"
        . "- If the person says they are NOT interested, no longer looking, or already found a place, "
        . "append [NOT_INTERESTED] at the end of your response instead.\n"
        . "- NEVER include [HANDOFF] or [NOT_INTERESTED] in the visible message text — "
        . "always place the tag AFTER your natural reply, separated by a space.\n";

    // Admin-configured topics to avoid
    if ($offLimits) {
        $prompt .= "- ADDITIONAL THINGS TO AVOID:\n";
        foreach (explode("\n", $offLimits) as $item) {
            $item = trim($item);
            if ($item) $prompt .= "  - {$item}\n";
        }
    }

    $prompt .= "\n";

    // Admin-configured talking points
    if ($talkingPoints) {
        $prompt .= "PRIORITIES — weave these into conversation when relevant:\n";
        foreach (explode("\n", $talkingPoints) as $point) {
            $point = trim($point);
            if ($point) $prompt .= "- {$point}\n";
        }
        $prompt .= "\n";
    }

    // ── Lead-Specific Context ──
    $prompt .= "WHAT YOU KNOW ABOUT THIS PERSON:\n";
    $hasContext = false;
    if (!empty($lead['name']) && $lead['name'] !== 'there') {
        $prompt .= "- Name: {$lead['name']}\n";
        $hasContext = true;
    }
    if (!empty($lead['source'])) {
        $prompt .= "- They submitted a {$lead['source']} form\n";
        $hasContext = true;
    }
    if (!empty($lead['budget'])) {
        $prompt .= "- Budget: {$lead['budget']}\n";
        $hasContext = true;
    }
    if (!empty($lead['unit'])) {
        $prompt .= "- Interested in unit: {$lead['unit']}\n";
        $hasContext = true;
    }
    if (!empty($lead['unit_type'])) {
        $prompt .= "- Unit type preference: {$lead['unit_type']}\n";
        $hasContext = true;
    }
    if (!empty($lead['move_in_date'])) {
        $prompt .= "- Target move-in: {$lead['move_in_date']}\n";
        $hasContext = true;
    }
    if (!empty($lead['job_title']) && !empty($lead['company'])) {
        $prompt .= "- Works as {$lead['job_title']} at {$lead['company']} (use this context subtly, don't mention it unless relevant)\n";
        $hasContext = true;
    }
    if (!empty($lead['message'])) {
        $prompt .= "- Their message when signing up: \"{$lead['message']}\"\n";
        $hasContext = true;
    }
    if (!$hasContext) {
        $prompt .= "- No prior info on this person. They texted the building's number directly. "
            . "Be friendly, introduce yourself as being with The Eleanor leasing team, and ask what you can help with.\n";
    }

    // ── Static Property Details ──
    $prompt .= "\nABOUT THE ELEANOR:\n"
        . "Address: 52 4th Avenue, Brooklyn, NY 11217 (Boerum Hill — at the convergence of Boerum Hill, Cobble Hill, Carroll Gardens, and Downtown Brooklyn)\n\n"

        . "Building Overview:\n"
        . "- Brand-new luxury rental, currently leasing\n"
        . "- Studios, 1-bedrooms, and 2-bedrooms available\n"
        . "- Pricing ranges from roughly \$3,050 (studios) to \$7,200 (2-beds)\n"
        . "- Pet-friendly building\n\n"

        . "Unit Features:\n"
        . "- In-unit washer/dryer\n"
        . "- Central air conditioning\n"
        . "- Warm oak flooring throughout\n"
        . "- Two-tone cabinetry with oak finishes\n"
        . "- Marble-look countertops\n"
        . "- Expansive windows with abundant natural light\n"
        . "- Open-concept layouts\n"
        . "- Select units have balconies or terraces\n\n"

        . "Building Amenities:\n"
        . "- Landscaped rooftop terrace with lounge seating, oversized chess, and mature plantings\n"
        . "- Fully equipped fitness center (free weights, cable machines, cardio)\n"
        . "- Co-working lounge with leather seating, oak flooring, and acoustic partitions\n"
        . "- Residents' library with floor-to-ceiling glass, modern fireplace, and courtyard views\n"
        . "- Courtyard with natural stone and shaded gathering spots\n"
        . "- Bike storage\n"
        . "- Package room\n\n"

        . "Transit (one of Brooklyn's best-connected locations):\n"
        . "- Atlantic Ave-Barclays Center station: 2, 3, 4, 5, B, D, N, Q, R, W lines + LIRR (0.2 miles)\n"
        . "- 10+ subway lines within walking distance\n"
        . "- Under 15 minutes to Midtown Manhattan\n\n"

        . "Neighborhood Highlights:\n"
        . "- Barclays Center (3 min walk) — Brooklyn Nets, concerts, events\n"
        . "- Brooklyn Academy of Music / BAM (8 min walk) — performing arts\n"
        . "- Smith Street & Court Street (5 min walk) — acclaimed restaurants and wine bars\n"
        . "- DeKalb Market Hall (7 min walk) — food hall with dozens of vendors\n"
        . "- Atlantic Avenue (2 min walk) — Middle Eastern cuisine, boutiques\n"
        . "- Fort Greene Park (15 min walk), Prospect Park (20 min walk), Brooklyn Bridge Park (25 min walk)\n"
        . "- Gowanus breweries and art scene (10 min walk)\n\n";

    // Admin-configured extra property info
    if ($extraPropertyInfo) {
        $prompt .= "ADDITIONAL NOTES FROM THE LEASING TEAM:\n{$extraPropertyInfo}\n\n";
    }

    // ── Live Unit Inventory ──
    $units = fetchAvailableUnits();
    if (!empty($units)) {
        $prompt .= "CURRENT AVAILABLE UNITS:\n"
            . "(Use this data to answer questions about specific units, pricing, and availability. "
            . "Only mention units marked as available — do not mention leased units.)\n\n";

        // Group by type for readability
        $studios = [];
        $oneBeds = [];
        $twoBeds = [];

        foreach ($units as $u) {
            if ($u['isleased']) continue;

            $line = "Unit {$u['unit']}: {$u['bedbath']}, {$u['sqft']} sqft, \${$u['rent']}/mo";
            if (!empty($u['outdoor'])) $line .= ", {$u['outdoor']}";
            if (!empty($u['view'])) $line .= ", {$u['view']}";

            $type = strtolower($u['type'] ?? '');
            if (strpos($type, 'studio') !== false) {
                $studios[] = $line;
            } elseif (strpos($type, '2') !== false) {
                $twoBeds[] = $line;
            } else {
                $oneBeds[] = $line;
            }
        }

        if ($studios) {
            $prompt .= "Studios:\n" . implode("\n", $studios) . "\n\n";
        }
        if ($oneBeds) {
            $prompt .= "1-Bedrooms:\n" . implode("\n", $oneBeds) . "\n\n";
        }
        if ($twoBeds) {
            $prompt .= "2-Bedrooms:\n" . implode("\n", $twoBeds) . "\n\n";
        }
    }

    return $prompt;
}

/**
 * Fetch live unit data from the Google Sheet endpoint (same source as the website).
 * Cached for 5 minutes to avoid hammering the endpoint on every message.
 */
function fetchAvailableUnits() {
    $cacheFile = sys_get_temp_dir() . '/eleanor_units_cache.json';
    $cacheTTL = 300; // 5 minutes

    // Return cached data if fresh
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) return $cached;
    }

    $endpoint = 'https://script.google.com/macros/s/AKfycbz_-tiYBDHaMa4O4Rk6bdgJagBMLHZDf5R3SJmuZyymEUXp5ipfA8q7QHT-kS8WkbLfxQ/exec';

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300 || empty($response)) {
        error_log("Failed to fetch Google Sheet units ($httpCode)");
        // Return stale cache if available
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        return [];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) return [];

    // Normalize the data
    $units = [];
    foreach ($data as $unit) {
        $units[] = [
            'unit'     => $unit['unit'] ?? '',
            'type'     => $unit['type'] ?? '',
            'bedbath'  => $unit['bedbath'] ?? $unit['bedBath'] ?? '',
            'rent'     => preg_replace('/[^0-9]/', '', $unit['rent'] ?? ''),
            'sqft'     => $unit['squarefootage'] ?? $unit['sqft'] ?? '',
            'outdoor'  => $unit['outdoor'] ?? '',
            'view'     => $unit['view'] ?? '',
            'isleased' => !empty($unit['isleased']),
        ];
    }

    // Cache it
    file_put_contents($cacheFile, json_encode($units));

    return $units;
}

/**
 * Load SMS settings from DB (cached 60 seconds).
 */
function getSMSSettings() {
    global $sb;
    static $cachedConfig = null;
    static $cachedAt = 0;

    if ($cachedConfig !== null && (time() - $cachedAt) < 60) {
        return $cachedConfig;
    }

    $settings = $sb->select('settings', '*');
    $config = [];
    foreach ($settings as $s) {
        $config[$s['key']] = $s['value'];
    }

    $cachedConfig = $config;
    $cachedAt = time();
    return $config;
}

/**
 * Check if SMS is intentionally enabled (master toggle + campaign dates).
 * If this returns false, messages should NOT be queued.
 */
function isSMSMasterEnabled() {
    $config = getSMSSettings();

    // Master toggle
    if (($config['sms_enabled'] ?? 'off') !== 'on') {
        return false;
    }

    // Campaign date range (optional)
    $campaignStart = $config['sms_campaign_start'] ?? '';
    $campaignEnd   = $config['sms_campaign_end'] ?? '';
    $today = date('Y-m-d');
    if ($campaignStart && $today < $campaignStart) return false;
    if ($campaignEnd && $today > $campaignEnd) return false;

    return true;
}

/**
 * Check if current time is within the send window (day + hours).
 * If this returns false but master is enabled, messages should be queued.
 */
function isWithinSendWindow() {
    $config = getSMSSettings();

    // Check active days (0=Sun, 1=Mon, ..., 6=Sat)
    $activeDays = $config['sms_active_days'] ?? '1,2,3,4,5';
    $allowedDays = array_map('trim', explode(',', $activeDays));
    $currentDay = (string) date('w');
    if (!in_array($currentDay, $allowedDays)) {
        return false;
    }

    // Check send window hours
    $windowStart = $config['sms_window_start'] ?? '09:00';
    $windowEnd   = $config['sms_window_end'] ?? '19:00';
    $now = date('H:i');
    if ($now < $windowStart || $now > $windowEnd) {
        return false;
    }

    return true;
}

/**
 * Check if SMS automation is currently allowed (master + window).
 * Backward-compatible wrapper.
 */
function isSMSAutomationAllowed() {
    return isSMSMasterEnabled() && isWithinSendWindow();
}

/**
 * Calculate the next datetime when the send window opens.
 * Returns ISO 8601 timestamp.
 */
function getNextWindowOpen() {
    $config = getSMSSettings();
    $windowStart = $config['sms_window_start'] ?? '09:00';
    $activeDays = $config['sms_active_days'] ?? '1,2,3,4,5';
    $allowedDays = array_map('trim', explode(',', $activeDays));

    $now = new DateTime('now', new DateTimeZone('America/New_York'));

    // Check up to 8 days ahead (covers full week + 1)
    for ($i = 0; $i <= 7; $i++) {
        $candidate = clone $now;
        if ($i > 0) {
            $candidate->modify("+{$i} days");
        }
        $dayOfWeek = (string) $candidate->format('w');

        if (in_array($dayOfWeek, $allowedDays)) {
            $openTime = clone $candidate;
            $openTime->setTime(
                (int) explode(':', $windowStart)[0],
                (int) explode(':', $windowStart)[1]
            );

            // If it's today and window hasn't opened yet, use today
            // If it's a future day, use that day's window start
            if ($openTime > $now) {
                return $openTime->format('c');
            }
        }
    }

    // Fallback: tomorrow at window start
    $fallback = clone $now;
    $fallback->modify('+1 day');
    $fallback->setTime(
        (int) explode(':', $windowStart)[0],
        (int) explode(':', $windowStart)[1]
    );
    return $fallback->format('c');
}

/**
 * Queue an inbound message for AI response when the send window opens.
 */
function queueSMSResponse($phone, $email, $inboundBody, $telnyxMessageId) {
    global $sb;
    $scheduledFor = getNextWindowOpen();
    $sb->insert('sms_queue', [
        'lead_phone'        => $phone,
        'lead_email'        => $email,
        'inbound_body'      => $inboundBody,
        'telnyx_message_id' => $telnyxMessageId,
        'scheduled_for'     => $scheduledFor,
        'status'            => 'pending'
    ]);
}

/**
 * Check if AI is active for a specific lead (not paused by broker).
 */
function isAIActiveForLead($phone) {
    global $sb;

    $phone = normalizePhone($phone);
    if (!$phone) return false;

    // If no automation record exists, AI is active by default
    $record = $sb->selectOne('sms_automation', 'status',
        ['lead_phone=eq.' . urlencode($phone)]);

    if (!$record) return true;

    return $record['status'] === 'active';
}

// Import normalizePhone from telnyx-sms.php if not already loaded
if (!function_exists('normalizePhone')) {
    require_once __DIR__ . '/telnyx-sms.php';
}

// Auto-update lead status (safe to call from any context)
if (!function_exists('autoUpdateLeadStatusFromWebhook')) {
    function autoUpdateLeadStatusFromWebhook($email, $newStatus) {
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
}

// Notification helper (safe to call from any context)
if (!function_exists('createNotificationFromWebhook')) {
    function createNotificationFromWebhook($type, $title, $body, $leadEmail = null, $brokerId = null, $link = null) {
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
}
