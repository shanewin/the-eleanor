<?php
/**
 * User Activity Tracking API
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
$eventData = $input['data'] ?? null;
$ipAddress = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session ID']);
    exit;
}

try {
    // 1. Ensure tracking session exists
    $sb->upsert('tracking_sessions', [
        'id' => $sessionId,
        'user_agent' => $userAgent,
        'ip_address' => $ipAddress
    ], 'id');

    // 2. Log the activity
    $sb->insert('activity_logs', [
        'session_id' => $sessionId,
        'event_type' => $eventType,
        'event_name' => $eventName,
        'event_data' => $eventData
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Logging failed']);
}
