<?php
/**
 * Lead Enrichment Service (Apollo.io)
 */
require_once 'db_config.php';
require_once 'config.php';
require_once 'smtp-mail.php';

// Fallback if constant is missing from config.php
if (!defined('APOLLO_WEBHOOK_URL')) {
    define('APOLLO_WEBHOOK_URL', null);
}

function getLikelyCountry($phone) {
    if (!$phone) return null;
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($cleanPhone, '+1') === 0 || (strlen($cleanPhone) === 10 && !strpos($cleanPhone, '+'))) {
        return "United States";
    }
    return null;
}

function getLikelyState($phone) {
    if (!$phone) return null;
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) > 10) $cleanPhone = substr($cleanPhone, -10);
    if (strlen($cleanPhone) !== 10) return null;
    
    $areaCode = substr($cleanPhone, 0, 3);
    $map = [
        '212' => 'New York', '646' => 'New York', '917' => 'New York', '332' => 'New York', 
        '718' => 'New York', '347' => 'New York', '929' => 'New York', '631' => 'New York', '516' => 'New York',
        '310' => 'California', '213' => 'California', '415' => 'California', '650' => 'California',
        '214' => 'Texas', '972' => 'Texas', '512' => 'Texas', '713' => 'Texas', '832' => 'Texas'
    ];
    return $map[$areaCode] ?? null;
}

function apolloRequest($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cache-Control: no-cache',
        'X-Api-Key: ' . APOLLO_API_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true), 'raw' => $response];
}

function tavilyRequest($query, $options = []) {
    $payload = array_merge([
        'api_key' => TAVILY_API_KEY,
        'query' => $query,
        'search_depth' => 'advanced',
        'include_answer' => false
    ], $options);

    $ch = curl_init("https://api.tavily.com/search");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function anthropicRequest($prompt, $maxTokens = 1500) {
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => $maxTokens,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function linkedinScraperRequest($linkedinUrl) {
    $ch = curl_init();
    $url = "https://" . RAPIDAPI_HOST . "/enrich-lead?linkedin_url=" . urlencode($linkedinUrl);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: " . RAPIDAPI_HOST,
            "x-rapidapi-key: " . RAPIDAPI_KEY
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['code' => 500, 'error' => $err];
    }
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function deepEnrichment($email, $firstName, $lastName, $phone = null) {
    error_log("Triggering Scraper-Assisted Deep Enrichment for: $email ($firstName $lastName)");

    // Extract company from email domain for better search
    $emailDomain = strtolower(substr($email, strpos($email, '@') + 1));
    $personalDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com', 'me.com', 'live.com', 'msn.com', 'protonmail.com', 'mail.com'];
    $isCorpEmail = !in_array($emailDomain, $personalDomains);
    $companyHint = $isCorpEmail ? str_replace(['.com', '.org', '.net', '.io', '.co'], '', $emailDomain) : '';

    $likelyState = getLikelyState($phone);

    // 1. Find LinkedIn URL via Tavily (using company domain for accuracy)
    $query = "\"$firstName $lastName\" " . ($companyHint ? "$companyHint " : "") . "LinkedIn";
    $resLinkedIn = tavilyRequest($query, [
        'include_domains' => ['linkedin.com/in'],
        'search_depth' => 'advanced',
        'max_results' => 5
    ]);

    $linkedinUrl = null;
    $results = $resLinkedIn['data']['results'] ?? [];
    
    // Selection Strategy: Prefer LinkedIn profiles that mention the company/domain
    foreach ($results as $item) {
        if (strpos($item['url'], 'linkedin.com/in/') !== false) {
            $snippet = strtolower($item['content'] ?? '' . ' ' . ($item['title'] ?? ''));
            $companyMatch = false;

            // Check for company name from email domain
            if ($companyHint && strpos($snippet, strtolower($companyHint)) !== false) $companyMatch = true;
            // Also check location as secondary signal
            if ($likelyState && strpos($snippet, strtolower($likelyState)) !== false) $companyMatch = true;
            if ($likelyState === 'New York' && strpos($snippet, 'nyc') !== false) $companyMatch = true;

            if ($companyMatch) {
                $linkedinUrl = $item['url'];
                error_log("Found company/location-matching LinkedIn URL: $linkedinUrl");
                break;
            }
        }
    }

    // Fallback: use the first LinkedIn result
    if (!$linkedinUrl && !empty($results)) {
        foreach ($results as $item) {
            if (strpos($item['url'], 'linkedin.com/in/') !== false) {
                $linkedinUrl = $item['url'];
                error_log("No company match in snippets. Falling back to first LinkedIn URL: $linkedinUrl");
                break;
            }
        }
    }

    error_log("Found LinkedIn URL: " . ($linkedinUrl ?? "NONE"));

    if (!$linkedinUrl) {
        error_log("DEEP_ENRICH_FAIL: No LinkedIn URL found for $email via Tavily query: $query");
        return ['error' => 'No LinkedIn URL found via Tavily', 'debug_query' => $query, 'tavily_results' => $results];
    }

    // 2. Fetch Full Profile Data via Scraper API
    error_log("Calling Scraper API for: $linkedinUrl...");
    $scraperRes = linkedinScraperRequest($linkedinUrl);
    
    if ($scraperRes['code'] !== 200 || empty($scraperRes['data']['data'])) {
        error_log("DEEP_ENRICH_FAIL: LinkedIn Scraper failed for $linkedinUrl: " . json_encode($scraperRes));
        return ['error' => 'LinkedIn Scraper API failed', 'code' => $scraperRes['code'] ?? 'UNK', 'response' => $scraperRes['data'] ?? 'EMPTY'];
    }

    $profileData = $scraperRes['data']['data'];
    $profileJson = json_encode($profileData, JSON_PRETTY_PRINT);

    // 3. Normalize and Verify via Anthropic
    $domainNote = $isCorpEmail ? "The target uses a corporate email at domain '$emailDomain'. The matched profile MUST work at a company associated with this domain." : "The target uses a personal email, so company verification is not possible via domain.";

    $prompt = "You are a professional background investigator.
    TARGET IDENTITY:
    Name: $firstName $lastName
    Email: $email
    Email Domain: $emailDomain

    SCRAPED LINKEDIN PROFILE:
    $profileJson

    STRICT VERIFICATION PROTOCOL:
    - DOMAIN IS A KEY VERIFIER: $domainNote If the profile's company domain does not match '$emailDomain', reduce confidence significantly.
    - EMAIL MATCH: If the email $email is mentioned in the profile, that is 100% confidence.
    - NAME MATCH: Verify the name matches. Common names require additional signals (company, email) to confirm.
    - REASONING: Be honest. If the company domain mismatches, state 'Domain mismatch: Target email is @$emailDomain but profile works at [Company Domain]. Rejected.'

    OUTPUT JSON:
    {
       \"full_name\": \"...\",
       \"job_title\": \"...\",
       \"company\": \"...\",
       \"city\": \"...\",
       \"state\": \"...\",
       \"country\": \"...\",
       \"linkedin_url\": \"$linkedinUrl\",
       \"photo_url\": \"...\",
       \"headline\": \"...\",
       \"industry\": \"...\",
       \"company_description\": \"...\",
       \"company_domain\": \"...\",
       \"employee_count\": \"...\",
       \"annual_revenue\": \"...\",
       \"identity_confidence\": 0-100,
       \"reasoning\": \"...\",
       \"employment_history\": [ { \"title\": \"...\", \"organization_name\": \"...\", \"current\": true, \"start_date\": \"YYYY-MM-DD\" } ],
       \"education_history\": [ { \"school_name\": \"...\", \"degree\": \"...\" } ]
    }

    RESPONSE: Return ONLY raw JSON.";

    $anthropicRes = anthropicRequest($prompt);

    if ($anthropicRes['code'] !== 200) {
        error_log("DEEP_ENRICH_FAIL: Anthropic extraction failed for $email: " . json_encode($anthropicRes['data']));
        return ['error' => 'Anthropic extraction failed', 'code' => $anthropicRes['code']];
    }

    $extractedData = json_decode($anthropicRes['data']['content'][0]['text'] ?? '{}', true);

    if (empty($extractedData) || empty($extractedData['full_name'])) {
        error_log("DEEP_ENRICH_FAIL: Anthropic could not normalize data for $email");
        return ['error' => 'Anthropic could not normalize data', 'raw_ai_response' => $anthropicRes['data']['content'][0]['text'] ?? 'EMPTY'];
    }

    if (($extractedData['identity_confidence'] ?? 0) < 50) {
        error_log("DEEP_ENRICH_FAIL: Low identity confidence for $email: " . ($extractedData['reasoning'] ?? 'N/A'));
        return ['error' => 'Identity confidence too low', 'reasoning' => $extractedData['reasoning'] ?? 'N/A'];
    }

    $extractedData['_source'] = 'linkedin_scraper';
    
    // If the extracted name is abbreviated or missing, use the target name
    if (empty($extractedData['full_name']) || strpos($extractedData['full_name'], '.') !== false) {
        $extractedData['full_name'] = "$firstName $lastName";
    }

    return $extractedData;
}

function enrichLead($email, $firstName = null, $lastName = null, $phone = null) {
    global $pdo;

    // 1. Check if we already have data for this email (Case-insensitive)
    $stmt = $pdo->prepare("SELECT id FROM lead_enrichment WHERE TRIM(LOWER(email)) = ?");
    $stmt->execute([trim(strtolower($email))]);
    if ($stmt->fetch()) {
        return ['status' => 'already_enriched'];
    }

    // Extract email domain for verification (ignore common personal email providers)
    $emailDomain = strtolower(substr($email, strpos($email, '@') + 1));
    $personalDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com', 'me.com', 'live.com', 'msn.com', 'protonmail.com', 'mail.com'];
    $isCorpEmail = !in_array($emailDomain, $personalDomains);

    // 2. Try Standard Match with Webhook
    $matchRes = apolloRequest("https://api.apollo.io/api/v1/people/match", [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_number' => $phone,
        'reveal_personal_emails' => true,
        'reveal_phone_number' => true,
        'webhook_url' => APOLLO_WEBHOOK_URL,
        'run_waterfall_email' => true,
        'run_waterfall_phone' => true
    ]);

    $person = $matchRes['data']['person'] ?? null;
    $finalResponseRaw = $matchRes['raw'];

    // 3. Disambiguation — verify Apollo match against email domain
    $isSuspicious = false;
    if ($person && $isCorpEmail) {
        $personDomain = strtolower($person['organization']['primary_domain'] ?? '');
        $personEmail = strtolower($person['email'] ?? '');

        // If lead used a corporate email, the matched person's company domain should match
        if ($personDomain && $personDomain !== $emailDomain) {
            // Also check if the person's email domain matches (they might have multiple domains)
            $personEmailDomain = $personEmail ? strtolower(substr($personEmail, strpos($personEmail, '@') + 1)) : '';
            if ($personEmailDomain !== $emailDomain) {
                $isSuspicious = true;
                error_log("Domain mismatch: Lead email domain '$emailDomain' != Apollo company domain '$personDomain'. Flagging as suspicious.");
            }
        }
    }

    if (!$person || $isSuspicious) {
        if ($isSuspicious) {
            error_log("Apollo Collision Detected: Matched person's domain doesn't match lead email domain '$emailDomain'. Triggering fallbacks.");
        } else {
            error_log("Apollo No Match Found: Triggering search fallback.");
        }

        // Try Apollo Search — use email domain as company filter for corp emails
        $searchPayload = ['q_person_name' => trim("$firstName $lastName"), 'page' => 1, 'per_page' => 5];
        if ($isCorpEmail) {
            $searchPayload['q_organization_domains'] = $emailDomain;
        }
        $searchRes = apolloRequest("https://api.apollo.io/api/v1/mixed_people/api_search", $searchPayload);

        $searchPeople = $searchRes['data']['people'] ?? [];
        $bestMatch = null;

        // Find the best match: prioritize email match, then domain match
        foreach ($searchPeople as $candidate) {
            if (strtolower($candidate['email'] ?? '') === strtolower($email)) {
                $bestMatch = $candidate;
                break;
            }
            $candidateDomain = strtolower($candidate['organization']['primary_domain'] ?? '');
            if ($isCorpEmail && $candidateDomain === $emailDomain && !$bestMatch) {
                $bestMatch = $candidate;
            }
        }

        if ($bestMatch) {
            $hydratePayload = ['person_ids' => [$bestMatch['id']], 'reveal_personal_emails' => true, 'webhook_url' => APOLLO_WEBHOOK_URL];
            $hydrateRes = apolloRequest("https://api.apollo.io/api/v1/people/bulk_match", $hydratePayload);
            $hydratedPerson = $hydrateRes['data']['people'][0] ?? null;

            if ($hydratedPerson) {
                $person = $hydratedPerson;
                $isSuspicious = false;
                $finalResponseRaw = json_encode(['person' => $person]);
                error_log("Apollo Search found verified match for $email via domain '$emailDomain'.");
            }
        }
    }

    // 4. DEEP SEARCH FALLBACK (LinkedIn Scraper)
    // We run this if Apollo found nothing OR if Apollo is still suspicious (location mismatch)
    if (!$person || $isSuspicious) {
        // --- DEEP SEARCH FALLBACK ---
        error_log("Apollo failed to find a match for $email. Triggering Deep Search fallback.");
        $deepData = deepEnrichment($email, $firstName, $lastName, $phone);
        
        if ($deepData && !isset($deepData['error'])) {
            // Remap deepData to match Apollo structure for the database insert
            $person = [
                'name' => $deepData['full_name'] ?? "$firstName $lastName",
                'title' => $deepData['job_title'] ?? null,
                'linkedin_url' => $deepData['linkedin_url'] ?? null,
                'twitter_url' => $deepData['twitter_url'] ?? null,
                'github_url' => $deepData['github_url'] ?? null,
                'facebook_url' => $deepData['facebook_url'] ?? null,
                'city' => $deepData['city'] ?? null,
                'state' => $deepData['state'] ?? null,
                'country' => $deepData['country'] ?? null,
                'seniority' => $deepData['seniority'] ?? null,
                'photo_url' => $deepData['photo_url'] ?? null,
                'headline' => $deepData['headline'] ?? null,
                'employment_history' => $deepData['employment_history'] ?? [],
                'education_history' => $deepData['education_history'] ?? [],
                'organization' => [
                    'name' => $deepData['company'] ?? null,
                    'primary_domain' => $deepData['company_domain'] ?? null,
                    'industry' => $deepData['industry'] ?? null,
                    'short_description' => $deepData['company_description'] ?? null,
                    'estimated_num_employees' => $deepData['employee_count'] ?? null,
                    'annual_revenue_printed' => $deepData['annual_revenue'] ?? null,
                    'logo_url' => null
                ],
                '_deep_data' => $deepData
            ];
            $finalResponseRaw = json_encode([
                'source' => 'tavily_fallback', 
                'person' => $person
            ]);
        } else {
            return ['status' => 'no_match'];
        }
    }

    // 4. Store the Enriched Data
    try {
        $stmt = $pdo->prepare("INSERT INTO lead_enrichment 
            (email, full_name, job_title, company, company_domain, seniority, linkedin_url, twitter_url, github_url, facebook_url, city, state, country, employee_count, industry, annual_revenue, company_logo, company_description, headline, photo_url, raw_response) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $email,
            $person['name'] ?? null,
            $person['title'] ?? null,
            $person['organization']['name'] ?? null,
            $person['organization']['primary_domain'] ?? null,
            $person['seniority'] ?? null,
            $person['linkedin_url'] ?? null,
            $person['twitter_url'] ?? null,
            $person['github_url'] ?? null,
            $person['facebook_url'] ?? null,
            $person['city'] ?? null,
            $person['state'] ?? null,
            $person['country'] ?? null,
            $person['organization']['estimated_num_employees'] ?? null,
            $person['organization']['industry'] ?? null,
            $person['organization']['annual_revenue_printed'] ?? null,
            $person['organization']['logo_url'] ?? null,
            $person['organization']['short_description'] ?? null,
            $person['headline'] ?? null,
            $person['photo_url'] ?? null,
            $finalResponseRaw
        ]);

        sendEnrichmentEmail($email, $firstName, $lastName, $person);

        return [
            'status' => 'success', 
            'data' => $person
        ];
    } catch (PDOException $e) {
        error_log("Database error in enrichment: " . $e->getMessage());
        return ['status' => 'db_error', 'message' => $e->getMessage()];
    }
}

function sendEnrichmentEmail($email, $firstName, $lastName, $person) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT event_type, event_name, event_data, created_at 
        FROM activity_logs 
        WHERE session_id IN (
            SELECT tracking_id FROM waitlist_submissions WHERE email = ?
            UNION SELECT tracking_id FROM unit_inquiries WHERE email = ?
            UNION SELECT tracking_id FROM mailing_list WHERE email = ?
        )
        ORDER BY created_at ASC
    ");
    $stmt->execute([$email, $email, $email]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEvents = count($logs);
    $sectionsViewed = [];
    $buttonsClicked = [];

    foreach ($logs as $log) {
        if ($log['event_type'] === 'visibility' && $log['event_name'] === 'section_leave') {
            $data = json_decode($log['event_data'], true);
            if ($data && isset($data['section'])) {
                $sec = $data['section'];
                $time = $data['secondsSpent'] ?? 0;
                if (!isset($sectionsViewed[$sec])) $sectionsViewed[$sec] = 0;
                $sectionsViewed[$sec] += $time;
            }
        }
        if ($log['event_type'] === 'click' && $log['event_name'] === 'button_click') {
            $data = json_decode($log['event_data'], true);
            if ($data && !empty($data['text'])) {
                $buttonsClicked[] = $data['text'];
            }
        }
    }

    $behavioralRaw = "BEHAVIORAL JOURNEY (WEBSITE ANALYTICS)\n";
    $behavioralRaw .= "----------------------------------------\n";
    $behavioralRaw .= "Total Tracking Events: $totalEvents\n\n";

    if (!empty($sectionsViewed)) {
        arsort($sectionsViewed);
        $behavioralRaw .= "Sections Viewed (Total Time):\n";
        $count = 0;
        foreach ($sectionsViewed as $sec => $time) {
            if ($count++ >= 5) break;
            $behavioralRaw .= "  - $sec (" . round($time) . " seconds)\n";
        }
        $behavioralRaw .= "\n";
    }

    if (!empty($buttonsClicked)) {
        $counts = array_count_values($buttonsClicked);
        arsort($counts);
        $behavioralRaw .= "Buttons/Links Clicked:\n";
        foreach ($counts as $btn => $clk) {
            $behavioralRaw .= "  - $btn ($clk" . ($clk > 1 ? " clicks" : " click") . ")\n";
        }
        $behavioralRaw .= "\n";
    }

    if (empty($sectionsViewed) && empty($buttonsClicked)) {
        $behavioralRaw .= "No significant behavioral data logged prior to submission.\n";
    }

    $name = $person['name'] ?? trim("$firstName $lastName");
    $title = $person['title'] ?? 'Not found';
    $company = $person['organization']['name'] ?? 'Not found';
    $industry = $person['organization']['industry'] ?? 'Not found';
    $employees = $person['organization']['estimated_num_employees'] ?? 'Not found';
    $revenue = $person['organization']['annual_revenue_printed'] ?? 'Not found';
    $linkedin = $person['linkedin_url'] ?? 'Not found';

    $isDeepSearch = isset($person['_deep_data']) && isset($person['_deep_data']['identity_confidence']);
    $verificationRaw = "";
    if ($isDeepSearch) {
        $conf = $person['_deep_data']['identity_confidence'];
        $reason = $person['_deep_data']['reasoning'];
        $verificationRaw = "\nDEEP SEARCH VERIFICATION\n----------------------------------------\nAI Confidence Score: $conf%\nReasoning: $reason\n";
    }

    $to = defined('NOTIFICATION_EMAIL') ? NOTIFICATION_EMAIL : 'admin@theeleanor.nyc';
    $subject = "Enrichment Profile: $name";
    $headers = [
        'From: info@theeleanor.nyc',
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];

    $body = <<<EOD
LEAD INTELLIGENCE PROFILE
----------------------------------------
Identity
- Name: $name
- Email: $email
- Title: $title
- Company: $company

Firmographics
- Industry: $industry
- Employees: $employees
- Revenue: $revenue

Social & Links
- LinkedIn: $linkedin
$verificationRaw
$behavioralRaw
EOD;

    smtpSend($to, $subject, $body, $email);
}
