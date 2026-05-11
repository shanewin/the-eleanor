// Settings page JavaScript

async function loadSettings() {
    try {
        const res = await fetch('/api/admin-api.php?action=get_settings');
        const settings = await res.json();
        document.getElementById('settingsNotificationEmails').value = settings.notification_emails || '';

        // SMS settings
        document.getElementById('smsEnabled').checked = (settings.sms_enabled === 'on');
        document.getElementById('smsWindowStart').value = settings.sms_window_start || '09:00';
        document.getElementById('smsWindowEnd').value = settings.sms_window_end || '19:00';
        document.getElementById('smsCampaignStart').value = settings.sms_campaign_start || '';
        document.getElementById('smsCampaignEnd').value = settings.sms_campaign_end || '';

        // Active days
        const activeDays = (settings.sms_active_days || '1,2,3,4,5').split(',').map(d => d.trim());
        document.querySelectorAll('.sms-day-check').forEach(cb => {
            cb.checked = activeDays.includes(cb.value);
            updateDayBtnStyle(cb);
        });

        // AI messaging settings
        document.getElementById('aiTone').value = settings.ai_tone || 'friendly';
        document.getElementById('aiWelcomeInstructions').value = settings.ai_welcome_instructions || '';
        document.getElementById('aiTourHours').value = settings.ai_tour_hours || '';
        document.getElementById('aiTalkingPoints').value = settings.ai_talking_points || '';
        document.getElementById('aiExtraPropertyInfo').value = settings.ai_extra_property_info || '';
        document.getElementById('aiOffLimits').value = settings.ai_off_limits || '';
    } catch (e) {
        console.error('Failed to load settings:', e);
    }
}

function updateDayBtnStyle(cb) {
    const btn = cb.closest('label');
    if (cb.checked) {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary');
    } else {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-secondary');
    }
}

async function saveSettingsForm() {
    const statusEl = document.getElementById('settingsStatus');
    const emails = document.getElementById('settingsNotificationEmails').value.trim();

    if (!emails) {
        statusEl.className = 'small mb-3 text-danger';
        statusEl.textContent = 'Please enter at least one email address.';
        statusEl.style.display = 'block';
        return;
    }

    try {
        const res = await fetch('/api/admin-api.php?action=save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_emails: emails })
        });
        const data = await res.json();
        if (data.success) {
            statusEl.className = 'small mb-3 text-success';
            statusEl.textContent = 'Settings saved successfully.';
        } else {
            statusEl.className = 'small mb-3 text-danger';
            statusEl.textContent = data.error || 'Failed to save settings.';
        }
    } catch (e) {
        statusEl.className = 'small mb-3 text-danger';
        statusEl.textContent = 'Network error. Please try again.';
    }
    statusEl.style.display = 'block';
}

async function saveSMSSettings() {
    const statusEl = document.getElementById('smsSettingsStatus');

    // Gather active days
    const activeDays = [];
    document.querySelectorAll('.sms-day-check').forEach(cb => {
        if (cb.checked) activeDays.push(cb.value);
    });

    const payload = {
        sms_enabled: document.getElementById('smsEnabled').checked ? 'on' : 'off',
        sms_window_start: document.getElementById('smsWindowStart').value,
        sms_window_end: document.getElementById('smsWindowEnd').value,
        sms_active_days: activeDays.join(','),
        sms_campaign_start: document.getElementById('smsCampaignStart').value,
        sms_campaign_end: document.getElementById('smsCampaignEnd').value
    };

    try {
        const res = await fetch('/api/admin-api.php?action=save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            statusEl.className = 'small mb-3 text-success';
            statusEl.textContent = 'SMS settings saved successfully.';
        } else {
            statusEl.className = 'small mb-3 text-danger';
            statusEl.textContent = data.error || 'Failed to save SMS settings.';
        }
    } catch (e) {
        statusEl.className = 'small mb-3 text-danger';
        statusEl.textContent = 'Network error. Please try again.';
    }
    statusEl.style.display = 'block';
    setTimeout(() => statusEl.style.display = 'none', 3000);
}

async function saveAISettings() {
    const statusEl = document.getElementById('aiSettingsStatus');
    const payload = {
        ai_tone: document.getElementById('aiTone').value,
        ai_welcome_instructions: document.getElementById('aiWelcomeInstructions').value.trim(),
        ai_tour_hours: document.getElementById('aiTourHours').value.trim(),
        ai_talking_points: document.getElementById('aiTalkingPoints').value.trim(),
        ai_extra_property_info: document.getElementById('aiExtraPropertyInfo').value.trim(),
        ai_off_limits: document.getElementById('aiOffLimits').value.trim()
    };

    try {
        const res = await fetch('/api/admin-api.php?action=save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            statusEl.className = 'small mb-3 text-success';
            statusEl.textContent = 'AI settings saved successfully.';
        } else {
            statusEl.className = 'small mb-3 text-danger';
            statusEl.textContent = data.error || 'Failed to save AI settings.';
        }
    } catch (e) {
        statusEl.className = 'small mb-3 text-danger';
        statusEl.textContent = 'Network error. Please try again.';
    }
    statusEl.style.display = 'block';
    setTimeout(() => statusEl.style.display = 'none', 3000);
}

function showSettingsTab(tab, event) {
    if (event) event.preventDefault();
    // Toggle tab links
    document.querySelectorAll('#settingsTabs .nav-link').forEach(link => {
        if (link.getAttribute('data-settings-tab') === tab) {
            link.classList.add('active', 'text-white');
            link.classList.remove('text-white-50');
        } else {
            link.classList.remove('active', 'text-white');
            link.classList.add('text-white-50');
        }
    });
    // Toggle tab panes
    document.querySelectorAll('.settings-tab-pane').forEach(pane => {
        pane.style.display = pane.id === 'settingsTab-' + tab ? 'block' : 'none';
    });
    // Refresh on open for tabs whose data may be stale
    if (tab === 'jobs') loadLeadJobs();
}

// ─── Lead-processing jobs tab ───────────────────────────────────────────────

function escapeJobsHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
}

function formatJobsTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d)) return iso;
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function renderJobRow(job, withRetry) {
    const steps = Array.isArray(job.steps_done) ? job.steps_done.join(', ') : (job.steps_done || '—');
    const err = job.last_error ? `<div class="text-danger small mt-1" style="word-break:break-word">${escapeJobsHtml(job.last_error)}</div>` : '';
    const retryBtn = withRetry
        ? `<button class="btn btn-sm btn-outline-primary" onclick="retryLeadJob(${job.id})">Retry</button>`
        : '';
    return `
        <div class="d-flex justify-content-between align-items-start py-2 border-bottom border-secondary border-opacity-25">
            <div class="flex-grow-1 me-3">
                <div class="text-white small fw-semibold">${escapeJobsHtml(job.lead_email)}</div>
                <div class="text-white-50" style="font-size:0.75rem">
                    ${escapeJobsHtml(job.source_table)} #${job.source_id} · attempts ${job.attempts} · updated ${formatJobsTime(job.updated_at)}
                </div>
                <div class="text-white-50" style="font-size:0.75rem">steps: ${escapeJobsHtml(steps) || '—'}</div>
                ${err}
            </div>
            <div>${retryBtn}</div>
        </div>
    `;
}

function renderJobRowDone(job) {
    return `
        <div class="d-flex justify-content-between align-items-center py-1" style="font-size:0.8rem">
            <span class="text-white-50">${escapeJobsHtml(job.lead_email)} · ${escapeJobsHtml(job.source_table)}</span>
            <span class="text-white-50">${formatJobsTime(job.updated_at)}</span>
        </div>
    `;
}

async function loadLeadJobs() {
    const failedEl = document.getElementById('jobsFailedBody');
    const stuckEl  = document.getElementById('jobsStuckBody');
    const doneEl   = document.getElementById('jobsDoneBody');
    const badgeEl  = document.getElementById('jobsTabBadge');
    if (!failedEl || !stuckEl || !doneEl) return;

    try {
        const res = await fetch('/api/admin-api.php?action=get_lead_jobs');
        const data = await res.json();
        if (data.error) {
            failedEl.innerHTML = `<div class="text-danger small">${escapeJobsHtml(data.error)}</div>`;
            stuckEl.innerHTML = '';
            doneEl.innerHTML = '';
            return;
        }

        const failed = data.failed || [];
        const stuck = data.stuck || [];
        const done  = data.recent_done || [];

        failedEl.innerHTML = failed.length
            ? failed.map(j => renderJobRow(j, true)).join('')
            : '<div class="text-white-50 small">No failed jobs. 🎉</div>';
        stuckEl.innerHTML = stuck.length
            ? stuck.map(j => renderJobRow(j, true)).join('')
            : '<div class="text-white-50 small">Nothing stuck.</div>';
        doneEl.innerHTML = done.length
            ? done.map(renderJobRowDone).join('')
            : '<div class="text-white-50 small">No recent completions.</div>';

        // Tab badge: total of attention-needed jobs.
        const attention = failed.length + stuck.length;
        if (badgeEl) {
            if (attention > 0) {
                badgeEl.textContent = attention;
                badgeEl.style.display = 'inline-block';
            } else {
                badgeEl.style.display = 'none';
            }
        }
    } catch (e) {
        failedEl.innerHTML = `<div class="text-danger small">Failed to load jobs: ${escapeJobsHtml(e.message)}</div>`;
    }
}

async function retryLeadJob(id) {
    try {
        const res = await fetch('/api/admin-api.php?action=retry_lead_job', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Retry failed');
            return;
        }
        loadLeadJobs();
    } catch (e) {
        alert('Retry failed: ' + e.message);
    }
}

// Day button toggle styling
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sms-day-check').forEach(cb => {
        cb.closest('label').addEventListener('click', function(e) {
            e.preventDefault();
            cb.checked = !cb.checked;
            updateDayBtnStyle(cb);
        });
    });

    loadSettings();
    loadLeadJobs();
});
