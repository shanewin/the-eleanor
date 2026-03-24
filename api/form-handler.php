<?php
// Reduce error verbosity in production; use server logs for debugging
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 0);
session_start();
require_once 'enrichment.php';
require_once 'smtp-mail.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: application/json');

// Generate CSRF if missing
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

// Validate CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Invalid CSRF token']);
  exit;
}

// Rate limiting
$dataDir = __DIR__ . '/data/';
$rateLimitFile = $dataDir . 'waitlist_rate_limits.txt';
$submissionFile = $dataDir . 'waitlist_submissions.txt';
$ip = $_SERVER['REMOTE_ADDR'];
$currentTime = time();
$rateLimitDuration = 300;

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0750, true);
}

if (!file_exists($rateLimitFile)) {
  file_put_contents($rateLimitFile, '{}');
}

// $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];

// if (isset($rateLimits[$ip]) && ($currentTime - $rateLimits[$ip] < $rateLimitDuration)) {
//   http_response_code(429);
//   echo json_encode(['error' => 'Please wait before submitting again.']);
//   exit;
// }

// Sanitize inputs
function clean($field) {
  return htmlspecialchars(trim($_POST[$field] ?? ''), ENT_QUOTES);
}

$firstName = clean('firstName');
$lastName = clean('lastName');
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$phone = clean('phone');
$moveInDate = clean('moveInDate');
$budget = clean('budget');
$hearAboutUs = clean('hearAboutUs');
$unit = clean('unit');
$unitType = clean('unitType');
$message = clean('message');
$trackingId = clean('tracking_id');

if (!$firstName || !$lastName || !$email || !$phone) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields.']);
  exit;
}

// Database storage
require_once 'db_config.php';
try {
  $stmt = $pdo->prepare("INSERT INTO waitlist_submissions 
    (first_name, last_name, email, phone, move_in_date, budget, unit, unit_type, hear_about_us, message, ip_address, tracking_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $firstName, $lastName, $email, $phone, $moveInDate, $budget, $unit, $unitType, $hearAboutUs, $message, $ip, $trackingId
  ]);
  
  // Trigger Apollo Enrichment (Async if possible, but here we'll just run it)
  enrichLead($email, $firstName, $lastName, $phone);
} catch (PDOException $e) {
  // Log error but continue with email
  error_log("Database insert failed: " . $e->getMessage());
}

// Log to file (Legacy support)
$rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
$rateLimits[$ip] = $currentTime;
file_put_contents($rateLimitFile, json_encode($rateLimits));

$logEntry = implode('|', [
  date('Y-m-d H:i:s'),
  $ip,
  $unit,
  $unitType,
  $firstName,
  $lastName,
  $email,
  $phone,
  $moveInDate,
  $budget,
  $hearAboutUs,
  $message
]) . PHP_EOL;

file_put_contents($submissionFile, $logEntry, FILE_APPEND);

// Send email via SMTP
$to = NOTIFICATION_EMAIL;
$subject = 'New Wait List Submission - ' . $firstName . ' ' . $lastName;
$body = <<<EOD
New Wait List Inquiry for The Eleanor:

Name: $firstName $lastName
Email: $email
Phone: $phone
Move-In Date: $moveInDate
Budget: $budget
Unit Type: $unitType
How Did You Hear About Us: $hearAboutUs

Message:
$message

Date: ${logEntry}
EOD;

$emailSent = smtpSend($to, $subject, $body, $email);

if (!$emailSent) {
    error_log("Failed to send notification email to $to");
}

// Always return success if DB insert worked
http_response_code(200);
echo json_encode([
    'success' => true, 
    'message' => $emailSent ? 'Submission successful' : 'Submission saved (Email notification failed)'
]);
