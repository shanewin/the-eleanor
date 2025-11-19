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

// Prepare email
$to = 'theeleanor@doorway.nyc';
$subject = 'New Email List Signup - ' . $firstName . ' ' . $lastName;

$headers = [
    'From: info@theeleanor.nyc',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion()
];

$body = "New Email List Signup:\n\n" . implode("\n", [
    "Name: " . $firstName . " " . $lastName,
    "Email: " . $email,
    "Interests: " . ($interests ?: 'Not specified'),
    "Consent: " . $consent,
    "Date: " . date('Y-m-d H:i:s')
]);

// Send email
if (mail($to, $subject, $body, implode("\r\n", $headers))) {
    echo json_encode([
        'success' => true,
        'message' => 'Successfully joined email list'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please try again.'
    ]);
}
?> 
