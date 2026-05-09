<?php
/**
 * Master Cron Runner
 * Runs every 5 minutes via Hostinger cron job.
 * Executes multiple scheduled tasks internally.
 *
 * Hostinger cron: */5 * * * * php public_html/api/cron-runner.php
 */

// Task 1: Process SMS queue (every run — every 5 min)
require_once __DIR__ . '/process-sms-queue.php';

// Task 2: Process follow-ups (every 6th run — roughly every 30 min)
// Use a simple file-based timer to avoid running every 5 min
$followupLock = sys_get_temp_dir() . '/eleanor_followup_last.txt';
$lastFollowup = file_exists($followupLock) ? (int) file_get_contents($followupLock) : 0;

if (time() - $lastFollowup >= 1800) { // 30 minutes
    file_put_contents($followupLock, time());
    require_once __DIR__ . '/process-followups.php';
}
