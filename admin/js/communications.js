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
                'Showed': 'secondary', 'Applied': 'success', 'Leased': 'success', 'Lost': 'danger'
            };

            // SMS badge
            let smsBadge = '<span class="text-white-50" style="font-size:0.75rem">—</span>';
            if (sms) {
                const aiColor = sms.ai_status === 'active' ? 'success' : sms.ai_status === 'paused_handoff' ? 'warning' : 'secondary';
                const aiLabel = sms.ai_status === 'active' ? 'AI Active' : sms.ai_status === 'paused_handoff' ? 'Handoff' : 'Paused';
                smsBadge = '<span class="badge bg-' + aiColor + ' bg-opacity-25 text-' + aiColor + '" style="font-size:0.7rem">' + aiLabel + '</span>';

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
                + ['New','Contacted','Showing Scheduled','Showed','Applied','Leased','Lost'].map(s => '<option value="' + s + '"' + (s === status ? ' selected' : '') + '>' + s + '</option>').join('')
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

    const container = document.getElementById('timelineContainer');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';

    try {
        const res = await fetch(API + '?action=get_unified_timeline&email=' + encodeURIComponent(email));
        const data = await res.json();

        if (data.error) {
            container.innerHTML = '<div class="text-center text-danger py-5">' + esc(data.error) + '</div>';
            return;
        }

        currentTimelineData = data;
        renderTimelineHeader(data);
        renderTimeline(data.timeline);
        setupReplyBox(data.lead);
        setupAIToggle(data);
    } catch(err) {
        container.innerHTML = '<div class="text-center text-danger py-5">Error loading timeline.</div>';
    }
}

function renderTimelineHeader(data) {
    const lead = data.lead;
    document.getElementById('timelineLeadName').textContent = lead.name || lead.email;
    document.getElementById('timelineLeadEmail').textContent = lead.email;
    document.getElementById('timelineLeadPhone').textContent = lead.phone ? ' · ' + lead.phone : '';
}

function renderTimeline(items) {
    const container = document.getElementById('timelineContainer');

    if (!items || items.length === 0) {
        container.innerHTML = '<div class="text-center text-body-tertiary py-5">No communications yet.</div>';
        return;
    }

    // Group by date
    const grouped = {};
    items.forEach(item => {
        const day = new Date(item.created_at).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        if (!grouped[day]) grouped[day] = [];
        grouped[day].push(item);
    });

    let html = '';
    Object.entries(grouped).forEach(([day, dayItems]) => {
        html += '<div class="text-center my-3"><span class="badge bg-body-secondary text-white-50 px-3 py-1" style="font-size:0.7rem;letter-spacing:0.05em">' + esc(day) + '</span></div>';

        dayItems.forEach(item => {
            if (item.type === 'sms') {
                html += renderSMSBubble(item);
            } else {
                html += renderCommCard(item);
            }
        });
    });

    container.innerHTML = html;

    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
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

function renderCommCard(item) {
    const dt = new Date(item.created_at);
    const time = dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    const channelConfig = {
        email: { icon: 'bi-envelope-fill', color: '#3b82f6', label: 'Email' },
        phone: { icon: 'bi-telephone-fill', color: '#f59e0b', label: 'Phone Call' },
        note:  { icon: 'bi-sticky-fill', color: '#8b5cf6', label: 'Note' }
    };
    const ch = channelConfig[item.type] || channelConfig.note;

    const dirColors = {
        inbound: { border: 'rgba(16,185,129,0.5)', bg: 'rgba(16,185,129,0.04)', label: '<span style="color:#10b981;font-size:0.65rem;font-weight:600"><i class="bi bi-arrow-down-left"></i> Inbound</span>' },
        outbound: { border: 'rgba(59,130,246,0.5)', bg: 'rgba(59,130,246,0.04)', label: '<span style="color:#3b82f6;font-size:0.65rem;font-weight:600"><i class="bi bi-arrow-up-right"></i> Outbound</span>' },
        internal: { border: 'rgba(107,114,128,0.4)', bg: 'rgba(107,114,128,0.04)', label: '<span style="color:#9ca3af;font-size:0.65rem;font-weight:600"><i class="bi bi-bell"></i> Internal</span>' }
    };
    const dir = dirColors[item.direction] || dirColors.outbound;

    return '<div class="rounded-3 mb-3 overflow-hidden" style="border-left:3px solid ' + dir.border + ';' + dir.bg + '">'
        + '<div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:rgba(255,255,255,0.02)">'
        + '<div class="d-flex align-items-center gap-2">'
        + '<i class="bi ' + ch.icon + '" style="color:' + ch.color + ';font-size:0.8rem"></i>'
        + '<span class="fw-semibold text-white" style="font-size:0.78rem">' + esc(ch.label) + '</span>'
        + dir.label
        + '</div>'
        + '<span class="text-white-50" style="font-size:0.7rem">' + time + '</span>'
        + '</div>'
        + '<div class="px-3 py-2">'
        + (item.subject ? '<div class="fw-semibold text-white mb-1" style="font-size:0.82rem">' + esc(item.subject) + '</div>' : '')
        + '<div class="d-flex gap-3 mb-1" style="font-size:0.7rem;color:rgba(255,255,255,0.3)">'
        + (item.sender ? '<span>From: ' + esc(item.sender) + '</span>' : '')
        + '</div>'
        + (item.body ? '<div class="text-white-50" style="font-size:0.8rem;line-height:1.5">' + esc(item.body) + '</div>' : '')
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
