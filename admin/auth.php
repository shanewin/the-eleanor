<?php
/**
 * Admin Session Logic
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../api/config.php';

function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function logout() {
    $_SESSION['admin_logged_in'] = false;
    session_destroy();
    header('Location: login.php');
    exit;
}
