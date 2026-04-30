<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 0);
session_start();
require_once 'db_config.php';
require_once 'enrichment.php';
require_once 'smtp-mail.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: application/json');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Invalid CSRF token']);
  exit;
}

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
$ip = $_SERVER['REMOTE_ADDR'];

if (!$firstName || !$lastName || !$email || !$phone) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields.']);
  exit;
}

try {
  $sb->insert('waitlist_submissions', [
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'move_in_date' => $moveInDate,
    'budget' => $budget,
    'unit' => $unit,
    'unit_type' => $unitType,
    'hear_about_us' => $hearAboutUs,
    'message' => $message,
    'ip_address' => $ip,
    'tracking_id' => $trackingId
  ]);

  enrichLead($email, $firstName, $lastName, $phone);
} catch (Exception $e) {
  error_log("Database insert failed: " . $e->getMessage());
}

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
EOD;

$emailSent = smtpSend($to, $subject, $body, $email);
if (!$emailSent) {
    error_log("Failed to send notification email to $to");
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => $emailSent ? 'Submission successful' : 'Submission saved (Email notification failed)'
]);
