// ── Communications ──

async function loadCommPipeline() {
    const tbody = document.querySelector('#commPipelineTable tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>';

    try {
        const res = await fetch('/api/admin-api.php?action=leads');
        const leads = await res.json();
        if (!Array.isArray(leads) || leads.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-body-tertiary py-5">No leads yet.</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        leads.forEach(lead => {
            const status = lead.lead_status || 'New';
            const statusColors = {
                'New': 'primary',
                'Contacted': 'info',
                'Showing Scheduled': 'warning',
                'Showed': 'secondary',
                'Applied': 'success',
                'Leased': 'success',
                'Lost': 'danger'
            };
            const badgeColor = statusColors[status] || 'secondary';

            const lastComm = lead.last_comm_subject
                ? '<div style="font-size:0.8rem" class="text-white">' + esc(lead.last_comm_subject) + '</div><small class="text-white-50">' + esc(lead.last_comm_at ? new Date(lead.last_comm_at).toLocaleString() : '') + '</small>'
                : '<span class="text-white-50" style="font-size:0.8rem">No communications</span>';

            const row = document.createElement('tr');
            row.style.cursor = 'pointer';
            row.onclick = () => showCommTimeline(lead.email, esc(lead.first_name + ' ' + lead.last_name));

            row.innerHTML = '<td><div class="fw-semibold text-white">' + esc(lead.first_name + ' ' + lead.last_name) + '</div><small class="text-white-50">' + esc(lead.source || '') + '</small></td>'
                + '<td><div style="font-size:0.8rem">' + esc(lead.email) + '</div><small class="text-white-50">' + esc(lead.phone || '') + '</small></td>'
                + '<td><select class="form-select form-select-sm bg-dark border-secondary text-white" style="width:auto;font-size:0.75rem" onchange="event.stopPropagation(); updateLeadStatus(\'' + esc(lead.email).replace(/'/g, "\\'") + '\', \'' + esc(lead.source || '').replace(/'/g, "\\'") + '\', this.value)">'
                + ['New','Contacted','Showing Scheduled','Showed','Applied','Leased','Lost'].map(s => '<option value="' + s + '"' + (s === status ? ' selected' : '') + '>' + s + '</option>').join('')
                + '</select></td>'
                + '<td>' + lastComm + '</td>'
                + '<td>' + (lead.broker_name ? '<span class="text-white" style="font-size:0.85rem">' + esc(lead.broker_name) + '</span>' : '<span class="text-danger" style="font-size:0.8rem">Unassigned</span>') + '</td>';

            tbody.appendChild(row);
        });
    } catch(err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading pipeline.</td></tr>';
    }
}

function showCommPipeline() {
    document.getElementById('commPipelineView').style.display = 'block';
    document.getElementById('commTimelineView').style.display = 'none';
}

function showCommTimeline(email, name) {
    document.getElementById('commPipelineView').style.display = 'none';
    document.getElementById('commTimelineView').style.display = 'block';
    document.getElementById('commTimelineName').textContent = name || email;
    document.getElementById('commSearchEmail').value = email;
    loadAllComms(email);
}

async function loadAllComms(filterEmail) {
    const email = filterEmail || '';
    const container = document.getElementById('commsTimeline');

    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';

    try {
        const url = email
            ? '/api/admin-api.php?action=get_communications&email=' + encodeURIComponent(email)
            : '/api/admin-api.php?action=get_communications';
        const res = await fetch(url);
        let comms = await res.json();

        if (!Array.isArray(comms)) { comms = []; }

        if (comms.length === 0) {
            container.innerHTML = '<div class="text-center text-body-tertiary py-5">No communications recorded yet.</div>';
            return;
        }

        // Group by date
        const grouped = {};
        comms.forEach(c => {
            const day = new Date(c.created_at).toLocaleDateString();
            if (!grouped[day]) grouped[day] = [];
            grouped[day].push(c);
        });

        let html = '';
        Object.entries(grouped).forEach(([day, items]) => {
            html += '<div class="mb-3 mt-4"><span class="badge bg-body-secondary text-white-50 px-3 py-2" style="font-size:0.7rem;letter-spacing:0.08em">' + esc(day) + '</span></div>';
            items.forEach(c => {
                const dt = new Date(c.created_at);
                const dateTime = dt.toLocaleDateString() + ' at ' + dt.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                const isInternal = c.direction === 'internal';
                const isInbound = c.direction === 'inbound';

                // Channel info
                let channelIcon, channelColor, channelLabel;
                switch(c.channel) {
                    case 'email': channelIcon = 'bi-envelope-fill'; channelColor = '#3b82f6'; channelLabel = 'Email'; break;
                    case 'sms': channelIcon = 'bi-chat-dots-fill'; channelColor = '#10b981'; channelLabel = 'SMS'; break;
                    case 'phone': channelIcon = 'bi-telephone-fill'; channelColor = '#f59e0b'; channelLabel = 'Phone Call'; break;
                    case 'note': channelIcon = 'bi-sticky-fill'; channelColor = '#8b5cf6'; channelLabel = 'Note'; break;
                    default: channelIcon = 'bi-record-circle'; channelColor = '#6b7280'; channelLabel = c.channel;
                }

                // Card styling based on type
                let cardBorder, cardBg, headerBg;
                if (isInternal) {
                    cardBorder = 'border-left:3px solid rgba(107,114,128,0.4)';
                    cardBg = 'background:rgba(107,114,128,0.05)';
                    headerBg = 'background:rgba(107,114,128,0.08)';
                } else if (isInbound) {
                    cardBorder = 'border-left:3px solid rgba(16,185,129,0.6)';
                    cardBg = 'background:rgba(16,185,129,0.03)';
                    headerBg = 'background:rgba(16,185,129,0.06)';
                } else {
                    cardBorder = 'border-left:3px solid rgba(59,130,246,0.6)';
                    cardBg = 'background:rgba(59,130,246,0.03)';
                    headerBg = 'background:rgba(59,130,246,0.06)';
                }

                // Direction label
                let dirLabel;
                if (isInternal) dirLabel = '<span style="color:#9ca3af;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em"><i class="bi bi-bell"></i> Team Notification</span>';
                else if (isInbound) dirLabel = '<span style="color:#10b981;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em"><i class="bi bi-arrow-down-left"></i> From Applicant</span>';
                else dirLabel = '<span style="color:#3b82f6;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em"><i class="bi bi-arrow-up-right"></i> To Applicant</span>';

                let failBadge = c.status === 'failed' ? ' <span class="badge bg-danger" style="font-size:0.6rem">Failed</span>' : '';

                html += '<div class="rounded-3 mb-3 overflow-hidden" style="' + cardBorder + ';' + cardBg + '">'
                    // Header bar
                    + '<div class="d-flex justify-content-between align-items-center px-3 py-2" style="' + headerBg + '">'
                    + '<div class="d-flex align-items-center gap-2">'
                    + '<i class="bi ' + channelIcon + '" style="color:' + channelColor + ';font-size:0.85rem"></i>'
                    + '<span class="fw-semibold text-white" style="font-size:0.8rem">' + esc(channelLabel) + '</span>'
                    + dirLabel + failBadge
                    + '</div>'
                    + '<span class="text-white-50" style="font-size:0.75rem">' + esc(dateTime) + '</span>'
                    + '</div>'
                    // Body
                    + '<div class="px-3 py-3">'
                    + '<div class="fw-semibold text-white mb-1" style="font-size:0.85rem">' + esc(c.subject || 'No subject') + '</div>'
                    + '<div class="d-flex gap-3 mb-2" style="font-size:0.75rem;color:rgba(255,255,255,0.35)">'
                    + (c.sender ? '<span>From: ' + esc(c.sender) + '</span>' : '')
                    + (c.recipient ? '<span>To: ' + esc(c.recipient) + '</span>' : '')
                    + '</div>'
                    + (c.body ? '<div class="text-white-50" style="font-size:0.8rem;line-height:1.6;max-height:3.2em;overflow:hidden;cursor:pointer" onclick="if(this.style.maxHeight===\x27none\x27){this.style.maxHeight=\x273.2em\x27;this.nextElementSibling.textContent=\x27Show full message\x27}else{this.style.maxHeight=\x27none\x27;this.nextElementSibling.textContent=\x27Collapse\x27}">' + esc(c.body) + '</div><a href="#" class="text-primary d-inline-block mt-1" style="font-size:0.72rem" onclick="event.preventDefault();var b=this.previousElementSibling;if(b.style.maxHeight===\x27none\x27){b.style.maxHeight=\x273.2em\x27;this.textContent=\x27Show full message\x27}else{b.style.maxHeight=\x27none\x27;this.textContent=\x27Collapse\x27}">Show full message</a>' : '')
                    + '</div>'
                    + '</div>';
            });
        });

        container.innerHTML = html;
    } catch(err) {
        container.innerHTML = '<div class="alert alert-danger">Error loading communications.</div>';
    }
}

async function updateLeadStatus(email, source, status) {
    try {
        await fetch('/api/admin-api.php?action=update_lead_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, source, status })
        });
    } catch(err) {
        console.error('Status update failed:', err);
    }
}

function loadCommsForEmail(email) {
    showCommTimeline(email, '');
}

function showAddCommModal() {
    const emailField = document.getElementById('commSearchEmail');
    document.getElementById('commLeadEmail').value = emailField ? emailField.value : '';
    document.getElementById('commDirection').value = 'outbound';
    document.getElementById('commChannel').value = 'note';
    document.getElementById('commSubject').value = '';
    document.getElementById('commBody').value = '';
    document.getElementById('commSender').value = '';
    document.getElementById('commRecipient').value = '';
    const modal = new bootstrap.Modal(document.getElementById('commModal'));
    modal.show();
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
        const res = await fetch('/api/admin-api.php?action=add_communication', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('commModal')).hide();
            if (document.getElementById('commTimelineView').style.display !== 'none') {
                loadAllComms(document.getElementById('commSearchEmail').value);
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

document.addEventListener('DOMContentLoaded', loadCommPipeline);
