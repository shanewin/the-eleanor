// ── Unified Communications CRM ──

let currentTimelineData = null;

// ── Pipeline View ──
async function loadCommPipeline() {
    const tbody = document.querySelector('#commPipelineTable tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>';

    try {
        const res = await fetch(API + '?action=leads');
        const leads = await res.json();
        if (!Array.isArray(leads) || leads.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-body-tertiary py-5">No leads yet.</td></tr>';
            return;
        }

        // Fetch SMS conversations to enrich pipeline
        let smsConvos = {};
        try {
            const smsRes = await fetch(API + '?action=sms_conversations');
            const smsData = await smsRes.json();
            if (Array.isArray(smsData)) {
                smsData.forEach(c => {
                    if (c.lead_email) smsConvos[c.lead_email.toLowerCase()] = c;
                });
            }
        } catch(e) {}

        tbody.innerHTML = '';
        leads.forEach(lead => {
            const email = (lead.email || '').toLowerCase();
            const sms = smsConvos[email];
            const status = lead.lead_status || 'New';
            const statusColors = {
                'New': 'primary', 'Contacted': 'info', 'Showing Scheduled': 'warning',
                'Showed': 'success', 'Lost': 'danger'
            };

            // SMS badge
            let smsBadge = '<span class="text-white-50" style="font-size:0.75rem">—</span>';
            if (sms) {
                const aiColor = sms.ai_status === 'active' ? 'success' : sms.ai_status === 'paused_handoff' ? 'warning' : 'secondary';
                const aiLabel = sms.ai_status === 'active' ? 'AI Active' : sms.ai_status === 'paused_handoff' ? 'Handoff' : 'Paused';
                // Unread badge
                const unreadBadge = sms.unread > 0
                    ? '<span class="badge bg-danger rounded-pill ms-1" style="font-size:0.65rem">' + sms.unread + '</span>'
                    : '';
                smsBadge = '<span class="badge bg-' + aiColor + ' bg-opacity-25 text-' + aiColor + '" style="font-size:0.7rem">' + aiLabel + '</span>' + unreadBadge;

                // Follow-up status badge
                if (sms.followup_status === 'sent_1') {
                    smsBadge += '<div class="mt-1"><span class="badge bg-warning bg-opacity-25 text-warning" style="font-size:0.6rem">Follow-up 1 sent</span></div>';
                } else if (sms.followup_status === 'sent_2' || sms.followup_status === 'cold') {
                    smsBadge += '<div class="mt-1"><span class="badge bg-secondary bg-opacity-50 text-white-50" style="font-size:0.6rem">Gone Cold</span></div>';
                } else {
                    smsBadge += '<div class="text-white-50 mt-1" style="font-size:0.7rem">' + sms.message_count + ' msgs</div>';
                }
            }

            // Last activity — check both comm and SMS timestamps
            let lastActivity = '';
            const commAt = lead.last_comm_at ? new Date(lead.last_comm_at) : null;
            const smsAt = sms ? new Date(sms.last_at) : null;
            const latest = commAt && smsAt ? (smsAt > commAt ? smsAt : commAt) : (smsAt || commAt);
            if (latest) {
                lastActivity = '<div style="font-size:0.8rem" class="text-white">' + latest.toLocaleDateString() + '</div>'
                    + '<small class="text-white-50">' + latest.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '</small>';
            } else {
                lastActivity = '<span class="text-white-50" style="font-size:0.8rem">No activity</span>';
            }

            const row = document.createElement('tr');
            row.style.cursor = 'pointer';
            row.onclick = () => showCommTimeline(lead.email);

            row.innerHTML = '<td><div class="fw-semibold text-white">' + esc(lead.first_name + ' ' + lead.last_name) + '</div><small class="text-white-50">' + esc(lead.source || '') + '</small></td>'
                + '<td><div style="font-size:0.8rem">' + esc(lead.email) + '</div><small class="text-white-50">' + esc(lead.phone || '') + '</small></td>'
                + '<td><select class="form-select form-select-sm bg-dark border-secondary text-white" style="width:auto;font-size:0.75rem" onchange="event.stopPropagation(); updateLeadStatus(\'' + esc(lead.email).replace(/'/g, "\\'") + '\', \'' + esc(lead.source || '').replace(/'/g, "\\'") + '\', this.value)">'
                + ['New','Contacted','Showing Scheduled','Showed','Lost'].map(s => '<option value="' + s + '"' + (s === status ? ' selected' : '') + '>' + s + '</option>').join('')
                + '</select></td>'
                + '<td>' + smsBadge + '</td>'
                + '<td>' + lastActivity + '</td>'
                + '<td>' + (lead.broker_name ? '<span class="text-white" style="font-size:0.85rem">' + esc(lead.broker_name) + '</span>' : '<span class="text-danger" style="font-size:0.8rem">Unassigned</span>') + '</td>';

            tbody.appendChild(row);
        });
    } catch(err) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading pipeline.</td></tr>';
    }
}

function showCommPipeline() {
    document.getElementById('commPipelineView').style.display = 'block';
    document.getElementById('commTimelineView').style.display = 'none';
    loadCommPipeline();
}

// ── Timeline View ──
async function showCommTimeline(email) {
    document.getElementById('commPipelineView').style.display = 'none';
    document.getElementById('commTimelineView').style.display = 'block';
    document.getElementById('currentLeadEmail').value = email;

    const smsContainer = document.getElementById('smsThreadContainer');
    const emailContainer = document.getElementById('emailThreadContainer');
    smsContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';
    emailContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';

    try {
        const res = await fetch(API + '?action=get_unified_timeline&email=' + encodeURIComponent(email));
        const data = await res.json();

        if (data.error) {
            smsContainer.innerHTML = '<div class="text-center text-danger py-5">' + esc(data.error) + '</div>';
            emailContainer.innerHTML = '';
            return;
        }

        currentTimelineData = data;
        renderTimelineHeader(data);
        renderStatusPipeline(data.lead);
        renderTourBanner(data.tour);
        renderNextPlanned(data.next_planned, data.lead);
        renderFormSubmissions(data.submissions || []);
        renderConversations(data.timeline || []);
        renderInternalLog(data.timeline || []);
        setupReplyBox(data.lead);
        setupAIToggle(data);

        // Mark messages as read
        fetch(API + '?action=mark_sms_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        });
    } catch(err) {
        smsContainer.innerHTML = '<div class="text-center text-danger py-5">Error loading timeline.</div>';
        emailContainer.innerHTML = '';
    }
}

function renderTimelineHeader(data) {
    const lead = data.lead;
    document.getElementById('timelineLeadName').textContent = lead.name || lead.email;
    document.getElementById('timelineLeadEmail').textContent = lead.email;
    document.getElementById('timelineLeadPhone').textContent = lead.phone ? ' · ' + lead.phone : '';
}

// ── Status Pipeline ──
const PIPELINE_STEPS = [
    { key: 'New',               label: 'New' },
    { key: 'Contacted',         label: 'Contacted' },
    { key: 'Showing Scheduled', label: 'Booked' },
    { key: 'Showed',            label: 'Showed' },
    { key: 'Lost',              label: 'Closed' }
];

function renderStatusPipeline(lead) {
    const el = document.getElementById('statusPipeline');
    if (!el) return;
    const current = lead.lead_status || 'New';
    const currentIdx = PIPELINE_STEPS.findIndex(s => s.key === current);
    // Lost is a terminal sideline, not a forward step — render it as the final node only when reached
    const isLost = current === 'Lost';

    const html = '<div class="d-flex align-items-center justify-content-between" style="position:relative">'
        + PIPELINE_STEPS.map((step, i) => {
            const reached = isLost ? (step.key === 'Lost') : (i <= currentIdx);
            const isCurrent = step.key === current;
            const dotColor = isLost && isCurrent ? '#ef4444' : (reached ? '#10b981' : '#374151');
            const labelColor = isCurrent ? (isLost ? '#ef4444' : '#10b981') : (reached ? '#9ca3af' : '#6b7280');
            const lineColor = (i < PIPELINE_STEPS.length - 1 && i < currentIdx && !isLost) ? '#10b981' : '#374151';
            const showLine = i < PIPELINE_STEPS.length - 1;
            return '<div class="d-flex flex-column align-items-center" style="flex:0 0 auto;position:relative;z-index:2">'
                + '<div style="width:14px;height:14px;border-radius:50%;background:' + dotColor + ';border:2px solid #1f2937"></div>'
                + '<div class="mt-2" style="font-size:0.7rem;color:' + labelColor + ';font-weight:' + (isCurrent ? 600 : 400) + '">' + esc(step.label) + '</div>'
                + '</div>'
                + (showLine ? '<div style="flex:1;height:2px;background:' + lineColor + ';margin:0 -2px;align-self:flex-start;margin-top:6px;z-index:1"></div>' : '');
        }).join('')
        + '</div>';

    el.innerHTML = html;
}

// ── Tour Banner ──
function renderTourBanner(tour) {
    const el = document.getElementById('tourBanner');
    if (!el) return;
    if (!tour) { el.style.display = 'none'; el.innerHTML = ''; return; }

    const dt = tour.scheduled_at ? new Date(tour.scheduled_at) : null;
    const whenStr = dt ? dt.toLocaleString([], { weekday: 'long', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Time TBD';

    const palette = {
        confirmed:   { border: '#10b981', bg: 'rgba(16,185,129,0.08)',  icon: 'bi-calendar-check-fill', label: 'TOUR BOOKED',     color: '#10b981' },
        pending:     { border: '#f59e0b', bg: 'rgba(245,158,11,0.08)', icon: 'bi-hourglass-split',       label: 'TOUR PENDING',    color: '#f59e0b' },
        rescheduled: { border: '#f59e0b', bg: 'rgba(245,158,11,0.08)', icon: 'bi-arrow-repeat',          label: 'TOUR RESCHEDULED',color: '#f59e0b' },
        cancelled:   { border: '#ef4444', bg: 'rgba(239,68,68,0.08)',  icon: 'bi-x-circle-fill',         label: 'TOUR CANCELLED',  color: '#ef4444' },
        no_show:     { border: '#ef4444', bg: 'rgba(239,68,68,0.08)',  icon: 'bi-person-x-fill',         label: 'NO SHOW',         color: '#ef4444' },
        completed:   { border: '#6b7280', bg: 'rgba(107,114,128,0.08)',icon: 'bi-check-circle-fill',     label: 'TOUR COMPLETED',  color: '#9ca3af' },
    };
    const p = palette[tour.status] || palette.pending;

    const meta = [];
    if (tour.unit) meta.push('Unit ' + tour.unit);
    if (tour.broker_name) meta.push('with ' + tour.broker_name);
    const metaLine = meta.length ? '<div class="text-white-50" style="font-size:0.85rem">' + esc(meta.join(' · ')) + '</div>' : '';

    el.style.display = '';
    el.innerHTML = '<div class="d-flex align-items-center gap-3 p-3 rounded-3" style="border-left:4px solid ' + p.border + ';background:' + p.bg + '">'
        + '<i class="bi ' + p.icon + '" style="font-size:1.6rem;color:' + p.color + '"></i>'
        + '<div class="flex-grow-1">'
        + '<div style="font-size:0.7rem;color:' + p.color + ';font-weight:700;letter-spacing:0.1em">' + p.label + '</div>'
        + '<div class="fw-semibold text-white" style="font-size:1.05rem">' + esc(whenStr) + '</div>'
        + metaLine
        + '</div>'
        + '<a href="/admin/calendar.php" class="btn btn-sm btn-outline-light">Open Calendar</a>'
        + '</div>';
}

// ── Up Next (planned automated event) ──
function renderNextPlanned(next, lead) {
    const el = document.getElementById('nextPlannedCard');
    if (!el) return;
    if (!next) { el.style.display = 'none'; el.innerHTML = ''; return; }

    // Don't surface 'none' — empty state is just noise on the page.
    if (next.type === 'none') { el.style.display = 'none'; el.innerHTML = ''; return; }

    const palette = {
        followup_1:    { color: '#a78bfa', icon: 'bi-chat-square-dots',     border: 'rgba(139,92,246,0.5)', bg: 'rgba(139,92,246,0.08)' },
        followup_2:    { color: '#f59e0b', icon: 'bi-chat-square-dots-fill',border: 'rgba(245,158,11,0.5)', bg: 'rgba(245,158,11,0.08)' },
        tour_reminder: { color: '#3b82f6', icon: 'bi-calendar-event',       border: 'rgba(59,130,246,0.5)', bg: 'rgba(59,130,246,0.08)' },
        paused:        { color: '#9ca3af', icon: 'bi-pause-circle',         border: 'rgba(107,114,128,0.5)',bg: 'rgba(107,114,128,0.08)' },
    };
    const p = palette[next.type] || palette.paused;

    let whenStr = '';
    if (next.scheduled_for) {
        const dt = new Date(next.scheduled_for);
        const now = new Date();
        const diffMs = dt - now;
        const sameDay = dt.toDateString() === now.toDateString();
        const tomorrow = new Date(now); tomorrow.setDate(tomorrow.getDate() + 1);
        const isTomorrow = dt.toDateString() === tomorrow.toDateString();
        const timeStr = dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (diffMs < 0) {
            whenStr = 'Overdue — will fire on the next cron tick';
        } else if (sameDay) {
            whenStr = 'Today at ' + timeStr;
        } else if (isTomorrow) {
            whenStr = 'Tomorrow at ' + timeStr;
        } else {
            whenStr = dt.toLocaleString([], { weekday: 'long', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        }
    }

    let skipBtn = '';
    if (next.skip_action === 'skip_followups') {
        skipBtn = '<button class="btn btn-sm btn-outline-light" onclick="skipFollowups()">Skip follow-ups</button>';
    } else if (next.skip_action === 'skip_tour_reminder') {
        skipBtn = '<button class="btn btn-sm btn-outline-light" onclick="skipTourReminder(' + (next.skip_target || 'null') + ')">Skip reminder</button>';
    }

    el.style.display = '';
    el.innerHTML = '<div class="d-flex align-items-start gap-3 p-3 rounded-3" style="border-left:3px solid ' + p.border + ';background:' + p.bg + '">'
        + '<i class="bi ' + p.icon + '" style="color:' + p.color + ';font-size:1.3rem;margin-top:2px"></i>'
        + '<div class="flex-grow-1" style="min-width:0">'
        + '<div style="font-size:0.7rem;color:' + p.color + ';font-weight:700;letter-spacing:0.1em">UP NEXT</div>'
        + '<div class="fw-semibold text-white" style="font-size:0.95rem">' + esc(next.label) + (whenStr ? ' — <span class="text-white-50 fw-normal">' + esc(whenStr) + '</span>' : '') + '</div>'
        + (next.detail ? '<div class="text-white-50 mt-1" style="font-size:0.8rem;line-height:1.4">' + esc(next.detail) + '</div>' : '')
        + '</div>'
        + (skipBtn ? '<div style="align-self:center">' + skipBtn + '</div>' : '')
        + '</div>';
}

async function skipFollowups() {
    if (!confirm('Stop the AI follow-up nudges for this lead?\n\nReactive AI replies will still work if the lead texts in.')) return;
    const phone = document.getElementById('currentLeadPhone').value;
    const email = document.getElementById('currentLeadEmail').value;
    if (!phone) return;
    try {
        const res = await fetch(API + '?action=skip_followups', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone })
        });
        const data = await res.json();
        if (data.success) {
            if (email) showCommTimeline(email);
        } else {
            alert(data.error || 'Failed to skip follow-ups');
        }
    } catch (e) {
        alert('Network error');
    }
}

async function skipTourReminder(tourId) {
    if (!tourId) return;
    if (!confirm('Skip the automated day-before tour reminder for this tour?')) return;
    const email = document.getElementById('currentLeadEmail').value;
    try {
        const res = await fetch(API + '?action=skip_tour_reminder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: tourId })
        });
        const data = await res.json();
        if (data.success) {
            if (email) showCommTimeline(email);
        } else {
            alert(data.error || 'Failed to skip reminder');
        }
    } catch (e) {
        alert('Network error');
    }
}

// ── Form Submissions Section ──
const SUBMISSION_FIELDS = [
    { key: 'first_name',    label: 'First name' },
    { key: 'last_name',     label: 'Last name' },
    { key: 'email',         label: 'Email' },
    { key: 'phone',         label: 'Phone' },
    { key: 'unit',          label: 'Unit' },
    { key: 'unit_type',     label: 'Unit type' },
    { key: 'budget',        label: 'Budget' },
    { key: 'move_in_date',  label: 'Move-in date' },
    { key: 'hear_about_us', label: 'Heard about us' },
    { key: 'message',       label: 'Message' }
];

function renderFormSubmissions(submissions) {
    const card = document.getElementById('formSubmissionsCard');
    const list = document.getElementById('formSubmissionsList');
    if (!card) return;

    if (!submissions || submissions.length === 0) {
        card.style.display = 'none';
        return;
    }

    card.style.display = '';

    list.innerHTML = submissions.map(s => {
        const dt = s.created_at ? new Date(s.created_at) : null;
        const timeStr = dt ? dt.toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Unknown time';
        const sourceColor = s.source === 'Waitlist' ? 'primary' : 'success';

        // Show ALL fields — blanks render as a dim em-dash so the broker can see what wasn't filled in.
        const fieldRows = SUBMISSION_FIELDS.map(f => {
            const raw = s[f.key];
            const filled = raw !== null && raw !== undefined && String(raw).trim() !== '';
            const valueHtml = filled
                ? '<span class="text-white">' + esc(String(raw)) + '</span>'
                : '<span class="text-white-50" style="opacity:0.5">—</span>';
            return '<div class="row g-2 mb-1"><div class="col-4 small text-white-50">' + esc(f.label) + '</div><div class="col-8 small">' + valueHtml + '</div></div>';
        }).join('');

        return '<div class="border border-secondary rounded-3 p-3 mb-2" style="background:rgba(255,255,255,0.02)">'
            + '<div class="d-flex justify-content-between align-items-start mb-3">'
            + '<div>'
            + '<div class="fw-semibold text-white" style="font-size:0.95rem"><i class="bi bi-file-earmark-text me-2"></i>Form submission</div>'
            + '<div class="text-white-50 mt-1" style="font-size:0.8rem">' + esc(timeStr) + '</div>'
            + '</div>'
            + '<span class="badge bg-' + sourceColor + ' bg-opacity-25 text-' + sourceColor + '" style="font-size:0.7rem">' + esc(s.source || '') + '</span>'
            + '</div>'
            + fieldRows
            + '</div>';
    }).join('');
}

// ── Conversation columns (SMS + Email) and Internal Log ──
function classifyTimelineItem(item) {
    // SMS bubbles always go in the SMS column.
    if (item.type === 'sms') return 'sms';
    // Outbound/inbound email messages go in the Email column.
    if (item.type === 'email' && item.direction !== 'internal') return 'email';
    // Everything else — internal notes, system alerts, enrichment emails to owners — go to the internal log.
    return 'internal';
}

function renderConversations(items) {
    const smsItems = items.filter(it => classifyTimelineItem(it) === 'sms');
    const emailItems = items.filter(it => classifyTimelineItem(it) === 'email');

    renderConversationColumn('smsThreadContainer', 'smsThreadCount', smsItems, 'sms');
    renderConversationColumn('emailThreadContainer', 'emailThreadCount', emailItems, 'email');
}

function renderConversationColumn(containerId, countId, items, kind) {
    const container = document.getElementById(containerId);
    const countEl = document.getElementById(countId);
    if (!container) return;

    if (countEl) countEl.textContent = items.length ? items.length + (items.length === 1 ? ' msg' : ' msgs') : '';

    if (!items.length) {
        const label = kind === 'sms' ? 'No SMS yet.' : 'No email conversation yet.';
        container.innerHTML = '<div class="text-center text-body-tertiary py-4 small">' + label + '</div>';
        return;
    }

    const grouped = {};
    items.forEach(item => {
        const day = new Date(item.created_at).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        if (!grouped[day]) grouped[day] = [];
        grouped[day].push(item);
    });

    let html = '';
    Object.entries(grouped).forEach(([day, dayItems]) => {
        html += '<div class="text-center my-2"><span class="badge bg-body-secondary text-white-50 px-3 py-1" style="font-size:0.65rem;letter-spacing:0.05em">' + esc(day) + '</span></div>';
        dayItems.forEach(item => {
            html += kind === 'sms' ? renderSMSBubble(item) : renderEmailCard(item);
        });
    });

    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function renderEmailCard(item) {
    const time = new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const isInbound = item.direction === 'inbound';
    const borderColor = isInbound ? 'rgba(16,185,129,0.5)' : 'rgba(59,130,246,0.5)';
    const labelColor = isInbound ? '#10b981' : '#3b82f6';
    const dirLabel = isInbound ? 'Inbound' : 'Outbound';

    return '<div class="rounded-3 mb-2 p-2" style="border-left:3px solid ' + borderColor + ';background:rgba(255,255,255,0.02)">'
        + '<div class="d-flex justify-content-between align-items-center mb-1">'
        + '<span style="font-size:0.65rem;color:' + labelColor + ';font-weight:600">' + dirLabel + '</span>'
        + '<span class="text-white-50" style="font-size:0.7rem">' + time + '</span>'
        + '</div>'
        + (item.subject ? '<div class="fw-semibold text-white" style="font-size:0.85rem">' + esc(item.subject) + '</div>' : '')
        + (item.sender ? '<div class="text-white-50" style="font-size:0.7rem">From: ' + esc(item.sender) + '</div>' : '')
        + (item.body ? '<div class="text-white-50 mt-1" style="font-size:0.8rem;line-height:1.4">' + esc(item.body) + '</div>' : '')
        + '</div>';
}

function renderInternalLog(items) {
    const card = document.getElementById('internalLogCard');
    const list = document.getElementById('internalLogList');
    const countEl = document.getElementById('internalLogCount');
    const latestEl = document.getElementById('latestInternalNote');
    if (!card || !list) return;

    const internalItems = items
        .filter(it => classifyTimelineItem(it) === 'internal')
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    if (!internalItems.length) {
        card.style.display = 'none';
        if (latestEl) { latestEl.style.display = 'none'; latestEl.innerHTML = ''; }
        return;
    }

    // Surface the most recent internal note as a banner at the top.
    if (latestEl) {
        const latest = internalItems[0];
        const dt = new Date(latest.created_at);
        const time = dt.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        const subject = latest.subject || (latest.type === 'email' ? 'Email notification' : 'Note');
        const body = latest.body ? '<div class="text-white-50 mt-1" style="font-size:0.82rem;line-height:1.45">' + esc(latest.body) + '</div>' : '';

        latestEl.style.display = '';
        latestEl.innerHTML = '<div class="d-flex align-items-start gap-3 p-3 rounded-3" style="border-left:3px solid #6b7280;background:rgba(107,114,128,0.08)">'
            + '<i class="bi bi-bell-fill" style="color:#9ca3af;font-size:1.05rem;margin-top:1px"></i>'
            + '<div class="flex-grow-1" style="min-width:0">'
            + '<div class="d-flex justify-content-between align-items-baseline gap-2">'
            + '<span class="fw-semibold text-white" style="font-size:0.9rem">' + esc(subject) + '</span>'
            + '<span class="text-white-50" style="font-size:0.72rem;white-space:nowrap">' + esc(time) + '</span>'
            + '</div>'
            + body
            + '</div>'
            + '</div>';
    }

    card.style.display = '';
    countEl.textContent = internalItems.length;

    list.innerHTML = internalItems.map(item => {
        const dt = new Date(item.created_at);
        const time = dt.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        const subject = item.subject || (item.type === 'email' ? 'Email notification' : 'Note');
        const body = item.body ? '<div class="text-white-50 mt-1" style="font-size:0.78rem;line-height:1.4">' + esc(item.body) + '</div>' : '';
        return '<div class="border-bottom border-secondary py-2 d-flex justify-content-between align-items-start gap-3">'
            + '<div style="min-width:0;flex:1">'
            + '<div class="text-white" style="font-size:0.85rem">' + esc(subject) + '</div>'
            + body
            + '</div>'
            + '<small class="text-white-50" style="font-size:0.7rem;white-space:nowrap">' + esc(time) + '</small>'
            + '</div>';
    }).join('');
}

function toggleInternalLog() {
    const list = document.getElementById('internalLogList');
    const chevron = document.getElementById('internalLogChevron');
    if (!list) return;
    const isOpen = list.style.display !== 'none';
    list.style.display = isOpen ? 'none' : '';
    if (chevron) chevron.className = 'bi text-white-50 ' + (isOpen ? 'bi-chevron-down' : 'bi-chevron-up');
}

function renderSMSBubble(item) {
    const time = new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const isInbound = item.direction === 'inbound';

    if (isInbound) {
        // Lead message — right aligned
        return '<div class="d-flex justify-content-end mb-2">'
            + '<div style="max-width:75%;background:rgba(255,255,255,0.08);border-radius:12px 12px 4px 12px;padding:0.6rem 0.85rem;">'
            + '<div class="text-white" style="font-size:0.85rem;line-height:1.5">' + esc(item.body) + '</div>'
            + '<div class="text-end mt-1" style="font-size:0.65rem;color:rgba(255,255,255,0.3)">' + esc(item.sender) + ' · ' + time + '</div>'
            + '</div></div>';
    }

    // Outbound — left aligned
    const isAI = item.source === 'ai';
    const bgColor = isAI ? 'rgba(139,92,246,0.12)' : 'rgba(59,130,246,0.12)';
    const borderColor = isAI ? 'rgba(139,92,246,0.3)' : 'rgba(59,130,246,0.3)';
    const labelColor = isAI ? '#a78bfa' : '#93c5fd';

    let statusIcon = '';
    if (item.status === 'failed') statusIcon = ' <i class="bi bi-exclamation-circle text-danger" style="font-size:0.7rem"></i>';
    else if (item.status === 'delivered') statusIcon = ' <i class="bi bi-check2-all" style="font-size:0.7rem;color:rgba(255,255,255,0.3)"></i>';
    else if (item.status === 'sent') statusIcon = ' <i class="bi bi-check2" style="font-size:0.7rem;color:rgba(255,255,255,0.3)"></i>';

    return '<div class="d-flex justify-content-start mb-2">'
        + '<div style="max-width:75%;background:' + bgColor + ';border:1px solid ' + borderColor + ';border-radius:12px 12px 12px 4px;padding:0.6rem 0.85rem;">'
        + '<div style="font-size:0.65rem;color:' + labelColor + ';font-weight:600;margin-bottom:0.2rem">' + esc(item.sender) + '</div>'
        + '<div class="text-white" style="font-size:0.85rem;line-height:1.5">' + esc(item.body) + '</div>'
        + '<div class="mt-1" style="font-size:0.65rem;color:rgba(255,255,255,0.3)">' + time + statusIcon + '</div>'
        + '</div></div>';
}

// ── SMS Reply Box ──
function setupReplyBox(lead) {
    const replyBox = document.getElementById('smsReplyBox');
    const noPhoneMsg = document.getElementById('noPhoneMessage');
    const input = document.getElementById('smsReplyInput');

    if (lead.phone) {
        replyBox.style.display = 'block';
        noPhoneMsg.style.display = 'none';
        document.getElementById('currentLeadPhone').value = lead.phone;

        // Character counter
        input.addEventListener('input', function() {
            document.getElementById('smsCharCount').textContent = this.value.length;
        });
    } else {
        replyBox.style.display = 'none';
        noPhoneMsg.style.display = 'block';
    }
}

async function sendSMSReply() {
    const input = document.getElementById('smsReplyInput');
    const body = input.value.trim();
    const phone = document.getElementById('currentLeadPhone').value;
    const btn = document.getElementById('smsReplyBtn');
    const statusEl = document.getElementById('smsReplyStatus');

    if (!body || !phone) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    statusEl.textContent = 'Sending...';

    try {
        const res = await fetch(API + '?action=sms_send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone, body: body, sender_name: 'Broker' })
        });
        const result = await res.json();

        if (result.success) {
            input.value = '';
            document.getElementById('smsCharCount').textContent = '0';
            statusEl.textContent = 'Sent';
            setTimeout(() => statusEl.textContent = '', 2000);

            // Refresh timeline
            const email = document.getElementById('currentLeadEmail').value;
            if (email) showCommTimeline(email);
        } else {
            statusEl.textContent = 'Failed: ' + (result.error || 'Unknown error');
        }
    } catch(err) {
        statusEl.textContent = 'Connection error';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send"></i>';
}

// ── AI Toggle ──
function setupAIToggle(data) {
    const section = document.getElementById('aiStatusSection');
    const toggle = document.getElementById('aiToggle');
    const label = document.getElementById('aiStatusLabel');

    if (!data.lead.phone) {
        section.style.display = 'none';
        return;
    }

    section.style.display = '';
    section.style.removeProperty('display');

    const status = data.ai_status.status || 'active';
    toggle.checked = status === 'active';

    if (status === 'active') {
        label.innerHTML = '<span class="text-success">Active</span>';
    } else if (status === 'paused_handoff') {
        label.innerHTML = '<span class="text-warning">Handoff</span>';
    } else if (status === 'paused_optout') {
        label.innerHTML = '<span class="text-danger">Opted Out</span>';
    } else {
        const pausedBy = data.ai_status.paused_by ? ' by ' + esc(data.ai_status.paused_by) : '';
        label.innerHTML = '<span class="text-secondary">Paused' + pausedBy + '</span>';
    }
}

async function handleAIToggle() {
    const toggle = document.getElementById('aiToggle');
    const phone = document.getElementById('currentLeadPhone').value;
    if (!phone) return;

    const newStatus = toggle.checked ? 'active' : 'paused_manual';

    try {
        await fetch(API + '?action=sms_toggle_ai', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone, status: newStatus })
        });

        const label = document.getElementById('aiStatusLabel');
        label.innerHTML = toggle.checked
            ? '<span class="text-success">Active</span>'
            : '<span class="text-secondary">Paused</span>';
    } catch(err) {
        toggle.checked = !toggle.checked;
    }
}

// ── Lead Status Update ──
async function updateLeadStatus(email, source, status) {
    try {
        await fetch(API + '?action=update_lead_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, source, status })
        });
    } catch(err) {
        console.error('Status update failed:', err);
    }
}

// ── Log Communication Modal ──
function showAddCommModal() {
    const emailField = document.getElementById('currentLeadEmail');
    document.getElementById('commLeadEmail').value = emailField ? emailField.value : '';
    document.getElementById('commDirection').value = 'outbound';
    document.getElementById('commChannel').value = 'note';
    document.getElementById('commSubject').value = '';
    document.getElementById('commBody').value = '';
    document.getElementById('commSender').value = '';
    document.getElementById('commRecipient').value = '';
    new bootstrap.Modal(document.getElementById('commModal')).show();
}

async function saveComm() {
    const payload = {
        lead_email: document.getElementById('commLeadEmail').value.trim(),
        direction: document.getElementById('commDirection').value,
        channel: document.getElementById('commChannel').value,
        subject: document.getElementById('commSubject').value.trim(),
        body: document.getElementById('commBody').value.trim(),
        sender: document.getElementById('commSender').value.trim(),
        recipient: document.getElementById('commRecipient').value.trim()
    };
    if (!payload.lead_email) { alert('Lead email is required'); return; }

    try {
        const res = await fetch(API + '?action=add_communication', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('commModal')).hide();
            if (document.getElementById('commTimelineView').style.display !== 'none') {
                showCommTimeline(document.getElementById('currentLeadEmail').value);
            } else {
                loadCommPipeline();
            }
        } else {
            alert('Error: ' + (result.error || 'Unknown'));
        }
    } catch(err) {
        alert('Network error');
    }
}

// ── Init ──
document.addEventListener('DOMContentLoaded', loadCommPipeline);
