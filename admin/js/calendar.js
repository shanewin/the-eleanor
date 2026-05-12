// ── Showings Calendar ──
let showingsCalendar = null;
let availabilityEventSource = null;
let connectedBrokers = [];
let selectedBrokerIds = [];
let tourRequests = [];

const BROKER_COLORS = ['#6366f1', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];

// ── Load Tour Requests from Supabase ──
async function loadTourRequests() {
    try {
        const res = await fetch(API + '?action=get_tour_requests');
        const data = await res.json();
        tourRequests = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Error loading tour requests:', err);
        tourRequests = [];
    }
}

function tourRequestsToEvents(requests) {
    return requests.map(t => {
        const endTime = new Date(new Date(t.scheduled_at).getTime() + (t.duration_minutes || 30) * 60000);
        const status = t.status || 'confirmed';
        const prefix = status === 'pending' ? '⏳ ' : '';
        const title = prefix + (t.unit ? 'Unit ' + t.unit + ' — ' : '') + (t.lead_name || 'Tour');
        return {
            id: t.id,
            title: title,
            start: t.scheduled_at,
            end: endTime.toISOString(),
            classNames: ['fc-event-' + status],
            extendedProps: {
                status: status,
                unit: t.unit || '',
                lead: t.lead_name || 'Unknown',
                broker: t.broker_name || 'Unassigned',
                brokerId: t.broker_id || null,
                source: t.source || '',
                notes: t.notes || '',
                tourId: t.id
            }
        };
    });
}

// ── Init Calendar ──
async function initShowingsCalendar() {
    if (showingsCalendar) {
        showingsCalendar.render();
        return;
    }

    await loadTourRequests();

    const calEl = document.getElementById('showingsCalendar');
    showingsCalendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        height: 'auto',
        nowIndicator: true,
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
        events: tourRequestsToEvents(tourRequests),
        eventClick: function(info) {
            if (info.event.display === 'background') return;

            const p = info.event.extendedProps;
            const time = info.event.start.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
            const statusBadge = p.status === 'pending'
                ? '<span class="badge bg-warning bg-opacity-25 text-warning">Pending</span>'
                : p.status === 'confirmed'
                ? '<span class="badge bg-success bg-opacity-25 text-success">Confirmed</span>'
                : p.status === 'completed'
                ? '<span class="badge bg-info bg-opacity-25 text-info">Completed</span>'
                : p.status === 'cancelled'
                ? '<span class="badge bg-danger bg-opacity-25 text-danger">Cancelled</span>'
                : p.status === 'no_show'
                ? '<span class="badge bg-warning bg-opacity-25 text-warning">No Show</span>'
                : '<span class="badge bg-secondary">' + esc(p.status) + '</span>';

            const sourceLabel = p.source === 'sms_ai'
                ? '<span class="badge bg-purple bg-opacity-25 text-purple-300" style="font-size:0.65rem">Booked by AI</span>'
                : p.source === 'public_form'
                ? '<span class="badge bg-info bg-opacity-25 text-info" style="font-size:0.65rem">Booked online</span>'
                : '';

            // Build broker options for the assign dropdown (pending flow)
            const allBrokers = (typeof brokersCache !== 'undefined' && Array.isArray(brokersCache) && brokersCache.length)
                ? brokersCache
                : (Array.isArray(connectedBrokers) ? connectedBrokers : []);
            const brokerOptions = allBrokers
                .map(b => '<option value="' + b.id + '"' + (String(p.brokerId) === String(b.id) ? ' selected' : '') + '>' + esc(b.name) + '</option>')
                .join('');

            const pendingControls = p.status === 'pending' && p.tourId ? `
                <div class="mt-3 p-3" style="background:rgba(255,255,255,0.04); border-radius:6px">
                    <div class="small text-white-50 mb-2">Approve this request</div>
                    <select class="form-select form-select-sm bg-dark border-secondary text-white mb-2" id="approveBrokerSel-${esc(p.tourId)}">
                        <option value="">Assign a broker…</option>
                        ${brokerOptions}
                    </select>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm flex-grow-1" onclick="approveTour('${esc(p.tourId)}')">
                            <i class="bi bi-check-lg me-1"></i>Approve &amp; Send Confirmation
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="updateTourStatus('${esc(p.tourId)}', 'cancelled')">Decline</button>
                    </div>
                </div>
            ` : '';

            const statusControls = p.status !== 'pending' && p.tourId ? `
                <div class="mt-3"><select class="form-select form-select-sm bg-dark border-secondary text-white" onchange="updateTourStatus('${esc(p.tourId)}', this.value)">
                    <option value="confirmed"${p.status === 'confirmed' ? ' selected' : ''}>Confirmed</option>
                    <option value="completed"${p.status === 'completed' ? ' selected' : ''}>Completed</option>
                    <option value="cancelled"${p.status === 'cancelled' ? ' selected' : ''}>Cancelled</option>
                    <option value="no_show"${p.status === 'no_show' ? ' selected' : ''}>No Show</option>
                </select></div>
            ` : '';

            const isOwner = (typeof USER_ROLE !== 'undefined') && USER_ROLE === 'owner';
            const deleteControl = isOwner && p.tourId ? `
                <div class="mt-3 pt-3 border-top border-secondary">
                    <button class="btn btn-outline-danger btn-sm w-100" onclick="deleteTour('${esc(p.tourId)}', '${esc(p.lead || 'this lead')}')">
                        <i class="bi bi-trash me-1"></i>Delete Tour
                    </button>
                </div>
            ` : '';

            document.getElementById('showingDetailBody').innerHTML = `
                <div class="mb-3">${statusBadge} ${sourceLabel}</div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="small text-white-50">Unit</div>
                        <div class="fw-semibold">${esc(p.unit || 'Not specified')}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-white-50">Applicant</div>
                        <div class="fw-semibold">${esc(p.lead)}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-white-50">Date & Time</div>
                        <div class="fw-semibold">${time}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-white-50">Broker</div>
                        <div class="fw-semibold">${esc(p.broker)}</div>
                    </div>
                </div>
                ${p.notes ? '<div class="mt-3"><div class="small text-white-50">Notes</div><div class="text-white-50 small">' + esc(p.notes) + '</div></div>' : ''}
                ${pendingControls}
                ${statusControls}
                ${deleteControl}
            `;
            new bootstrap.Modal(document.getElementById('showingDetailModal')).show();
        },
        datesSet: function(info) {
            if (selectedBrokerIds.length > 0) {
                loadBrokerAvailability();
            }
        },
        dateClick: function(info) {
            openNewShowingModal(info.dateStr);
        }
    });

    showingsCalendar.render();
    updateCalendarStats();
    renderUpcomingShowings();
    initBrokerToggles();
}

// ── Update Tour Status ──
async function updateTourStatus(tourId, status, brokerId) {
    try {
        const payload = { id: tourId, status: status };
        if (brokerId !== undefined) payload.broker_id = brokerId || null;
        await fetch(API + '?action=update_tour_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        // Refresh
        await loadTourRequests();
        showingsCalendar.removeAllEvents();
        showingsCalendar.addEventSource(tourRequestsToEvents(tourRequests));
        updateCalendarStats();
        renderUpcomingShowings();

        const modalEl = document.getElementById('showingDetailModal');
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    } catch (err) {
        console.error('Error updating tour:', err);
    }
}

// Owner-only: permanently delete a tour. Confirmation is mandatory because
// this destroys the lead's follow-up trail (no reschedule, no SMS history
// linkage on that tour row).
function deleteTour(tourId, leadLabel) {
    const html = `
        <div class="modal fade" id="deleteTourConfirm" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete this tour?</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">You're about to permanently delete the tour for <strong>${esc(leadLabel)}</strong>.</p>
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>This cannot be undone.</strong> Deleting the tour removes the ability to reschedule or follow up with this lead through the calendar. Their lead record stays, but the tour-specific history (broker assignment, notes, calendar event link) is lost.
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Keep tour</button>
                        <button type="button" class="btn btn-danger btn-sm" id="deleteTourConfirmBtn">
                            <i class="bi bi-trash me-1"></i>Delete permanently
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    // Remove any prior instance, then inject and show
    const prior = document.getElementById('deleteTourConfirm');
    if (prior) prior.remove();
    document.body.insertAdjacentHTML('beforeend', html);

    const modalEl = document.getElementById('deleteTourConfirm');
    const modal = new bootstrap.Modal(modalEl);
    modalEl.addEventListener('hidden.bs.modal', () => modalEl.remove());

    document.getElementById('deleteTourConfirmBtn').addEventListener('click', async () => {
        const btn = document.getElementById('deleteTourConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';
        try {
            const res = await fetch(API + '?action=delete_tour_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: tourId })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                alert('Could not delete: ' + (data.error || 'Unknown error'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete permanently';
                return;
            }
            // Close both modals + refresh
            modal.hide();
            const detail = bootstrap.Modal.getInstance(document.getElementById('showingDetailModal'));
            if (detail) detail.hide();
            await loadTourRequests();
            showingsCalendar.removeAllEvents();
            showingsCalendar.addEventSource(tourRequestsToEvents(tourRequests));
            updateCalendarStats();
            renderUpcomingShowings();
        } catch (err) {
            console.error('Delete tour failed:', err);
            alert('Network error while deleting.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete permanently';
        }
    });

    modal.show();
}

// Approve a pending tour: requires a broker selection, then flips status to
// 'confirmed'. Backend (updateTourRequest) sends the confirmation SMS and
// creates the Google Calendar event if the broker is connected.
async function approveTour(tourId) {
    const sel = document.getElementById('approveBrokerSel-' + tourId);
    const brokerId = sel ? sel.value : '';
    if (!brokerId) {
        if (sel) {
            sel.classList.add('is-invalid');
            sel.focus();
        }
        alert('Please assign a broker before approving.');
        return;
    }
    await updateTourStatus(tourId, 'confirmed', brokerId);
}

// ── Broker Availability Toggles ──
async function initBrokerToggles() {
    try {
        const res = await fetch(API + '?action=google_calendar_status');
        const brokers = await res.json();
        connectedBrokers = Array.isArray(brokers) ? brokers : [];
    } catch (err) {
        connectedBrokers = [];
    }

    const container = document.getElementById('brokerAvailToggles');

    if (connectedBrokers.length === 0) {
        container.innerHTML = '<span class="small text-white-50">No brokers configured</span>';
        return;
    }

    container.innerHTML = connectedBrokers.map((b, i) => {
        const color = BROKER_COLORS[i % BROKER_COLORS.length];
        const connected = b.google_calendar_connected;
        const tooltip = connected ? 'Google Calendar connected' : 'Using default availability';
        const icon = connected ? 'bi-calendar-check' : 'bi-calendar';
        return `
            <label class="btn btn-sm btn-outline-secondary broker-avail-toggle" title="${esc(tooltip)}" style="--toggle-color:${color}">
                <input type="checkbox" class="d-none broker-avail-check" value="${b.id}" data-color="${color}">
                <i class="${icon} me-1" style="color:${color}"></i>${esc(b.name)}
            </label>
        `;
    }).join('');

    container.querySelectorAll('.broker-avail-check').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.broker-avail-toggle').classList.toggle('active', cb.checked);
            updateSelectedBrokers();
            loadBrokerAvailability();
        });
    });

    // Auto-enable every broker that has Google Calendar connected so their busy
    // times render immediately. Without this, the toggles are all off by default
    // and the calendar looks empty, which has led to double-bookings.
    const connectedIds = new Set(connectedBrokers.filter(b => b.google_calendar_connected).map(b => String(b.id)));
    if (connectedIds.size > 0) {
        container.querySelectorAll('.broker-avail-check').forEach(cb => {
            if (connectedIds.has(cb.value)) {
                cb.checked = true;
                cb.closest('.broker-avail-toggle').classList.add('active');
            }
        });
        updateSelectedBrokers();
        loadBrokerAvailability();
    }
}

function updateSelectedBrokers() {
    selectedBrokerIds = [];
    document.querySelectorAll('.broker-avail-check:checked').forEach(cb => {
        selectedBrokerIds.push(parseInt(cb.value));
    });
}

async function loadBrokerAvailability() {
    if (!showingsCalendar || selectedBrokerIds.length === 0) {
        if (availabilityEventSource) {
            availabilityEventSource.remove();
            availabilityEventSource = null;
        }
        return;
    }

    const view = showingsCalendar.view;
    const timeMin = view.activeStart.toISOString();
    const timeMax = view.activeEnd.toISOString();

    try {
        const res = await fetch(API + '?action=google_calendar_availability', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ broker_ids: selectedBrokerIds, time_min: timeMin, time_max: timeMax })
        });
        const data = await res.json();
        if (data.error) return;
        renderAvailabilityEvents(data.brokers || {});
    } catch (err) {
        console.error('Error loading availability:', err);
    }
}

function renderAvailabilityEvents(brokersData) {
    console.log('[availability] render called with:', brokersData);

    if (availabilityEventSource) {
        availabilityEventSource.remove();
        availabilityEventSource = null;
    }

    const events = [];
    let brokerIndex = 0;

    for (const [brokerId, info] of Object.entries(brokersData)) {
        const checkbox = document.querySelector(`.broker-avail-check[value="${brokerId}"]`);
        const color = checkbox ? checkbox.dataset.color : BROKER_COLORS[brokerIndex % BROKER_COLORS.length];

        console.log('[availability] broker', brokerId, info.name, 'connected:', info.connected, 'busy count:', info.busy ? info.busy.length : 0, 'color:', color);

        if (info.connected && info.busy) {
            info.busy.forEach(block => {
                events.push({
                    title: esc(info.name) + ' — Busy',
                    start: block.start,
                    end: block.end,
                    display: 'background',
                    backgroundColor: color,
                    borderColor: color,
                    extendedProps: { type: 'busy', broker: info.name }
                });
            });
        }
        brokerIndex++;
    }

    console.log('[availability] events to add:', events.length, 'sample:', events[0]);

    if (events.length > 0) {
        availabilityEventSource = showingsCalendar.addEventSource(events);
        const allEvents = showingsCalendar.getEvents();
        const bgEvents = allEvents.filter(e => e.display === 'background');
        console.log('[availability] post-add: total events =', allEvents.length, 'background events =', bgEvents.length);
    }
}

// ── Stats & Upcoming ──
function updateCalendarStats() {
    const today = new Date().toISOString().split('T')[0];
    const weekEnd = new Date();
    weekEnd.setDate(weekEnd.getDate() + 7);
    const weekEndStr = weekEnd.toISOString().split('T')[0];

    const active = tourRequests.filter(t => t.status !== 'cancelled' && t.status !== 'no_show');
    const pending = active.filter(t => t.status === 'pending').length;
    const confirmed = active.filter(t => t.status === 'confirmed').length;
    const todayCount = active.filter(t => t.scheduled_at && t.scheduled_at.startsWith(today)).length;
    const weekCount = active.filter(t => {
        const d = t.scheduled_at ? t.scheduled_at.split('T')[0] : '';
        return d >= today && d <= weekEndStr;
    }).length;

    document.getElementById('calStatPending').textContent = pending;
    document.getElementById('calStatConfirmed').textContent = confirmed;
    document.getElementById('calStatToday').textContent = todayCount;
    document.getElementById('calStatThisWeek').textContent = weekCount;
}

function renderUpcomingShowings() {
    const today = new Date().toISOString().split('T')[0];
    const upcoming = tourRequests
        .filter(t => t.scheduled_at && t.scheduled_at.split('T')[0] >= today && t.status !== 'cancelled')
        .sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at));

    const container = document.getElementById('upcomingShowingsList');
    if (upcoming.length === 0) {
        container.innerHTML = '<div class="text-white-50 small text-center py-4">No upcoming showings</div>';
        return;
    }

    container.innerHTML = upcoming.map(t => {
        const dt = new Date(t.scheduled_at);
        const time = dt.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        const statusClass = t.status === 'confirmed' ? 'confirmed' : t.status === 'completed' ? 'confirmed' : 'pending';
        const badgeClass = t.status === 'confirmed' ? 'bg-success bg-opacity-25 text-success' : 'bg-info bg-opacity-25 text-info';
        return `
            <div class="showing-item">
                <div class="showing-dot ${statusClass}"></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small">${esc(t.lead_name || 'Unknown')} ${t.unit ? '— Unit ' + esc(t.unit) : ''}</div>
                    <div class="text-white-50" style="font-size:0.75rem">${time} &middot; ${esc(t.broker_name || 'Unassigned')}</div>
                </div>
                <span class="badge ${badgeClass}" style="font-size:0.7rem">${esc(t.status)}</span>
            </div>
        `;
    }).join('');
}

function openNewShowingModal(dateStr) {
    // Clear form
    document.getElementById('newShowingEmail').value = '';
    document.getElementById('newShowingPhone').value = '';
    document.getElementById('newShowingDate').value = dateStr || '';
    document.getElementById('newShowingTime').value = '10:00';
    document.getElementById('newShowingUnit').value = '';
    document.getElementById('newShowingNotes').value = '';
    const alert = document.getElementById('newShowingAlert');
    if (alert) alert.style.display = 'none';

    // Populate broker dropdown
    const brokerSelect = document.getElementById('newShowingBroker');
    brokerSelect.innerHTML = '<option value="">Unassigned</option>';
    if (connectedBrokers.length > 0) {
        connectedBrokers.forEach(b => {
            brokerSelect.innerHTML += '<option value="' + b.id + '">' + esc(b.name) + '</option>';
        });
    } else {
        fetchBrokers(function(brokers) {
            brokers.forEach(b => {
                brokerSelect.innerHTML += '<option value="' + b.id + '">' + esc(b.name) + '</option>';
            });
        });
    }

    // Phone formatting
    const phoneInput = document.getElementById('newShowingPhone');
    if (!phoneInput._formatted) {
        phoneInput.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, '');
            if (val.length > 10) val = val.slice(0, 10);
            if (val.length >= 7) e.target.value = '(' + val.slice(0, 3) + ') ' + val.slice(3, 6) + '-' + val.slice(6);
            else if (val.length >= 4) e.target.value = '(' + val.slice(0, 3) + ') ' + val.slice(3);
            else if (val.length > 0) e.target.value = '(' + val;
            else e.target.value = '';
        });
        phoneInput._formatted = true;
    }

    new bootstrap.Modal(document.getElementById('newShowingModal')).show();
}

async function saveNewShowing() {
    const date = document.getElementById('newShowingDate').value;
    const time = document.getElementById('newShowingTime').value;
    const alertEl = document.getElementById('newShowingAlert');

    if (!date || !time) {
        alertEl.className = 'alert alert-danger small';
        alertEl.textContent = 'Date and time are required.';
        alertEl.style.display = '';
        return;
    }

    const scheduledAt = date + 'T' + time + ':00';
    const payload = {
        lead_email: document.getElementById('newShowingEmail').value.trim(),
        lead_phone: document.getElementById('newShowingPhone').value.trim(),
        unit: document.getElementById('newShowingUnit').value.trim(),
        broker_id: document.getElementById('newShowingBroker').value || null,
        scheduled_at: scheduledAt,
        notes: document.getElementById('newShowingNotes').value.trim()
    };

    const btn = document.querySelector('#newShowingModal .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scheduling...';

    try {
        const res = await fetch(API + '?action=add_tour_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('newShowingModal')).hide();
            // Refresh calendar
            await loadTourRequests();
            showingsCalendar.removeAllEvents();
            showingsCalendar.addEventSource(tourRequestsToEvents(tourRequests));
            updateCalendarStats();
            renderUpcomingShowings();
        } else {
            alertEl.className = 'alert alert-danger small';
            alertEl.textContent = 'Error: ' + (result.error || 'Unknown');
            alertEl.style.display = '';
        }
    } catch(err) {
        alertEl.className = 'alert alert-danger small';
        alertEl.textContent = 'Connection error.';
        alertEl.style.display = '';
    }

    btn.disabled = false;
    btn.innerHTML = 'Schedule Tour';
}

document.addEventListener('DOMContentLoaded', () => {
    // Mark calendar as viewed so the sidebar badge clears.
    try { localStorage.setItem('calendar_last_viewed', new Date().toISOString()); } catch(e) {}
    initShowingsCalendar();
    if (typeof updateSidebarCalendarBadge === 'function') updateSidebarCalendarBadge();
});
