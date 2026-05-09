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
        const title = (t.unit ? 'Unit ' + t.unit + ' — ' : '') + (t.lead_name || 'Tour');
        return {
            id: t.id,
            title: title,
            start: t.scheduled_at,
            end: endTime.toISOString(),
            classNames: ['fc-event-' + (t.status || 'confirmed')],
            extendedProps: {
                status: t.status || 'confirmed',
                unit: t.unit || '',
                lead: t.lead_name || 'Unknown',
                broker: t.broker_name || 'Unassigned',
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
            const statusBadge = p.status === 'confirmed'
                ? '<span class="badge bg-success bg-opacity-25 text-success">Confirmed</span>'
                : p.status === 'completed'
                ? '<span class="badge bg-info bg-opacity-25 text-info">Completed</span>'
                : p.status === 'cancelled'
                ? '<span class="badge bg-danger bg-opacity-25 text-danger">Cancelled</span>'
                : p.status === 'no_show'
                ? '<span class="badge bg-warning bg-opacity-25 text-warning">No Show</span>'
                : '<span class="badge bg-secondary">' + esc(p.status) + '</span>';

            const sourceLabel = p.source === 'sms_ai' ? '<span class="badge bg-purple bg-opacity-25 text-purple-300" style="font-size:0.65rem">Booked by AI</span>' : '';

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
                ${p.tourId ? '<div class="mt-3"><select class="form-select form-select-sm bg-dark border-secondary text-white" onchange="updateTourStatus(\'' + esc(p.tourId) + '\', this.value)"><option value="confirmed"' + (p.status === 'confirmed' ? ' selected' : '') + '>Confirmed</option><option value="completed"' + (p.status === 'completed' ? ' selected' : '') + '>Completed</option><option value="cancelled"' + (p.status === 'cancelled' ? ' selected' : '') + '>Cancelled</option><option value="no_show"' + (p.status === 'no_show' ? ' selected' : '') + '>No Show</option></select></div>' : ''}
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
async function updateTourStatus(tourId, status) {
    try {
        await fetch(API + '?action=update_tour_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: tourId, status: status })
        });
        // Refresh
        await loadTourRequests();
        showingsCalendar.removeAllEvents();
        showingsCalendar.addEventSource(tourRequestsToEvents(tourRequests));
        updateCalendarStats();
        renderUpcomingShowings();
    } catch (err) {
        console.error('Error updating tour:', err);
    }
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
    if (availabilityEventSource) {
        availabilityEventSource.remove();
        availabilityEventSource = null;
    }

    const events = [];
    let brokerIndex = 0;

    for (const [brokerId, info] of Object.entries(brokersData)) {
        const checkbox = document.querySelector(`.broker-avail-check[value="${brokerId}"]`);
        const color = checkbox ? checkbox.dataset.color : BROKER_COLORS[brokerIndex % BROKER_COLORS.length];

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

    if (events.length > 0) {
        availabilityEventSource = showingsCalendar.addEventSource(events);
    }
}

// ── Stats & Upcoming ──
function updateCalendarStats() {
    const today = new Date().toISOString().split('T')[0];
    const weekEnd = new Date();
    weekEnd.setDate(weekEnd.getDate() + 7);
    const weekEndStr = weekEnd.toISOString().split('T')[0];

    const active = tourRequests.filter(t => t.status !== 'cancelled' && t.status !== 'no_show');
    const confirmed = active.filter(t => t.status === 'confirmed').length;
    const completed = active.filter(t => t.status === 'completed').length;
    const todayCount = active.filter(t => t.scheduled_at && t.scheduled_at.startsWith(today)).length;
    const weekCount = active.filter(t => {
        const d = t.scheduled_at ? t.scheduled_at.split('T')[0] : '';
        return d >= today && d <= weekEndStr;
    }).length;

    document.getElementById('calStatPending').textContent = confirmed;
    document.getElementById('calStatConfirmed').textContent = completed;
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

document.addEventListener('DOMContentLoaded', initShowingsCalendar);
