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
                                    <th>Google Calendar</th>
                                    <th data-col="avail">Availability</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="8" class="text-center py-5 text-body-tertiary">Loading brokers...</td></tr>
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
            <div class="row mb-3">
              <div class="col-6">
                <label class="form-label small text-white-50">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control bg-dark border-secondary text-white" id="brokerFirstName" required>
              </div>
              <div class="col-6">
                <label class="form-label small text-white-50">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control bg-dark border-secondary text-white" id="brokerLastName" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control bg-dark border-secondary text-white" id="brokerEmail" required>
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Phone Number</label>
              <input type="tel" class="form-control bg-dark border-secondary text-white" id="brokerPhone" placeholder="(555) 555-5555" maxlength="14">
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Role <span class="text-danger">*</span></label>
              <select class="form-select bg-dark border-secondary text-white" id="brokerRole">
                <option value="broker">Broker</option>
                <option value="owner">Owner</option>
              </select>
            </div>
            <p class="small text-white-50 mb-0" id="brokerInviteNote"><i class="bi bi-info-circle me-1"></i>An invite email will be sent to this address to set up their login.</p>

            <!-- Default Availability (edit mode only) -->
            <div id="brokerAvailSection" class="mt-4 pt-3 border-top border-secondary" style="display:none">
              <h6 class="fw-semibold mb-1 small text-white">Default Availability</h6>
              <p class="small text-white-50 mb-3">Used for tour bookings when Google Calendar isn't connected.</p>
              <div class="row g-3 mb-3">
                <div class="col-6">
                  <label class="form-label small text-white-50">Start Time</label>
                  <input type="time" class="form-control bg-dark border-secondary text-white" id="brokerAvailStart" value="09:00">
                </div>
                <div class="col-6">
                  <label class="form-label small text-white-50">End Time</label>
                  <input type="time" class="form-control bg-dark border-secondary text-white" id="brokerAvailEnd" value="18:00">
                </div>
              </div>
              <label class="form-label small text-white-50">Available Days</label>
              <div class="d-flex gap-2 flex-wrap">
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="0"> Sun</label>
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="1"> Mon</label>
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="2"> Tue</label>
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="3"> Wed</label>
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="4"> Thu</label>
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="5"> Fri</label>
                <label class="btn btn-sm btn-outline-secondary broker-avail-day-btn"><input type="checkbox" class="d-none broker-avail-day-check" value="6"> Sat</label>
              </div>
            </div>

            <div id="brokerSaveAlert" class="alert small mt-3" style="display:none"></div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveBroker()">Save</button>
          </div>
        </div>
      </div>
    </div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
