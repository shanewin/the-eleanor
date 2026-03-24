<?php
require_once 'auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aurelian | Dashboard</title> <!-- Original: The Eleanor | Dashboard -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body>

    <aside>
        <span class="logo">AURELIAN</span> <!-- THE ELEANOR -->
        <nav id="mainNav">
            <a href="#" class="nav-link active" data-view="overview">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Overview
            </a>
            <a href="#" class="nav-link" data-view="leads">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Leads
            </a>
            <a href="#" class="nav-link" data-view="analytics">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002 2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Analytics
            </a>
            <a href="#" class="nav-link" data-view="settings">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
            <div style="margin-top: auto; padding-top: 2rem;">
                <a href="?logout=1" style="color: rgba(239, 68, 68, 0.6); font-size: 0.8rem;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Sign Out
                </a>
            </div>
        </nav>
    </aside>

    <main>
        <!-- Overview View -->
        <div id="view-overview" class="dashboard-view">
            <header>
                <h1>Dashboard Overview</h1>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.4);">Updating in real-time</div>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Visitors</div>
                    <div class="stat-value" id="statSessions">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Leads</div>
                    <div class="stat-value" id="statLeads">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Conversion Rate</div>
                    <div class="stat-value" id="statConv">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Top Interest</div>
                    <div class="stat-value" id="statHot">-</div>
                </div>
            </div>

            <div class="card" style="padding: 2.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem">
                    <h2 style="margin:0">Recent Conversions & Enrichment</h2>
                    <div style="font-size:0.75rem; color:var(--text-dim)">Sort by: 
                        <select id="overview-sort" onchange="fetchData()" style="background:rgba(255,255,255,0.05); color:white; border:1px solid var(--border); padding:0.4rem; border-radius:8px; outline:none">
                            <option value="date">Date (Newest)</option>
                            <option value="grade">Grade (Elite First)</option>
                        </select>
                    </div>
                </div>
                <table class="leadsTable">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Identity</th>
                            <th>Contact</th>
                            <th>Grade</th>
                            <th>Engagement</th>
                            <th>Role & Company</th>
                            <th style="display:none">Actions</th>
                        </tr>
                    </thead>
                    <tbody><!-- Dynamic --></tbody>
                </table>
            </div>
        </div>

        <!-- Leads View -->
        <div id="view-leads" class="dashboard-view" style="display:none">
            <header>
                <h1>All Qualified Leads</h1>
            </header>
            <div class="card">
                <table class="leadsTable">
                    <thead>
                        <tr>
                            <th class="sortable" onclick="sortLeads('created_at')">Timestamp</th>
                            <th class="sortable" onclick="sortLeads('last_name')">Lead</th>
                            <th class="sortable" onclick="sortLeads('grade_score')">Grade</th>
                            <th>Signals</th>
                            <th class="sortable" onclick="sortLeads('event_count')">Engagement</th>
                            <th class="sortable" onclick="sortLeads('job_title')">Role & Company</th>
                            <th>Management</th>
                        </tr>
                    </thead>
                    <tbody><!-- Dynamic --></tbody>
                </table>
            </div>
        </div>

        <!-- Analytics View -->
        <div id="view-analytics" class="dashboard-view" style="display:none">
            <header>
                <h1>User Behavioral Insights</h1>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.4);">Deep behavioral analysis</div>
            </header>
            
            <div class="analytics-grid">
                <!-- Section Engagement -->
                <div class="card analytics-card">
                    <h2>Section Engagement</h2>
                    <p style="font-size:0.75rem; color:var(--text-dim); margin-top:-1.5rem; margin-bottom:2rem">Average time spent per section (seconds)</p>
                    <div id="section-engagement-list" class="metrics-list">
                        <!-- Dynamic -->
                    </div>
                </div>

                <!-- Interaction Hotspots -->
                <div class="card analytics-card">
                    <h2>Top Interactions</h2>
                    <p style="font-size:0.75rem; color:var(--text-dim); margin-top:-1.5rem; margin-bottom:2rem">Most clicked buttons and CTAs</p>
                    <div id="top-interactions-list" class="metrics-list">
                        <!-- Dynamic -->
                    </div>
                </div>

                <!-- Device Breakdown -->
                <div class="card analytics-card">
                    <h2>Device Distribution</h2>
                    <div id="device-breakdown-list" class="metrics-list" style="margin-top:1rem">
                        <!-- Dynamic -->
                    </div>
                </div>

                <!-- Traffic Trends -->
                <div class="card analytics-card">
                    <h2>Recent Traffic Trend</h2>
                    <div id="traffic-trends-list" class="metrics-list" style="margin-top:1rem">
                        <!-- Dynamic -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Profile View (In-Page) -->
        <div id="view-lead-profile" class="dashboard-view" style="display:none">
            <div style="margin-bottom: 2rem">
                <a href="#" class="back-link" onclick="event.preventDefault(); showView('leads')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.5rem"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Leads
                </a>
            </div>
            <div id="inPageProfileContent" class="profile-container">
                <!-- Dynamic Content -->
            </div>
        </div>
    </main>

    <div id="journeyPanel">
        <!-- Content dynamic -->
    </div>

    <?php
    if (isset($_GET['logout'])) { logout(); }
    ?>

    <script>
        // Navigation Logic
        function showView(view) {
            // Toggle nav state
            document.querySelectorAll('.nav-link').forEach(l => {
                l.classList.remove('active');
                if (l.getAttribute('data-view') === view) l.classList.add('active');
            });
            
            // Toggle view content
            document.querySelectorAll('.dashboard-view').forEach(v => v.style.display = 'none');
            const target = document.getElementById(`view-${view}`);
            if (target) target.style.display = 'block';

            if (view === 'analytics') {
                fetchAnalytics();
            }
        }

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showView(link.getAttribute('data-view'));
            });
        });

        function formatTenure(years) {
            if (years <= 0) return "N/A";
            if (years < 1) return "< 1 year";
            return Math.floor(years) + (Math.floor(years) === 1 ? " year" : " years");
        }

        function calculateLeadGrade(lead) {
            let insights = [];
            let raw = {};
            try {
                raw = typeof lead.raw_response === 'string' ? JSON.parse(lead.raw_response) : (lead.raw_response || {});
            } catch (e) { raw = {}; }

            const person = raw.person || {};
            const org = person.organization || {};
            const employment = person.employment_history || [];
            const education = person.education_history || person.education || [];

            // Tenure Calculations
            let roleTenure = 0;
            let companyTenure = 0;
            const now = new Date();

            // Helper to parse messy dates from AI
            function parseTenure(dateStr) {
                if (!dateStr || dateStr.toLowerCase().includes('present')) return now;
                const d = new Date(dateStr);
                return isNaN(d) ? null : d;
            }

            if (employment.length > 0) {
                const firstJobStart = parseTenure(employment[0].start_date);
                if (firstJobStart) {
                    roleTenure = (now - firstJobStart) / (365 * 24 * 60 * 60 * 1000);
                }
                const primaryCo = lead.company || (employment[0] && employment[0].organization_name);
                employment.forEach(job => {
                    const start = parseTenure(job.start_date);
                    if (start) {
                        const end = job.current ? now : (parseTenure(job.end_date) || now);
                        const yrs = (end - start) / (365 * 24 * 60 * 60 * 1000);
                        if (primaryCo && job.organization_name && job.organization_name.toLowerCase().includes(primaryCo.toLowerCase())) {
                            companyTenure += yrs;
                        }
                    }
                });
            }

            // 1. Career Seniority (Primary Signal)
            const title = (person.title || lead.job_title || '').toLowerCase();
            if (['chief', 'ceo', 'cto', 'cfo', 'coo', 'cmo', 'cpo', 'founder', 'owner', 'president'].some(k => title.includes(k))) {
                insights.push({ label: "Executive Leadership", type: "success", icon: "👑", points: 40 });
            } else if (['vice president', 'vp', 'director', 'head of'].some(k => title.includes(k))) {
                insights.push({ label: "Senior Management", type: "success", icon: "💎", points: 30 });
            } else if (['manager', 'senior', 'lead', 'principal', 'engineer', 'architect', 'developer', 'analyst', 'specialist', 'consultant'].some(k => title.includes(k))) {
                insights.push({ label: "Professional Lead", type: "info", icon: "🚀", points: 20 });
            }

            // 2. Career Depth (Total Experience)
            let totalYrsExp = 0;
            employment.forEach(job => {
                const s = parseTenure(job.start_date);
                const e = job.current ? now : (parseTenure(job.end_date) || now);
                if (s && e) totalYrsExp += (e - s) / (365 * 24 * 60 * 60 * 1000);
            });
            if (totalYrsExp >= 10) {
                insights.push({ label: "Industry Veteran", type: "success", icon: "🏆", points: 25 });
            } else if (totalYrsExp >= 5) {
                insights.push({ label: "Seasoned Pro", type: "success", icon: "🌟", points: 15 });
            }

            // 3. Verified Professional Signal
            const jobTitle = lead.job_title || lead.title || person.title;
            const companyName = lead.company || (org ? org.name : null) || (employment[0] ? employment[0].organization_name : null);
            if (jobTitle && companyName) {
                insights.push({ label: "Verified Identity", type: "success", icon: "✅", points: 15 });
            }

            // 4. Intent & Source
            const source = (lead.source || lead.submission_type || '').toLowerCase();
            if (source.includes('unit interest')) {
                insights.push({ label: "High Intent", type: "success", icon: "🔥", points: 15 });
            }

            // 5. Stability & Tenure
            if (companyTenure >= 3) {
                insights.push({ label: "Established Pro", type: "success", icon: "⚓", points: 20 });
            }
            if (roleTenure > 0 && roleTenure < 0.5) { // Under 6 months
                insights.push({ label: "New Transition", type: "warning", icon: "🌱", points: -15 });
            }
            // Job Hopper check: Too many roles in too short a time
            if (employment.length >= 5 && totalYrsExp < 10) {
                insights.push({ label: "Volatile History", type: "warning", icon: "⚠️", points: -10 });
            }
            
            // 6. Organization Tier
            const revenue = (lead.annual_revenue || org.annual_revenue_printed || '').toUpperCase();
            if (revenue.includes('B') || revenue.includes('T')) {
                insights.push({ label: "Enterprise Scale", type: "info", icon: "🏢", points: 15 });
            } else if (org.primary_domain || org.short_description) {
                insights.push({ label: "Verified Entity", type: "info", icon: "🏢", points: 10 });
            }

            // 7. Multi-Org Advisor
            if (employment.some(job => job.current && ['board', 'advisor', 'trustee'].some(k => (job.title || '').toLowerCase().includes(k)))) {
                insights.push({ label: "Board Advisor", type: "info", icon: "⚖️", points: 15 });
            }

            const profScore = insights.reduce((sum, i) => sum + (i.points || 0), 0);
            const engagementScore = Math.min(20, (lead.event_count || 0) * 2);
            const totalScore = Math.min(100, profScore + engagementScore);

            const getLetter = (s) => {
                if (s >= 95) return 'A+';
                if (s >= 90) return 'A';
                if (s >= 85) return 'A-';
                if (s >= 80) return 'B+';
                if (s >= 75) return 'B';
                if (s >= 70) return 'B-';
                if (s >= 65) return 'C+';
                if (s >= 60) return 'C';
                if (s >= 55) return 'C-';
                if (s >= 40) return 'D';
                return 'F';
            };

            return { score: totalScore, letter: getLetter(totalScore), insights: insights, roleTenure, companyTenure };
        }

        let currentLeads = [];
        let sortConfig = { column: 'created_at', direction: 'desc' };

        function sortLeads(column) {
            if (sortConfig.column === column) {
                sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
            } else {
                sortConfig.column = column;
                sortConfig.direction = column === 'created_at' || column === 'grade_score' || column === 'event_count' ? 'desc' : 'asc';
            }
            fetchData();
        }

        async function fetchData() {
            try {
                // Fetch Stats
                const statsResponse = await fetch('../api/admin-api.php?action=stats');
                const stats = await statsResponse.json();
                document.getElementById('statSessions').innerText = stats.totalSessions;
                document.getElementById('statLeads').innerText = stats.totalLeads;
                document.getElementById('statConv').innerText = stats.conversionRate;
                document.getElementById('statHot').innerText = stats.hottestSection;

                // Fetch Leads
                const leadsResponse = await fetch('../api/admin-api.php?action=leads');
                if (!leadsResponse.ok) throw new Error("API Error");
                
                let leads = await leadsResponse.json();
                
                // Add Grade to each lead object
                leads = leads.map(l => ({ 
                    ...l, 
                    grade: calculateLeadGrade(l),
                    grade_score: calculateLeadGrade(l).score // For sorting
                }));

                currentLeads = leads;

                // Sorting for Overview (stays simple)
                const sortVal = document.getElementById('overview-sort')?.value || 'date';
                
                // Unified Sorting Logic
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

                // Overwrite sorting for Overview if not explicitly sorted via headers
                if (document.getElementById('view-overview').style.display !== 'none') {
                    if (sortVal === 'grade') {
                        leads.sort((a, b) => b.grade.score - a.grade.score);
                    } else {
                        leads.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    }
                }

                // Render to all lead tables
                document.querySelectorAll('.leadsTable').forEach(table => {
                    const tbody = table.querySelector('tbody');
                    const isOverview = table.closest('#view-overview') !== null;
                    
                    // Update header active states
                    if (!isOverview) {
                        table.querySelectorAll('th.sortable').forEach(th => {
                            th.classList.remove('active');
                            if (th.getAttribute('onclick')?.includes(`'${sortConfig.column}'`)) {
                                th.classList.add('active');
                            }
                        });
                    }
                    
                    if (leads.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="${isOverview ? 6 : 7}" style="text-align:center; padding:3rem; color:rgba(255,255,255,0.2)">No leads recorded yet.</td></tr>`;
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    leads.forEach(lead => {
                        const row = document.createElement('tr');
                        row.className = 'clickable-row';
                        row.onclick = () => {
                            const activeView = document.querySelector('.nav-link.active').getAttribute('data-view');
                            if (activeView === 'leads') {
                                viewInPageProfile(lead.tracking_id, lead.email);
                            } else {
                                viewJourney(lead.tracking_id, lead.email);
                            }
                        };
                        
                        const dateObj = new Date(lead.created_at);
                        const timestamp = `
                            <div style="display:flex; flex-direction:column">
                                <span style="font-weight:600">${dateObj.toLocaleDateString()}</span>
                                <span style="font-size:0.75rem; color:var(--text-dim)">${dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            </div>
                        `;

                        const avatar = lead.photo_url ? `<img src="${lead.photo_url}" class="user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${lead.first_name}+${lead.last_name}&background=3b82f6&color=fff'">` : 
                            `<div class="user-avatar" style="display:flex;align-items:center;justify-content:center;font-weight:600;font-size:1rem;background:rgba(59,130,246,0.1);color:var(--accent)">${lead.first_name[0]}${lead.last_name[0]}</div>`;
                        
                        const contactInfo = `
                            <div style="display:flex; flex-direction:column; gap:0.2rem">
                                <span style="font-weight:600; color:white; font-size:1rem">${lead.first_name} ${lead.last_name}</span>
                                <div style="display:flex; align-items:center; gap:0.5rem">
                                    <span style="font-size:0.8rem; color:var(--text-dim)">${lead.email}</span>
                                    <button class="mini-copy-btn" onclick="event.stopPropagation(); copyToClipboard('${lead.email}', this)">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>
                            </div>
                        `;

                        const identityCombined = `
                            <div class="user-cell">
                                ${avatar}
                                ${contactInfo}
                            </div>
                        `;

                        const enrichment = lead.job_title ? `
                            <div style="display:flex; flex-direction:column; gap:0.4rem">
                                <div style="display:flex; align-items:center; gap:0.5rem">
                                    ${lead.company_logo ? `<img src="${lead.company_logo}" class="company-logo" style="width:24px; height:24px">` : ''}
                                    <span style="font-weight:500; font-size:0.85rem; line-height:1.3">${lead.job_title} @ ${lead.company}</span>
                                </div>
                            </div>
                        ` : '<span style="color:rgba(255,255,255,0.2)">Pending...</span>';
                        
                        const activityLevel = lead.event_count > 10 ? '<span style="color:#10b981">● Hot</span>' : 
                                             lead.event_count > 5 ? '<span style="color:#3b82f6">● Active</span>' : 
                                             '<span style="color:rgba(255,255,255,0.3)">● Quiet</span>';
                        
                        const engagement = `
                            <div style="display:flex; flex-direction:column; gap:0.2rem">
                                <span style="font-size:0.7rem; text-transform:uppercase; color:var(--text-dim); letter-spacing:0.05em">${lead.source}</span>
                                <span style="font-size:0.75rem; font-weight:600">${activityLevel}</span>
                            </div>
                        `;

                        const rowContent = isOverview ? `
                            <td>${timestamp}</td>
                            <td>
                                <div class="user-cell">
                                    ${avatar}
                                    <div style="display:flex; flex-direction:column">
                                        <span style="font-weight:600; color:white">${lead.first_name} ${lead.last_name}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.5rem">
                                    <span style="font-size:0.9rem">${lead.email}</span>
                                    <button class="mini-copy-btn" onclick="event.stopPropagation(); copyToClipboard('${lead.email}', this)">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td style="text-align:center">
                                <div class="grade-pill ${lead.grade.score >= 80 ? 'elite' : ''}">
                                    ${lead.grade.letter}
                                </div>
                            </td>
                            <td>${engagement}</td>
                            <td>${enrichment}</td>
                            <td style="display:none"></td>
                        ` : `
                            <td>${timestamp}</td>
                            <td>${identityCombined}</td>
                            <td style="text-align:center">
                                <div class="grade-pill ${lead.grade.score >= 80 ? 'elite' : ''}">
                                    ${lead.grade.letter}
                                </div>
                            </td>
                            <td style="vertical-align: top; padding-top: 1.5rem">
                                <div style="display:flex; flex-direction:column; gap:0.4rem; align-items: flex-start">
                                    ${lead.grade.insights.map(i => {
                                        let extraClass = '';
                                        if (i.label.toLowerCase().includes('alum')) extraClass = 'elite-alum';
                                        if (i.label.toLowerCase().includes('exec')) extraClass = 'executive';
                                        if (i.label.toLowerCase().includes('leader')) extraClass = 'leadership';
                                        if (i.label.toLowerCase().includes('giant')) extraClass = 'market-giant';
                                        return `<span class="badge ${extraClass}"><i>${i.icon}</i> ${i.label}</span>`;
                                    }).join('')}
                                </div>
                            </td>
                            <td>${engagement}</td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:0.2rem">
                                    <div style="display:flex; align-items:center; gap:0.5rem">
                                        ${lead.company_logo ? `<img src="${lead.company_logo}" class="company-logo" style="width:16px; height:16px; border-radius:3px; background:white; padding:2px; flex-shrink:0">` : ''}
                                        <span style="font-weight:600; color:white; font-size:0.85rem; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">${lead.company}</span>
                                    </div>
                                    <span style="font-size:0.75rem; color:var(--text-dim); line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">${lead.job_title}</span>
                                </div>
                            </td>
                            <td style="text-align:right">
                                <button class="delete-btn" onclick="event.stopPropagation(); deleteLead('${lead.email}', '${lead.source}')">Delete</button>
                            </td>
                        `;
                        row.innerHTML = rowContent;
                        tbody.appendChild(row);
                    });
                });

            } catch (err) {
                console.error("Dashboard error:", err);
            }
        }

        async function deleteLead(email, source) {
            if (!confirm(`Are you sure you want to permanently delete this lead from the ${source} list?`)) return;

            try {
                const formData = new FormData();
                formData.append('email', email);
                formData.append('source', source);

                const response = await fetch('../api/admin-api.php?action=delete_lead', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    fetchData(); // Refresh list
                } else {
                    alert('Error deleting lead: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Connection error while deleting lead');
            }
        }

        async function fetchAnalytics() {
            try {
                const response = await fetch('../api/admin-api.php?action=analytics');
                const data = await response.json();

                // 1. Section Engagement (Heatmap Style)
                const sectionList = document.getElementById('section-engagement-list');
                sectionList.innerHTML = '';
                const engagementData = data.sectionEngagement.filter(s => s.section && s.section !== 'unknown');
                const maxSeconds = Math.max(...engagementData.map(s => s.avg_seconds), 1);
                
                engagementData.forEach(s => {
                    const pct = (s.avg_seconds / maxSeconds) * 100;
                    const name = s.section.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    sectionList.innerHTML += `
                        <div class="metric-row">
                            <div class="metric-info">
                                <span class="metric-name">${name}</span>
                                <span class="metric-val">${Math.round(s.avg_seconds)}s <span style="font-size:0.7rem; color:var(--text-dim)">avg</span></span>
                            </div>
                            <div class="metric-bar-bg">
                                <div class="metric-bar-fill" style="width: ${pct}%"></div>
                            </div>
                        </div>
                    `;
                });

                // 2. Top Interactions (High Impact Actions)
                const interactionList = document.getElementById('top-interactions-list');
                interactionList.innerHTML = '';
                const maxClicks = Math.max(...data.topInteractions.map(i => i.click_count), 1);

                data.topInteractions.forEach(i => {
                    const pct = (i.click_count / maxClicks) * 100;
                    interactionList.innerHTML += `
                        <div class="metric-row">
                            <div class="metric-info">
                                <span class="metric-name">${i.button_text}</span>
                                <span class="metric-badge">${i.click_count}</span>
                            </div>
                            <div class="metric-bar-bg">
                                <div class="metric-bar-fill interaction-bar" style="width: ${pct}%"></div>
                            </div>
                        </div>
                    `;
                });

                // 3. Device Breakdown
                const deviceList = document.getElementById('device-breakdown-list');
                deviceList.innerHTML = '';
                const totalDevices = data.deviceBreakdown.reduce((sum, d) => sum + parseInt(d.count), 0);

                data.deviceBreakdown.forEach(d => {
                    const pct = (d.count / totalDevices) * 100;
                    deviceList.innerHTML += `
                        <div class="metric-row">
                            <div class="metric-info">
                                <span class="metric-name">${d.device_type}</span>
                                <span class="metric-val">${Math.round(pct)}%</span>
                            </div>
                            <div class="metric-bar-bg">
                                <div class="metric-bar-fill device-bar" style="width: ${pct}%"></div>
                            </div>
                        </div>
                    `;
                });

                // 4. Traffic Trends
                const trafficList = document.getElementById('traffic-trends-list');
                trafficList.innerHTML = '';
                const maxSessions = Math.max(...data.trafficTrends.map(t => t.sessions), 1);

                data.trafficTrends.forEach(t => {
                    const pct = (t.sessions / maxSessions) * 100;
                    const date = new Date(t.date).toLocaleDateString([], { month: 'short', day: 'numeric' });
                    trafficList.innerHTML += `
                        <div class="metric-row">
                            <div class="metric-info">
                                <span class="metric-name">${date}</span>
                                <span class="metric-val">${t.sessions} <span style="font-size:0.7rem; color:var(--text-dim)">visits</span> / ${t.leads} <span style="font-size:0.7rem; color:#10b981">leads</span></span>
                            </div>
                            <div class="metric-bar-bg">
                                <div class="metric-bar-fill trend-bar" style="width: ${pct}%"></div>
                            </div>
                        </div>
                    `;
                });

            } catch (err) {
                console.error("Analytics Error:", err);
            }
        }

        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<svg width="12" height="12" fill="none" stroke="#10b981" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>';
                btn.style.color = '#10b981';
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.style.color = '';
                }, 1500);
            }).catch(err => {
                console.error('Copy failed: ', err);
            });
        }

        async function viewInPageProfile(sessionId, email) {
            showView('lead-profile');
            const container = document.getElementById('inPageProfileContent');
            container.innerHTML = `<div style="padding: 4rem; text-align: center; color: rgba(255,255,255,0.2)">Generating intelligence profile...</div>`;
            
            try {
                const { intel, logs } = await fetchLeadData(email);
                renderLeadProfile(intel, logs, container, false);
            } catch (err) {
                container.innerHTML = `<div style="color: #ef4444">Error loading profile.</div>`;
            }
        }

        async function fetchLeadData(email) {
            const intelRes = await fetch(`../api/admin-api.php?action=lead_detail&email=${email}`);
            const intel = await intelRes.json();
            let logs = [];
            if (email) {
                const logsRes = await fetch(`../api/admin-api.php?action=lead_activity&email=${email}`);
                logs = await logsRes.json();
            }
            return { intel, logs };
        }

        function renderLeadProfile(intel, logs, container, isPanel = true) {
            const name = intel.full_name || "Lead Profile";
            const primaryAvatar = intel.photo_url || `https://ui-avatars.com/api/?name=${name}&background=3b82f6&color=fff&size=128`;
            
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
            const roleTenure = gradeInfo.roleTenure;
            const companyTenure = gradeInfo.companyTenure;

            let html = `
                ${isPanel ? `<button onclick="closeJourney()" style="position:absolute; right:2rem; top:2rem; background:none; border:none; color:white; cursor:pointer; font-size:2rem; z-index:10">&times;</button>` : ''}
                
                <div class="score-circle ${totalScore >= 80 ? 'score-high' : ''}">
                    <div class="score-val">${grade}</div>
                    <div class="score-label">Grade</div>
                </div>

                <div class="profile-header">
                    <img src="${primaryAvatar}" class="profile-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${name}&background=3b82f6&color=fff&size=128'">
                    <div class="profile-name">${name}</div>
                    <div class="profile-title">${person.title || intel.job_title || 'Private Individual'}</div>
                    
                    <div class="action-bar">
                        <div class="action-item">
                            <span>EMAIL</span>
                            <span class="action-val">${intel.email || 'N/A'}</span>
                            <button class="icon-btn" onclick="copyToClipboard('${intel.email}', this)">Copy</button>
                        </div>
                        ${intel.phone_number ? `
                        <div class="action-item">
                            <span>PHONE</span>
                            <span class="action-val">${intel.phone_number}</span>
                            <button class="icon-btn" onclick="copyToClipboard('${intel.phone_number}', this)">Copy</button>
                        </div>
                        ` : ''}
                    </div>

                    <div class="insight-container">
                        ${insights.map(i => `<div class="insight-badge badge-${i.type}"><i>${i.icon}</i> ${i.label}</div>`).join('')}
                    </div>

                    <!-- AI Narrative Summary -->
                    <div id="aiSummarySection" style="margin: 1.5rem auto; max-width: 600px; position: relative">
                        ${intel.ai_summary ? `
                            <div class="ai-summary-box" style="position:relative">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
                                    <div style="font-size: 0.7rem; color: var(--accent); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600">
                                        ✨ Prospect Summary
                                    </div>
                                    <button class="mini-copy-btn" onclick="generateAISummary('${intel.email}', '${insights.map(i => i.label).join('|')}', '${grade}', ${totalScore}, ${JSON.stringify(logs.map(l => ({event: l.event_name, time: l.created_at}))).replace(/"/g, '&quot;')})" title="Regenerate Summary">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                </div>
                                <div style="font-size: 0.85rem; line-height: 1.6; color: rgba(255,255,255,0.7); text-align: left">
                                    ${intel.ai_summary.split('\n').map(line => `<div style="margin-bottom:0.4rem">${line}</div>`).join('')}
                                </div>
                            </div>
                        ` : `
                            <button id="genSummaryBtn" class="premium-btn" onclick="generateAISummary('${intel.email}', '${insights.map(i => i.label).join('|')}', '${grade}', ${totalScore}, ${JSON.stringify(logs.map(l => ({event: l.event_name, time: l.created_at}))).replace(/"/g, '&quot;')})">
                                <span>✨ Generate Prospect Summary</span>
                            </button>
                        `}
                    </div>

                    <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1rem">
                        ${intel.linkedin_url ? `<a href="${intel.linkedin_url}" target="_blank" style="color:var(--accent)"><svg style="width:20px;height:20px;fill:currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg></a>` : ''}
                        ${intel.twitter_url ? `<a href="${intel.twitter_url}" target="_blank" style="color:rgba(255,255,255,0.6)"><svg style="width:20px;height:20px;fill:currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg></a>` : ''}
                    </div>
                </div>

                <div class="${isPanel ? '' : 'detail-cols'}" style="${isPanel ? '' : 'display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-top:2rem'}">
                    <div class="panel-content">
                        <div class="profile-section">
                            <div class="section-label">Submission Intent</div>
                            <div class="intel-grid">
                                <div class="intel-item">
                                    <span class="intel-label">Source</span>
                                    <span class="intel-val" style="color:var(--accent)">${intel.submission_type || 'General Lead'}</span>
                                </div>
                                ${intel.budget ? `
                                <div class="intel-item">
                                    <span class="intel-label">Budget</span>
                                    <span class="intel-val">${intel.budget}</span>
                                </div>
                                ` : ''}
                                ${intel.move_in_date ? `
                                <div class="intel-item">
                                    <span class="intel-label">Timeline</span>
                                    <span class="intel-val">${intel.move_in_date}</span>
                                </div>
                                ` : ''}
                                <div class="intel-item">
                                    <span class="intel-label">Captured At</span>
                                    <span class="intel-val" style="color:rgba(255,255,255,0.5)">${new Date(intel.created_at).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-section">
                            <div class="section-label">Executive Intelligence</div>
                            <div class="intel-grid">
                                <div class="intel-item">
                                    <span class="intel-label">Seniority</span>
                                    <span class="intel-val">${intel.seniority || 'Unknown'}</span>
                                </div>
                                <div class="intel-item">
                                    <span class="intel-label">Role Tenure</span>
                                    <span class="intel-val">${formatTenure(roleTenure)}</span>
                                </div>
                                <div class="intel-item">
                                    <span class="intel-label">Company</span>
                                    <span class="intel-val">${intel.company || 'Not specified'}</span>
                                </div>
                                <div class="intel-item">
                                    <span class="intel-label">Annual Revenue</span>
                                    <span class="intel-val">${intel.annual_revenue || org.annual_revenue_printed || 'Under $1M'}</span>
                                </div>
                                <div class="intel-item" style="grid-column: span 2; margin-top: 1rem">
                                    <span class="intel-label">About ${intel.company || 'the Company'}</span>
                                    <div style="background:rgba(255,255,255,0.03); padding:1rem; border-radius:12px; font-size:0.85rem; line-height:1.6; color:rgba(255,255,255,0.7); margin-top:0.4rem">
                                        ${intel.company_description || org.short_description || 'No description available.'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        ${(education.length > 0 || boardRoles.length > 0) ? `
                        <div class="profile-section">
                            <div class="section-label">Elite Social Signals</div>
                            <div class="intel-grid">
                                ${education.length > 0 ? `
                                <div class="intel-item" style="grid-column: span 2">
                                    <span class="intel-label">Education</span>
                                    ${education.map(edu => `
                                        <div style="margin-top:0.4rem">
                                            <span style="display:block; font-weight:600; font-size:0.85rem">${edu.school_name}</span>
                                            <span style="display:block; font-size:0.75rem; color:rgba(255,255,255,0.4)">${edu.degree || ''} ${edu.major ? `• ${edu.major}` : ''}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                ` : ''}
                                ${boardRoles.length > 0 ? `
                                <div class="intel-item" style="grid-column: span 2">
                                    <span class="intel-label">Board & Advisory Roles</span>
                                    ${boardRoles.map(role => `
                                        <div style="margin-top:0.4rem">
                                            <span style="display:block; font-weight:600; font-size:0.85rem">${role.title}</span>
                                            <span style="display:block; font-size:0.75rem; color:rgba(255,255,255,0.4)">${role.organization_name}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        ` : ''}

                        ${employment.length > 0 ? `
                        <div class="profile-section">
                            <div class="section-label">Career Journey</div>
                            <div style="display:flex; flex-direction:column; gap:1.2rem; margin-top:1rem">
                                ${employment.map((job, idx) => `
                                    <div style="display:flex; gap:1rem; align-items:flex-start">
                                        <div style="width:10px; height:10px; border-radius:50%; background:${idx === 0 ? 'var(--accent)' : 'rgba(255,255,255,0.1)'}; margin-top:0.4rem"></div>
                                        <div style="flex:1">
                                            <div style="font-size:0.9rem; font-weight:500; color:white">${job.title}</div>
                                            <div style="font-size:0.75rem; color:var(--accent)">${job.organization_name}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                    </div>

                    <div class="panel-content">
                        <div class="profile-section">
                            <div class="section-label">User Journey</div>
                            <div id="timelineContainer" style="margin-top: 1.5rem">
                                ${(() => {
                                    if (logs.length === 0) return '<p style="color:rgba(255,255,255,0.2)">No events recorded.</p>';
                                    
                                    // Sort ASC (oldest first) for "Journey" flow
                                    const sortedLogs = [...logs].sort((a,b) => new Date(a.created_at) - new Date(b.created_at));
                                    
                                    let lastTime = null;
                                    return sortedLogs.map((log, idx) => {
                                        const currentTime = new Date(log.created_at);
                                        const isConversion = log.event_name.includes('submit') || log.event_name.includes('confirm');
                                        
                                        // Detect session breaks (> 30 mins)
                                        let sessionBreak = '';
                                        if (lastTime && (currentTime - lastTime) > 30 * 60 * 1000) {
                                            sessionBreak = `<div style="font-size:0.6rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.2em; margin: 1.5rem 0 1.5rem -0.8rem; font-weight:700; opacity:0.6">— New Session —</div>`;
                                        }
                                        lastTime = currentTime;

                                        // Icon assignment
                                        let icon = '👁️';
                                        if (log.event_name.includes('hero')) icon = '🏡';
                                        if (log.event_name.includes('gallery')) icon = '🖼️';
                                        if (log.event_name.includes('unit')) icon = '🏢';
                                        if (log.event_name.includes('form') || log.event_name.includes('waitlist')) icon = '📝';
                                        if (isConversion) icon = '✨';

                                        return `
                                            ${sessionBreak}
                                            <div class="journey-item" style="${isConversion ? 'border-left: 2px solid #10b981' : ''}">
                                                <div class="journey-time">
                                                    ${currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </div>
                                                <div style="display:flex; align-items:center; gap:0.5rem">
                                                    <span style="font-size:0.8rem; opacity:0.8">${icon}</span>
                                                    <span class="journey-title">${log.event_name.replace(/_/g, ' ')}</span>
                                                </div>
                                            </div>
                                        `;
                                    }).join('');
                                })()}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML = html;
        }

        async function viewJourney(sessionId, email) {
            const panel = document.getElementById('journeyPanel');
            panel.innerHTML = `<div style="padding: 2rem; text-align: center; color: rgba(255,255,255,0.2)">Generating profile...</div>`;
            panel.classList.add('active');

            try {
                const { intel, logs } = await fetchLeadData(email);
                renderLeadProfile(intel, logs, panel, true);
            } catch (err) {
                panel.innerHTML = `<div style="color: #ef4444; padding: 2rem">Error.</div>`;
            }
        }

        function closeJourney() {
            document.getElementById('journeyPanel').classList.remove('active');
        }

        async function generateAISummary(email, insightsRaw = '', grade = 'N/A', score = 0, logs = []) {
            const btn = document.getElementById('genSummaryBtn');
            const section = document.getElementById('aiSummarySection');
            const originalHtml = btn ? btn.innerHTML : '';
            
            // Convert piped string back to array
            const insights = typeof insightsRaw === 'string' ? insightsRaw.split('|').filter(i => i) : insightsRaw;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span><svg class="spin" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:8px"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Generating...</span>';
            }

            try {
                const response = await fetch(`../api/ai-summary.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, insights, grade, score, logs })
                });
                const result = await response.json();
                
                if (result.success) {
                    section.innerHTML = `
                        <div class="ai-summary-box" style="animation: fadeIn 0.8s ease-out; position:relative">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
                                <div style="font-size: 0.7rem; color: var(--accent); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600">
                                    ✨ Prospect Summary
                                </div>
                                <button class="mini-copy-btn" onclick="generateAISummary('${email}', '${insights.join('|')}', '${grade}', ${score}, ${JSON.stringify(logs).replace(/"/g, '&quot;')})" title="Regenerate Summary">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                            </div>
                            <div style="font-size: 0.85rem; line-height: 1.6; color: rgba(255,255,255,0.7); text-align: left">
                                ${result.summary.split('\n').map(line => `<div style="margin-bottom:0.4rem">${line}</div>`).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    console.error("Narrative Error:", result);
                    alert('Error: ' + (result.error || 'Unknown error'));
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                }
            } catch (err) {
                alert('Connection error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            }
        }

        // Auto Refresh every 30s
        fetchData();
        setInterval(fetchData, 30000);
    </script>
</body>
</html>
