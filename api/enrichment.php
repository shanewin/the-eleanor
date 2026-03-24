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

function fullContactRequest($email, $phone = null, $firstName = null, $lastName = null) {
    $payload = [];
    if ($email) $payload['emails'] = [$email];
    if ($phone) {
        // Ensure phone has + prefix and country code
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) === 10) $cleanPhone = '1' . $cleanPhone;
        $payload['phones'] = ['+' . $cleanPhone];
    }
    if ($firstName && $lastName) {
        $payload['name'] = ['given' => $firstName, 'family' => $lastName];
    }

    $ch = curl_init('https://api.fullcontact.com/v3/person.enrich');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . FULLCONTACT_API_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    error_log("FullContact response ($httpCode): " . substr($response, 0, 500));
    return ['code' => $httpCode, 'data' => $data, 'raw' => $response];
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

    if (($extractedData['identity_confidence'] ?? 0) < 70) {
        error_log("DEEP_ENRICH_FAIL: Low identity confidence for $email: " . ($extractedData['reasoning'] ?? 'N/A'));
        return ['error' => 'Identity confidence too low', 'reasoning' => $extractedData['reasoning'] ?? 'N/A'];
    }

    // Hard domain check: if corporate email, the extracted company domain must match
    if ($isCorpEmail && !empty($extractedData['company_domain'])) {
        $extractedDomain = strtolower($extractedData['company_domain']);
        if ($extractedDomain !== $emailDomain && strpos($extractedDomain, $emailDomain) === false && strpos($emailDomain, $extractedDomain) === false) {
            error_log("DEEP_ENRICH_FAIL: Domain mismatch for $email: extracted '$extractedDomain' != '$emailDomain'");
            return ['error' => 'Company domain mismatch in deep search', 'extracted_domain' => $extractedDomain, 'expected_domain' => $emailDomain];
        }
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

    // 1. Check if we already have data for this email
    $stmt = $pdo->prepare("SELECT id FROM lead_enrichment WHERE TRIM(LOWER(email)) = ?");
    $stmt->execute([trim(strtolower($email))]);
    if ($stmt->fetch()) {
        return ['status' => 'already_enriched'];
    }

    $person = null;
    $finalResponseRaw = null;

    // 2. FullContact — send email + phone + name to identify the person
    error_log("FullContact: Enriching $email ($firstName $lastName, $phone)");
    $fcRes = fullContactRequest($email, $phone, $firstName, $lastName);
    $fc = ($fcRes['code'] === 200) ? $fcRes['data'] : null;

    // 3. Extract work email from FullContact to use with Apollo
    $workEmail = null;
    if ($fc && !empty($fc['details']['emails'])) {
        foreach ($fc['details']['emails'] as $fcEmail) {
            $fcEmailVal = strtolower($fcEmail['value'] ?? '');
            // Skip if it's the same email they submitted
            if ($fcEmailVal && $fcEmailVal !== strtolower($email)) {
                $workEmail = $fcEmailVal;
                error_log("FullContact: Found additional email: $workEmail");
                break;
            }
        }
    }

    // 4. Try Apollo — first with work email from FullContact, then with submitted email
    $emailsToTry = array_filter([$workEmail, $email]);
    foreach ($emailsToTry as $tryEmail) {
        error_log("Apollo: Trying match with email=$tryEmail");
        $matchRes = apolloRequest("https://api.apollo.io/api/v1/people/match", [
            'email' => $tryEmail,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'reveal_personal_emails' => true
        ]);
        $person = $matchRes['data']['person'] ?? null;
        if ($person) {
            $finalResponseRaw = $matchRes['raw'];
            error_log("Apollo: Found match via $tryEmail");
            break;
        }
    }

    // 5. If Apollo found nothing but FullContact had data, use FullContact data
    if (!$person && $fc && !empty($fc['fullName'])) {
        $person = [
            'name' => $fc['fullName'] ?? "$firstName $lastName",
            'title' => $fc['title'] ?? null,
            'linkedin_url' => $fc['linkedin'] ?? null,
            'twitter_url' => $fc['twitter'] ?? null,
            'city' => null,
            'state' => null,
            'country' => null,
            'seniority' => null,
            'photo_url' => $fc['avatar'] ?? null,
            'headline' => $fc['bio'] ?? null,
            'organization' => [
                'name' => $fc['organization'] ?? null,
                'primary_domain' => null,
                'industry' => null,
                'short_description' => null,
                'estimated_num_employees' => null,
                'annual_revenue_printed' => null,
                'logo_url' => null
            ]
        ];
        if (!empty($fc['location'])) {
            $parts = array_map('trim', explode(',', $fc['location']));
            if (count($parts) >= 3) {
                $person['city'] = $parts[0];
                $person['state'] = $parts[1];
                $person['country'] = $parts[2];
            } elseif (count($parts) === 2) {
                $person['city'] = $parts[0];
                $person['state'] = $parts[1];
            }
        }
        $finalResponseRaw = json_encode(['source' => 'fullcontact', 'data' => $fc]);
        error_log("Using FullContact data for " . $person['name']);
    }

    if (!$person) {
        error_log("Enrichment: No match found for $email ($firstName $lastName)");
        return ['status' => 'no_match'];
    }

    // 6. If we have a LinkedIn URL, scrape fresh data to fill gaps
    $linkedinUrl = $person['linkedin_url'] ?? null;
    if ($linkedinUrl) {
        error_log("LinkedIn Scraper: Fetching fresh data from $linkedinUrl");
        $liRes = linkedinScraperRequest($linkedinUrl);

        if ($liRes['code'] === 200 && !empty($liRes['data']['data'])) {
            $li = $liRes['data']['data'];
            error_log("LinkedIn Scraper: Got fresh profile for " . ($li['full_name'] ?? 'unknown'));

            // LinkedIn is the source of truth — overwrite with fresh data
            if (!empty($li['headline'])) $person['headline'] = $li['headline'];
            if (!empty($li['profile_photo'])) $person['photo_url'] = $li['profile_photo'];
            if (!empty($li['city'])) $person['city'] = $li['city'];
            if (!empty($li['state'])) $person['state'] = $li['state'];
            if (!empty($li['country'])) $person['country'] = $li['country'];
            if (!empty($li['full_name'])) $person['name'] = $li['full_name'];

            // Get current job from experience — this is the most up-to-date
            $currentJob = null;
            foreach (($li['experiences'] ?? []) as $exp) {
                if (!empty($exp['is_current'])) {
                    $currentJob = $exp;
                    break;
                }
            }
            if ($currentJob) {
                if (!empty($currentJob['title'])) $person['title'] = $currentJob['title'];
                if (!empty($currentJob['company'])) {
                    $person['organization']['name'] = $currentJob['company'];
                }
            }

            // Append LinkedIn data to raw response
            $existingRaw = json_decode($finalResponseRaw, true) ?? [];
            $existingRaw['linkedin_scraper'] = $li;
            $finalResponseRaw = json_encode($existingRaw);
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
    $title = $person['title'] ?? '—';
    $company = $person['organization']['name'] ?? '—';
    $industry = $person['organization']['industry'] ?? '—';
    $employees = $person['organization']['estimated_num_employees'] ?? '—';
    $revenue = $person['organization']['annual_revenue_printed'] ?? '—';
    $linkedin = $person['linkedin_url'] ?? null;
    $photo = $person['photo_url'] ?? null;
    $headline = $person['headline'] ?? '—';
    $city = $person['city'] ?? null;
    $state = $person['state'] ?? null;
    $country = $person['country'] ?? null;
    $location = implode(', ', array_filter([$city, $state, $country])) ?: '—';

    $initials = strtoupper(substr($firstName ?? '', 0, 1) . substr($lastName ?? '', 0, 1));
    $avatarUrl = $photo ?: "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=3b82f6&color=fff&size=120&bold=true";
    $linkedinRow = $linkedin ? "<tr><td style=\"color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a\">LinkedIn</td><td style=\"padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a\"><a href=\"{$linkedin}\" style=\"color:#5b9bf6;text-decoration:none\">View Profile</a></td></tr>" : '';

    // Build behavioral rows
    $behaviorRows = '';
    if (!empty($sectionsViewed)) {
        arsort($sectionsViewed);
        $count = 0;
        foreach ($sectionsViewed as $sec => $time) {
            if ($count++ >= 5) break;
            $behaviorRows .= "<tr><td style=\"color:#999;padding:6px 12px;font-size:13px\">" . ucfirst($sec) . "</td><td style=\"padding:6px 12px;font-size:13px;color:#ccc\">" . round($time) . "s</td></tr>";
        }
    }
    $actionTags = '';
    if (!empty($buttonsClicked)) {
        $counts = array_count_values($buttonsClicked);
        arsort($counts);
        foreach ($counts as $btn => $clk) {
            $actionTags .= "<span style=\"display:inline-block;margin:3px;padding:4px 12px;background:#2a2a2a;border-radius:12px;font-size:12px;color:#ccc\">{$btn} ({$clk})</span>";
        }
    }

    $to = defined('NOTIFICATION_EMAIL') ? NOTIFICATION_EMAIL : 'admin@theeleanor.nyc';
    $subject = "New Lead: $name" . ($company !== '—' ? " @ $company" : '');

    $body = <<<EOD
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#111;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#111111"><tr><td align="center" style="padding:20px 10px">
<table width="560" cellpadding="0" cellspacing="0" bgcolor="#1a1a1a" style="border-radius:8px;overflow:hidden">

  <!-- Header -->
  <tr><td bgcolor="#1a1a1a" style="padding:28px 30px 0;text-align:center">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:4px;color:#666">The Eleanor</div>
    <div style="font-size:20px;color:#fff;margin-top:4px;font-weight:300;letter-spacing:1px">Lead Intelligence Report</div>
    <div style="width:40px;height:2px;background:#5b9bf6;margin:16px auto 0"></div>
  </td></tr>

  <!-- Avatar + Name -->
  <tr><td style="padding:28px 30px 20px;text-align:center">
    <img src="{$avatarUrl}" width="90" height="90" style="border-radius:50%;border:3px solid #333;display:block;margin:0 auto" />
    <div style="font-size:22px;font-weight:700;color:#fff;margin-top:14px">{$name}</div>
    <div style="font-size:14px;color:#5b9bf6;margin-top:4px">{$title}</div>
    <div style="font-size:13px;color:#888;margin-top:2px">{$company}</div>
  </td></tr>

  <!-- Divider -->
  <tr><td style="padding:0 30px"><div style="height:1px;background:#2a2a2a"></div></td></tr>

  <!-- Contact -->
  <tr><td style="padding:20px 30px">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td width="50%" style="padding:10px 12px;background:#222;border-radius:6px">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#666">Email</div>
          <div style="font-size:13px;color:#fff;margin-top:3px">{$email}</div>
        </td>
        <td width="10"></td>
        <td width="50%" style="padding:10px 12px;background:#222;border-radius:6px">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#666">Phone</div>
          <div style="font-size:13px;color:#fff;margin-top:3px">{$phone}</div>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- Professional Intel -->
  <tr><td style="padding:0 30px 20px">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:2px;color:#666;margin-bottom:10px;font-weight:700">Professional Intel</div>
    <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#222" style="border-radius:6px;overflow:hidden">
      <tr><td style="color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a">Title</td><td style="padding:8px 12px;font-size:13px;color:#fff;border-bottom:1px solid #2a2a2a">{$title}</td></tr>
      <tr><td style="color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a">Company</td><td style="padding:8px 12px;font-size:13px;color:#fff;border-bottom:1px solid #2a2a2a">{$company}</td></tr>
      <tr><td style="color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a">Industry</td><td style="padding:8px 12px;font-size:13px;color:#fff;border-bottom:1px solid #2a2a2a">{$industry}</td></tr>
      <tr><td style="color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a">Employees</td><td style="padding:8px 12px;font-size:13px;color:#fff;border-bottom:1px solid #2a2a2a">{$employees}</td></tr>
      <tr><td style="color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a">Location</td><td style="padding:8px 12px;font-size:13px;color:#fff;border-bottom:1px solid #2a2a2a">{$location}</td></tr>
      <tr><td style="color:#999;padding:8px 12px;font-size:13px;border-bottom:1px solid #2a2a2a">Headline</td><td style="padding:8px 12px;font-size:13px;color:#fff;border-bottom:1px solid #2a2a2a">{$headline}</td></tr>
      {$linkedinRow}
    </table>
  </td></tr>

  <!-- Behavioral Journey -->
  <tr><td style="padding:0 30px 20px">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:2px;color:#666;margin-bottom:10px;font-weight:700">Behavioral Journey</div>
    <div style="font-size:12px;color:#555;margin-bottom:10px">{$totalEvents} events tracked</div>
    <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#222" style="border-radius:6px;overflow:hidden">
      {$behaviorRows}
    </table>
    <div style="margin-top:10px">{$actionTags}</div>
  </td></tr>

  <!-- Footer -->
  <tr><td style="padding:20px 30px;text-align:center;border-top:1px solid #2a2a2a">
    <a href="https://eleanorbk.com/admin/" style="color:#5b9bf6;font-size:13px;text-decoration:none;font-weight:600">Open Dashboard →</a>
    <div style="font-size:10px;color:#444;margin-top:8px">The Eleanor Intelligence System</div>
  </td></tr>

</table>
</td></tr></table>
</body></html>
EOD;

    smtpSend($to, $subject, $body, $email, true);
}
