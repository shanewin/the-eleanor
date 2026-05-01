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

function pdlRequest($email, $phone = null, $firstName = null, $lastName = null, $linkedinUrl = null) {
    $params = ['min_likelihood' => '6', 'pretty' => 'true'];
    if ($email) $params['email'] = $email;
    if ($phone) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) === 10) $cleanPhone = '1' . $cleanPhone;
        $params['phone'] = '+' . $cleanPhone;
    }
    if ($firstName) $params['first_name'] = $firstName;
    if ($lastName) $params['last_name'] = $lastName;
    if ($linkedinUrl) {
        // PDL expects format: linkedin.com/in/username (no https://)
        $params['profile'] = preg_replace('#^https?://(www\.)?#', '', $linkedinUrl);
    }

    $url = 'https://api.peopledatalabs.com/v5/person/enrich?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . PDL_API_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    error_log("PDL response ($httpCode): " . substr($response, 0, 500));
    return ['code' => $httpCode, 'data' => $data['data'] ?? null, 'likelihood' => $data['likelihood'] ?? 0, 'raw' => $response];
}

function apolloRequest($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-haiku-4-5-20251001',
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
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
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

    // 1. Find LinkedIn URL via Tavily (using company domain + location for accuracy)
    $locationHint = $likelyState ? "$likelyState " : "";
    $query = "\"$firstName $lastName\" " . ($companyHint ? "$companyHint " : "") . $locationHint . "LinkedIn";
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

    $aiText = $anthropicRes['data']['content'][0]['text'] ?? '';
    // Strip markdown code blocks if Claude wrapped the JSON
    $aiText = preg_replace('/^```(?:json)?\s*/i', '', trim($aiText));
    $aiText = preg_replace('/\s*```$/', '', $aiText);
    $extractedData = json_decode($aiText, true);

    if (empty($extractedData) || empty($extractedData['full_name'])) {
        error_log("DEEP_ENRICH_FAIL: Anthropic could not normalize data for $email. Raw: " . substr($aiText, 0, 300));
        return ['error' => 'Anthropic could not normalize data', 'raw_ai_response' => $aiText];
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
    global $sb;

    // 1. Check if we already have data for this email
    $existing = $sb->selectOne('lead_enrichment', 'id', ['email=eq.' . urlencode(trim(strtolower($email)))]);
    if ($existing) {
        return ['status' => 'already_enriched'];
    }

    $person = null;
    $finalResponseRaw = null;

    // 2. People Data Labs — send email + phone + name to identify the person
    error_log("PDL: Enriching $email ($firstName $lastName, $phone)");
    $pdlRes = pdlRequest($email, $phone, $firstName, $lastName);
    $pdl = ($pdlRes['code'] === 200 && $pdlRes['likelihood'] >= 6) ? $pdlRes['data'] : null;

    // 2b. Location sanity check — reject PDL match if phone is US but match is not
    $likelyCountry = getLikelyCountry($phone);
    if ($pdl && $likelyCountry === 'United States') {
        $pdlCountry = strtolower($pdl['location_country'] ?? '');
        if ($pdlCountry && !in_array($pdlCountry, ['united states', 'us', 'usa'])) {
            error_log("PDL: Location mismatch — phone is US but PDL match is in '$pdlCountry'. Rejecting PDL match.");
            $pdl = null;
        }
    }

    // 3. Extract work email from PDL to use with Apollo
    $workEmail = null;
    if ($pdl) {
        // Check work_email field
        $pdlWorkEmail = $pdl['work_email'] ?? null;
        if ($pdlWorkEmail && is_string($pdlWorkEmail) && $pdlWorkEmail !== 'true' && strtolower($pdlWorkEmail) !== strtolower($email)) {
            $workEmail = strtolower($pdlWorkEmail);
            error_log("PDL: Found work email: $workEmail");
        }
        // Also check emails array for professional emails
        if (!$workEmail && !empty($pdl['emails'])) {
            foreach ($pdl['emails'] as $pdlEmail) {
                if (!is_array($pdlEmail)) continue;
                $val = $pdlEmail['address'] ?? '';
                $type = $pdlEmail['type'] ?? '';
                // Prefer professional emails, skip personal
                if ($val && strtolower($val) !== strtolower($email) && strpos($type, 'professional') !== false) {
                    $workEmail = strtolower($val);
                    error_log("PDL: Found professional email: $workEmail");
                    break;
                }
            }
        }
        // Fallback: any email that's not the submitted one
        if (!$workEmail && !empty($pdl['emails'])) {
            foreach ($pdl['emails'] as $pdlEmail) {
                $val = is_array($pdlEmail) ? ($pdlEmail['address'] ?? '') : $pdlEmail;
                if ($val && strtolower($val) !== strtolower($email)) {
                    $workEmail = strtolower($val);
                    error_log("PDL: Found additional email: $workEmail");
                    break;
                }
            }
        }
    }

    // 4. Try Apollo — first with work email from PDL, then with submitted email
    $emailsToTry = array_filter([$workEmail, $email]);
    foreach ($emailsToTry as $tryEmail) {
        error_log("Apollo: Trying match with email=$tryEmail");
        $matchRes = apolloRequest("https://api.apollo.io/api/v1/people/match", [
            'email' => $tryEmail,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'reveal_personal_emails' => true
        ]);
        $apolloPerson = $matchRes['data']['person'] ?? null;
        if ($apolloPerson) {
            // Location sanity check on Apollo result too
            if ($likelyCountry === 'United States') {
                $apolloCountry = strtolower($apolloPerson['country'] ?? '');
                if ($apolloCountry && !in_array($apolloCountry, ['united states', 'us', 'usa', ''])) {
                    error_log("Apollo: Location mismatch — phone is US but Apollo match is in '$apolloCountry'. Skipping.");
                    continue;
                }
            }
            $person = $apolloPerson;
            $finalResponseRaw = $matchRes['raw'];
            error_log("Apollo: Found verified match via $tryEmail");
            break;
        }
    }

    // 4b. Apollo name search removed — unreliable for common names.
    // Deep enrichment (Tavily + LinkedIn + Claude) handles this case instead.

    // 5. Build profile from PDL data
    if ($pdl) {
        // Extract social URLs from PDL
        $pdlLinkedin = !empty($pdl['linkedin_url']) ? 'https://' . ltrim($pdl['linkedin_url'], '/') : null;
        $pdlTwitter = !empty($pdl['twitter_url']) ? 'https://' . ltrim($pdl['twitter_url'], '/') : null;
        $pdlFacebook = !empty($pdl['facebook_url']) ? 'https://' . ltrim($pdl['facebook_url'], '/') : null;
        $pdlGithub = !empty($pdl['github_url']) ? 'https://' . ltrim($pdl['github_url'], '/') : null;

        if ($person) {
            // Apollo found a match — supplement with PDL social URLs where Apollo is missing
            if (empty($person['linkedin_url']) && $pdlLinkedin) $person['linkedin_url'] = $pdlLinkedin;
            if (empty($person['twitter_url']) && $pdlTwitter) $person['twitter_url'] = $pdlTwitter;
            if (empty($person['facebook_url']) && $pdlFacebook) $person['facebook_url'] = $pdlFacebook;
            if (empty($person['github_url']) && $pdlGithub) $person['github_url'] = $pdlGithub;

            // Capture inferred salary from PDL
            if (!empty($pdl['inferred_salary'])) $person['inferred_salary'] = $pdl['inferred_salary'];

            // Append PDL to raw response
            $existingRaw = json_decode($finalResponseRaw, true) ?? [];
            $existingRaw['pdl'] = $pdl;
            $finalResponseRaw = json_encode($existingRaw);
            error_log("PDL: Supplemented Apollo data with social URLs + salary");
        } else {
            // Apollo found nothing — use PDL as primary profile
            $person = [
                'name' => $pdl['full_name'] ?? "$firstName $lastName",
                'title' => $pdl['job_title'] ?? null,
                'linkedin_url' => $pdlLinkedin,
                'twitter_url' => $pdlTwitter,
                'facebook_url' => $pdlFacebook,
                'github_url' => $pdlGithub,
                'city' => $pdl['location_locality'] ?? null,
                'state' => $pdl['location_region'] ?? null,
                'country' => $pdl['location_country'] ?? null,
                'seniority' => $pdl['job_title_levels'][0] ?? null,
                'inferred_salary' => $pdl['inferred_salary'] ?? null,
                'photo_url' => null,
                'headline' => $pdl['job_title'] . ' at ' . ($pdl['job_company_name'] ?? ''),
                'organization' => [
                    'name' => $pdl['job_company_name'] ?? null,
                    'primary_domain' => $pdl['job_company_website'] ?? null,
                    'industry' => $pdl['job_company_industry'] ?? null,
                    'short_description' => null,
                    'estimated_num_employees' => $pdl['job_company_size'] ?? null,
                    'annual_revenue_printed' => null,
                    'logo_url' => null
                ]
            ];
            $finalResponseRaw = json_encode(['source' => 'pdl', 'data' => $pdl]);
            error_log("Using PDL as primary profile for " . $person['name']);
        }
    }

    // 5a. If we have a person with LinkedIn URL but PDL didn't provide social URLs or salary, try PDL by LinkedIn URL
    $pdlHadUsefulData = $pdl && (!empty($pdl['twitter_url']) || !empty($pdl['github_url']) || !empty($pdl['inferred_salary']));
    if ($person && !$pdlHadUsefulData && !empty($person['linkedin_url'])) {
        error_log("PDL: Re-trying with LinkedIn URL to grab social URLs + salary");
        $pdlByLi = pdlRequest(null, null, null, null, $person['linkedin_url']);
        if ($pdlByLi['code'] === 200 && $pdlByLi['likelihood'] >= 6 && $pdlByLi['data']) {
            $pdlData = $pdlByLi['data'];
            // Supplement social URLs
            if (empty($person['twitter_url']) && !empty($pdlData['twitter_url'])) $person['twitter_url'] = 'https://' . ltrim($pdlData['twitter_url'], '/');
            if (empty($person['facebook_url']) && !empty($pdlData['facebook_url'])) $person['facebook_url'] = 'https://' . ltrim($pdlData['facebook_url'], '/');
            if (empty($person['github_url']) && !empty($pdlData['github_url'])) $person['github_url'] = 'https://' . ltrim($pdlData['github_url'], '/');
            // Grab salary
            if (!empty($pdlData['inferred_salary'])) $person['inferred_salary'] = $pdlData['inferred_salary'];
            // Append to raw response
            $existingRaw = json_decode($finalResponseRaw, true) ?? [];
            $existingRaw['pdl_by_linkedin'] = $pdlData;
            $finalResponseRaw = json_encode($existingRaw);
            error_log("PDL: Got social URLs + salary via LinkedIn URL");
        }
    }

    // 5b. Deep enrichment fallback — use Tavily to find LinkedIn URL, then re-enrich
    if (!$person && $firstName && $lastName) {
        error_log("Deep Enrichment: All standard sources failed for $email. Trying Tavily + LinkedIn discovery.");
        $deepData = deepEnrichment($email, $firstName, $lastName, $phone);

        if ($deepData && !isset($deepData['error']) && !empty($deepData['linkedin_url'])) {
            $discoveredLinkedIn = $deepData['linkedin_url'];
            error_log("Deep Enrichment: Discovered LinkedIn URL: $discoveredLinkedIn. Re-enriching via PDL + Apollo.");

            // 5c. Re-run PDL with the discovered LinkedIn URL for correct identity
            $pdlRerun = pdlRequest(null, null, null, null, $discoveredLinkedIn);
            $pdlRerunData = ($pdlRerun['code'] === 200 && $pdlRerun['likelihood'] >= 6) ? $pdlRerun['data'] : null;

            // Extract work email from PDL re-run
            $discoveredWorkEmail = null;
            if ($pdlRerunData) {
                $dwe = $pdlRerunData['work_email'] ?? null;
                if ($dwe && is_string($dwe) && $dwe !== 'true' && strtolower($dwe) !== strtolower($email)) {
                    $discoveredWorkEmail = strtolower($dwe);
                    error_log("PDL Re-run: Found work email via LinkedIn: $discoveredWorkEmail");
                }
                if (!$discoveredWorkEmail && !empty($pdlRerunData['emails'])) {
                    foreach ($pdlRerunData['emails'] as $pdlEmail) {
                        if (!is_array($pdlEmail)) continue;
                        $val = $pdlEmail['address'] ?? '';
                        $type = $pdlEmail['type'] ?? '';
                        if ($val && strtolower($val) !== strtolower($email) && strpos($type, 'professional') !== false) {
                            $discoveredWorkEmail = strtolower($val);
                            error_log("PDL Re-run: Found professional email: $discoveredWorkEmail");
                            break;
                        }
                    }
                }
            }

            // 5d. Re-run Apollo with discovered LinkedIn URL and/or work email
            $rerunEmails = array_filter([$discoveredWorkEmail, $email]);
            foreach ($rerunEmails as $tryEmail) {
                error_log("Apollo Re-run: Trying $tryEmail + LinkedIn URL");
                $apolloRerun = apolloRequest("https://api.apollo.io/api/v1/people/match", [
                    'email' => $tryEmail,
                    'linkedin_url' => $discoveredLinkedIn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'reveal_personal_emails' => true
                ]);
                $apolloRerunPerson = $apolloRerun['data']['person'] ?? null;
                if ($apolloRerunPerson) {
                    $person = $apolloRerunPerson;
                    $finalResponseRaw = json_encode([
                        'source' => 'deep_enrichment_rerun',
                        'person' => $person,
                        'pdl_rerun' => $pdlRerunData,
                        'deep_data' => $deepData
                    ]);
                    error_log("Apollo Re-run: Found match via LinkedIn URL — " . ($person['name'] ?? 'unknown'));
                    break;
                }
            }

            // Also try Apollo with just LinkedIn URL + name (no email)
            if (!$person) {
                error_log("Apollo Re-run: Trying LinkedIn URL only");
                $apolloRerun = apolloRequest("https://api.apollo.io/api/v1/people/match", [
                    'linkedin_url' => $discoveredLinkedIn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'reveal_personal_emails' => true
                ]);
                $apolloRerunPerson = $apolloRerun['data']['person'] ?? null;
                if ($apolloRerunPerson) {
                    $person = $apolloRerunPerson;
                    $finalResponseRaw = json_encode([
                        'source' => 'deep_enrichment_rerun',
                        'person' => $person,
                        'pdl_rerun' => $pdlRerunData,
                        'deep_data' => $deepData
                    ]);
                    error_log("Apollo Re-run: Found match via LinkedIn URL only — " . ($person['name'] ?? 'unknown'));
                }
            }

            // 5e. If Apollo re-run failed, use deep enrichment data directly
            if (!$person) {
                error_log("Apollo Re-run: No match. Using deep enrichment data directly.");
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
                $finalResponseRaw = json_encode(['source' => 'deep_enrichment', 'person' => $person, 'pdl_rerun' => $pdlRerunData]);
            }

            // Supplement with PDL social URLs if available
            if ($person && $pdlRerunData) {
                $pdlLi = !empty($pdlRerunData['linkedin_url']) ? 'https://' . ltrim($pdlRerunData['linkedin_url'], '/') : null;
                $pdlTw = !empty($pdlRerunData['twitter_url']) ? 'https://' . ltrim($pdlRerunData['twitter_url'], '/') : null;
                $pdlFb = !empty($pdlRerunData['facebook_url']) ? 'https://' . ltrim($pdlRerunData['facebook_url'], '/') : null;
                $pdlGh = !empty($pdlRerunData['github_url']) ? 'https://' . ltrim($pdlRerunData['github_url'], '/') : null;
                if (empty($person['linkedin_url']) && $pdlLi) $person['linkedin_url'] = $pdlLi;
                if (empty($person['twitter_url']) && $pdlTw) $person['twitter_url'] = $pdlTw;
                if (empty($person['facebook_url']) && $pdlFb) $person['facebook_url'] = $pdlFb;
                if (empty($person['github_url']) && $pdlGh) $person['github_url'] = $pdlGh;
            }
        } elseif ($deepData && !isset($deepData['error'])) {
            // Deep enrichment succeeded but no LinkedIn URL — use data directly
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
            $finalResponseRaw = json_encode(['source' => 'deep_enrichment', 'person' => $person]);
            error_log("Deep Enrichment: Found match (no LinkedIn URL) for $email — " . $person['name']);
        }
    }

    if (!$person) {
        error_log("Enrichment: All sources exhausted for $email ($firstName $lastName). No match.");
        return ['status' => 'no_match'];
    }

    // 6. If we have a LinkedIn URL, scrape fresh data (source of truth)
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
        $rawJson = json_decode($finalResponseRaw, true);
        $sb->insert('lead_enrichment', [
            'email' => $email,
            'full_name' => $person['name'] ?? null,
            'job_title' => $person['title'] ?? null,
            'company' => $person['organization']['name'] ?? null,
            'company_domain' => $person['organization']['primary_domain'] ?? null,
            'seniority' => $person['seniority'] ?? null,
            'linkedin_url' => $person['linkedin_url'] ?? null,
            'twitter_url' => $person['twitter_url'] ?? null,
            'github_url' => $person['github_url'] ?? null,
            'facebook_url' => $person['facebook_url'] ?? null,
            'city' => $person['city'] ?? null,
            'state' => $person['state'] ?? null,
            'country' => $person['country'] ?? null,
            'employee_count' => $person['organization']['estimated_num_employees'] ?? null,
            'industry' => $person['organization']['industry'] ?? null,
            'annual_revenue' => $person['organization']['annual_revenue_printed'] ?? null,
            'company_logo' => $person['organization']['logo_url'] ?? null,
            'company_description' => $person['organization']['short_description'] ?? null,
            'headline' => $person['headline'] ?? null,
            'photo_url' => $person['photo_url'] ?? null,
            'inferred_salary' => $person['inferred_salary'] ?? null,
            'raw_response' => $rawJson
        ]);

        sendEnrichmentEmail($email, $firstName, $lastName, $person);

        return [
            'status' => 'success',
            'data' => $person
        ];
    } catch (Exception $e) {
        error_log("Database error in enrichment: " . $e->getMessage());
        return ['status' => 'db_error', 'message' => $e->getMessage()];
    }
}

function sendEnrichmentEmail($email, $firstName, $lastName, $person) {
    global $sb;

    // Get tracking IDs for this email from all submission tables
    $trackingIds = [];
    foreach (['waitlist_submissions', 'unit_inquiries', 'mailing_list'] as $table) {
        $rows = $sb->select($table, 'tracking_id', ['email=eq.' . urlencode($email)]);
        foreach ($rows as $r) {
            if (!empty($r['tracking_id'])) $trackingIds[] = $r['tracking_id'];
        }
    }
    $trackingIds = array_unique($trackingIds);

    $logs = [];
    if (!empty($trackingIds)) {
        $idList = '(' . implode(',', $trackingIds) . ')';
        $logs = $sb->select('activity_logs', 'event_type,event_name,event_data,created_at',
            ['session_id=in.' . $idList],
            'created_at.asc'
        );
    }

    $totalEvents = count($logs);
    $sectionsViewed = [];
    $buttonsClicked = [];

    foreach ($logs as $log) {
        if ($log['event_type'] === 'visibility' && $log['event_name'] === 'section_leave') {
            $data = is_string($log['event_data']) ? json_decode($log['event_data'], true) : $log['event_data'];
            if ($data && isset($data['section'])) {
                $sec = $data['section'];
                $time = $data['secondsSpent'] ?? 0;
                if (!isset($sectionsViewed[$sec])) $sectionsViewed[$sec] = 0;
                $sectionsViewed[$sec] += $time;
            }
        }
        if ($log['event_type'] === 'click' && $log['event_name'] === 'button_click') {
            $data = is_string($log['event_data']) ? json_decode($log['event_data'], true) : $log['event_data'];
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

    // Get notification emails from settings table
    $notifyRow = $sb->selectOne('settings', 'value', ['key=eq.notification_emails']);
    $notifyEmails = array_filter(array_map('trim', explode(',', $notifyRow['value'] ?? (defined('NOTIFICATION_EMAIL') ? NOTIFICATION_EMAIL : ''))));

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

    foreach ($notifyEmails as $to) {
        smtpSend(trim($to), $subject, $body, $email, true);
    }
}
