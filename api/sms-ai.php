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

    // 5. Call Claude API
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
        'max_tokens' => 300,
        'system'     => $systemPrompt,
        'messages'   => $messages
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Claude AI SMS response failed ($httpCode): $response");
        return null;
    }

    $data = json_decode($response, true);
    $reply = $data['content'][0]['text'] ?? null;

    // Trim to SMS-friendly length — cut at last sentence boundary
    if ($reply && strlen($reply) > 480) {
        $truncated = substr($reply, 0, 480);
        // Find the last sentence-ending punctuation
        $lastPeriod = strrpos($truncated, '.');
        $lastQuestion = strrpos($truncated, '?');
        $lastExclaim = strrpos($truncated, '!');
        $cutAt = max($lastPeriod ?: 0, $lastQuestion ?: 0, $lastExclaim ?: 0);
        if ($cutAt > 100) {
            $reply = substr($reply, 0, $cutAt + 1);
        } else {
            // No good sentence break — cut at last space
            $lastSpace = strrpos($truncated, ' ');
            $reply = substr($reply, 0, $lastSpace ?: 477) . '...';
        }
    }

    return $reply;
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
        . "- If they want to schedule a tour, be enthusiastic and suggest a few time windows ({$tourHours}). "
        . "Don't commit to exact times — say the team will confirm.\n"
        . "- If they say STOP or ask to stop texting, acknowledge it gracefully and end the conversation.\n"
        . "- Match their energy. If they're brief, be brief. If they have questions, answer them.\n"
        . "- Don't overwhelm them with info they didn't ask for. If they ask about a 1-bed, don't list every 1-bed — "
        . "pick 2-3 that fit their budget or preference and mention those.\n";

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
 * Check if SMS automation is currently allowed by global settings.
 * Cached for 60 seconds to avoid DB query on every inbound message.
 */
function isSMSAutomationAllowed() {
    global $sb;
    static $cachedResult = null;
    static $cachedAt = 0;

    if ($cachedResult !== null && (time() - $cachedAt) < 60) {
        return $cachedResult;
    }

    // Check master toggle
    $settings = $sb->select('settings', '*');
    $config = [];
    foreach ($settings as $s) {
        $config[$s['key']] = $s['value'];
    }

    // Evaluate all checks
    $allowed = true;

    // Master toggle
    if (($config['sms_enabled'] ?? 'off') !== 'on') {
        $allowed = false;
    }

    // Check active days (0=Sun, 1=Mon, ..., 6=Sat)
    if ($allowed) {
        $activeDays = $config['sms_active_days'] ?? '1,2,3,4,5';
        $allowedDays = array_map('trim', explode(',', $activeDays));
        $currentDay = (string) date('w');
        if (!in_array($currentDay, $allowedDays)) {
            $allowed = false;
        }
    }

    // Check send window
    if ($allowed) {
        $windowStart = $config['sms_window_start'] ?? '09:00';
        $windowEnd   = $config['sms_window_end'] ?? '19:00';
        $now = date('H:i');
        if ($now < $windowStart || $now > $windowEnd) {
            $allowed = false;
        }
    }

    // Check campaign date range (optional)
    if ($allowed) {
        $campaignStart = $config['sms_campaign_start'] ?? '';
        $campaignEnd   = $config['sms_campaign_end'] ?? '';
        $today = date('Y-m-d');
        if ($campaignStart && $today < $campaignStart) $allowed = false;
        if ($campaignEnd && $today > $campaignEnd) $allowed = false;
    }

    $cachedResult = $allowed;
    $cachedAt = time();
    return $allowed;
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
