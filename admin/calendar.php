<?php
$pageTitle = 'Calendar';
$activePage = 'calendar';
$useCalendar = true;
$extraJs = ['/admin/js/calendar.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-0">Showings Calendar</h1>
        <small class="text-body-tertiary">Tour requests and confirmed showings</small>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openNewShowingModal()">
        <i class="bi bi-plus-lg me-1"></i> New Showing
    </button>
</div>

<!-- Calendar Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-body-tertiary border-0">
            <div class="card-body p-3 text-center">
                <div class="stat-value fs-3 fw-bold" id="calStatPending">0</div>
                <div class="stat-label small text-warning">Pending Requests</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-body-tertiary border-0">
            <div class="card-body p-3 text-center">
                <div class="stat-value fs-3 fw-bold" id="calStatConfirmed">0</div>
                <div class="stat-label small text-success">Confirmed</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-body-tertiary border-0">
            <div class="card-body p-3 text-center">
                <div class="stat-value fs-3 fw-bold" id="calStatToday">0</div>
                <div class="stat-label small text-info">Today</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-body-tertiary border-0">
            <div class="card-body p-3 text-center">
                <div class="stat-value fs-3 fw-bold" id="calStatThisWeek">0</div>
                <div class="stat-label small text-white-50">This Week</div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Container -->
<div class="card bg-body-tertiary border-0">
    <div class="card-body p-4">
        <div id="showingsCalendar"></div>
    </div>
</div>

<!-- Upcoming Showings List -->
<div class="card bg-body-tertiary border-0 mt-4">
    <div class="card-body p-4">
        <h5 class="fw-semibold mb-3">Upcoming Showings</h5>
        <div id="upcomingShowingsList">
            <div class="text-white-50 small text-center py-4">No upcoming showings</div>
        </div>
    </div>
</div>

<!-- Showing Detail Modal -->
<div class="modal fade" id="showingDetailModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Showing Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="showingDetailBody"></div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
