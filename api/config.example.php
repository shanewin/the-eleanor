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
// Generate hash with: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', 'PASTE_YOUR_BCRYPT_HASH_HERE');

// --- Notification Settings ---
define('NOTIFICATION_EMAIL', 'YOUR_EMAIL@example.com');

// --- SMTP Settings ---
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'YOUR_SMTP_EMAIL');
define('SMTP_PASS', 'YOUR_SMTP_PASSWORD');
define('SMTP_FROM', 'YOUR_SMTP_EMAIL');
define('SMTP_FROM_NAME', 'The Eleanor');

// --- Frontend Preview Setting ---
define('PREVIEW_PASSWORD', 'YOUR_PREVIEW_PASSWORD_HERE');
