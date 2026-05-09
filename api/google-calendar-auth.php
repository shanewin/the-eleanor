<?php
/**
 * Google Calendar OAuth Callback
 * Handles the redirect from Google after broker authorizes.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/google-calendar.php';

$redirectBase = '/admin/profile.php';

// Check for error (user denied consent)
if (!empty($_GET['error'])) {
    error_log('Google OAuth error: ' . $_GET['error']);
    header('Location: ' . $redirectBase . '?gcal=error');
    exit;
}

// Validate required params
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state) {
    error_log('Google OAuth callback missing code or state');
    header('Location: ' . $redirectBase . '?gcal=error');
    exit;
}

// Validate state (CSRF + broker ID)
$stateData = googleValidateState($state);
if (!$stateData) {
    error_log('Google OAuth invalid state parameter');
    header('Location: ' . $redirectBase . '?gcal=error');
    exit;
}

$brokerId = intval($stateData['broker_id']);

// Exchange code for tokens
$tokenData = googleExchangeCode($code);
if (!$tokenData) {
    error_log('Google OAuth token exchange failed for broker ' . $brokerId);
    header('Location: ' . $redirectBase . '?gcal=error');
    exit;
}

// Get the connected Google account email
$calendarEmail = googleGetCalendarEmail($tokenData['access_token']);
if (!$calendarEmail) {
    $calendarEmail = 'unknown';
}

// Store tokens in broker record
global $sb;
$sb->update('brokers', [
    'google_calendar_token'     => json_encode($tokenData),
    'google_calendar_email'     => $calendarEmail,
    'google_calendar_connected' => true
], ['id=eq.' . $brokerId]);

// Clean up session
unset($_SESSION['google_oauth_nonce']);
unset($_SESSION['google_oauth_broker_id']);

// Redirect back to profile
header('Location: ' . $redirectBase . '?gcal=connected');
exit;
