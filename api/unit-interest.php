<?php
// Reduce error verbosity in production
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 0);

session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

require_once 'enrichment.php';
require_once 'db_config.php';
require_once 'config.php';
require_once 'smtp-mail.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: application/json');

// Validate POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Validate CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Input validation
function clean($field) {
    return htmlspecialchars(trim($_POST[$field] ?? ''), ENT_QUOTES, 'UTF-8');
}

$required = ['firstName', 'lastName', 'email', 'phone'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate data
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// Prepare data
$unitValue = clean('unit');
$firstName = clean('firstName');
$lastName = clean('lastName');
$phone = clean('phone');
$moveInDate = clean('moveInDate');
$budget = clean('budget');
$hearAboutUs = clean('hearAboutUs');
$message = clean('message');
$trackingId = clean('tracking_id');
$ip_address = $_SERVER['REMOTE_ADDR'];

// Database storage
try {
    $sb->insert('unit_inquiries', [
        'unit' => $unitValue,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'move_in_date' => $moveInDate,
        'budget' => $budget,
        'hear_about_us' => $hearAboutUs,
        'message' => $message,
        'ip_address' => $ip_address,
        'tracking_id' => $trackingId
    ]);

    // Trigger Apollo Enrichment (Non-blocking as it might take time)
    enrichLead($email, $firstName, $lastName, $phone);
} catch (Exception $e) {
    error_log("Database insert failed (Unit Inquiry): " . $e->getMessage());
}

// Send email notification via SMTP
$to = NOTIFICATION_EMAIL;
$subject = "New Unit Inquiry: " . $unitValue;
$body = "New Unit Inquiry details:\n\n" . implode("\n", [
    "Unit: " . $unitValue,
    "Name: " . $firstName . " " . $lastName,
    "Email: " . $email,
    "Phone: " . $phone,
    "Move-in Date: " . $moveInDate,
    "Message:\n" . $message
]);

$emailSent = smtpSend($to, $subject, $body, $email);

if (!$emailSent) {
    error_log("Failed to send unit inquiry notification to $to");
}

// Regenerate CSRF token after successful submission (prevent reuse)
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'success' => true,
    'message' => 'Thank you for your interest!'
]);
