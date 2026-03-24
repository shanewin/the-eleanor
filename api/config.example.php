<?php
/**
 * Global Configuration & Security (TEMPLATE)
 * Copy this file to config.php and fill in your actual keys.
 */

// --- Apollo.io Configuration ---
define('APOLLO_API_KEY', 'YOUR_APOLLO_API_KEY_HERE');
define('APOLLO_WEBHOOK_URL', 'https://eleanorbk.com/api/apollo-webhook.php');

// --- Anthropic Configuration ---
define('ANTHROPIC_API_KEY', 'YOUR_ANTHROPIC_API_KEY_HERE');

// --- Tavily Configuration ---
define('TAVILY_API_KEY', 'YOUR_TAVILY_API_KEY_HERE');
define('RAPIDAPI_KEY', 'YOUR_RAPIDAPI_KEY_HERE');
define('RAPIDAPI_HOST', 'fresh-linkedin-profile-data.p.rapidapi.com');

// --- Admin Dashboard Credentials ---
// Change this to a secure password! 
define('ADMIN_PASSWORD_HASH', password_hash('YOUR_SECURE_PASSWORD', PASSWORD_DEFAULT));

// --- Notification Settings ---
define('NOTIFICATION_EMAIL', 'YOUR_EMAIL@example.com');
