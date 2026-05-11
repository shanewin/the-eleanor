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

    $__cronJobName = $jobName;
    $__cronItemsProcessed = 0;
    $__cronExtraError = null;

    $row = @$sb->insert('cron_runs', [
        'job_name'   => $jobName,
        'started_at' => date('c'),
        'status'     => 'running'
    ]);
    $__cronRunId = (is_array($row) && isset($row['id'])) ? $row['id'] : null;

    register_shutdown_function('cronRunShutdown');
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
    global $sb, $__cronRunId, $__cronJobName, $__cronItemsProcessed, $__cronExtraError;
    if (!$__cronRunId) return;

    $status = 'success';
    $errorMsg = $__cronExtraError;

    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $status = 'failed';
        $errorMsg = substr($err['message'] . ' @ ' . $err['file'] . ':' . $err['line'], 0, 1000);
    } elseif ($__cronExtraError !== null) {
        $status = 'failed';
    }

    @$sb->update('cron_runs', [
        'finished_at'     => date('c'),
        'status'          => $status,
        'error'           => $errorMsg,
        'items_processed' => $__cronItemsProcessed
    ], ['id=eq.' . $__cronRunId]);

    if ($__cronJobName) {
        cronRunPrune($__cronJobName);
    }
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
