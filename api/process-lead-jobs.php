<?php
/**
 * Lead-job queue drainer — invoked from cron-runner.php on every run.
 *
 * Picks up any lead_processing_jobs row that is either still pending or has
 * been stuck in 'running' past the stale-lock cutoff, and re-invokes the worker
 * (which is idempotent — steps_done lets it skip work that already succeeded).
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/job-runner.php';

$staleCutoff = gmdate('Y-m-d\TH:i:s\Z', time() - LEAD_JOB_STALE_LOCK_SECONDS);

// Pending jobs first (oldest first, max 10 per run).
$pending = $sb->select(
    'lead_processing_jobs',
    'id',
    ['status=eq.pending'],
    'created_at.asc',
    10
);

// Plus any 'running' rows whose lock has gone stale.
$stuck = $sb->select(
    'lead_processing_jobs',
    'id',
    ['status=eq.running', 'locked_at=lt.' . $staleCutoff],
    'locked_at.asc',
    10
);

$jobs = array_merge($pending, $stuck);
if (empty($jobs)) {
    return;
}

foreach ($jobs as $job) {
    runLeadProcessingJob((int) $job['id']);
}
