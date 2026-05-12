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

<!-- Broker Availability Toggles -->
<div class="card bg-body-tertiary border-0 mb-4">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="small fw-semibold text-white-50"><i class="bi bi-calendar-check me-1"></i>Broker Availability</span>
                <div id="brokerAvailToggles" class="d-flex gap-2 flex-wrap">
                    <span class="small text-white-50">Loading...</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" id="availabilityModeToggle" aria-label="Availability view mode">
                    <input type="radio" class="btn-check" name="availMode" id="modeConflicts" value="conflicts" checked>
                    <label class="btn btn-outline-secondary" for="modeConflicts" title="Show busy time and off-hours"><i class="bi bi-slash-circle me-1"></i>Conflicts</label>
                    <input type="radio" class="btn-check" name="availMode" id="modeOpenings" value="openings">
                    <label class="btn btn-outline-secondary" for="modeOpenings" title="Show only bookable open slots"><i class="bi bi-check2-circle me-1"></i>Openings</label>
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadBrokerAvailability()" title="Refresh availability">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
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

<!-- New Showing Modal -->
<div class="modal fade" id="newShowingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Schedule a Tour</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small text-white-50">Lead Email</label>
                        <input type="email" class="form-control bg-dark border-secondary text-white" id="newShowingEmail" placeholder="lead@example.com">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-white-50">Lead Phone</label>
                        <input type="tel" class="form-control bg-dark border-secondary text-white" id="newShowingPhone" placeholder="(555) 555-5555" maxlength="14">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small text-white-50">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control bg-dark border-secondary text-white" id="newShowingDate">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-white-50">Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control bg-dark border-secondary text-white" id="newShowingTime" value="10:00">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small text-white-50">Unit</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="newShowingUnit" placeholder="e.g., 4B">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-white-50">Assign Broker</label>
                        <select class="form-select bg-dark border-secondary text-white" id="newShowingBroker">
                            <option value="">Unassigned</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-white-50">Notes</label>
                    <textarea class="form-control bg-dark border-secondary text-white" id="newShowingNotes" rows="2" placeholder="Optional notes..."></textarea>
                </div>
                <div id="newShowingAlert" class="alert small" style="display:none"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNewShowing()">Schedule Tour</button>
            </div>
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
