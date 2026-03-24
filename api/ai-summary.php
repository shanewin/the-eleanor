<?php
/**
 * AI Score Justification Service (Anthropic Claude)
 */
header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'config.php';
require_once '../admin/auth.php';

// Security: Only allow logged-in admins
if (!isAdmin()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Handle both GET (for simple migration/tests) and POST (for live generation)
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_GET['email'] ?? '';
$insights = $input['insights'] ?? [];
$grade = $input['grade'] ?? 'N/A';
$score = $input['score'] ?? 0;
$logs = $input['logs'] ?? [];

if (empty($email)) {
    die(json_encode(['error' => 'Email required']));
}

generateJustification($email, $insights, $grade, $score, $logs);

function generateJustification($email, $insights, $grade, $score, $logs) {
    global $pdo;

    // 1. Fetch Enrichment Data
    $stmt = $pdo->prepare("SELECT raw_response, ai_summary FROM lead_enrichment WHERE email = ?");
    $stmt->execute([$email]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        die(json_encode(['error' => 'Lead not found or not enriched yet']));
    }

    $raw = json_decode($lead['raw_response'], true);
    $person = $raw['person'] ?? [];
    $org = $person['organization'] ?? [];
    $employment = $person['employment_history'] ?? [];
    $education = $person['education_history'] ?? $person['education'] ?? [];

    // 2. Prepare context for Anthropic
    $careerPath = array_map(function($j) {
        return ($j['title'] ?? 'Role') . " at " . ($j['organization_name'] ?? 'Company');
    }, array_slice($employment, 0, 5));
    
    $eduPath = array_map(function($e) {
        return ($e['school_name'] ?? 'University') . " (" . ($e['degree'] ?? 'Degree') . ")";
    }, array_slice($education, 0, 3));

    // 3. Format Activity Logs for AI
    $activitySummary = "No recent activity recorded.";
    if (!empty($logs)) {
        $logEntries = array_map(function($l) {
            $event = str_replace('_', ' ', $l['event'] ?? $l['event_name'] ?? 'View');
            $time = isset($l['time']) ? date('H:i', strtotime($l['time'])) : 'Recent';
            return "[$time] $event";
        }, array_slice($logs, -15)); // Latest 15 activities
        $activitySummary = implode(", ", $logEntries);
    }

    $prompt = "You are a dry, matter-of-fact real estate leasing analyst. 
    Review this professional profile and user activity to provide a concise, bulleted prospect summary. 
    
    ASSIGNED GRADE: " . $grade . " (Score: " . $score . "/100)
    
    SIGNALS EARNED:
    " . implode(", ", $insights) . "
    
    USER JOURNEY (Web Activity):
    " . $activitySummary . "
    
    PROFILE DATA:
    Name: " . ($person['name'] ?? 'Subject') . "
    Current: " . ($person['title'] ?? 'Unknown') . " at " . ($org['name'] ?? 'Unknown') . "
    Recent Career: " . implode(" -> ", $careerPath) . "
    Education: " . implode(", ", $eduPath) . "
    Industry: " . ($org['industry'] ?? 'Corporate') . "
    Revenue: " . ($org['annual_revenue_printed'] ?? 'Unknown') . "
    Publicly Traded: " . (!empty($org['publicly_traded_symbol']) ? 'Yes (' . $org['publicly_traded_symbol'] . ')' : 'No') . "
    
    INSTRUCTIONS:
    - Group related signals into combined, high-impact bullet points to AVOID REDUNDANCY. 
    - Reference specific evidence from the profile data (titles, companies, schools).
    - ANALYZE INTENT: Based on the User Journey, include one bullet point summarizing their interest level and what they focused on (e.g., units, amenities, or neighborhood).
    - Tone: Analytic, professional, strictly factual, NO fluff.
    - Format: Plain text bullets starting with '•'.
    - CONCLUSION: End with a single high-impact sentence assessing the professional reliability and suitability of " . ($person['name'] ?? 'this individual') . " for a luxury residential building based on their career profile, assigned grade, and demonstrated interest.
    
    RESPONSE FORMAT: Just the bullets followed by the conclusion sentence. No intro, no closing.";

    // 3. Call Anthropic
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => 250,
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

    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        die(json_encode(['success' => false, 'error' => $err['error']['message'] ?? 'API Error']));
    }

    $resData = json_decode($response, true);
    $summary = trim($resData['content'][0]['text'] ?? '');

    // 4. Update Database
    $updateStmt = $pdo->prepare("UPDATE lead_enrichment SET ai_summary = ? WHERE email = ?");
    $updateStmt->execute([$summary, $email]);

    echo json_encode(['success' => true, 'summary' => $summary]);
}
