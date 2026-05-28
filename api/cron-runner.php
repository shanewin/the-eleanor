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

// SMS tasks DISABLED for this engagement (client request). The welcome SMS is
// off, so there are no conversations to service. Restore the requires below to
// re-enable the SMS queue, proactive follow-ups, and tour reminders.
// require_once __DIR__ . '/process-sms-queue.php';
// require_once __DIR__ . '/process-followups.php';
// require_once __DIR__ . '/process-tour-reminders.php';
