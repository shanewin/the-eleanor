<?php
$pageTitle = 'Communications';
$activePage = 'communications';
$extraJs = ['/admin/js/communications.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<div id="commPipelineView">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold mb-0">Communications</h1>
        <button class="btn btn-primary btn-sm" onclick="showAddCommModal()"><i class="bi bi-plus-lg me-1"></i>Log Communication</button>
    </div>
    <div class="card bg-body-tertiary border-0">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" style="background:transparent" id="commPipelineTable">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Last Communication</th>
                            <th>Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="5" class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary"></div><span class="text-body-tertiary ms-2">Loading...</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div id="commTimelineView" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="#" class="text-body-tertiary text-decoration-none" onclick="event.preventDefault(); showCommPipeline()"><i class="bi bi-arrow-left"></i></a>
            <h1 class="h3 fw-bold mb-0" id="commTimelineName">Communications</h1>
        </div>
        <button class="btn btn-primary btn-sm" onclick="showAddCommModal()"><i class="bi bi-plus-lg me-1"></i>Log Communication</button>
    </div>
    <div id="commsTimeline"></div>
</div>
<input type="hidden" id="commSearchEmail" value="">

<!-- Communication Modal -->
<div class="modal fade" id="commModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Log Communication</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-white-50">Lead Email</label>
                    <input type="email" class="form-control bg-dark border-secondary text-white" id="commLeadEmail">
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label small text-white-50">Direction</label>
                        <select class="form-select bg-dark border-secondary text-white" id="commDirection">
                            <option value="outbound">Outbound</option>
                            <option value="inbound">Inbound</option>
                            <option value="internal">Internal</option>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label small text-white-50">Channel</label>
                        <select class="form-select bg-dark border-secondary text-white" id="commChannel">
                            <option value="note">Note</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="phone">Phone Call</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-white-50">Subject</label>
                    <input type="text" class="form-control bg-dark border-secondary text-white" id="commSubject">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-white-50">Body / Notes</label>
                    <textarea class="form-control bg-dark border-secondary text-white" id="commBody" rows="3"></textarea>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label small text-white-50">From</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="commSender">
                    </div>
                    <div class="col">
                        <label class="form-label small text-white-50">To</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="commRecipient">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveComm()">Save</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
