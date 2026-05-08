<?php
$pageTitle = 'Brokers';
$activePage = 'brokers';
$extraJs = ['/admin/js/brokers.js'];
include __DIR__ . '/includes/layout-header.php';
if (!isOwner()) { echo '<div class="alert alert-danger">Access denied. Owner only.</div>'; include __DIR__ . '/includes/layout-footer.php'; exit; }
?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 fw-bold mb-0">Broker Management</h1>
                <button class="btn btn-primary" onclick="showBrokerModal()"><i class="bi bi-plus-lg me-1"></i>Add Broker</button>
            </div>
            <div class="card bg-body-tertiary border-0">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" style="background:transparent;" id="brokersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" class="text-center py-5 text-body-tertiary">Loading brokers...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

    <!-- Broker Modal -->
    <div class="modal fade" id="brokerModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content bg-dark">
          <div class="modal-header border-secondary">
            <h5 class="modal-title" id="brokerModalTitle">Add Broker</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="brokerEditId">
            <div class="mb-3">
              <label class="form-label small text-white-50">Name</label>
              <input type="text" class="form-control bg-dark border-secondary text-white" id="brokerName">
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Email</label>
              <input type="email" class="form-control bg-dark border-secondary text-white" id="brokerEmail">
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Phone</label>
              <input type="text" class="form-control bg-dark border-secondary text-white" id="brokerPhone">
            </div>
            <p class="small text-white-50 mb-0" id="brokerInviteNote">An invite email will be sent to this address to set up their login.</p>
            <div class="mb-3">
              <label class="form-label small text-white-50">Role</label>
              <select class="form-select bg-dark border-secondary text-white" id="brokerRole">
                <option value="broker">Broker</option>
                <option value="owner">Owner</option>
              </select>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveBroker()">Save</button>
          </div>
        </div>
      </div>
    </div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
