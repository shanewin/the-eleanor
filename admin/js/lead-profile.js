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

function renderFullProfile(intel, logs, container) {
    const name = esc(intel.full_name || 'Lead Profile');
    const photoUrl = safeUrl(intel.photo_url);
    const fallbackAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(intel.full_name || '') + '&background=6366f1&color=fff&size=128';
    const avatar = photoUrl || fallbackAvatar;

    let raw = {};
    try { raw = typeof intel.raw_response === 'string' ? JSON.parse(intel.raw_response) : (intel.raw_response || {}); } catch(e) { raw = {}; }

    const person = raw.person || {};
    const org = person.organization || {};
    const employment = person.employment_history || [];
    const education = person.education_history || person.education || [];

    const gradeInfo = calculateLeadGrade({ ...intel, raw_response: raw, event_count: logs.length });

    // LinkedIn scraper data (richer)
    const liData = raw.linkedin_scraper || raw.pdl_by_linkedin || {};
    const experiences = liData.experiences || employment;
    const educations = liData.educations || education;
    const about = liData.about || '';

    // Header
    let html = '<div class="card bg-body-tertiary border-0 mb-4"><div class="card-body p-4 text-center">'
        + '<div class="d-flex justify-content-end"><div class="grade-pill ' + (gradeInfo.score >= 80 ? 'elite' : '') + '" style="width:64px;height:64px;font-size:1.5rem">' + esc(gradeInfo.letter) + '</div></div>'
        + '<img src="' + esc(avatar) + '" class="rounded-circle mb-3" style="width:100px;height:100px;object-fit:cover;border:2px solid rgba(255,255,255,0.1)" onerror="this.src=\'' + esc(fallbackAvatar) + '\'">'
        + '<h3 class="fw-bold text-white mb-1">' + name + '</h3>'
        + '<div class="text-white-50 mb-3">' + esc(intel.job_title || '') + (intel.company ? ' @ ' + esc(intel.company) : '') + '</div>';

    // Key info bar
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

    // Left column
    html += '<div class="col-lg-6">';

    // About
    if (about) {
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
            + '<h6 class="text-white-50 text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.1em">About</h6>'
            + '<p class="text-white-50 mb-0" style="font-size:0.85rem;line-height:1.7">' + esc(about).substring(0, 500) + '</p>'
            + '</div></div>';
    }

    // Professional Intel
    html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
        + '<h6 class="text-white-50 text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.1em">Professional Intel</h6>'
        + '<table class="table table-sm table-dark mb-0" style="font-size:0.85rem"><tbody>'
        + '<tr><td class="text-white-50" style="width:40%">Title</td><td>' + esc(intel.job_title || '—') + '</td></tr>'
        + '<tr><td class="text-white-50">Company</td><td>' + esc(intel.company || '—') + '</td></tr>'
        + '<tr><td class="text-white-50">Industry</td><td>' + esc(intel.industry || org.industry || '—') + '</td></tr>'
        + '<tr><td class="text-white-50">Company Size</td><td>' + esc(intel.employee_count || org.estimated_num_employees || '—') + '</td></tr>'
        + '<tr><td class="text-white-50">Location</td><td>' + esc([intel.city, intel.state, intel.country].filter(Boolean).join(', ') || '—') + '</td></tr>'
        + (intel.inferred_salary ? '<tr><td class="text-white-50">Est. Salary</td><td class="fw-semibold">$' + esc(intel.inferred_salary) + '</td></tr>' : '')
        + (intel.headline ? '<tr><td class="text-white-50">Headline</td><td style="font-size:0.8rem">' + esc(intel.headline).substring(0, 200) + '</td></tr>' : '')
        + '</tbody></table></div></div>';

    // Career History
    if (experiences.length > 0) {
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
            + '<h6 class="text-white-50 text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.1em">Career History</h6>'
            + '<div class="d-flex flex-column gap-3 mt-2">';
        experiences.slice(0, 8).forEach(function(job, idx) {
            const title = esc(job.title || job.job_title || '—');
            const company = esc(job.company || job.organization_name || '—');
            const isCurrent = job.is_current || job.current;
            const dotColor = isCurrent ? '#10b981' : 'rgba(255,255,255,0.15)';
            const desc = job.description ? '<div class="text-white-50 mt-1" style="font-size:0.75rem;line-height:1.5">' + esc(job.description).substring(0, 150) + '...</div>' : '';
            html += '<div class="d-flex gap-3 align-items-start">'
                + '<div style="width:8px;height:8px;border-radius:50%;background:' + dotColor + ';margin-top:0.5rem;flex-shrink:0"></div>'
                + '<div><div class="fw-medium text-white" style="font-size:0.85rem">' + title + '</div>'
                + '<small class="text-primary">' + company + '</small>'
                + (isCurrent ? '<span class="badge bg-success bg-opacity-25 text-success ms-2" style="font-size:0.6rem">Current</span>' : '')
                + desc + '</div></div>';
        });
        html += '</div></div></div>';
    }

    html += '</div>'; // end left col

    // Right column
    html += '<div class="col-lg-6">';

    // Submission Details
    html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
        + '<h6 class="text-white-50 text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.1em">Submission Details</h6>'
        + '<table class="table table-sm table-dark mb-0" style="font-size:0.85rem"><tbody>'
        + '<tr><td class="text-white-50" style="width:40%">Form</td><td class="text-primary fw-semibold">' + esc(intel.submission_type || 'General') + '</td></tr>'
        + '<tr><td class="text-white-50">Submitted</td><td>' + esc(intel.created_at ? new Date(intel.created_at).toLocaleString() : 'N/A') + '</td></tr>'
        + (intel.unit ? '<tr><td class="text-white-50">Unit</td><td>' + esc(intel.unit) + '</td></tr>' : '')
        + (intel.unit_type ? '<tr><td class="text-white-50">Unit Type</td><td>' + esc(intel.unit_type) + '</td></tr>' : '')
        + (intel.budget ? '<tr><td class="text-white-50">Budget</td><td>$' + esc(String(intel.budget)).replace(/^\$/, '') + '</td></tr>' : '')
        + (intel.move_in_date ? '<tr><td class="text-white-50">Move-In</td><td>' + esc(intel.move_in_date) + '</td></tr>' : '')
        + (intel.hear_about_us ? '<tr><td class="text-white-50">How Found Us</td><td>' + esc(intel.hear_about_us) + '</td></tr>' : '')
        + (intel.message ? '<tr><td class="text-white-50">Message</td><td style="white-space:pre-wrap">' + esc(intel.message) + '</td></tr>' : '')
        + '</tbody></table></div></div>';

    // Grade Explanation
    html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
        + '<div class="d-flex justify-content-between align-items-center mb-3">'
        + '<h6 class="text-white-50 text-uppercase fw-semibold mb-0" style="font-size:0.7rem;letter-spacing:0.1em">Grade Breakdown</h6>'
        + '<div class="d-flex align-items-center gap-2"><span class="grade-pill ' + (gradeInfo.score >= 80 ? 'elite' : '') + '" style="width:36px;height:36px;font-size:1rem">' + esc(gradeInfo.letter) + '</span><span class="text-white-50" style="font-size:0.8rem">' + gradeInfo.score + '/100</span></div>'
        + '</div>'
        + '<div class="d-flex flex-column gap-2">';
    gradeInfo.insights.forEach(function(i) {
        const color = i.points > 0 ? 'text-success' : (i.points < 0 ? 'text-danger' : 'text-white-50');
        const sign = i.points > 0 ? '+' : '';
        html += '<div class="d-flex justify-content-between align-items-center">'
            + '<span style="font-size:0.85rem">' + i.icon + ' ' + esc(i.label) + '</span>'
            + '<span class="' + color + ' fw-semibold" style="font-size:0.85rem">' + sign + i.points + '</span>'
            + '</div>';
    });
    if (gradeInfo.insights.length === 0) {
        html += '<div class="text-white-50 text-center" style="font-size:0.85rem">No scoring signals available</div>';
    }
    html += '</div></div></div>';

    // Education
    if (educations.length > 0) {
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
            + '<h6 class="text-white-50 text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.1em">Education</h6>'
            + '<div class="d-flex flex-column gap-2 mt-2">';
        educations.slice(0, 4).forEach(function(edu) {
            const school = esc(edu.school || edu.school_name || (edu.school && edu.school.name) || '—');
            const degree = esc([edu.degree, edu.field_of_study, edu.major].filter(Boolean).join(', ') || '');
            html += '<div><div class="fw-medium text-white" style="font-size:0.85rem">' + school + '</div>'
                + (degree ? '<small class="text-white-50">' + degree + '</small>' : '') + '</div>';
        });
        html += '</div></div></div>';
    }

    // Engagement Timeline
    if (logs.length > 0) {
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
            + '<h6 class="text-white-50 text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.1em">Engagement Timeline (' + logs.length + ' events)</h6>'
            + '<div class="d-flex flex-column gap-2 mt-2" style="max-height:300px;overflow-y:auto">';
        logs.slice(-15).forEach(function(log) {
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
        html += '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4 text-center text-white-50">No engagement events recorded.</div></div>';
    }

    html += '<div class="text-center mt-2">'
        + '<a href="/admin/communications.php?email=' + encodeURIComponent(intel.email || '') + '" class="text-primary text-decoration-none" style="font-size:0.8rem">'
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
