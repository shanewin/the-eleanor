<?php
$pageTitle = 'Overview';
$activePage = 'overview';
$extraJs = ['/admin/js/overview.js', '/admin/js/auto-text.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-0">Lead-to-Showing Command Center</h1>
        <small class="text-body-tertiary">Updating in real-time</small>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card bg-body-tertiary border-0 stat-card">
            <div class="card-body">
                <div class="stat-label">Unique Visitors</div>
                <div class="stat-value" id="statSessions"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card bg-body-tertiary border-0 stat-card">
            <div class="card-body">
                <div class="stat-label">Total Leads</div>
                <div class="stat-value" id="statLeads"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card bg-body-tertiary border-0 stat-card">
            <div class="card-body">
                <div class="stat-label">Visitor-to-Lead Rate</div>
                <div class="stat-value" id="statConv"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card bg-body-tertiary border-0 stat-card">
            <div class="card-body">
                <div class="stat-label">New Today <span id="statTodayDate" style="font-weight:400;letter-spacing:0.05em;opacity:0.6"></span></div>
                <div class="stat-value" id="statHot"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Auto SMS Quick Toggle -->
<div class="d-flex align-items-center gap-3 mb-4 px-1">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="overviewSmsToggle" style="width:2.5rem;height:1.25rem;cursor:pointer" onchange="toggleOverviewSMS()">
    </div>
    <div>
        <span class="text-white fw-medium" style="font-size:0.85rem">Auto SMS</span>
        <span class="ms-2" id="overviewSmsStatus" style="font-size:0.75rem"></span>
    </div>
</div>

<!-- Recent Conversions & Enrichment Table -->
<div class="card bg-body-tertiary border-0">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 fw-bold mb-0">Recent Conversions &amp; Enrichment</h2>
            <div class="d-flex align-items-center gap-2">
                <small class="text-body-tertiary">Sort by:</small>
                <select id="overview-sort" onchange="fetchData()" class="form-select form-select-sm" style="width:auto;">
                    <option value="date">Date (Newest)</option>
                    <option value="grade">Grade (Elite First)</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover leadsTable mb-0" style="background:transparent;">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Identity</th>
                        <th>Contact</th>
                        <th>Intent</th>
                        <th>Engagement</th>
                        <th>Grade</th>
                        <th>Assigned</th>
                        <th>First Response</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="8" class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div><span class="text-body-tertiary ms-2">Loading...</span></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Journey Slide-Out Panel -->
<div id="journeyPanel">
    <!-- Dynamic content loaded by overview.js -->
</div>

<!-- Auto Text Modal -->
<div class="modal fade" id="autoTextModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-robot me-2"></i>Auto Text</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Lead info -->
                <div class="d-flex justify-content-between align-items-center mb-3" id="autoTextLeadInfo" style="display:none!important">
                    <div>
                        <span class="fw-semibold text-white" id="autoTextLeadName"></span>
                        <span class="text-white-50 small ms-2" id="autoTextLeadPhone"></span>
                    </div>
                </div>

                <!-- Loading state -->
                <div id="autoTextLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-secondary mb-2"></div>
                    <div class="text-white-50 small">Generating message...</div>
                </div>

                <!-- Message editor -->
                <div id="autoTextEditor" style="display:none">
                    <label class="form-label small text-white-50">Message Preview</label>
                    <textarea class="form-control bg-dark border-secondary text-white" id="autoTextBody" rows="5" style="font-size:0.9rem;line-height:1.5"></textarea>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-white-50">Edit the message or send as-is</small>
                        <small class="text-white-50" id="autoTextCharCount">0 / 160</small>
                    </div>
                </div>

                <!-- Success state -->
                <div id="autoTextSuccess" class="text-center py-4" style="display:none">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:2rem"></i>
                    <div class="text-white mt-2">Message sent</div>
                </div>

                <!-- Error state -->
                <div id="autoTextError" class="alert alert-danger small mt-3" style="display:none"></div>
            </div>
            <div class="modal-footer border-secondary" id="autoTextFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="autoTextSendBtn" onclick="sendAutoText()" style="display:none">
                    <i class="bi bi-send me-1"></i>Send
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
