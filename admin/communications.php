<?php
$pageTitle = 'Communications';
$activePage = 'communications';
$extraJs = ['/admin/js/communications.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<!-- Pipeline View -->
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
                            <th>SMS</th>
                            <th>Last Activity</th>
                            <th>Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary"></div><span class="text-body-tertiary ms-2">Loading...</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Timeline View -->
<div id="commTimelineView" style="display:none">
    <!-- Lead Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="#" class="text-body-tertiary text-decoration-none" onclick="event.preventDefault(); showCommPipeline()"><i class="bi bi-arrow-left fs-5"></i></a>
            <div>
                <h1 class="h4 fw-bold mb-0" id="timelineLeadName"></h1>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <small class="text-white-50" id="timelineLeadEmail"></small>
                    <small class="text-white-50" id="timelineLeadPhone"></small>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <!-- AI Status -->
            <div id="aiStatusSection" class="d-flex align-items-center gap-2" style="display:none!important">
                <span class="small text-white-50">AI Auto-Reply</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="aiToggle" style="width:2.5rem;height:1.25rem;cursor:pointer" onchange="handleAIToggle()">
                </div>
                <span class="small" id="aiStatusLabel"></span>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="showAddCommModal()"><i class="bi bi-plus-lg me-1"></i>Log</button>
        </div>
    </div>

    <!-- Lead-to-Showing Status Pipeline -->
    <div id="statusPipelineCard" class="card bg-body-tertiary border-0 mb-3">
        <div class="card-body p-3">
            <div id="statusPipeline"></div>
        </div>
    </div>

    <!-- Tour Banner (active or recent tour) -->
    <div id="tourBanner" style="display:none" class="mb-3"></div>

    <!-- Form Submissions -->
    <div id="formSubmissionsCard" class="card bg-body-tertiary border-0 mb-3" style="display:none">
        <div class="card-body p-3">
            <div id="formSubmissionsList"></div>
        </div>
    </div>

    <!-- Two-column conversation: SMS + Email -->
    <div class="row g-3 mb-3" id="conversationRow">
        <!-- SMS column -->
        <div class="col-12 col-lg-6">
            <div class="card bg-body-tertiary border-0 h-100" style="min-height:400px;display:flex;flex-direction:column">
                <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center px-3 py-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-chat-dots-fill text-info"></i>
                        <span class="fw-semibold text-white" style="font-size:0.9rem">SMS</span>
                    </div>
                    <small class="text-white-50" id="smsThreadCount"></small>
                </div>
                <div class="card-body p-3 flex-grow-1" id="smsThreadContainer" style="overflow-y:auto;max-height:calc(100vh - 480px);min-height:300px">
                    <div class="text-center text-body-tertiary py-4 small">Loading...</div>
                </div>
                <!-- SMS Reply Box -->
                <div id="smsReplyBox" class="border-top border-secondary p-3" style="display:none">
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="smsReplyInput" placeholder="Type an SMS reply..." maxlength="480" onkeydown="if(event.key==='Enter')sendSMSReply()">
                        <button class="btn btn-primary px-3" onclick="sendSMSReply()" id="smsReplyBtn">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-white-50" id="smsReplyStatus"></small>
                        <small class="text-white-50"><span id="smsCharCount">0</span> / 480</small>
                    </div>
                </div>
                <div id="noPhoneMessage" class="border-top border-secondary p-3 text-center" style="display:none">
                    <small class="text-white-50"><i class="bi bi-exclamation-circle me-1"></i>No phone number on file</small>
                </div>
            </div>
        </div>
        <!-- Email column -->
        <div class="col-12 col-lg-6">
            <div class="card bg-body-tertiary border-0 h-100" style="min-height:400px;display:flex;flex-direction:column">
                <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center px-3 py-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-fill text-info"></i>
                        <span class="fw-semibold text-white" style="font-size:0.9rem">Email</span>
                    </div>
                    <small class="text-white-50" id="emailThreadCount"></small>
                </div>
                <div class="card-body p-3 flex-grow-1" id="emailThreadContainer" style="overflow-y:auto;max-height:calc(100vh - 480px);min-height:300px">
                    <div class="text-center text-body-tertiary py-4 small">Loading...</div>
                </div>
                <div class="border-top border-secondary p-3 text-center">
                    <small class="text-white-50"><i class="bi bi-info-circle me-1"></i>Outbound email composer coming soon</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Internal Log (collapsed by default) -->
    <div id="internalLogCard" class="card bg-body-tertiary border-0 mb-3" style="display:none">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center" style="cursor:pointer" onclick="toggleInternalLog()">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill text-secondary"></i>
                    <span class="fw-semibold text-white" style="font-size:0.9rem">Internal Log</span>
                    <span class="badge bg-secondary bg-opacity-25 text-white-50" id="internalLogCount" style="font-size:0.65rem"></span>
                </div>
                <i class="bi bi-chevron-down text-white-50" id="internalLogChevron"></i>
            </div>
            <div id="internalLogList" style="display:none" class="mt-3"></div>
        </div>
    </div>
</div>

<!-- Hidden state -->
<input type="hidden" id="currentLeadEmail" value="">
<input type="hidden" id="currentLeadPhone" value="">

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
