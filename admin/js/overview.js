// ── Overview Page JS ──

// ── Fetch Data (Overview-only) ──
async function fetchData() {
    try {
        // Fetch stats
        const statsResponse = await fetch(API + '?action=stats');
        const stats = await statsResponse.json();

        if (stats.error) {
            console.error("Stats API Error:", stats.error);
            document.getElementById('statSessions').innerText = 'API Error';
            document.getElementById('statLeads').innerText = 'API Error';
            document.getElementById('statConv').innerText = 'API Error';
            document.getElementById('statHot').innerText = '-';
        } else {
            document.getElementById('statSessions').innerText = stats.totalSessions !== undefined ? stats.totalSessions : '-';
            document.getElementById('statLeads').innerText = stats.totalLeads !== undefined ? stats.totalLeads : '-';
            document.getElementById('statConv').innerText = stats.conversionRate !== undefined ? stats.conversionRate : '-';
            document.getElementById('statHot').innerText = stats.newToday !== undefined ? stats.newToday : '0';
            const today = new Date();
            document.getElementById('statTodayDate').innerText = (today.getMonth()+1) + '/' + today.getDate() + '/' + today.getFullYear();
        }

        loadSmsStatus();

        // Fetch brokers for assignment dropdowns
        await fetchBrokers();

        // Fetch leads
        const leadsResponse = await fetch(API + '?action=leads');
        if (!leadsResponse.ok) throw new Error("API Error");

        let leads = await leadsResponse.json();

        leads = leads.map(l => ({
            ...l,
            grade: calculateLeadGrade(l),
            grade_score: calculateLeadGrade(l).score
        }));

        const sortVal = document.getElementById('overview-sort')?.value || 'date';

        if (sortVal === 'grade') {
            leads.sort((a, b) => b.grade.score - a.grade.score);
        } else {
            leads.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        }

        renderOverviewTable(leads);

    } catch (err) {
        console.error("Dashboard error:", err);
    }
}

// ── Render Overview Table ──
function renderOverviewTable(leads) {
    const tbody = document.querySelector('.leadsTable tbody');
    if (!tbody) return;

    if (leads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-body-tertiary py-5">No leads recorded yet.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    leads.forEach(lead => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.onclick = () => viewJourney(lead.tracking_id, lead.email);

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

        // Engagement label
        const activityLabel = lead.event_count > 10 ? '<span class="text-success"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Hot</span>' :
                             lead.event_count > 5 ? '<span class="text-primary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Active</span>' :
                             lead.event_count > 0 ? '<span class="text-body-tertiary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Quiet</span>' :
                             '<span class="text-body-tertiary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> No Activity</span>';
        const engagementLabel = '<span style="font-size:0.8rem;" class="fw-semibold">' + activityLabel + '</span>';

        // Grade pill
        const gradeClass = lead.grade.gradeClass;

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
                + (USER_ROLE === 'owner' ? '<option value="delete">Delete</option>' : '')
                + '</select>'
                + '</div>';
        }

        const submissionBadge = (lead.submission_count > 1)
            ? ' <span class="badge bg-info bg-opacity-25 text-info ms-1" title="' + esc(String(lead.submission_count)) + ' form submissions" style="font-size:0.65rem">' + esc(String(lead.submission_count)) + 'x</span>'
            : '';

        row.innerHTML = '<td>' + timestamp + '</td>'
            + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<div><span class="fw-semibold text-white">' + esc(lead.first_name) + ' ' + esc(lead.last_name) + '</span>' + submissionBadge + '</div></div></td>'
            + '<td>' + contactHtml + '</td>'
            + '<td>' + intentHtml + '</td>'
            + '<td>' + engagementLabel + '</td>'
            + '<td class="text-center"><div class="grade-pill ' + gradeClass + '">' + esc(lead.grade.letter) + '</div></td>'
            + '<td>' + assignedHtml + '</td>'
            + '<td>' + firstResponseHtml + '</td>';

        tbody.appendChild(row);
    });
}

// ── Render Lead Profile (slide-out panel) ──
function renderLeadProfile(intel, logs, container, isPanel) {
    if (isPanel === undefined) isPanel = true;
    const name = intel.full_name || "Lead Profile";
    const photoUrl = safeUrl(intel.photo_url);
    const fallbackAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=6366f1&color=fff&size=128';
    const primaryAvatar = photoUrl || fallbackAvatar;

    let raw = {};
    try {
        raw = typeof intel.raw_response === 'string' ? JSON.parse(intel.raw_response) : (intel.raw_response || {});
    } catch (e) {
        raw = {};
    }

    const person = raw.person || {};
    const org = person.organization || {};
    const employment = person.employment_history || [];
    const education = person.education_history || person.education || [];

    const boardRoles = employment.filter(job =>
        job.current &&
        ['board', 'advisor', 'trustee', 'committee'].some(k => (job.title || '').toLowerCase().includes(k))
    );

    const gradeInfo = calculateLeadGrade({
        ...intel,
        raw_response: raw,
        event_count: logs.length
    });

    const totalScore = gradeInfo.score;
    const grade = gradeInfo.letter;
    const insights = gradeInfo.insights;

    const escapedEmail = esc(intel.email || '');
    const escapedName = esc(name);

    // Close button for panel
    let closeBtn = '';
    if (isPanel) {
        closeBtn = '<button onclick="closeJourney()" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index:10"></button>';
    }

    // LinkedIn / Twitter / Social links
    const linkedinUrl = safeUrl(intel.linkedin_url);
    const twitterUrl = safeUrl(intel.twitter_url);
    const facebookUrl = safeUrl(intel.facebook_url);
    const githubUrl = safeUrl(intel.github_url);
    let socialLinks = '<div class="d-flex justify-content-center gap-3 mt-3">';
    if (linkedinUrl) socialLinks += '<a href="' + esc(linkedinUrl) + '" target="_blank" class="text-primary"><i class="bi bi-linkedin fs-5"></i></a>';
    if (twitterUrl) socialLinks += '<a href="' + esc(twitterUrl) + '" target="_blank" class="text-body-tertiary"><i class="bi bi-twitter-x fs-5"></i></a>';
    if (facebookUrl) socialLinks += '<a href="' + esc(facebookUrl) + '" target="_blank" class="text-body-tertiary"><i class="bi bi-facebook fs-5"></i></a>';
    if (githubUrl) socialLinks += '<a href="' + esc(githubUrl) + '" target="_blank" class="text-body-tertiary"><i class="bi bi-github fs-5"></i></a>';
    socialLinks += '</div>';

    // Phone row
    let phoneHtml = '';
    if (intel.phone_number) {
        phoneHtml = '<div class="action-item"><span>PHONE</span><span class="action-val">' + esc(intel.phone_number) + '</span>'
            + '<button class="icon-btn" id="copyPhoneBtn">Copy</button></div>';
    }

    // Education section
    let educationHtml = '';
    if (education.length > 0) {
        educationHtml = '<div class="intel-item" style="grid-column:span 2"><span class="intel-label">Education</span>';
        education.forEach(function(edu) {
            educationHtml += '<div class="mt-1"><span class="d-block fw-semibold" style="font-size:0.85rem">' + esc(edu.school_name) + '</span>'
                + '<small class="text-body-tertiary">' + esc(edu.degree || '') + (edu.major ? ' &bull; ' + esc(edu.major) : '') + '</small></div>';
        });
        educationHtml += '</div>';
    }

    // Board roles
    let boardHtml = '';
    if (boardRoles.length > 0) {
        boardHtml = '<div class="intel-item" style="grid-column:span 2"><span class="intel-label">Board & Advisory Roles</span>';
        boardRoles.forEach(function(role) {
            boardHtml += '<div class="mt-1"><span class="d-block fw-semibold" style="font-size:0.85rem">' + esc(role.title) + '</span>'
                + '<small class="text-body-tertiary">' + esc(role.organization_name) + '</small></div>';
        });
        boardHtml += '</div>';
    }

    // Journey timeline
    let journeyHtml = '';
    if (logs.length === 0) {
        journeyHtml = '<p class="text-body-tertiary">No events recorded.</p>';
    } else {
        const sortedLogs = logs.slice().sort(function(a, b) { return new Date(a.created_at) - new Date(b.created_at); });
        let lastTime = null;
        sortedLogs.forEach(function(log, idx) {
            const currentTime = new Date(log.created_at);
            const isConversion = log.event_name.includes('submit') || log.event_name.includes('confirm');

            if (lastTime && (currentTime - lastTime) > 30 * 60 * 1000) {
                journeyHtml += '<div class="text-primary text-uppercase fw-bold opacity-50 my-3" style="font-size:0.6rem;letter-spacing:0.2em">&mdash; New Session &mdash;</div>';
            }
            lastTime = currentTime;

            let icon = '\u{1F441}\uFE0F';
            if (log.event_name.includes('hero')) icon = '\u{1F3E1}';
            if (log.event_name.includes('gallery')) icon = '\u{1F5BC}\uFE0F';
            if (log.event_name.includes('unit')) icon = '\u{1F3E2}';
            if (log.event_name.includes('form') || log.event_name.includes('waitlist')) icon = '\u{1F4DD}';
            if (isConversion) icon = '\u2728';

            const borderStyle = isConversion ? 'border-left:2px solid #10b981' : '';
            journeyHtml += '<div class="journey-item" style="' + borderStyle + '">'
                + '<div class="journey-time">' + esc(currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })) + '</div>'
                + '<div class="d-flex align-items-center gap-2"><span style="font-size:0.8rem;opacity:0.8">' + icon + '</span>'
                + '<span class="journey-title">' + esc(log.event_name.replace(/_/g, ' ')) + '</span></div></div>';
        });
    }

    // Build the layout
    let html = closeBtn
        + '<div class="score-circle ' + esc(gradeInfo.gradeClass) + '">'
        + '<div class="score-val">' + esc(grade) + '</div>'
        + '<div class="score-label">Grade</div></div>'
        + '<img src="' + esc(primaryAvatar) + '" class="profile-avatar" onerror="this.src=\'' + esc(fallbackAvatar) + '\'">'
        + '<div class="profile-name">' + escapedName + '</div>'
        + '<div class="profile-title">' + esc(intel.job_title || person.title || '') + (intel.company ? ' @ ' + esc(intel.company) : '') + '</div>'
        + '<div class="d-flex justify-content-center gap-4 flex-wrap my-3 py-2" style="border-top:1px solid rgba(255,255,255,0.06);border-bottom:1px solid rgba(255,255,255,0.06)">'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Submitted</div><div style="font-size:0.8rem" class="text-white">' + esc(intel.created_at ? new Date(intel.created_at).toLocaleString() : 'N/A') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Source</div><div style="font-size:0.8rem" class="text-primary fw-semibold">' + esc(intel.submission_type || 'General') + (intel.unit ? ' — Unit ' + esc(intel.unit) : '') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Budget</div><div style="font-size:0.8rem" class="text-white">' + (intel.budget ? '$' + esc(String(intel.budget)).replace(/^\$/, '') : '—') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Move-In</div><div style="font-size:0.8rem" class="text-white">' + (intel.move_in_date ? esc(intel.move_in_date) : '—') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Assigned</div><div style="font-size:0.8rem" class="text-white">' + (intel.broker_name ? esc(intel.broker_name) : '<span class="text-danger">Unassigned</span>') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">First Response</div><div style="font-size:0.8rem">' + (intel.first_response_at ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> ' + esc(intel.response_method || '') + '</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Pending</span>') + '</div></div>'
        + '</div>'
        + '<div class="action-bar">'
        + '<div class="action-item"><span>EMAIL</span><span class="action-val">' + escapedEmail + '</span>'
        + '<button class="icon-btn" id="copyEmailBtn">Copy</button></div>'
        + phoneHtml
        + '</div>'
        + socialLinks
        + '<div class="mx-auto mt-4" style="max-width:600px">'
            + renderGradeBreakdown(gradeInfo)
            + '<div class="accordion" id="submissionAccordion">'
            + '<div class="accordion-item bg-body-tertiary border-0">'
            + '<h2 class="accordion-header">'
            + '<button class="accordion-button collapsed bg-body-tertiary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#submissionCollapse" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;font-weight:600">'
            + 'Submission Details'
            + '</button></h2>'
            + '<div id="submissionCollapse" class="accordion-collapse collapse" data-bs-parent="#submissionAccordion">'
            + '<div class="accordion-body p-3">'
            + '<table class="table table-sm table-dark mb-0" style="font-size:0.85rem">'
            + '<tbody>'
            + '<tr><td class="text-white-50" style="width:40%">Form</td><td class="text-primary fw-semibold">' + esc(intel.submission_type || 'General Lead') + '</td></tr>'
            + '<tr><td class="text-white-50">Submitted</td><td>' + esc(intel.created_at ? new Date(intel.created_at).toLocaleString() : 'N/A') + '</td></tr>'
            + (intel.phone || intel.phone_number ? '<tr><td class="text-white-50">Phone</td><td>' + esc(intel.phone || intel.phone_number) + '</td></tr>' : '')
            + (intel.unit ? '<tr><td class="text-white-50">Unit</td><td>' + esc(intel.unit) + '</td></tr>' : '')
            + (intel.unit_type ? '<tr><td class="text-white-50">Unit Type</td><td>' + esc(intel.unit_type) + '</td></tr>' : '')
            + (intel.budget ? '<tr><td class="text-white-50">Budget</td><td>$' + esc(String(intel.budget)).replace(/^\$/, '') + '</td></tr>' : '')
            + (intel.move_in_date ? '<tr><td class="text-white-50">Move-In Date</td><td>' + esc(intel.move_in_date) + '</td></tr>' : '')
            + (intel.hear_about_us ? '<tr><td class="text-white-50">How They Found Us</td><td>' + esc(intel.hear_about_us) + '</td></tr>' : '')
            + (intel.interests ? '<tr><td class="text-white-50">Interests</td><td>' + esc(intel.interests) + '</td></tr>' : '')
            + (intel.message ? '<tr><td class="text-white-50">Message</td><td style="white-space:pre-wrap">' + esc(intel.message) + '</td></tr>' : '')
            + '</tbody></table>'
            + '</div></div></div></div>'
        + '</div>';

    container.innerHTML = html;

    // Bind copy buttons after render
    const copyEmailBtn = document.getElementById('copyEmailBtn');
    if (copyEmailBtn) {
        copyEmailBtn.addEventListener('click', function() { copyToClipboard(intel.email || '', this); });
    }
    const copyPhoneBtn = document.getElementById('copyPhoneBtn');
    if (copyPhoneBtn) {
        copyPhoneBtn.addEventListener('click', function() { copyToClipboard(intel.phone_number || '', this); });
    }
}

// ── Fetch Lead Data ──
async function fetchLeadData(email) {
    const intelRes = await fetch(API + '?action=lead_detail&email=' + encodeURIComponent(email));
    const intel = await intelRes.json();
    let logs = [];
    if (email) {
        const logsRes = await fetch(API + '?action=lead_activity&email=' + encodeURIComponent(email));
        logs = await logsRes.json();
    }
    return { intel, logs };
}

// ── Journey Panel (Slide-out) ──
async function viewJourney(sessionId, email) {
    const panel = document.getElementById('journeyPanel');
    panel.innerHTML = '<div class="text-center text-body-tertiary py-5">Generating profile...</div>';
    panel.classList.add('active');

    try {
        const { intel, logs } = await fetchLeadData(email);
        renderLeadProfile(intel, logs, panel, true);
    } catch (err) {
        panel.innerHTML = '<div class="alert alert-danger m-3">Error loading profile.</div>';
    }
}

function closeJourney() {
    document.getElementById('journeyPanel').classList.remove('active');
}

// ── Auto SMS Status Card ──
async function loadSmsStatus() {
    const chipsEl = document.getElementById('smsStatusChips');
    const actionsEl = document.getElementById('smsStatusActions');
    if (!chipsEl) return;

    try {
        const res = await fetch(API + '?action=get_sms_status');
        const s = await res.json();
        renderSmsStatus(s);
    } catch (e) {
        chipsEl.innerHTML = '<span class="badge bg-danger bg-opacity-25 text-danger">Status unavailable</span>';
        actionsEl.innerHTML = '';
    }
}

function renderSmsStatus(s) {
    const chipsEl = document.getElementById('smsStatusChips');
    const actionsEl = document.getElementById('smsStatusActions');
    const isOwner = actionsEl.dataset.isOwner === '1';

    const masterChip = s.master_enabled
        ? '<span class="badge bg-success bg-opacity-25 text-success">Enabled</span>'
        : '<span class="badge bg-secondary bg-opacity-25 text-secondary">Disabled</span>';

    const windowChip = s.in_window
        ? '<span class="badge bg-info bg-opacity-25 text-info">Window open</span>'
        : '<span class="badge bg-secondary bg-opacity-25 text-body-tertiary">Window closed</span>';

    let effectiveChip;
    if (s.effective) {
        effectiveChip = '<span class="badge bg-success text-white"><i class="bi bi-broadcast"></i> Sending now</span>';
    } else {
        const reason = !s.master_enabled ? 'master off'
                     : s.override === 'force_off' ? 'overridden off'
                     : 'outside window';
        effectiveChip = '<span class="badge bg-secondary text-white-50"><i class="bi bi-pause-circle"></i> Paused</span>'
                      + ' <span class="text-body-tertiary small">(' + reason + ')</span>';
    }

    let overrideChip = '';
    if (s.override === 'force_on') {
        overrideChip = '<span class="badge bg-warning bg-opacity-25 text-warning"><i class="bi bi-lightning-charge"></i> Override: force on</span>';
    } else if (s.override === 'force_off') {
        overrideChip = '<span class="badge bg-warning bg-opacity-25 text-warning"><i class="bi bi-mute"></i> Override: force off</span>';
    }

    let nextWindowText = '';
    if (!s.in_window && s.next_window_open) {
        const d = new Date(s.next_window_open);
        nextWindowText = '<span class="text-body-tertiary small">Opens '
                       + d.toLocaleDateString([], { weekday: 'short' }) + ' '
                       + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
                       + '</span>';
    }

    chipsEl.innerHTML = [masterChip, windowChip, effectiveChip, overrideChip, nextWindowText]
        .filter(Boolean).join(' ');

    if (!isOwner || !s.master_enabled) {
        actionsEl.innerHTML = '';
        return;
    }

    const sendingNow = s.effective;
    const overrideActive = s.override && s.override !== 'none';
    const targetOverride = sendingNow ? 'force_off' : 'force_on';
    const btnLabel = sendingNow
        ? '<i class="bi bi-pause-fill"></i> Pause sending'
        : '<i class="bi bi-play-fill"></i> Force send now';
    const btnClass = sendingNow ? 'btn-outline-warning' : 'btn-outline-success';

    let html = '<button type="button" class="btn btn-sm ' + btnClass + '" onclick="setSmsOverride(\'' + targetOverride + '\')">'
             + btnLabel + '</button>';
    if (overrideActive) {
        html += ' <button type="button" class="btn btn-sm btn-link text-body-tertiary p-1" onclick="setSmsOverride(\'none\')" title="Return to schedule">Clear override</button>';
    }
    actionsEl.innerHTML = html;
}

async function setSmsOverride(value) {
    const actionsEl = document.getElementById('smsStatusActions');
    const prev = actionsEl.innerHTML;
    actionsEl.innerHTML = '<span class="text-body-tertiary small">Saving…</span>';

    try {
        const res = await fetch(API + '?action=set_sms_override', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ override: value })
        });
        const data = await res.json();
        if (data.success) {
            await loadSmsStatus();
        } else {
            actionsEl.innerHTML = prev;
            alert(data.error || 'Could not update override');
        }
    } catch (e) {
        actionsEl.innerHTML = prev;
        alert('Network error');
    }
}

// ── Mark dropdown dispatcher (native <select>) ──
function handleMarkAction(email, source, action) {
    if (!action) return;
    switch (action) {
        case 'sms':       respondLead(email, source, 'SMS'); break;
        case 'email':     respondLead(email, source, 'Email'); break;
        case 'phone':     respondLead(email, source, 'Phone'); break;
        case 'auto_text': engageAI(email); break;
        case 'delete':    deleteLeadFromOverview(email, source); break;
    }
}

// ── Delete Lead ──
function deleteLeadFromOverview(email, source) {
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
                    fetchData();
                } else {
                    alert('Error: ' + (result.error || 'Unknown'));
                }
            } catch(err) {
                alert('Connection error deleting lead.');
            }
        }
    });
}

// ── Realtime Subscriptions + Init ──
document.addEventListener('DOMContentLoaded', function() {
    fetchData();

    // Subscribe to realtime changes on key tables
    if (supabaseClient) {
        supabaseClient
            .channel('overview-changes')
            .on('postgres_changes', { event: '*', schema: 'public', table: 'waitlist_submissions' }, () => {
                console.log('Realtime: new waitlist submission');
                fetchData();
            })
            .on('postgres_changes', { event: '*', schema: 'public', table: 'unit_inquiries' }, () => {
                console.log('Realtime: new unit inquiry');
                fetchData();
            })
            .on('postgres_changes', { event: '*', schema: 'public', table: 'lead_enrichment' }, () => {
                console.log('Realtime: enrichment updated');
                fetchData();
            })
            .on('postgres_changes', { event: '*', schema: 'public', table: 'communications' }, () => {
                console.log('Realtime: new communication');
                fetchData();
            })
            .subscribe((status) => {
                console.log('Realtime subscription status:', status);
            });

        console.log('Realtime subscriptions active — no polling needed');
    } else {
        // Fallback to polling if Supabase client unavailable
        console.log('Supabase Realtime unavailable — falling back to 30s polling');
        setInterval(fetchData, 30000);
    }
});
