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
    let insights = [];
    const source = (lead.source || lead.submission_type || '').toLowerCase();

    // 1. Affordability Check (30 pts max)
    // Industry standard: rent should be no more than 1/40th of annual salary
    const salary = lead.inferred_salary || '';
    const budget = parseFloat((lead.budget || '0').replace(/[^0-9.]/g, ''));
    if (salary && budget > 0) {
        // Parse salary range like "100,000-150,000" -> use lower bound
        const salaryMatch = salary.replace(/,/g, '').match(/(\d+)/);
        const annualSalary = salaryMatch ? parseInt(salaryMatch[1]) : 0;
        const requiredSalary = budget * 40; // 40x rule

        if (annualSalary >= requiredSalary) {
            insights.push({ label: "Can Afford", type: "success", icon: "\u2705", points: 30 });
        } else if (annualSalary >= requiredSalary * 0.6) {
            insights.push({ label: "Borderline Afford", type: "warning", icon: "\u26A0\uFE0F", points: 15 });
        } else {
            insights.push({ label: "Budget Risk", type: "danger", icon: "\u274C", points: -10 });
        }
    }

    // 2. Intent Signal (20 pts)
    if (source.includes('unit interest')) {
        insights.push({ label: "High Intent", type: "success", icon: "\u{1F525}", points: 20 });
    } else if (source.includes('waitlist')) {
        insights.push({ label: "Waitlist", type: "info", icon: "\u{1F4CB}", points: 10 });
    }

    // 3. Verified Professional (15 pts)
    if (lead.job_title && lead.company) {
        insights.push({ label: "Verified Professional", type: "success", icon: "\u{1F4BC}", points: 15 });
    }

    // 4. Engagement (15 pts)
    const events = lead.event_count || 0;
    if (events >= 10) {
        insights.push({ label: "Highly Engaged", type: "success", icon: "\u{1F4CA}", points: 15 });
    } else if (events >= 5) {
        insights.push({ label: "Engaged", type: "info", icon: "\u{1F4CA}", points: 10 });
    }

    // 5. Budget Provided (5 pts)
    if (budget > 0) {
        insights.push({ label: "Budget Provided", type: "info", icon: "\u{1F4B0}", points: 5 });
    }

    // 6. Move-In Timeline (5 pts)
    if (lead.move_in_date) {
        insights.push({ label: "Timeline Set", type: "info", icon: "\u{1F4C5}", points: 5 });
    }

    // 7. Enrichment Quality (10 pts)
    if (lead.linkedin_url) {
        insights.push({ label: "LinkedIn Verified", type: "info", icon: "\u{1F517}", points: 10 });
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

    return { score: totalScore, letter: getLetter(totalScore), insights: insights };
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

// Update badge on load and every 30 seconds
document.addEventListener('DOMContentLoaded', () => {
    updateSidebarUnreadBadge();
    setInterval(updateSidebarUnreadBadge, 30000);
});
