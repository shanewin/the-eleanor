<?php
require_once 'db_config.php';

header('Content-Type: text/plain');

echo "--- Seeding Test Lead: Tim Cook ---\n";

$email = 'tim@apple.com';
$firstName = 'Tim';
$lastName = 'Cook';

try {
    // 1. Clear existing test data
    $pdo->prepare("DELETE FROM activity_logs WHERE session_id LIKE 'mock_session_tim_cook_%'")->execute();
    $pdo->prepare("DELETE FROM tracking_sessions WHERE id LIKE 'mock_session_tim_cook_%'")->execute();
    $pdo->prepare("DELETE FROM waitlist_submissions WHERE email = ?")->execute([$email]);
    $pdo->prepare("DELETE FROM lead_enrichment WHERE email = ?")->execute([$email]);

    // 1.5 Create Mock Session
    $sessionId = 'mock_session_tim_cook_' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO tracking_sessions (id, user_agent, ip_address) VALUES (?, ?, ?)")
        ->execute([$sessionId, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '17.0.0.1']);

    // 2. Insert Submission
    $stmt = $pdo->prepare("INSERT INTO waitlist_submissions (first_name, last_name, email, phone, move_in_date, budget, unit_type, hear_about_us, message, tracking_id) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $firstName, 
        $lastName, 
        $email, 
        '+1 408-996-1010', 
        'Immediately', 
        '$10,000+', 
        'Penthouse', 
        'Test Source', 
        'Interested in the highest floor possible.',
        null // This will be updated later if needed, or left null if tracking_id is not directly from this submission
    ]);

    // 0. Ensure schema is updated for multi-session tracking
    try {
        $pdo->exec("ALTER TABLE tracking_sessions ADD COLUMN email VARCHAR(255) AFTER id;");
    } catch (Exception $e) {
        // Column likely already exists
    }

    // 2. Insert Mock Tracking Session 1 (Waitlist Visit)
    $sessionId = 'mock_session_tim_cook_' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO tracking_sessions (id, email, user_agent, ip_address) VALUES (?, ?, ?, ?)")
        ->execute([$sessionId, $email, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '1.1.1.1']);

    // 2.5 Insert Mock Activity Logs (Session 1: Initial Visit)
    $logs1 = [
        ['visibility', 'landing_hero', ['time' => '1.2s']],
        ['visibility', 'residences_section', ['scroll_depth' => '25%']],
        ['click', 'unit_view_penthouse', ['unit' => 'PH-01']],
        ['visibility', 'amenities_section', ['interacted' => true]],
        ['click', 'waitlist_open', []],
        ['visibility', 'waitlist_form', ['status' => 'started']],
        ['submit', 'waitlist_confirm', ['success' => true]]
    ];

    $logStmt = $pdo->prepare("INSERT INTO activity_logs (session_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, ?)");
    $baseTime1 = time() - 172800; // 2 days ago
    foreach ($logs1 as $index => $log) {
        $logTime = date('Y-m-d H:i:s', $baseTime1 + ($index * 15));
        $logStmt->execute([$sessionId, $log[0], $log[1], json_encode($log[2]), $logTime]);
    }

    // 2.6 Create Mock Session 2 (Returning Visit)
    $sessionId2 = 'mock_session_tim_cook_return_' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO tracking_sessions (id, email, user_agent, ip_address) VALUES (?, ?, ?, ?)")
        ->execute([$sessionId2, $email, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15', '17.0.0.1']);

    $logs2 = [
        ['visibility', 'landing_hero', ['return' => true]],
        ['visibility', 'neighborhood_section', ['time' => '45s']],
        ['click', 'view_gallery', ['count' => 5]],
        ['visibility', 'contact_section', []]
    ];

    $baseTime2 = time() - 3600; // 1 hour ago
    foreach ($logs2 as $index => $log) {
        $logTime = date('Y-m-d H:i:s', $baseTime2 + ($index * 30));
        $logStmt->execute([$sessionId2, $log[0], $log[1], json_encode($log[2]), $logTime]);
    }

    // 3. Insert Enrichment (Rich Mock Data)
    $rawResponseMock = [
        'person' => [
            'id' => 'tim_cook_mock_id',
            'title' => 'Chief Executive Officer',
            'employment_history' => [
                ['title' => 'Member Board of Trustees', 'organization_name' => 'University of Chicago', 'start_date' => '2018-01-01', 'current' => true],
                ['title' => 'Global Advisory Board Member', 'organization_name' => 'Council on Foreign Relations', 'start_date' => '2015-01-01', 'current' => true],
                ['title' => 'Chief Executive Officer', 'organization_name' => 'Apple', 'start_date' => '2011-08-24', 'current' => true],
                ['title' => 'Board of Directors', 'organization_name' => 'Nike, Inc.', 'start_date' => '2005-01-01', 'current' => true],
                ['title' => 'Chief Operating Officer', 'organization_name' => 'Apple', 'start_date' => '2007-01-01', 'end_date' => '2011-08-24', 'current' => false],
                ['title' => 'VP of Worldwide Operations', 'organization_name' => 'Apple', 'start_date' => '2002-01-01', 'end_date' => '2007-01-01', 'current' => false],
                ['title' => 'VP of Corporate Materials', 'organization_name' => 'Compaq', 'start_date' => '1997-01-01', 'end_date' => '1998-01-01', 'current' => false],
                ['title' => 'Director of North American Fulfillment', 'organization_name' => 'Intelligent Electronics', 'start_date' => '1994-01-01', 'end_date' => '1997-01-01', 'current' => false],
                ['title' => 'Director of Operations', 'organization_name' => 'IBM', 'start_date' => '1982-01-01', 'end_date' => '1994-01-01', 'current' => false]
            ],
            'education_history' => [
                ['school_name' => 'Duke University', 'degree' => 'MBA', 'start_date' => '1988-01-01'],
                ['school_name' => 'Auburn University', 'degree' => 'BS', 'start_date' => '1982-01-01']
            ],
            'organization' => [
                'name' => 'Apple',
                'market_cap' => '$3.45T',
                'publicly_traded_symbol' => 'AAPL',
                'annual_revenue_printed' => '$383B',
                'short_description' => 'Apple Inc. is an American multinational technology company.',
                'keywords' => ['consumer electronics', 'software', 'services']
            ]
        ]
    ];

    $enrichment = [
        'email' => $email,
        'full_name' => 'Tim Cook',
        'job_title' => 'Chief Executive Officer',
        'company' => 'Apple',
        'company_domain' => 'apple.com',
        'seniority' => 'Executive',
        'annual_revenue' => '$380B+',
        'headline' => 'CEO at Apple',
        'photo_url' => 'https://upload.wikimedia.org/wikipedia/commons/e/e1/Tim_Cook_2017.jpg',
        'raw_response' => json_encode($rawResponseMock)
    ];

    $columns = implode(', ', array_keys($enrichment));
    $placeholders = implode(', ', array_fill(0, count($enrichment), '?'));
    $stmt = $pdo->prepare("INSERT INTO lead_enrichment ($columns) VALUES ($placeholders)");
    $stmt->execute(array_values($enrichment));

    // 4. VERIFY DATA
    $verify = $pdo->prepare("SELECT full_name, raw_response FROM lead_enrichment WHERE email = ?");
    $verify->execute([$email]);
    $data = $verify->fetch(PDO::FETCH_ASSOC);

    echo "SUCCESS: Tim Cook has been added as a test lead at " . date('H:i:s') . ".\n";
    echo "--- DATA VERIFICATION ---\n";
    echo "Full Name in DB: " . ($data['full_name'] ?? 'NULL') . "\n";
    echo "Raw Response Length: " . strlen($data['raw_response'] ?: '') . " bytes\n";
    echo "Raw Response Snippet: " . substr($data['raw_response'] ?: 'NULL', 0, 100) . "...\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
