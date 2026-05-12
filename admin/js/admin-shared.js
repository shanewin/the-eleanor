// API base path
const API = '/api/admin-api.php';

// ── Supabase Realtime Client ──
let supabaseClient = null;
if (typeof SUPABASE_URL !== 'undefined' && SUPABASE_URL && typeof supabase !== 'undefined') {
    supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY);
}

// ── HTML Escaping ──
function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ── URL Validation ──
function safeUrl(url) {
    if (!url) return '';
    const s = String(url).trim();
    if (/^https?:\/\//i.test(s)) return s;
    return '';
}

// ── Tenure Formatting ──
function formatTenure(years) {
    if (years <= 0) return "N/A";
    if (years < 1) return "< 1 year";
    return Math.floor(years) + (Math.floor(years) === 1 ? " year" : " years");
}

// ── Elapsed Time Formatting ──
function formatElapsed(ms) {
    if (ms < 60000) return '< 1m';
    if (ms < 3600000) return Math.floor(ms / 60000) + 'm';
    if (ms < 86400000) return Math.floor(ms / 3600000) + 'h ' + Math.floor((ms % 3600000) / 60000) + 'm';
    return Math.floor(ms / 86400000) + 'd ' + Math.floor((ms % 86400000) / 3600000) + 'h';
}

// ── Lead Grading Algorithm (Leasing-Focused) ──
function calculateLeadGrade(lead) {
    const insights = [];        // Earned signals (with details)
    const missed = [];          // Missed-opportunity signals
    const source = (lead.source || lead.submission_type || '').toLowerCase();

    // 1. Affordability Check (30 pts max, -10 worst case)
    // If the lead didn't submit a budget, the server computes an implied_budget
    // from their unit or the portfolio minimum so we can still run the check.
    const salary = lead.inferred_salary || '';
    const explicitBudget = parseFloat((lead.budget || '0').replace(/[^0-9.]/g, ''));
    const impliedBudget = parseFloat(lead.implied_budget || 0);
    const budget = explicitBudget > 0 ? explicitBudget : impliedBudget;
    const budgetIsImplied = explicitBudget <= 0 && impliedBudget > 0;
    const budgetSource = budgetIsImplied ? (lead.implied_budget_source || 'implied') : 'submitted';

    if (salary && budget > 0) {
        const salaryMatch = salary.replace(/,/g, '').match(/(\d+)/);
        const annualSalary = salaryMatch ? parseInt(salaryMatch[1]) : 0;
        const requiredSalary = budget * 40; // 40x rule

        const reqK = Math.round(requiredSalary / 1000);
        const haveK = Math.round(annualSalary / 1000);
        const monthly = '$' + Math.round(budget).toLocaleString();
        const budgetNote = budgetIsImplied
            ? ` (using ${budgetSource} since no budget was submitted)`
            : '';

        if (annualSalary >= requiredSalary) {
            insights.push({
                label: budgetIsImplied ? "Can Afford (implied budget)" : "Can Afford",
                type: "success", icon: "\u2705", points: 30,
                reason: `${monthly}/mo${budgetNote} needs ~$${reqK}k/yr. Inferred salary $${haveK}k+ clears the 40x rule.`
            });
        } else if (annualSalary >= requiredSalary * 0.6) {
            insights.push({
                label: budgetIsImplied ? "Borderline Afford (implied budget)" : "Borderline Afford",
                type: "warning", icon: "\u26A0\uFE0F", points: 15,
                reason: `${monthly}/mo${budgetNote} needs ~$${reqK}k/yr. Inferred salary $${haveK}k+ is below the 40x rule but within reach with a guarantor or partner.`
            });
        } else {
            insights.push({
                label: budgetIsImplied ? "Budget Risk (implied budget)" : "Budget Risk",
                type: "danger", icon: "\u274C", points: -10,
                reason: `${monthly}/mo${budgetNote} needs ~$${reqK}k/yr. Inferred salary $${haveK}k+ is well below \u2014 likely needs guarantor or higher co-signer income.`
            });
        }
    } else if (budget > 0) {
        // Budget is on file (explicit or implied) but enrichment didn't return a salary.
        missed.push({
            label: "Affordability unknown",
            reason: "No salary data from enrichment \u2014 we can't run the 40x check (up to +30 untouchable)."
        });
    }
    // If neither budget nor implied budget exists (rare \u2014 no inventory + no form data),
    // the combined missed-opportunity in the Budget section below covers it.

    // 2. Intent Signal (20 pts)
    if (source.includes('unit interest')) {
        insights.push({
            label: "High Intent", type: "success", icon: "\u{1F525}", points: 20,
            reason: `Filled out the Unit Interest form${lead.unit ? ' for Unit ' + lead.unit : ''} \u2014 strongest intent signal we capture.`
        });
    } else if (source.includes('waitlist')) {
        insights.push({
            label: "Waitlist", type: "info", icon: "\u{1F4CB}", points: 10,
            reason: "Joined the general Waitlist \u2014 interested but not tied to a specific unit yet."
        });
    } else {
        missed.push({
            label: "Low intent signal",
            reason: "Mailing list signup or unknown source \u2014 no intent points."
        });
    }

    // 3. Verified Professional (25 pts) \u2014 merged with LinkedIn since both come
    // from the same enrichment pipeline. Email + phone are already required on
    // the form, so LinkedIn isn't a new outreach channel \u2014 it's just another
    // piece of evidence confirming who they are.
    if (lead.job_title && lead.company) {
        const linkedinNote = lead.linkedin_url ? ' LinkedIn profile also confirmed.' : '';
        insights.push({
            label: "Verified Professional", type: "success", icon: "\u{1F4BC}", points: 25,
            reason: `Identified as ${lead.job_title} at ${lead.company} via enrichment.${linkedinNote}`
        });
    } else {
        missed.push({
            label: "Not professionally verified (+25 missed)",
            reason: "Enrichment couldn't match a job title and company \u2014 common for personal email addresses or low-data profiles."
        });
    }

    // 4. Engagement (15 pts)
    const events = lead.event_count || 0;
    if (events >= 10) {
        insights.push({
            label: "Highly Engaged", type: "success", icon: "\u{1F4CA}", points: 15,
            reason: `${events} tracked events on the site \u2014 they explored multiple sections before submitting.`
        });
    } else if (events >= 5) {
        insights.push({
            label: "Engaged", type: "info", icon: "\u{1F4CA}", points: 10,
            reason: `${events} tracked events on the site \u2014 moderate browsing activity.`
        });
    } else {
        missed.push({
            label: events > 0 ? `Low engagement (${events} events, +10 missed)` : "No engagement data (+10 missed)",
            reason: events > 0
                ? `Only ${events} tracked events. Threshold is 5 for partial credit, 10 for full.`
                : "No tracking events recorded \u2014 submitted without exploring or behaviour wasn't captured."
        });
    }

    // 5. Budget Provided (5 pts) \u2014 only awarded for an explicitly submitted budget.
    // If we used an implied budget for the affordability check, the +5 is still
    // missed (we don't reward inferred data), but we don't say "check skipped"
    // because the implied value let us run the check.
    if (explicitBudget > 0) {
        insights.push({
            label: "Budget Provided", type: "info", icon: "\u{1F4B0}", points: 5,
            reason: `Budget on file: $${explicitBudget.toLocaleString()}/month.`
        });
    } else if (budgetIsImplied) {
        missed.push({
            label: "No budget submitted (+5 missed)",
            reason: `Lead didn't fill in a budget \u2014 we ran the affordability check using the ${budgetSource} as a fallback, but they still miss the +5 for explicit data.`
        });
    } else {
        missed.push({
            label: "No budget (+5 missed, affordability check skipped)",
            reason: "Lead didn't fill in a budget and we couldn't infer one from the portfolio \u2014 both the +5 and the +30 affordability check are unavailable."
        });
    }

    // 6. Move-In Timeline (5 pts)
    if (lead.move_in_date) {
        insights.push({
            label: "Timeline Set", type: "info", icon: "\u{1F4C5}", points: 5,
            reason: `Target move-in: ${lead.move_in_date}.`
        });
    } else {
        missed.push({
            label: "No move-in date (+5 missed)",
            reason: "Lead didn't provide a target move-in date \u2014 timeline unclear."
        });
    }

    const totalScore = Math.max(0, Math.min(100, insights.reduce((sum, i) => sum + (i.points || 0), 0)));

    const getLetter = (s) => {
        if (s >= 90) return 'A+';
        if (s >= 80) return 'A';
        if (s >= 70) return 'B+';
        if (s >= 60) return 'B';
        if (s >= 50) return 'C+';
        if (s >= 40) return 'C';
        if (s >= 30) return 'D';
        return 'F';
    };

    const letter = getLetter(totalScore);
    const gradeClass = 'grade-' + letter.toLowerCase().replace('+', '-plus');

    return { score: totalScore, letter, insights, missed, gradeClass };
}

// ── Grade Breakdown Card ──
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

// ── Clipboard ──
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 1500);
    }).catch(err => {
        console.error('Copy failed: ', err);
    });
}

// ── Brokers ──
let brokersCache = [];

async function fetchBrokers(callback) {
    try {
        const res = await fetch(API + '?action=get_brokers');
        const data = await res.json();
        brokersCache = Array.isArray(data) ? data : [];
        if (callback) callback(brokersCache);
    } catch (err) {
        console.error('Error fetching brokers:', err);
        brokersCache = [];
    }
}

// ── Lead Assignment ──
async function assignLead(email, source, brokerId) {
    try {
        const res = await fetch(API + '?action=assign_lead', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: source, broker_id: brokerId || null })
        });
        const result = await res.json();
        if (result.success) {
            fetchData();
        } else {
            alert('Error assigning lead: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while assigning lead.');
    }
}

// ── Lead Response ──
async function respondLead(email, source, method) {
    try {
        const res = await fetch(API + '?action=respond_lead', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: source, method: method })
        });
        const result = await res.json();
        if (result.success) {
            fetchData();
        } else {
            alert('Error marking response: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while marking response.');
    }
}

// ── Custom Confirm Dialog ──
function showConfirm({ title, message, icon, btnText, btnClass, onConfirm }) {
    document.getElementById('confirmTitle').textContent = title || 'Are you sure?';
    document.getElementById('confirmMessage').textContent = message || '';
    document.getElementById('confirmIcon').innerHTML = icon || '<i class="bi bi-exclamation-triangle text-danger"></i>';
    const btn = document.getElementById('confirmActionBtn');
    btn.textContent = btnText || 'Delete';
    btn.className = 'btn btn-sm px-4 ' + (btnClass || 'btn-danger');
    btn.onclick = function() {
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
        if (onConfirm) onConfirm();
    };
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

// ── Notifications ──
let notifDropdownOpen = false;

const NOTIF_ICONS = {
    new_lead:         { icon: 'bi-person-plus-fill', bg: 'rgba(59,130,246,0.2)', color: '#93c5fd' },
    handoff:          { icon: 'bi-arrow-left-right', bg: 'rgba(234,179,8,0.2)', color: '#fde047' },
    tour_booked:      { icon: 'bi-calendar-check', bg: 'rgba(34,197,94,0.2)', color: '#86efac' },
    tour_cancelled:   { icon: 'bi-calendar-x', bg: 'rgba(239,68,68,0.2)', color: '#fca5a5' },
    tour_rescheduled: { icon: 'bi-calendar2-range', bg: 'rgba(139,92,246,0.2)', color: '#c4b5fd' },
    follow_up_cold:   { icon: 'bi-snow', bg: 'rgba(107,114,128,0.2)', color: '#9ca3af' }
};

function toggleNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    notifDropdownOpen = !notifDropdownOpen;
    dd.style.display = notifDropdownOpen ? 'block' : 'none';
    if (notifDropdownOpen) fetchNotifications();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (notifDropdownOpen && !e.target.closest('#notifBellBtn') && !e.target.closest('#notifDropdown')) {
        document.getElementById('notifDropdown').style.display = 'none';
        notifDropdownOpen = false;
    }
});

async function fetchNotifications() {
    try {
        const res = await fetch(API + '?action=get_notifications');
        const data = await res.json();

        // Update bell badge
        const badge = document.getElementById('notifBellBadge');
        if (badge) {
            if (data.unread_count > 0) {
                badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }

        // Render dropdown if open
        if (notifDropdownOpen) {
            renderNotifDropdown(data.notifications || []);
        }
    } catch(e) {}
}

function renderNotifDropdown(notifications) {
    const list = document.getElementById('notifList');
    if (!notifications.length) {
        list.innerHTML = '<div class="text-center text-white-50 small py-4">No notifications</div>';
        return;
    }

    list.innerHTML = notifications.slice(0, 30).map(n => {
        const config = NOTIF_ICONS[n.type] || NOTIF_ICONS.new_lead;
        const timeAgo = formatTimeAgo(n.created_at);
        const unreadClass = n.is_read ? '' : ' unread';
        const link = n.link || '#';

        return '<a href="' + esc(link) + '" class="notif-item' + unreadClass + '" onclick="markNotifRead(\'' + esc(n.id) + '\')">'
            + '<div class="notif-icon" style="background:' + config.bg + ';color:' + config.color + '"><i class="bi ' + config.icon + '"></i></div>'
            + '<div class="flex-grow-1 min-width-0">'
            + '<div class="text-white small" style="font-size:0.8rem;line-height:1.3">' + esc(n.title) + '</div>'
            + (n.body ? '<div class="text-white-50" style="font-size:0.7rem;line-height:1.3;margin-top:1px">' + esc(n.body).substring(0, 80) + '</div>' : '')
            + '<div class="text-white-50" style="font-size:0.65rem;margin-top:2px">' + timeAgo + '</div>'
            + '</div>'
            + '</a>';
    }).join('');
}

function formatTimeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return mins + 'm ago';
    const hours = Math.floor(mins / 60);
    if (hours < 24) return hours + 'h ago';
    const days = Math.floor(hours / 24);
    if (days < 7) return days + 'd ago';
    return new Date(dateStr).toLocaleDateString();
}

async function markNotifRead(id) {
    try {
        await fetch(API + '?action=mark_notifications_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: [id] })
        });
    } catch(e) {}
}

async function markAllNotifRead() {
    try {
        await fetch(API + '?action=mark_notifications_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        fetchNotifications();
    } catch(e) {}
}

// ── Unread SMS Badge (sidebar) ──
async function updateSidebarUnreadBadge() {
    try {
        const res = await fetch(API + '?action=sms_conversations');
        const convos = await res.json();
        if (!Array.isArray(convos)) return;

        const totalUnread = convos.reduce((sum, c) => sum + (c.unread || 0), 0);
        const badge = document.getElementById('sidebarUnreadBadge');
        if (badge) {
            if (totalUnread > 0) {
                badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch(e) {}
}

// ── Calendar Changes Badge (sidebar) ──
// Counts tour_requests created or updated since the user last opened the
// Calendar page (timestamp stored in localStorage by calendar.js).
async function updateSidebarCalendarBadge() {
    try {
        const since = localStorage.getItem('calendar_last_viewed')
            || new Date(Date.now() - 7 * 86400000).toISOString();
        const res = await fetch(API + '?action=tour_requests_new_count&since=' + encodeURIComponent(since));
        const data = await res.json();
        const count = data && data.count ? data.count : 0;
        const badge = document.getElementById('sidebarCalendarBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch(e) {}
}

// Update badges on load and every 30 seconds
document.addEventListener('DOMContentLoaded', () => {
    updateSidebarUnreadBadge();
    updateSidebarCalendarBadge();
    fetchNotifications();
    setInterval(() => {
        updateSidebarUnreadBadge();
        updateSidebarCalendarBadge();
        fetchNotifications();
    }, 30000);
});
