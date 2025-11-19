<?php
// Reduce error verbosity in production; use server logs for debugging
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 0);
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Initialize CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF Token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

// Input validation
function clean($field) {
    return htmlspecialchars(trim($_POST[$field] ?? ''), ENT_QUOTES, 'UTF-8');
}

$required = ['firstName', 'lastName', 'email', 'phone'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        die(json_encode(['error' => "Missing required field: $field"]));
    }
}

// Validate data
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid email address']));
}

// Prepare data
$data = [
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'],
    clean('unit'),
    clean('firstName'),
    clean('lastName'),
    $email,
    clean('phone'),
    clean('moveInDate'),
    clean('budget'),
    clean('hearAboutUs'),
    clean('message')
];

// Store submission (secure file handling)
$file = 'submissions.txt';
file_put_contents($file, implode('|', $data) . PHP_EOL, FILE_APPEND | LOCK_EX);
chmod($file, 0640);

// Send email
$headers = [
    'From: info@theeleanor.nyc',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion()
];

$body = "New Unit Inquiry:\n\n" . implode("\n", [
    "Unit: " . clean('unit'),
    "Name: " . clean('firstName') . " " . clean('lastName'),
    "Email: " . $email,
    "Phone: " . clean('phone'),
    "Move-in Date: " . clean('moveInDate'),
    "Message: " . clean('message')
]);

if (mail('theeleanor@doorway.nyc', "New Unit Inquiry: " . clean('unit'), $body, implode("\r\n", $headers))) {
    // Regenerate CSRF token after successful submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo json_encode(['success' => true]);
} else {
    error_log("Failed to send email for unit inquiry");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email.']);
}
