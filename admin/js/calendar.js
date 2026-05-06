// ── Showings Calendar ──
let showingsCalendar = null;

// Sample data for UI preview — will be replaced with Supabase data later
const sampleShowings = [
    { id: 1, title: 'Unit 4B — Sarah Chen', start: new Date().toISOString().split('T')[0] + 'T10:00:00', end: new Date().toISOString().split('T')[0] + 'T10:30:00', status: 'confirmed', unit: '4B', lead: 'Sarah Chen', broker: 'James Rivera' },
    { id: 2, title: 'Unit 2A — Michael Torres', start: (() => { const d = new Date(); d.setDate(d.getDate() + 1); return d.toISOString().split('T')[0]; })() + 'T14:00:00', end: (() => { const d = new Date(); d.setDate(d.getDate() + 1); return d.toISOString().split('T')[0]; })() + 'T14:30:00', status: 'pending', unit: '2A', lead: 'Michael Torres', broker: 'Unassigned' },
    { id: 3, title: 'Unit 6C — Priya Patel', start: (() => { const d = new Date(); d.setDate(d.getDate() + 2); return d.toISOString().split('T')[0]; })() + 'T11:00:00', end: (() => { const d = new Date(); d.setDate(d.getDate() + 2); return d.toISOString().split('T')[0]; })() + 'T11:30:00', status: 'confirmed', unit: '6C', lead: 'Priya Patel', broker: 'James Rivera' },
    { id: 4, title: 'Unit 3A — David Kim', start: (() => { const d = new Date(); d.setDate(d.getDate() + 3); return d.toISOString().split('T')[0]; })() + 'T16:00:00', end: (() => { const d = new Date(); d.setDate(d.getDate() + 3); return d.toISOString().split('T')[0]; })() + 'T16:30:00', status: 'pending', unit: '3A', lead: 'David Kim', broker: 'Unassigned' },
    { id: 5, title: 'Unit 5D — Emma Wilson', start: (() => { const d = new Date(); d.setDate(d.getDate() - 1); return d.toISOString().split('T')[0]; })() + 'T09:00:00', end: (() => { const d = new Date(); d.setDate(d.getDate() - 1); return d.toISOString().split('T')[0]; })() + 'T09:30:00', status: 'cancelled', unit: '5D', lead: 'Emma Wilson', broker: 'James Rivera' },
];

function initShowingsCalendar() {
    if (showingsCalendar) {
        showingsCalendar.render();
        return;
    }

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
        events: sampleShowings.map(s => ({
            id: s.id,
            title: s.title,
            start: s.start,
            end: s.end,
            classNames: ['fc-event-' + s.status],
            extendedProps: { status: s.status, unit: s.unit, lead: s.lead, broker: s.broker }
        })),
        eventClick: function(info) {
            const p = info.event.extendedProps;
            const time = info.event.start.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
            const statusBadge = p.status === 'confirmed'
                ? '<span class="badge bg-success bg-opacity-25 text-success">Confirmed</span>'
                : p.status === 'pending'
                ? '<span class="badge bg-warning bg-opacity-25 text-warning">Pending</span>'
                : '<span class="badge bg-danger bg-opacity-25 text-danger">Cancelled</span>';

            document.getElementById('showingDetailBody').innerHTML = `
                <div class="mb-3">${statusBadge}</div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="small text-white-50">Unit</div>
                        <div class="fw-semibold">${esc(p.unit)}</div>
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
            `;
            new bootstrap.Modal(document.getElementById('showingDetailModal')).show();
        },
        dateClick: function(info) {
            // Future: open new showing form with date pre-filled
        }
    });

    showingsCalendar.render();
    updateCalendarStats();
    renderUpcomingShowings();
}

function updateCalendarStats() {
    const today = new Date().toISOString().split('T')[0];
    const weekEnd = new Date();
    weekEnd.setDate(weekEnd.getDate() + 7);
    const weekEndStr = weekEnd.toISOString().split('T')[0];

    const pending = sampleShowings.filter(s => s.status === 'pending').length;
    const confirmed = sampleShowings.filter(s => s.status === 'confirmed').length;
    const todayCount = sampleShowings.filter(s => s.start.startsWith(today) && s.status !== 'cancelled').length;
    const weekCount = sampleShowings.filter(s => s.start.split('T')[0] >= today && s.start.split('T')[0] <= weekEndStr && s.status !== 'cancelled').length;

    document.getElementById('calStatPending').textContent = pending;
    document.getElementById('calStatConfirmed').textContent = confirmed;
    document.getElementById('calStatToday').textContent = todayCount;
    document.getElementById('calStatThisWeek').textContent = weekCount;
}

function renderUpcomingShowings() {
    const today = new Date().toISOString().split('T')[0];
    const upcoming = sampleShowings
        .filter(s => s.start.split('T')[0] >= today && s.status !== 'cancelled')
        .sort((a, b) => a.start.localeCompare(b.start));

    const container = document.getElementById('upcomingShowingsList');
    if (upcoming.length === 0) {
        container.innerHTML = '<div class="text-white-50 small text-center py-4">No upcoming showings</div>';
        return;
    }

    container.innerHTML = upcoming.map(s => {
        const dt = new Date(s.start);
        const time = dt.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        return `
            <div class="showing-item">
                <div class="showing-dot ${s.status}"></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small">${esc(s.lead)} — Unit ${esc(s.unit)}</div>
                    <div class="text-white-50" style="font-size:0.75rem">${time} &middot; ${esc(s.broker)}</div>
                </div>
                <span class="badge ${s.status === 'confirmed' ? 'bg-success bg-opacity-25 text-success' : 'bg-warning bg-opacity-25 text-warning'}" style="font-size:0.7rem">${s.status}</span>
            </div>
        `;
    }).join('');
}

function openNewShowingModal() {
    // Placeholder — will wire up to new showing form later
    alert('New Showing form coming soon — will connect to tour_requests table.');
}

document.addEventListener('DOMContentLoaded', initShowingsCalendar);
