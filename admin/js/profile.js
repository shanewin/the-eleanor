// ── Profile Page Logic ──

let profileData = null;

// When set, profile.php was loaded as /admin/profile.php?id=N. The backend
// decides whether the current user is allowed to edit; the UI just reflects it.
const PROFILE_TARGET_ID = (typeof window !== 'undefined' && window.PROFILE_TARGET_ID) ? window.PROFILE_TARGET_ID : null;

// ── Avatar Helpers ──
const AVATAR_COLORS = ['#6366f1', '#ec4899', '#14b8a6', '#f59e0b', '#8b5cf6', '#ef4444'];

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return parts[0][0].toUpperCase();
}

function getAvatarColor(name) {
    if (!name) return AVATAR_COLORS[0];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function renderAvatar(container, name, pictureUrl, size) {
    size = size || 100;
    if (pictureUrl) {
        container.innerHTML = `<img src="${esc(pictureUrl)}" alt="Profile" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;">`;
    } else {
        const color = getAvatarColor(name);
        const initials = getInitials(name);
        const fontSize = Math.round(size * 0.38);
        container.innerHTML = `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${color};display:flex;align-items:center;justify-content:center;font-size:${fontSize}px;font-weight:600;color:#fff;letter-spacing:0.05em;">${esc(initials)}</div>`;
    }
}

// ── Day Toggle Buttons ──
document.querySelectorAll('.avail-day-btn').forEach(btn => {
    const cb = btn.querySelector('.avail-day-check');
    if (cb.checked) btn.classList.add('active');
    btn.addEventListener('click', () => {
        cb.checked = !cb.checked;
        btn.classList.toggle('active', cb.checked);
    });
});

// ── Load Profile ──
async function loadProfile() {
    try {
        const url = API + '?action=get_my_profile' + (PROFILE_TARGET_ID ? '&id=' + PROFILE_TARGET_ID : '');
        const res = await fetch(url);
        const data = await res.json();
        if (data.error) {
            showProfileAlert('Could not load profile: ' + data.error, 'danger');
            return;
        }
        profileData = data;
        populateForm(data);
        applyAccessMode(data);
    } catch (err) {
        showProfileAlert('Connection error loading profile.', 'danger');
    }
}

// Reflect the backend's permission decision in the UI: read-only when the
// viewer can't edit; hide self-only controls (avatar upload, GCal connect)
// when viewing someone else.
function applyAccessMode(data) {
    const isSelf = !!data.is_self;
    const canEdit = !!data.can_edit;

    // Header + back link when viewing someone else
    const heading = document.getElementById('profileHeading');
    const back = document.getElementById('profileBackLink');
    if (!isSelf) {
        if (heading) heading.textContent = (data.name || 'Broker') + "'s Profile";
        if (back) back.style.display = '';
    } else {
        if (heading) heading.textContent = 'My Profile';
        if (back) back.style.display = 'none';
    }

    // Lock form when not editable
    const formIds = ['profileName','profileTitle','profilePhone','profileLicense','profileCompany','profileBio','profilePreferredContact','profileAvailStart','profileAvailEnd'];
    formIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = !canEdit;
    });
    document.querySelectorAll('.avail-day-btn').forEach(btn => {
        btn.style.pointerEvents = canEdit ? '' : 'none';
        btn.style.opacity = canEdit ? '' : '0.7';
    });

    const saveBtn = document.getElementById('profileSaveBtn');
    if (saveBtn) saveBtn.style.display = canEdit ? '' : 'none';

    // Avatar upload + Google Calendar are self-only (OAuth flow + photo upload
    // require the broker's own browser session).
    const avatarUpload = document.getElementById('profilePictureInput');
    if (avatarUpload) avatarUpload.disabled = !isSelf;
    const avatarLabel = avatarUpload ? avatarUpload.closest('label') : null;
    if (avatarLabel) avatarLabel.style.display = isSelf ? '' : 'none';
    const removeBtn = document.getElementById('removeAvatarBtn');
    if (removeBtn && !isSelf) removeBtn.style.display = 'none';
}

function populateForm(data) {
    // Avatar
    renderAvatar(document.getElementById('profileAvatar'), data.name, data.profile_picture, 100);
    document.getElementById('removeAvatarBtn').style.display = data.profile_picture ? '' : 'none';

    // Display fields
    document.getElementById('profileNameDisplay').textContent = data.name || 'No Name';
    document.getElementById('profileTitleDisplay').textContent = data.title || '';
    const roleColor = data.role === 'owner' ? 'bg-warning bg-opacity-25 text-warning' : 'bg-primary bg-opacity-25 text-primary';
    document.getElementById('profileRoleBadge').innerHTML = `<span class="badge ${roleColor} text-capitalize">${esc(data.role || 'broker')}</span>`;

    // Form fields
    document.getElementById('profileName').value = data.name || '';
    document.getElementById('profileTitle').value = data.title || '';
    document.getElementById('profileEmail').value = data.email || '';
    document.getElementById('profilePhone').value = data.phone || '';
    document.getElementById('profileLicense').value = data.license_number || '';
    document.getElementById('profileCompany').value = data.company || '';
    document.getElementById('profileBio').value = data.bio || '';
    document.getElementById('profilePreferredContact').value = data.preferred_contact || 'email';

    // Availability
    document.getElementById('profileAvailStart').value = data.default_availability_start || '09:00';
    document.getElementById('profileAvailEnd').value = data.default_availability_end || '18:00';

    const days = Array.isArray(data.default_availability_days) ? data.default_availability_days : [1,2,3,4,5];
    document.querySelectorAll('.avail-day-check').forEach(cb => {
        cb.checked = days.includes(parseInt(cb.value));
        cb.closest('.avail-day-btn').classList.toggle('active', cb.checked);
    });

    // Google Calendar status
    renderGcalStatus(data);
}

function renderGcalStatus(data) {
    const container = document.getElementById('gcalStatus');
    const isSelf = !!data.is_self;
    if (data.google_calendar_connected) {
        // Owner-only disconnect for someone else is supported by the API, but
        // we surface it only on self for now to avoid surprising the broker.
        const action = isSelf
            ? '<button class="btn btn-sm btn-outline-danger" onclick="disconnectGoogleCalendar()"><i class="bi bi-x-circle me-1"></i>Disconnect</button>'
            : '';
        container.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-success bg-opacity-25 text-success">Connected</span>
            </div>
            <div class="small text-white-50 mb-3">${esc(data.google_calendar_email || '')}</div>
            ${action}
        `;
    } else if (isSelf) {
        container.innerHTML = `
            <div class="small text-white-50 mb-3">Connect your Google Calendar to share your availability for tour scheduling.</div>
            <button class="btn btn-sm btn-primary" onclick="connectGoogleCalendar()">
                <i class="bi bi-google me-1"></i>Connect Google Calendar
            </button>
        `;
    } else {
        container.innerHTML = `
            <div class="small text-white-50">Not connected. Only this broker can connect their own Google account.</div>
        `;
    }
}

// ── Save Profile ──
async function saveProfile() {
    const btn = document.getElementById('profileSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const activeDays = [];
    document.querySelectorAll('.avail-day-check:checked').forEach(cb => activeDays.push(parseInt(cb.value)));

    const payload = {
        name: document.getElementById('profileName').value.trim(),
        title: document.getElementById('profileTitle').value.trim(),
        phone: document.getElementById('profilePhone').value.trim(),
        license_number: document.getElementById('profileLicense').value.trim(),
        company: document.getElementById('profileCompany').value.trim(),
        bio: document.getElementById('profileBio').value.trim(),
        preferred_contact: document.getElementById('profilePreferredContact').value,
        default_availability_start: document.getElementById('profileAvailStart').value,
        default_availability_end: document.getElementById('profileAvailEnd').value,
        default_availability_days: activeDays
    };
    if (PROFILE_TARGET_ID) payload.id = PROFILE_TARGET_ID;

    if (!payload.name) {
        showProfileAlert('Name is required.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';
        return;
    }

    try {
        const res = await fetch(API + '?action=update_my_profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            showProfileAlert('Profile saved successfully.', 'success');
            // Update display
            document.getElementById('profileNameDisplay').textContent = payload.name;
            document.getElementById('profileTitleDisplay').textContent = payload.title;
            renderAvatar(document.getElementById('profileAvatar'), payload.name, profileData?.profile_picture, 100);
        } else {
            showProfileAlert('Error: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showProfileAlert('Connection error saving profile.', 'danger');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';
}

// ── Avatar Upload ──
async function handleAvatarUpload(input) {
    const file = input.files[0];
    if (!file) return;

    // Validate size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        showProfileAlert('Image must be under 2MB.', 'danger');
        return;
    }

    // Convert to base64
    const reader = new FileReader();
    reader.onload = async function(e) {
        const base64 = e.target.result;

        try {
            const res = await fetch(API + '?action=upload_profile_picture', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: base64 })
            });
            const result = await res.json();
            if (result.success) {
                profileData.profile_picture = base64;
                renderAvatar(document.getElementById('profileAvatar'), profileData.name, base64, 100);
                document.getElementById('removeAvatarBtn').style.display = '';
                showProfileAlert('Photo updated.', 'success');
            } else {
                showProfileAlert('Error uploading photo.', 'danger');
            }
        } catch (err) {
            showProfileAlert('Connection error uploading photo.', 'danger');
        }
    };
    reader.readAsDataURL(file);
    input.value = '';
}

async function removeAvatar() {
    showConfirm({
        title: 'Remove Photo',
        message: 'Your profile will use initials instead.',
        icon: '<i class="bi bi-image text-warning"></i>',
        btnText: 'Remove',
        btnClass: 'btn-warning',
        onConfirm: () => _removeAvatarConfirmed()
    });
}

async function _removeAvatarConfirmed() {
    try {
        const res = await fetch(API + '?action=upload_profile_picture', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: null })
        });
        const result = await res.json();
        if (result.success) {
            profileData.profile_picture = null;
            renderAvatar(document.getElementById('profileAvatar'), profileData.name, null, 100);
            document.getElementById('removeAvatarBtn').style.display = 'none';
            showProfileAlert('Photo removed.', 'success');
        }
    } catch (err) {
        showProfileAlert('Connection error.', 'danger');
    }
}

// ── Google Calendar ──
async function connectGoogleCalendar() {
    try {
        const res = await fetch(API + '?action=google_calendar_connect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        const data = await res.json();
        if (data.url) {
            window.location.href = data.url;
        } else {
            showProfileAlert('Error: ' + (data.error || 'Could not generate auth URL'), 'danger');
        }
    } catch (err) {
        showProfileAlert('Connection error.', 'danger');
    }
}

async function disconnectGoogleCalendar() {
    showConfirm({
        title: 'Disconnect Calendar',
        message: 'Your availability will no longer sync. You can reconnect anytime.',
        icon: '<i class="bi bi-calendar-x text-warning"></i>',
        btnText: 'Disconnect',
        btnClass: 'btn-warning',
        onConfirm: () => _disconnectGcalConfirmed()
    });
}

async function _disconnectGcalConfirmed() {
    try {
        const res = await fetch(API + '?action=google_calendar_disconnect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        const result = await res.json();
        if (result.success) {
            profileData.google_calendar_connected = false;
            profileData.google_calendar_email = null;
            renderGcalStatus(profileData);
            showProfileAlert('Google Calendar disconnected.', 'success');
        } else {
            showProfileAlert('Error disconnecting.', 'danger');
        }
    } catch (err) {
        showProfileAlert('Connection error.', 'danger');
    }
}

// ── Alerts ──
function showProfileAlert(msg, type) {
    const el = document.getElementById('profileAlert');
    el.className = `alert alert-${type} small`;
    el.textContent = msg;
    el.style.display = '';
    setTimeout(() => el.style.display = 'none', 4000);
}

// ── Phone Formatting ──
function formatPhoneInput(input) {
    input.addEventListener('input', function(e) {
        let val = e.target.value.replace(/\D/g, '');
        if (val.length > 10) val = val.slice(0, 10);
        if (val.length >= 7) {
            e.target.value = '(' + val.slice(0, 3) + ') ' + val.slice(3, 6) + '-' + val.slice(6);
        } else if (val.length >= 4) {
            e.target.value = '(' + val.slice(0, 3) + ') ' + val.slice(3);
        } else if (val.length > 0) {
            e.target.value = '(' + val;
        } else {
            e.target.value = '';
        }
    });
}

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
    loadProfile();

    // Set up phone formatting
    const phoneInput = document.getElementById('profilePhone');
    if (phoneInput) formatPhoneInput(phoneInput);

    // Handle ?gcal=connected redirect
    const params = new URLSearchParams(window.location.search);
    if (params.get('gcal') === 'connected') {
        showProfileAlert('Google Calendar connected successfully!', 'success');
        window.history.replaceState({}, '', '/admin/profile.php');
    } else if (params.get('gcal') === 'error') {
        showProfileAlert('Google Calendar connection failed. Please try again.', 'danger');
        window.history.replaceState({}, '', '/admin/profile.php');
    }
});
