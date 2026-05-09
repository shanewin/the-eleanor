// ── Leads Table ──
let currentLeads = [];
let sortConfig = { column: 'created_at', direction: 'desc' };

function sortLeads(column) {
    if (sortConfig.column === column) {
        sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
    } else {
        sortConfig.column = column;
        sortConfig.direction = column === 'created_at' || column === 'grade_score' || column === 'event_count' ? 'desc' : 'asc';
    }
    renderLeadsTable();
}

function renderLeadsTable() {
    const table = document.querySelector('.leadsTable');
    const tbody = table.querySelector('tbody');

    // Update sort indicators
    table.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('active');
        if (th.getAttribute('onclick')?.includes("'" + sortConfig.column + "'")) {
            th.classList.add('active');
        }
    });

    // Sort leads
    const leads = currentLeads.slice();
    const sortColumn = sortConfig.column;
    const sortDir = sortConfig.direction === 'asc' ? 1 : -1;

    leads.sort((a, b) => {
        let valA = a[sortColumn];
        let valB = b[sortColumn];

        if (sortColumn === 'created_at') {
            valA = new Date(valA);
            valB = new Date(valB);
        }

        if (valA < valB) return -1 * sortDir;
        if (valA > valB) return 1 * sortDir;
        return 0;
    });

    if (leads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-body-tertiary py-5">No leads recorded yet.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    leads.forEach(lead => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.onclick = () => {
            window.location.href = '/admin/lead.php?email=' + encodeURIComponent(lead.email);
        };

        const dateObj = new Date(lead.created_at);
        const dateStr = esc(dateObj.toLocaleDateString());
        const timeStr = esc(dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
        const timestamp = '<div><span class="fw-semibold">' + dateStr + '</span><br><small class="text-body-tertiary">' + timeStr + '</small></div>';

        const photoUrl = safeUrl(lead.photo_url);
        const fallbackUrl = 'https://ui-avatars.com/api/?name=' + encodeURIComponent((lead.first_name || '') + ' ' + (lead.last_name || '')) + '&background=6366f1&color=fff';
        const avatar = photoUrl
            ? '<img src="' + esc(photoUrl) + '" class="user-avatar" onerror="this.src=\'' + esc(fallbackUrl) + '\'">'
            : '<div class="user-avatar-placeholder">' + esc((lead.first_name || '?')[0]) + esc((lead.last_name || '?')[0]) + '</div>';

        const escapedEmail = esc(lead.email || '');

        // Contact column with email + phone
        const phoneDisplay = lead.phone ? '<div style="font-size:0.8rem;color:rgba(255,255,255,0.6)">' + esc(lead.phone) + '</div>' : '';
        const contactHtml = '<div>'
            + '<div class="d-flex align-items-center gap-1"><small>' + escapedEmail + '</small>'
            + '<button class="mini-copy-btn" onclick="event.stopPropagation(); copyToClipboard(\'' + escapedEmail.replace(/'/g, "\\'") + '\', this)"><i class="bi bi-clipboard" style="font-size:0.65rem"></i></button></div>'
            + phoneDisplay + '</div>';

        // Intent column
        const intentParts = [];
        intentParts.push('<small class="text-uppercase fw-semibold" style="font-size:0.65rem;letter-spacing:0.05em;color:rgba(255,255,255,0.5)">' + esc(lead.source) + '</small>');
        if (lead.unit) intentParts.push('<span class="text-primary fw-semibold" style="font-size:0.85rem">Unit ' + esc(lead.unit) + '</span>');
        if (lead.budget) intentParts.push('<small class="text-body-tertiary">$' + esc(lead.budget).replace(/^\$/, '') + ' Budget</small>');
        if (lead.move_in_date) {
            const mid = new Date(lead.move_in_date + 'T00:00:00');
            const formatted = isNaN(mid) ? lead.move_in_date : ((mid.getMonth()+1) + '/' + mid.getDate() + '/' + mid.getFullYear());
            intentParts.push('<small class="text-body-tertiary">Move-In: ' + esc(formatted) + '</small>');
        }
        const intentHtml = '<div class="d-flex flex-column gap-0">' + intentParts.join('') + '</div>';

        // Engagement
        const activityLabel = lead.event_count > 10 ? '<span class="text-success"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Hot</span>' :
                             lead.event_count > 5 ? '<span class="text-primary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Active</span>' :
                             lead.event_count > 0 ? '<span class="text-body-tertiary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Quiet</span>' :
                             '<span class="text-body-tertiary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> No Activity</span>';
        const engagementLabel = '<span style="font-size:0.8rem;" class="fw-semibold">' + activityLabel + '</span>';

        // Grade
        const gradeClass = lead.grade.score >= 80 ? 'elite' : '';

        // Assigned column
        const escapedLeadEmail = esc(lead.email || '').replace(/'/g, "\\'");
        const escapedLeadSource = esc(lead.source || '').replace(/'/g, "\\'");
        let assignedHtml = '';
        if (lead.assigned_broker_id && brokersCache.length > 0) {
            const assignedBroker = brokersCache.find(b => b.id == lead.assigned_broker_id);
            if (assignedBroker) {
                assignedHtml = '<span class="badge bg-primary bg-opacity-25 text-primary-emphasis">' + esc(assignedBroker.name) + '</span>';
            }
        }
        if (!assignedHtml) {
            assignedHtml = '<select class="form-select form-select-sm bg-dark border-secondary text-white" style="width:auto;font-size:0.75rem;" onclick="event.stopPropagation()" onchange="assignLead(\'' + escapedLeadEmail + '\', \'' + escapedLeadSource + '\', this.value)">'
                + '<option value="">Unassigned</option>';
            brokersCache.forEach(function(b) {
                const sel = (lead.assigned_broker_id && lead.assigned_broker_id == b.id) ? ' selected' : '';
                assignedHtml += '<option value="' + esc(String(b.id)) + '"' + sel + '>' + esc(b.name) + '</option>';
            });
            assignedHtml += '</select>';
        }

        // First Response column
        let firstResponseHtml = '';
        const createdAt = new Date(lead.created_at);
        if (lead.first_response_at) {
            const respondedAt = new Date(lead.first_response_at);
            const diffMs = respondedAt - createdAt;
            const method = lead.first_response_method ? ' via ' + esc(lead.first_response_method) : '';
            firstResponseHtml = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' + formatElapsed(diffMs) + method + '</span>';
        } else {
            const elapsedMs = Date.now() - createdAt.getTime();
            firstResponseHtml = '<div class="d-flex align-items-center gap-2">'
                + '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>' + formatElapsed(elapsedMs) + '</span>'
                + '<select class="form-select form-select-sm bg-dark border-secondary text-white" style="width:auto;font-size:0.75rem;" onclick="event.stopPropagation()" onchange="handleMarkAction(\'' + escapedLeadEmail + '\', \'' + escapedLeadSource + '\', this.value); this.value=\'\'">'
                + '<option value="" selected>Mark</option>'
                + '<option value="sms">SMS</option>'
                + '<option value="email">Email</option>'
                + '<option value="phone">Phone</option>'
                + '<option value="auto_text">Auto Text</option>'
                + '</select>'
                + '</div>';
        }

        // Status column
        const statusValue = lead.lead_status || 'New';
        const escapedEmailForStatus = esc(lead.email || '').replace(/'/g, "\\'");
        const escapedSourceForStatus = esc(lead.source || '').replace(/'/g, "\\'");
        const statusOptions = ['New','Contacted','Showing Scheduled','Showed','Applied','Leased','Lost'];
        const statusColor = {
            'New': 'primary',
            'Contacted': 'info',
            'Showing Scheduled': 'warning',
            'Showed': 'secondary',
            'Applied': 'success',
            'Leased': 'success',
            'Lost': 'danger'
        }[statusValue] || 'secondary';
        const statusHtml = '<select class="form-select form-select-sm bg-dark border-' + statusColor + ' text-' + statusColor + '" '
            + 'style="width:auto;font-size:0.75rem;" onclick="event.stopPropagation()" '
            + 'onchange="updateLeadStatus(\'' + escapedEmailForStatus + '\', \'' + escapedSourceForStatus + '\', this.value)">'
            + statusOptions.map(s => '<option value="' + esc(s) + '"' + (s === statusValue ? ' selected' : '') + '>' + esc(s) + '</option>').join('')
            + '</select>';

        // Delete button
        const escapedEmailForDelete = esc(lead.email || '').replace(/'/g, "\\'");
        const escapedSourceForDelete = esc(lead.source || '').replace(/'/g, "\\'");

        const submissionBadge = (lead.submission_count > 1)
            ? ' <span class="badge bg-info bg-opacity-25 text-info ms-1" title="' + esc(String(lead.submission_count)) + ' form submissions" style="font-size:0.65rem">' + esc(String(lead.submission_count)) + 'x</span>'
            : '';

        row.innerHTML = '<td>' + timestamp + '</td>'
            + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<div><span class="fw-semibold text-white">' + esc(lead.first_name) + ' ' + esc(lead.last_name) + '</span>' + submissionBadge + '</div></div></td>'
            + '<td>' + contactHtml + '</td>'
            + '<td>' + intentHtml + '</td>'
            + '<td>' + statusHtml + '</td>'
            + '<td>' + engagementLabel + '</td>'
            + '<td class="text-center"><div class="grade-pill ' + gradeClass + '">' + esc(lead.grade.letter) + '</div></td>'
            + '<td>' + assignedHtml + '</td>'
            + '<td>' + firstResponseHtml + '</td>'
            + '<td class="text-end">' + (USER_ROLE === 'owner' ? '<button class="delete-btn" onclick="event.stopPropagation(); deleteLead(\'' + escapedEmailForDelete + '\', \'' + escapedSourceForDelete + '\')">Delete</button>' : '') + '</td>';

        tbody.appendChild(row);
    });
}

// ── Mark dropdown dispatcher (native <select>) ──
function handleMarkAction(email, source, action) {
    if (!action) return;
    switch (action) {
        case 'sms':       respondLead(email, source, 'SMS'); break;
        case 'email':     respondLead(email, source, 'Email'); break;
        case 'phone':     respondLead(email, source, 'Phone'); break;
        case 'auto_text': engageAI(email); break;
    }
}

// ── Update Lead Status ──
async function updateLeadStatus(email, source, status) {
    try {
        await fetch(API + '?action=update_lead_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, source, status })
        });
    } catch (err) {
        console.error('Status update failed:', err);
    }
}

// ── Delete Lead ──
function deleteLead(email, source) {
    showConfirm({
        title: 'Delete Lead',
        message: 'This will permanently remove this lead and their data. This cannot be undone.',
        icon: '<i class="bi bi-trash text-danger"></i>',
        btnText: 'Delete Lead',
        onConfirm: async () => {
            try {
                const formData = new FormData();
                formData.append('email', email);
                formData.append('source', source);
                const res = await fetch(API + '?action=delete_lead', { method: 'POST', body: formData });
                const result = await res.json();
                if (result.success) {
                    fetchLeadsData();
                } else {
                    alert('Error: ' + (result.error || 'Unknown'));
                }
            } catch(err) {
                alert('Connection error deleting lead.');
            }
        }
    });
}

// ── Fetch Leads Data ──
async function fetchLeadsData() {
    try {
        // Fetch brokers for assignment dropdowns
        await fetchBrokers();

        const leadsResponse = await fetch(API + '?action=leads');
        if (!leadsResponse.ok) throw new Error("API Error");

        let leads = await leadsResponse.json();

        leads = leads.map(l => ({
            ...l,
            grade: calculateLeadGrade(l),
            grade_score: calculateLeadGrade(l).score
        }));

        currentLeads = leads;
        renderLeadsTable();
    } catch (err) {
        console.error("Leads fetch error:", err);
        const tbody = document.querySelector('.leadsTable tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-5">Error loading leads.</td></tr>';
        }
    }
}

// Override assignLead/respondLead callbacks to refresh leads page
const _origAssignLead = assignLead;
assignLead = async function(email, source, brokerId) {
    try {
        const res = await fetch(API + '?action=assign_lead', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: source, broker_id: brokerId || null })
        });
        const result = await res.json();
        if (result.success) {
            fetchLeadsData();
        } else {
            alert('Error assigning lead: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while assigning lead.');
    }
};

const _origRespondLead = respondLead;
respondLead = async function(email, source, method) {
    try {
        const res = await fetch(API + '?action=respond_lead', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: source, method: method })
        });
        const result = await res.json();
        if (result.success) {
            fetchLeadsData();
        } else {
            alert('Error marking response: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while marking response.');
    }
};

document.addEventListener('DOMContentLoaded', fetchLeadsData);
