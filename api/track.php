<?php
/**
 * User Activity Tracking API
 * Handles session initialization and logging of user events.
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 0);
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Methods: POST');

require_once 'db_config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$sessionId = $input['sessionId'] ?? '';
$eventType = $input['type'] ?? '';
$eventName = $input['name'] ?? '';
$eventData = isset($input['data']) ? json_encode($input['data']) : null;
$ipAddress = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session ID']);
    exit;
}

try {
    // 1. Ensure tracking session exists
    $stmt = $pdo->prepare("INSERT INTO tracking_sessions (id, user_agent, ip_address) VALUES (?, ?, ?) ON CONFLICT (id) DO NOTHING");
    $stmt->execute([$sessionId, $userAgent, $ipAddress]);

    // 2. Log the activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (session_id, event_type, event_name, event_data) VALUES (?, ?, ?, ?)");
    $stmt->execute([$sessionId, $eventType, $eventName, $eventData]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Tracking error: " . $e->getMessage());
    // We don't want to break the user experience if tracking fails
    echo json_encode(['success' => false, 'error' => 'Logging failed']);
}
