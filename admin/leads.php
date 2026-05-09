<?php
$pageTitle = 'Leads';
$activePage = 'leads';
$extraJs = ['/admin/js/leads.js', '/admin/js/auto-text.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0">All Qualified Leads</h1>
</div>
<div class="card bg-body-tertiary border-0">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover leadsTable mb-0" style="background:transparent;">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortLeads('created_at')">Timestamp</th>
                        <th class="sortable" onclick="sortLeads('last_name')">Lead</th>
                        <th>Contact</th>
                        <th>Intent</th>
                        <th class="sortable" onclick="sortLeads('lead_status')">Status</th>
                        <th class="sortable" onclick="sortLeads('event_count')">Engagement</th>
                        <th class="sortable" onclick="sortLeads('grade_score')">Grade</th>
                        <th>Assigned</th>
                        <th>First Response</th>
                        <th>Management</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="11" class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div><span class="text-body-tertiary ms-2">Loading...</span></td></tr></tbody>
            </table>
        </div>
    </div>
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
