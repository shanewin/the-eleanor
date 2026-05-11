<?php
/**
 * Cron run instrumentation.
 *
 * Each scheduled script calls cronRunStart('<name>') near the top and
 * cronRunIncItems(n) as it processes work. A shutdown handler records
 * final status (success / failed) plus duration and items processed.
 *
 * Required table: cron_runs(id bigint pk, job_name text, started_at timestamptz,
 *   finished_at timestamptz, status text check in (running, success, failed),
 *   error text, items_processed int default 0).
 *
 * Code degrades gracefully when the table doesn't exist — every write is
 * suppressed and the cron job runs as normal.
 */

$__cronRunId = null;
$__cronJobName = null;
$__cronItemsProcessed = 0;
$__cronExtraError = null;

function cronRunStart(string $jobName): void {
    global $sb, $__cronRunId, $__cronJobName, $__cronItemsProcessed, $__cronExtraError;

    // cron-runner.php chains multiple scripts via require_once in a single
    // PHP process. If a previous run is still open, finalize it as success
    // before opening the new one. (Fatals are handled by cronRunShutdown.)
    if ($__cronRunId) {
        cronRunFinalize('success');
    }

    $__cronJobName = $jobName;
    $__cronItemsProcessed = 0;
    $__cronExtraError = null;

    $row = @$sb->insert('cron_runs', [
        'job_name'   => $jobName,
        'started_at' => date('c'),
        'status'     => 'running'
    ]);
    $__cronRunId = (is_array($row) && isset($row['id'])) ? $row['id'] : null;

    // Register the shutdown handler once per process.
    static $registered = false;
    if (!$registered) {
        register_shutdown_function('cronRunShutdown');
        $registered = true;
    }
}

/**
 * Close out the current run with the given status. Called both by
 * cronRunStart (success path between chained scripts) and cronRunShutdown
 * (fatal path at script end).
 */
function cronRunFinalize(string $status, ?string $errorMsg = null): void {
    global $sb, $__cronRunId, $__cronJobName, $__cronItemsProcessed, $__cronExtraError;
    if (!$__cronRunId) return;

    if ($errorMsg === null && $__cronExtraError !== null) {
        $errorMsg = $__cronExtraError;
        if ($status === 'success') $status = 'failed';
    }

    @$sb->update('cron_runs', [
        'finished_at'     => date('c'),
        'status'          => $status,
        'error'           => $errorMsg,
        'items_processed' => $__cronItemsProcessed
    ], ['id=eq.' . $__cronRunId]);

    if ($status === 'failed' && $__cronJobName) {
        cronRunNotifyFailure($__cronJobName, $errorMsg ?? 'Unknown error');
    }
    if ($__cronJobName) cronRunPrune($__cronJobName);

    $__cronRunId = null;
    $__cronJobName = null;
    $__cronItemsProcessed = 0;
    $__cronExtraError = null;
}

/**
 * Surface a cron failure in the bell-icon notification dropdown.
 *
 * Deduped: if there's already an unread cron_failure notification for this
 * job, we skip — otherwise a broken cron firing every 5 min would create a
 * notification each time and bury everything else. As soon as the operator
 * marks it read (or the cron recovers and they clear it), the next failure
 * generates a fresh notification.
 */
function cronRunNotifyFailure(string $jobName, string $errorMsg): void {
    global $sb;

    $title = "Cron failed: $jobName";
    $existing = @$sb->selectOne('notifications', 'id', [
        'type=eq.cron_failure',
        'is_read=eq.false',
        'title=eq.' . urlencode($title)
    ]);
    if ($existing) return;

    $body = trim(explode("\n", $errorMsg)[0]);
    @$sb->insert('notifications', [
        'type'       => 'cron_failure',
        'title'      => $title,
        'body'       => substr($body, 0, 250),
        'lead_email' => null,
        'broker_id'  => null,
        'is_read'    => false,
        'link'       => '/admin/settings.php#jobs'
    ]);
}

function cronRunIncItems(int $n = 1): void {
    global $__cronItemsProcessed;
    $__cronItemsProcessed += $n;
}

/**
 * Record a soft error without aborting the script (e.g. an individual item
 * failed but the cron itself kept going). The job is marked 'failed' in the UI
 * so the operator can investigate.
 */
function cronRunSetError(string $msg): void {
    global $__cronExtraError;
    $__cronExtraError = substr($msg, 0, 1000);
}

function cronRunShutdown(): void {
    global $__cronRunId;
    if (!$__cronRunId) return;

    $status = 'success';
    $errorMsg = null;
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $status = 'failed';
        $errorMsg = substr($err['message'] . ' @ ' . $err['file'] . ':' . $err['line'], 0, 1000);
    }

    cronRunFinalize($status, $errorMsg);
}

/**
 * Keep at most the last 100 rows per job. Cheap to run on every cron tick.
 */
function cronRunPrune(string $jobName): void {
    global $sb;
    $rows = @$sb->select('cron_runs', 'id', ['job_name=eq.' . urlencode($jobName)], 'started_at.desc', 200);
    if (!is_array($rows) || count($rows) <= 100) return;

    $idsToDelete = array_slice(array_column($rows, 'id'), 100);
    foreach ($idsToDelete as $id) {
        @$sb->delete('cron_runs', ['id=eq.' . $id]);
    }
}
