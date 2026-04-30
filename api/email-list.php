<?php
header('Content-Type: application/json');

// Enable CORS for development
// Restrict CORS in production to known origins; include localhost for dev
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedHosts = [
    'https://theeleanorbushwick.com',
    'http://localhost:8080',
    'http://localhost:8083',
];
if (in_array($allowedOrigin, $allowedHosts, true)) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Simple input sanitization
function clean($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Get and validate form data
$firstName = clean($_POST['firstName'] ?? '');
$lastName = clean($_POST['lastName'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$interests = clean($_POST['interests'] ?? '');
$consent = isset($_POST['consent']) ? 'Yes' : 'No';

// Validate required fields
if (empty($firstName) || empty($lastName) || !$email) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

if ($consent !== 'Yes') {
    echo json_encode(['success' => false, 'message' => 'Please agree to receive updates']);
    exit;
}

// Database storage
require_once 'db_config.php';
require_once 'enrichment.php';
require_once 'smtp-mail.php';

$trackingId = clean($_POST['tracking_id'] ?? '');

try {
    $sb->insert('mailing_list', [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'interests' => $interests,
        'consent' => $consent,
        'tracking_id' => $trackingId
    ]);

    // Trigger Apollo Enrichment
    enrichLead($email, $firstName, $lastName);
} catch (Exception $e) {
    error_log("Database insert failed: " . $e->getMessage());
}

// Prepare email
$to = NOTIFICATION_EMAIL;
$subject = 'New Email List Signup - ' . $firstName . ' ' . $lastName;
$body = "New Email List Signup:\n\n" . implode("\n", [
    "Name: " . $firstName . " " . $lastName,
    "Email: " . $email,
    "Interests: " . ($interests ?: 'Not specified'),
    "Consent: " . $consent,
    "Date: " . date('Y-m-d H:i:s')
]);

smtpSend($to, $subject, $body, $email);

echo json_encode([
    'success' => true,
    'message' => 'Successfully joined email list'
]);
?> 
