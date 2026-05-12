// ── Lead Profile Page ──

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

// All form fields we want to surface — including blank ones — so brokers see
// exactly what the applicant did and didn't fill in.
const FORM_FIELDS = [
    { key: 'first_name',     label: 'First Name' },
    { key: 'last_name',      label: 'Last Name' },
    { key: 'email',          label: 'Email' },
    { key: 'phone',          label: 'Phone' },
    { key: 'unit',           label: 'Unit' },
    { key: 'unit_type',      label: 'Unit Type' },
    { key: 'budget',         label: 'Budget',        prefix: '$' },
    { key: 'move_in_date',   label: 'Move-In Date' },
    { key: 'hear_about_us',  label: 'How They Heard About Us' },
    { key: 'message',        label: 'Message',       multiline: true }
];

function renderFormSubmissions(submissions) {
    if (!submissions || submissions.length === 0) {
        return '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4 text-white-50 text-center">No form submissions found.</div></div>';
    }

    let html = '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
        + '<div class="d-flex justify-content-between align-items-center mb-3">'
        + '<h6 class="text-white-50 text-uppercase fw-semibold mb-0" style="font-size:0.7rem;letter-spacing:0.1em">'
        + '<i class="bi bi-file-earmark-text me-1 text-info"></i>Form Submissions'
        + '</h6>'
        + (submissions.length > 1 ? '<span class="badge bg-info bg-opacity-25 text-info" style="font-size:0.65rem">' + submissions.length + ' submissions</span>' : '')
        + '</div>';

    submissions.forEach((s, idx) => {
        const sourceLabel = s.source || s.submission_type || 'Form';
        const sourceColor = sourceLabel === 'Waitlist' ? 'primary' : (sourceLabel === 'Unit Interest' ? 'success' : 'secondary');
        const dt = s.created_at ? new Date(s.created_at).toLocaleString() : 'N/A';

        html += '<div class="' + (idx > 0 ? 'mt-4 pt-4 border-top border-secondary' : '') + '">';

        // Submission header
        html += '<div class="d-flex justify-content-between align-items-center mb-3">'
            + '<span class="badge bg-' + sourceColor + ' bg-opacity-25 text-' + sourceColor + '" style="font-size:0.75rem">'
            + '<i class="bi bi-clipboard-check me-1"></i>' + esc(sourceLabel)
            + '</span>'
            + '<span class="text-white-50" style="font-size:0.75rem"><i class="bi bi-clock me-1"></i>' + esc(dt) + '</span>'
            + '</div>';

        html += '<table class="table table-sm table-dark mb-0" style="font-size:0.85rem"><tbody>';
        FORM_FIELDS.forEach(f => {
            const raw = s[f.key];
            const filled = raw !== null && raw !== undefined && String(raw).trim() !== '';
            const displayVal = filled
                ? esc((f.prefix || '') + String(raw).replace(/^\$/, ''))
                : '<span class="text-white-50 fst-italic" style="opacity:0.5">left blank</span>';
            const valStyle = f.multiline && filled ? ' style="white-space:pre-wrap"' : '';
            html += '<tr>'
                + '<td class="text-white-50" style="width:38%;font-size:0.78rem">' + esc(f.label) + '</td>'
                + '<td' + valStyle + ' class="' + (filled ? 'text-white' : '') + '">' + displayVal + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        html += '</div>';
    });

    html += '</div></div>';
    return html;
}

function renderGradeBreakdown(gradeInfo) {
    let html = '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">';

    // Header with score
    html += '<div class="d-flex justify-content-between align-items-center mb-3">'
        + '<div>'
        + '<h6 class="text-white-50 text-uppercase fw-semibold mb-0" style="font-size:0.7rem;letter-spacing:0.1em">'
        + '<i class="bi bi-graph-up-arrow me-1 text-info"></i>Grade Breakdown'
        + '</h6>'
        + '<small class="text-white-50">Why this lead got a ' + esc(gradeInfo.letter) + '</small>'
        + '</div>'
        + '<div class="d-flex align-items-center gap-2">'
        + '<span class="grade-pill ' + esc(gradeInfo.gradeClass) + '" style="width:48px;height:48px;font-size:1.2rem">' + esc(gradeInfo.letter) + '</span>'
        + '<span class="text-white-50 fw-semibold" style="font-size:0.95rem">' + gradeInfo.score + '/100</span>'
        + '</div>'
        + '</div>';

    // Earned signals
    if (gradeInfo.insights.length > 0) {
        html += '<div class="mb-3"><div class="small fw-semibold text-success mb-2"><i class="bi bi-check2-circle me-1"></i>Earned</div>';
        gradeInfo.insights.forEach(i => {
            const sign = i.points > 0 ? '+' : '';
            const color = i.points > 0 ? 'text-success' : (i.points < 0 ? 'text-danger' : 'text-white-50');
            html += '<div class="rounded-3 p-2 mb-2" style="background:rgba(255,255,255,0.03)">'
                + '<div class="d-flex justify-content-between align-items-center mb-1">'
                + '<span class="text-white" style="font-size:0.85rem">' + i.icon + ' ' + esc(i.label) + '</span>'
                + '<span class="' + color + ' fw-semibold" style="font-size:0.85rem">' + sign + i.points + '</span>'
                + '</div>'
                + (i.reason ? '<div class="text-white-50" style="font-size:0.75rem;line-height:1.5">' + esc(i.reason) + '</div>' : '')
                + '</div>';
        });
        html += '</div>';
    }

    // Missed opportunities
    if (gradeInfo.missed && gradeInfo.missed.length > 0) {
        html += '<div><div class="small fw-semibold text-white-50 mb-2"><i class="bi bi-dash-circle me-1"></i>Missed Opportunities</div>';
        gradeInfo.missed.forEach(m => {
            html += '<div class="rounded-3 p-2 mb-2" style="background:rgba(255,255,255,0.02);border-left:2px solid rgba(255,255,255,0.08)">'
                + '<div class="text-white-50" style="font-size:0.8rem">' + esc(m.label) + '</div>'
                + (m.reason ? '<div class="text-white-50" style="font-size:0.72rem;line-height:1.5;opacity:0.7;margin-top:2px">' + esc(m.reason) + '</div>' : '')
                + '</div>';
        });
        html += '</div>';
    }

    if (gradeInfo.insights.length === 0 && (!gradeInfo.missed || gradeInfo.missed.length === 0)) {
        html += '<div class="text-white-50 text-center" style="font-size:0.85rem">No scoring signals available</div>';
    }

    html += '</div></div>';
    return html;
}

function renderProfessionalIntel(intel, raw, org, experiences, educations) {
    const hasAny = intel.job_title || intel.company || intel.industry || intel.inferred_salary
        || experiences.length > 0 || educations.length > 0;

    if (!hasAny) {
        return '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4 text-white-50 text-center" style="font-size:0.85rem">No professional enrichment data available for this lead.</div></div>';
    }

    let html = '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
        + '<h6 class="text-white-50 text-uppercase fw-semibold mb-3" style="font-size:0.7rem;letter-spacing:0.1em">'
        + '<i class="bi bi-briefcase me-1 text-info"></i>Professional Intel'
        + '</h6>';

    // Key facts table
    html += '<table class="table table-sm table-dark mb-3" style="font-size:0.85rem"><tbody>'
        + (intel.job_title || intel.company ? '<tr><td class="text-white-50" style="width:38%">Current Role</td><td class="text-white">' + esc(intel.job_title || '—') + (intel.company ? ' @ <span class="text-primary">' + esc(intel.company) + '</span>' : '') + '</td></tr>' : '')
        + (intel.industry || org.industry ? '<tr><td class="text-white-50">Industry</td><td>' + esc(intel.industry || org.industry) + '</td></tr>' : '')
        + (intel.employee_count || org.estimated_num_employees ? '<tr><td class="text-white-50">Company Size</td><td>' + esc(intel.employee_count || org.estimated_num_employees) + '</td></tr>' : '')
        + (intel.city || intel.state || intel.country ? '<tr><td class="text-white-50">Location</td><td>' + esc([intel.city, intel.state, intel.country].filter(Boolean).join(', ')) + '</td></tr>' : '')
        + (intel.inferred_salary ? '<tr><td class="text-white-50">Est. Salary</td><td class="fw-semibold text-success">$' + esc(intel.inferred_salary) + '</td></tr>' : '')
        + (intel.headline ? '<tr><td class="text-white-50">Headline</td><td style="font-size:0.8rem">' + esc(intel.headline).substring(0, 200) + '</td></tr>' : '')
        + '</tbody></table>';

    // Career History
    if (experiences.length > 0) {
        html += '<div class="mb-3"><div class="small fw-semibold text-white-50 mb-2">Career History</div>'
            + '<div class="d-flex flex-column gap-3">';
        experiences.slice(0, 6).forEach(function(job) {
            const title = esc(job.title || job.job_title || '—');
            const company = esc(job.company || job.organization_name || '—');
            const isCurrent = job.is_current || job.current;
            const dotColor = isCurrent ? '#10b981' : 'rgba(255,255,255,0.15)';
            const desc = job.description ? '<div class="text-white-50 mt-1" style="font-size:0.72rem;line-height:1.5">' + esc(job.description).substring(0, 140) + '…</div>' : '';
            html += '<div class="d-flex gap-2 align-items-start">'
                + '<div style="width:8px;height:8px;border-radius:50%;background:' + dotColor + ';margin-top:0.5rem;flex-shrink:0"></div>'
                + '<div><div class="fw-medium text-white" style="font-size:0.85rem">' + title + '</div>'
                + '<small class="text-primary">' + company + '</small>'
                + (isCurrent ? '<span class="badge bg-success bg-opacity-25 text-success ms-2" style="font-size:0.6rem">Current</span>' : '')
                + desc + '</div></div>';
        });
        html += '</div></div>';
    }

    // Education
    if (educations.length > 0) {
        html += '<div><div class="small fw-semibold text-white-50 mb-2">Education</div>'
            + '<div class="d-flex flex-column gap-2">';
        educations.slice(0, 4).forEach(function(edu) {
            const school = esc(edu.school || edu.school_name || (edu.school && edu.school.name) || '—');
            const degree = esc([edu.degree, edu.field_of_study, edu.major].filter(Boolean).join(', ') || '');
            html += '<div><div class="fw-medium text-white" style="font-size:0.85rem">' + school + '</div>'
                + (degree ? '<small class="text-white-50">' + degree + '</small>' : '') + '</div>';
        });
        html += '</div></div>';
    }

    html += '</div></div>';
    return html;
}

function renderFullProfile(intel, logs, container) {
    const name = esc(intel.full_name || (intel.first_name + ' ' + intel.last_name).trim() || 'Lead Profile');
    const photoUrl = safeUrl(intel.photo_url);
    const fallbackAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(intel.full_name || name) + '&background=6366f1&color=fff&size=128';
    const avatar = photoUrl || fallbackAvatar;

    let raw = {};
    try { raw = typeof intel.raw_response === 'string' ? JSON.parse(intel.raw_response) : (intel.raw_response || {}); } catch(e) { raw = {}; }

    const person = raw.person || {};
    const org = person.organization || {};
    const employment = person.employment_history || [];
    const education = person.education_history || person.education || [];

    const gradeInfo = calculateLeadGrade({ ...intel, raw_response: raw, event_count: logs.length });

    const liData = raw.linkedin_scraper || raw.pdl_by_linkedin || {};
    const experiences = liData.experiences || employment;
    const educations = liData.educations || education;

    // Header card — color-coded grade pill in top-right
    let html = '<div class="card bg-body-tertiary border-0 mb-4"><div class="card-body p-4 text-center">'
        + '<div class="d-flex justify-content-end">'
        + '<div class="grade-pill ' + esc(gradeInfo.gradeClass) + '" style="width:64px;height:64px;font-size:1.5rem" title="' + gradeInfo.score + '/100">'
        + esc(gradeInfo.letter)
        + '</div>'
        + '</div>'
        + '<img src="' + esc(avatar) + '" class="rounded-circle mb-3" style="width:100px;height:100px;object-fit:cover;border:2px solid rgba(255,255,255,0.1)" onerror="this.src=\'' + esc(fallbackAvatar) + '\'">'
        + '<h3 class="fw-bold text-white mb-1">' + name + '</h3>'
        + '<div class="text-white-50 mb-3">' + esc(intel.job_title || '') + (intel.company ? ' @ ' + esc(intel.company) : '') + '</div>';

    // Quick stats bar
    html += '<div class="d-flex justify-content-center gap-4 flex-wrap py-2 mb-3" style="border-top:1px solid rgba(255,255,255,0.06);border-bottom:1px solid rgba(255,255,255,0.06)">'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Submitted</div><div style="font-size:0.8rem" class="text-white">' + esc(intel.created_at ? new Date(intel.created_at).toLocaleString() : 'N/A') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Source</div><div style="font-size:0.8rem" class="text-primary fw-semibold">' + esc(intel.submission_type || 'General') + (intel.unit ? ' — Unit ' + esc(intel.unit) : '') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Budget</div><div style="font-size:0.8rem" class="text-white">' + (intel.budget ? '$' + esc(String(intel.budget)).replace(/^\$/, '') : '—') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Move-In</div><div style="font-size:0.8rem" class="text-white">' + (intel.move_in_date ? esc(intel.move_in_date) : '—') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">Assigned</div><div style="font-size:0.8rem">' + (intel.broker_name ? esc(intel.broker_name) : '<span class="text-danger">Unassigned</span>') + '</div></div>'
        + '<div class="text-center"><div class="text-white-50" style="font-size:0.6rem;text-transform:uppercase;letter-spacing:0.1em">First Response</div><div style="font-size:0.8rem">' + (intel.first_response_at ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> ' + esc(intel.response_method || '') + '</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Pending</span>') + '</div></div>'
        + '</div>';

    // Contact + Social
    html += '<div class="d-flex justify-content-center gap-4 mb-3">'
        + '<div><small class="text-white-50">EMAIL</small><div class="text-white" style="font-size:0.9rem">' + esc(intel.email || '') + '</div></div>'
        + '<div><small class="text-white-50">PHONE</small><div class="text-white" style="font-size:0.9rem">' + esc(intel.phone || intel.phone_number || '') + '</div></div>'
        + '</div>';

    const linkedinUrl = safeUrl(intel.linkedin_url);
    const twitterUrl = safeUrl(intel.twitter_url);
    const facebookUrl = safeUrl(intel.facebook_url);
    const githubUrl = safeUrl(intel.github_url);
    html += '<div class="d-flex justify-content-center gap-3">';
    if (linkedinUrl) html += '<a href="' + esc(linkedinUrl) + '" target="_blank" class="text-primary"><i class="bi bi-linkedin fs-5"></i></a>';
    if (twitterUrl) html += '<a href="' + esc(twitterUrl) + '" target="_blank" class="text-body-tertiary"><i class="bi bi-twitter-x fs-5"></i></a>';
    if (facebookUrl) html += '<a href="' + esc(facebookUrl) + '" target="_blank" class="text-body-tertiary"><i class="bi bi-facebook fs-5"></i></a>';
    if (githubUrl) html += '<a href="' + esc(githubUrl) + '" target="_blank" class="text-body-tertiary"><i class="bi bi-github fs-5"></i></a>';
    html += '</div></div></div>';

    // Two-column layout
    html += '<div class="row g-4">';

    // Left column: Form Submissions + Professional Intel
    html += '<div class="col-lg-7">';
    html += renderFormSubmissions(intel.submissions && intel.submissions.length > 0 ? intel.submissions : [intel]);
    html += renderProfessionalIntel(intel, raw, org, experiences, educations);
    html += '</div>';

    // Right column: Grade Breakdown + Engagement
    html += '<div class="col-lg-5">';
    html += renderGradeBreakdown(gradeInfo);

    // Engagement Timeline
    if (logs.length > 0) {
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
            + '<h6 class="text-white-50 text-uppercase fw-semibold mb-2" style="font-size:0.7rem;letter-spacing:0.1em">'
            + '<i class="bi bi-activity me-1 text-info"></i>Engagement Timeline (' + logs.length + ' events)'
            + '</h6>'
            + '<div class="d-flex flex-column gap-2 mt-2" style="max-height:400px;overflow-y:auto">';
        logs.slice(-25).reverse().forEach(function(log) {
            const evtData = typeof log.event_data === 'string' ? JSON.parse(log.event_data || '{}') : (log.event_data || {});
            const time = new Date(log.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            const label = esc((log.event_name || '').replace(/_/g, ' '));
            const detail = evtData.section || evtData.text || '';
            html += '<div class="d-flex gap-2 align-items-center">'
                + '<small class="text-white-50" style="width:50px;flex-shrink:0">' + esc(time) + '</small>'
                + '<small class="text-white">' + label + (detail ? ' — <span class="text-white-50">' + esc(detail) + '</span>' : '') + '</small>'
                + '</div>';
        });
        html += '</div></div></div>';
    } else {
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4 text-center text-white-50" style="font-size:0.85rem">No engagement events recorded.</div></div>';
    }

    html += '<div class="text-center mt-2">'
        + '<a href="/admin/communications.php?email=' + encodeURIComponent(intel.email || '') + '" class="text-primary text-decoration-none" style="font-size:0.85rem">'
        + '<i class="bi bi-chat-left-text me-1"></i>View Communications'
        + '</a></div>';

    html += '</div>'; // end right col
    html += '</div>'; // end row

    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', async () => {
    if (!LEAD_EMAIL) {
        document.getElementById('leadProfileContent').innerHTML = '<div class="alert alert-warning">No email specified.</div>';
        return;
    }
    const container = document.getElementById('leadProfileContent');
    try {
        const { intel, logs } = await fetchLeadData(LEAD_EMAIL);
        renderFullProfile(intel, logs, container);
    } catch(err) {
        container.innerHTML = '<div class="alert alert-danger">Error loading profile.</div>';
    }
});
