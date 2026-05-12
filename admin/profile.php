<?php
$targetBrokerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pageTitle = $targetBrokerId ? 'Broker Profile' : 'My Profile';
$activePage = 'profile';
$extraJs = ['/admin/js/profile.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<script>
    // Set before profile.js loads so the page can pick up the target broker.
    window.PROFILE_TARGET_ID = <?= $targetBrokerId ? (int)$targetBrokerId : 'null' ?>;
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/brokers.php" id="profileBackLink" class="btn btn-sm btn-outline-secondary" style="display:none" title="Back to brokers">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0" id="profileHeading"><?= $targetBrokerId ? 'Broker Profile' : 'My Profile' ?></h1>
    </div>
    <button class="btn btn-primary" onclick="saveProfile()" id="profileSaveBtn">
        <i class="bi bi-check-lg me-1"></i>Save Changes
    </button>
</div>

<div id="profileAlert" class="alert small" style="display:none"></div>

<div class="row g-4">
    <!-- Left Column: Avatar + Quick Info -->
    <div class="col-lg-4">
        <div class="card bg-body-tertiary border-0">
            <div class="card-body p-4 text-center">
                <!-- Avatar -->
                <div class="position-relative d-inline-block mb-3">
                    <div id="profileAvatar" class="profile-avatar-lg mx-auto"></div>
                    <label class="btn btn-sm btn-outline-secondary position-absolute bottom-0 end-0 rounded-circle p-1" style="width:32px;height:32px;line-height:1;" title="Upload photo">
                        <i class="bi bi-camera" style="font-size:0.85rem"></i>
                        <input type="file" id="profilePictureInput" accept="image/*" class="d-none" onchange="handleAvatarUpload(this)">
                    </label>
                </div>
                <div id="profileNameDisplay" class="fw-semibold fs-5 text-white"></div>
                <div id="profileTitleDisplay" class="small text-white-50 mb-2"></div>
                <div id="profileRoleBadge" class="mb-3"></div>
                <button class="btn btn-sm btn-outline-danger mt-2" id="removeAvatarBtn" onclick="removeAvatar()" style="display:none">
                    <i class="bi bi-trash me-1"></i>Remove Photo
                </button>
            </div>
        </div>

        <!-- Google Calendar Card -->
        <div class="card bg-body-tertiary border-0 mt-3">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3"><i class="bi bi-google me-2"></i>Google Calendar</h6>
                <div id="gcalStatus">
                    <div class="text-white-50 small">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Form Fields -->
    <div class="col-lg-8">
        <!-- Personal Info -->
        <div class="card bg-body-tertiary border-0 mb-3">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">Personal Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">Full Name</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="profileName">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">Title</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="profileTitle" placeholder="e.g., Licensed Real Estate Broker">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">Email</label>
                        <input type="email" class="form-control bg-dark border-secondary text-white-50" id="profileEmail" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">Phone</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="profilePhone">
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Info -->
        <div class="card bg-body-tertiary border-0 mb-3">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">Professional Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">License Number</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="profileLicense" placeholder="NY Real Estate License #">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">Brokerage / Company</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" id="profileCompany">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-white-50">Bio</label>
                        <textarea class="form-control bg-dark border-secondary text-white" id="profileBio" rows="3" placeholder="Brief professional bio..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-white-50">Preferred Contact Method</label>
                        <select class="form-select bg-dark border-secondary text-white" id="profilePreferredContact">
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="text">Text</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Default Availability -->
        <div class="card bg-body-tertiary border-0">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-1">Default Availability</h6>
                <p class="small text-white-50 mb-3">Used as a fallback when Google Calendar is not connected.</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-white-50">Start Time</label>
                        <input type="time" class="form-control bg-dark border-secondary text-white" id="profileAvailStart" value="09:00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-white-50">End Time</label>
                        <input type="time" class="form-control bg-dark border-secondary text-white" id="profileAvailEnd" value="18:00">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label small text-white-50">Available Days</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="0"> Sun</label>
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="1" checked> Mon</label>
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="2" checked> Tue</label>
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="3" checked> Wed</label>
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="4" checked> Thu</label>
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="5" checked> Fri</label>
                        <label class="btn btn-sm btn-outline-secondary avail-day-btn"><input type="checkbox" class="d-none avail-day-check" value="6"> Sat</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
