<?php
/**
 * Admin Authentication via Supabase Auth
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../api/config.php';

/**
 * Authenticate with Supabase using email/password.
 * Returns access_token on success, false on failure.
 */
function supabaseLogin($email, $password) {
    $url = SUPABASE_URL . '/auth/v1/token?grant_type=password';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_PUBLISHABLE_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data;
    }

    error_log("Supabase login failed ($httpCode): $response");
    return false;
}

/**
 * Verify a Supabase access token is still valid.
 */
function supabaseVerifyToken($accessToken) {
    $url = SUPABASE_URL . '/auth/v1/user';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'apikey: ' . SUPABASE_PUBLISHABLE_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return false;
}

/**
 * Refresh a Supabase access token using a refresh token.
 * Updates session tokens on success.
 */
function supabaseRefreshToken($refreshToken) {
    $url = SUPABASE_URL . '/auth/v1/token?grant_type=refresh_token';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'refresh_token' => $refreshToken
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_PUBLISHABLE_KEY
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $_SESSION['supabase_access_token'] = $data['access_token'];
        $_SESSION['supabase_refresh_token'] = $data['refresh_token'];
        return true;
    }

    error_log("Supabase token refresh failed ($httpCode): $response");
    return false;
}

function isAdmin() {
    if (empty($_SESSION['supabase_access_token'])) {
        return false;
    }
    // Verify token is still valid
    $user = supabaseVerifyToken($_SESSION['supabase_access_token']);
    if (!$user) {
        // Token expired — try to refresh before clearing session
        if (!empty($_SESSION['supabase_refresh_token']) && supabaseRefreshToken($_SESSION['supabase_refresh_token'])) {
            return true;
        }
        // Refresh failed — clear session
        $_SESSION = [];
        return false;
    }
    return true;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function logout() {
    // Sign out from Supabase
    if (!empty($_SESSION['supabase_access_token'])) {
        $ch = curl_init(SUPABASE_URL . '/auth/v1/logout');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $_SESSION['supabase_access_token'],
            'apikey: ' . SUPABASE_PUBLISHABLE_KEY
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    $_SESSION = [];
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}
