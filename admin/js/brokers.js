// Brokers page JavaScript

// ── Phone Number Formatting ──
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

// ── Status Logic ──
function getBrokerStatusBadge(broker) {
    if (broker.is_active === false || broker.status === 'inactive') {
        return '<span class="badge bg-secondary">Inactive</span>';
    }
    // If they have never logged in, they're pending invite confirmation
    if (!broker.last_login_at) {
        return '<span class="badge bg-warning bg-opacity-25 text-warning">Pending</span>';
    }
    return '<span class="badge bg-success">Active</span>';
}

// ── Availability summary ──
const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function formatHourLabel(hhmm) {
    if (!hhmm) return '';
    const [h, m] = hhmm.split(':').map(Number);
    const ampm = h >= 12 ? 'pm' : 'am';
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return m ? `${h12}:${String(m).padStart(2, '0')}${ampm}` : `${h12}${ampm}`;
}

function getBrokerDays(broker) {
    let days = broker.default_availability_days;
    if (typeof days === 'string') {
        try { days = JSON.parse(days); } catch (_) { days = []; }
    }
    return Array.isArray(days) ? days.map(Number).sort((a, b) => a - b) : [];
}

function summarizeAvailability(broker) {
    const days = getBrokerDays(broker);
    if (days.length === 0) return '<span class="text-white-50">No days set</span>';

    // Detect a contiguous run for a compact label like "Mon–Fri"
    let label;
    const isRun = days.every((d, i) => i === 0 || d === days[i - 1] + 1);
    if (isRun && days.length >= 2) {
        label = `${DAY_NAMES[days[0]]}–${DAY_NAMES[days[days.length - 1]]}`;
    } else {
        label = days.map(d => DAY_NAMES[d]).join(', ');
    }

    const start = formatHourLabel(broker.default_availability_start);
    const end = formatHourLabel(broker.default_availability_end);
    const hours = (start && end) ? `${start}–${end}` : '';
    return `<div class="small">${esc(label)}</div>` +
           (hours ? `<div class="small text-white-50" style="font-size:0.7rem">${esc(hours)}</div>` : '');
}

// ── Render Table ──
function renderBrokersTable(brokers) {
    const tbody = document.querySelector('#brokersTable tbody');
    if (!tbody) return;

    if (brokers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-body-tertiary py-5">No brokers added yet.</td></tr>';
        return;
    }
    tbody.innerHTML = '';
    brokers.forEach(function(broker) {
        const statusBadge = getBrokerStatusBadge(broker);
        const row = document.createElement('tr');
        const gcalCell = broker.google_calendar_connected
            ? '<span class="badge bg-success bg-opacity-25 text-success">Connected</span>'
              + '<div class="small text-white-50 mt-1" style="font-size:0.7rem">' + esc(broker.google_calendar_email || '') + '</div>'
            : '<span class="badge bg-secondary bg-opacity-50 text-white-50">Not Connected</span>';

        row.innerHTML = '<td class="fw-semibold text-white">' + esc(broker.name) + '</td>'
            + '<td>' + esc(broker.email) + '</td>'
            + '<td>' + esc(broker.phone || '-') + '</td>'
            + '<td><span class="text-capitalize">' + esc(broker.role || 'broker') + '</span></td>'
            + '<td>' + statusBadge + '</td>'
            + '<td>' + gcalCell + '</td>'
            + '<td>' + summarizeAvailability(broker) + '</td>'
            + '<td class="text-end">'
            + '<button class="btn btn-sm btn-outline-primary me-1" onclick="showBrokerModal(' + esc(String(broker.id)) + ')"><i class="bi bi-pencil"></i></button>'
            + '<button class="btn btn-sm btn-outline-danger" onclick="deleteBroker(' + esc(String(broker.id)) + ')"><i class="bi bi-trash"></i></button>'
            + '</td>';
        tbody.appendChild(row);
    });
}

// ── Parse Name into First/Last ──
function splitName(fullName) {
    const parts = (fullName || '').trim().split(/\s+/);
    if (parts.length >= 2) {
        return { first: parts[0], last: parts.slice(1).join(' ') };
    }
    return { first: parts[0] || '', last: '' };
}

// ── Show Modal ──
function showBrokerModal(brokerId) {
    const modal = new bootstrap.Modal(document.getElementById('brokerModal'));
    document.getElementById('brokerEditId').value = '';
    document.getElementById('brokerFirstName').value = '';
    document.getElementById('brokerLastName').value = '';
    document.getElementById('brokerEmail').value = '';
    document.getElementById('brokerPhone').value = '';
    document.getElementById('brokerRole').value = 'broker';
    document.getElementById('brokerModalTitle').textContent = 'Add Broker';

    const inviteNote = document.getElementById('brokerInviteNote');
    const alertEl = document.getElementById('brokerSaveAlert');
    if (alertEl) alertEl.style.display = 'none';
    const emailInput = document.getElementById('brokerEmail');
    const availSection = document.getElementById('brokerAvailSection');

    if (brokerId) {
        const broker = brokersCache.find(b => b.id == brokerId);
        if (broker) {
            const { first, last } = splitName(broker.name);
            document.getElementById('brokerEditId').value = broker.id;
            document.getElementById('brokerFirstName').value = first;
            document.getElementById('brokerLastName').value = last;
            document.getElementById('brokerEmail').value = broker.email || '';
            document.getElementById('brokerPhone').value = broker.phone || '';
            document.getElementById('brokerRole').value = broker.role || 'broker';
            document.getElementById('brokerModalTitle').textContent = 'Edit Broker';
            emailInput.readOnly = true;

            // Populate availability
            const startVal = (broker.default_availability_start || '09:00:00').slice(0, 5);
            const endVal = (broker.default_availability_end || '18:00:00').slice(0, 5);
            document.getElementById('brokerAvailStart').value = startVal;
            document.getElementById('brokerAvailEnd').value = endVal;
            const days = getBrokerDays(broker);
            document.querySelectorAll('.broker-avail-day-check').forEach(cb => {
                cb.checked = days.includes(parseInt(cb.value));
                cb.closest('.broker-avail-day-btn').classList.toggle('active', cb.checked);
            });
            if (availSection) availSection.style.display = '';
        }
        if (inviteNote) inviteNote.style.display = 'none';
    } else {
        emailInput.readOnly = false;
        if (inviteNote) inviteNote.style.display = 'block';
        if (availSection) availSection.style.display = 'none';
    }
    modal.show();
}

// Day toggle wiring (idempotent — guards repeat clicks)
function wireBrokerDayToggles() {
    document.querySelectorAll('.broker-avail-day-btn').forEach(btn => {
        if (btn._wired) return;
        const cb = btn.querySelector('.broker-avail-day-check');
        btn.addEventListener('click', (e) => {
            // Bootstrap label-with-hidden-checkbox quirk: clicking the label fires
            // both the label click and the implicit checkbox toggle. Cancel the
            // implicit one so we control state explicitly.
            e.preventDefault();
            cb.checked = !cb.checked;
            btn.classList.toggle('active', cb.checked);
        });
        btn._wired = true;
    });
}

// ── Save Broker ──
async function saveBroker() {
    const id = document.getElementById('brokerEditId').value;
    const firstName = document.getElementById('brokerFirstName').value.trim();
    const lastName = document.getElementById('brokerLastName').value.trim();
    const email = document.getElementById('brokerEmail').value.trim();
    const phone = document.getElementById('brokerPhone').value.trim();
    const role = document.getElementById('brokerRole').value;

    if (!firstName || !lastName) {
        showBrokerAlert('First name and last name are required.', 'danger');
        return;
    }
    if (!email) {
        showBrokerAlert('Email is required.', 'danger');
        return;
    }
    if (!role) {
        showBrokerAlert('Role is required.', 'danger');
        return;
    }

    const payload = {
        name: firstName + ' ' + lastName,
        email: email,
        phone: phone || null,
        role: role
    };

    // Include availability when editing (the section only renders for edits)
    if (id) {
        const activeDays = [];
        document.querySelectorAll('.broker-avail-day-check:checked').forEach(cb => activeDays.push(parseInt(cb.value)));
        payload.default_availability_start = document.getElementById('brokerAvailStart').value;
        payload.default_availability_end = document.getElementById('brokerAvailEnd').value;
        payload.default_availability_days = activeDays;
    }

    const saveBtn = document.querySelector('#brokerModal .btn-primary');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    try {
        const action = id ? 'update_broker' : 'add_broker';
        if (id) payload.id = id;
        const res = await fetch('/api/admin-api.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            if (!id && result.auth_created) {
                // Show success message for new broker invites
                showBrokerAlert('Invite email sent to ' + esc(email) + '. They will need to verify their email and set a password.', 'success');
                // Refresh table in background
                fetchBrokers(renderBrokersTable);
                // Auto-close modal after a moment
                setTimeout(() => {
                    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('brokerModal'));
                    if (modalInstance) modalInstance.hide();
                }, 3000);
            } else {
                bootstrap.Modal.getInstance(document.getElementById('brokerModal')).hide();
                fetchBrokers(renderBrokersTable);
            }
        } else {
            showBrokerAlert('Error: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showBrokerAlert('Connection error while saving broker.', 'danger');
    }

    saveBtn.disabled = false;
    saveBtn.innerHTML = 'Save';
}

function showBrokerAlert(msg, type) {
    const el = document.getElementById('brokerSaveAlert');
    if (!el) return;
    el.className = `alert alert-${type} small mt-3`;
    el.innerHTML = msg;
    el.style.display = '';
}

// ── Delete Broker ──
async function deleteBroker(id) {
    showConfirm({
        title: 'Delete Broker',
        message: 'This will remove this broker and unassign all their leads. This cannot be undone.',
        icon: '<i class="bi bi-person-x text-danger"></i>',
        btnText: 'Delete Broker',
        onConfirm: async () => { await _deleteBrokerConfirmed(id); }
    });
}

async function _deleteBrokerConfirmed(id) {
    try {
        const res = await fetch('/api/admin-api.php?action=delete_broker', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        if (result.success) {
            fetchBrokers(renderBrokersTable);
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while deleting broker.');
    }
}

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
    fetchBrokers(renderBrokersTable);
    // Set up phone formatting
    const phoneInput = document.getElementById('brokerPhone');
    if (phoneInput) formatPhoneInput(phoneInput);
    wireBrokerDayToggles();
});
