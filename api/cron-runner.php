<?php
/**
 * Master Cron Runner
 * Runs every 5 minutes via Hostinger cron job.
 * Executes multiple scheduled tasks internally.
 *
 * Hostinger cron schedule: every 5 minutes — "*\/5 * * * * php public_html/api/cron-runner.php"
 */

// Task 0: Drain any pending lead-processing jobs (every run — safety net for
// jobs that didn't finish inline after the form submission flushed)
require_once __DIR__ . '/process-lead-jobs.php';

// SMS tasks: queue replay, proactive follow-ups, tour reminders.
require_once __DIR__ . '/process-sms-queue.php';
require_once __DIR__ . '/process-followups.php';
require_once __DIR__ . '/process-tour-reminders.php';
