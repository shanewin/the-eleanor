<?php
require_once 'auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Eleanor | Lead-to-Showing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-body-font-family: 'Inter', sans-serif;
            --bs-body-bg: #0a0a0f;
            --bs-tertiary-bg: #141420;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0f;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1030;
            background: #0d0d14;
            border-right: 1px solid rgba(255,255,255,0.06);
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1rem;
        }
        .sidebar .brand {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #fff;
            padding: 0 0.75rem;
        }
        .sidebar .brand-sub {
            font-size: 0.7rem;
            font-weight: 400;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.35);
            padding: 0 0.75rem;
            margin-bottom: 2rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.5);
            border-radius: 0.5rem;
            padding: 0.6rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.15s;
        }
        .sidebar .nav-link:hover {
            color: rgba(255,255,255,0.8);
            background: rgba(255,255,255,0.04);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(99,102,241,0.15);
        }
        .sidebar .nav-link i {
            font-size: 1.1rem;
            width: 1.3rem;
            text-align: center;
        }

        /* Main content offset */
        .main-content {
            margin-left: 240px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
        }

        /* Stat cards */
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgba(255,255,255,0.4);
            margin-bottom: 0.4rem;
        }

        /* Tables */
        .table > thead > tr > th {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.35);
            border-bottom-color: rgba(255,255,255,0.06);
            font-weight: 600;
            white-space: nowrap;
        }
        .table > tbody > tr {
            cursor: pointer;
            transition: background 0.15s;
        }
        .table > tbody > tr:hover {
            background: rgba(255,255,255,0.03) !important;
        }
        .table > tbody > tr > td {
            vertical-align: middle;
            border-bottom-color: rgba(255,255,255,0.04);
            padding: 0.9rem 0.75rem;
        }
        th.sortable { cursor: pointer; }
        th.sortable:hover { color: rgba(255,255,255,0.7); }
        th.sortable.active { color: #6366f1; }

        /* User avatar */
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .user-avatar-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            background: rgba(99,102,241,0.15);
            color: #818cf8;
            flex-shrink: 0;
        }

        /* Grade pill */
        .grade-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.8rem;
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.6);
        }
        .grade-pill.elite {
            background: rgba(99,102,241,0.2);
            color: #a5b4fc;
            box-shadow: 0 0 12px rgba(99,102,241,0.15);
        }

        /* Mini copy button */
        .mini-copy-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,0.25);
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: all 0.15s;
        }
        .mini-copy-btn:hover { color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.05); }

        /* Company logo */
        .company-logo {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: #fff;
            padding: 2px;
            flex-shrink: 0;
        }

        /* Delete button */
        .delete-btn {
            background: none;
            border: 1px solid rgba(239,68,68,0.2);
            color: rgba(239,68,68,0.5);
            font-size: 0.75rem;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .delete-btn:hover { background: rgba(239,68,68,0.1); color: #ef4444; border-color: rgba(239,68,68,0.4); }

        /* Analytics metric rows */
        .metric-row { margin-bottom: 1.2rem; }
        .metric-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; }
        .metric-name { font-size: 0.82rem; font-weight: 500; color: rgba(255,255,255,0.75); }
        .metric-val { font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.5); }
        .metric-badge {
            background: rgba(99,102,241,0.15);
            color: #818cf8;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
        }

        /* Journey panel (slide-out) */
        #journeyPanel {
            position: fixed;
            top: 0;
            right: -520px;
            width: 520px;
            height: 100vh;
            background: #111118;
            border-left: 1px solid rgba(255,255,255,0.06);
            z-index: 1050;
            overflow-y: auto;
            transition: right 0.3s ease;
            padding: 2rem;
        }
        #journeyPanel.active { right: 0; }

        /* Profile styles */
        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            border: 2px solid rgba(255,255,255,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .score-circle.score-high {
            border-color: rgba(99,102,241,0.4);
            background: rgba(99,102,241,0.08);
        }
        .score-val { font-size: 1.4rem; font-weight: 700; color: #fff; }
        .score-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.4); }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            display: block;
            border: 2px solid rgba(255,255,255,0.08);
        }
        .profile-name { font-size: 1.4rem; font-weight: 700; color: #fff; text-align: center; }
        .profile-title { font-size: 0.85rem; color: rgba(255,255,255,0.5); text-align: center; margin-bottom: 1.5rem; }

        .insight-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            margin: 0.2rem;
        }
        .badge-success { background: rgba(16,185,129,0.12); color: #6ee7b7; }
        .badge-info { background: rgba(99,102,241,0.12); color: #a5b4fc; }
        .badge-warning { background: rgba(245,158,11,0.12); color: #fcd34d; }

        .action-bar {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        .action-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.15rem;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .action-val { font-size: 0.82rem; color: rgba(255,255,255,0.7); text-transform: none; letter-spacing: 0; }
        .icon-btn {
            background: rgba(255,255,255,0.05);
            border: none;
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

        .section-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.3);
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .intel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .intel-item { display: flex; flex-direction: column; gap: 0.2rem; }
        .intel-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.3); }
        .intel-val { font-size: 0.85rem; color: rgba(255,255,255,0.75); font-weight: 500; }

        .journey-item {
            padding: 0.6rem 0.8rem;
            border-left: 2px solid rgba(255,255,255,0.06);
            margin-left: 0.5rem;
        }
        .journey-time { font-size: 0.65rem; color: rgba(255,255,255,0.3); margin-bottom: 0.15rem; }
        .journey-title { font-size: 0.82rem; color: rgba(255,255,255,0.7); }

        .ai-summary-box-removed {
            background: rgba(99,102,241,0.04);
            border: 1px solid rgba(99,102,241,0.12);
            border-radius: 12px;
            padding: 1.2rem;
        }

        .premium-btn {
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.15));
            border: 1px solid rgba(99,102,241,0.2);
            color: #a5b4fc;
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
        }
        .premium-btn:hover { background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(139,92,246,0.25)); }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spin { animation: spin 1s linear infinite; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">THE ELEANOR</div>
        <div class="brand-sub">Command Center</div>
        <nav class="nav nav-pills flex-column gap-1" id="mainNav">
            <a href="#" class="nav-link active" data-view="overview">
                <i class="bi bi-grid-1x2"></i> Overview
            </a>
            <a href="#" class="nav-link" data-view="leads">
                <i class="bi bi-people"></i> Leads
            </a>
            <a href="#" class="nav-link" data-view="analytics">
                <i class="bi bi-bar-chart-line"></i> Analytics
            </a>
            <a href="#" class="nav-link" data-view="brokers">
                <i class="bi bi-person-badge"></i> Brokers
            </a>
            <a href="#" class="nav-link" data-view="settings">
                <i class="bi bi-gear"></i> Settings
            </a>
        </nav>
        <div class="mt-auto pt-4">
            <a href="?logout=1" class="nav-link text-danger opacity-50" style="font-size:0.8rem;">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Overview View -->
        <div id="view-overview" class="dashboard-view">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">Lead-to-Showing Command Center</h1>
                    <small class="text-body-tertiary">Updating in real-time</small>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-body-tertiary border-0 stat-card">
                        <div class="card-body">
                            <div class="stat-label">Unique Visitors</div>
                            <div class="stat-value" id="statSessions"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-body-tertiary border-0 stat-card">
                        <div class="card-body">
                            <div class="stat-label">Total Leads</div>
                            <div class="stat-value" id="statLeads"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-body-tertiary border-0 stat-card">
                        <div class="card-body">
                            <div class="stat-label">Visitor-to-Lead Rate</div>
                            <div class="stat-value" id="statConv"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-body-tertiary border-0 stat-card">
                        <div class="card-body">
                            <div class="stat-label">New Today <span id="statTodayDate" style="font-weight:400;letter-spacing:0.05em;opacity:0.6"></span></div>
                            <div class="stat-value" id="statHot"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-body-tertiary border-0">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Recent Conversions &amp; Enrichment</h2>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-body-tertiary">Sort by:</small>
                            <select id="overview-sort" onchange="fetchData()" class="form-select form-select-sm" style="width:auto;">
                                <option value="date">Date (Newest)</option>
                                <option value="grade">Grade (Elite First)</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover leadsTable mb-0" style="background:transparent;">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Identity</th>
                                    <th>Contact</th>
                                    <th>Intent</th>
                                    <th>Engagement</th>
                                    <th>Grade</th>
                                    <th>Assigned</th>
                                    <th>First Response</th>
                                </tr>
                            </thead>
                            <tbody><tr><td colspan="10" class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div><span class="text-body-tertiary ms-2">Loading...</span></td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads View -->
        <div id="view-leads" class="dashboard-view" style="display:none">
            <div class="mb-4">
                <h1 class="h3 fw-bold mb-0">All Qualified Leads</h1>
            </div>
            <div class="card bg-body-tertiary border-0">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover leadsTable mb-0" style="background:transparent;">
                            <thead>
                                <tr>
                                    <th class="sortable" onclick="sortLeads('created_at')">Timestamp</th>
                                    <th class="sortable" onclick="sortLeads('last_name')">Lead</th>
                                    <th>Contact</th>
                                    <th>Intent</th>
                                    <th class="sortable" onclick="sortLeads('event_count')">Engagement</th>
                                    <th class="sortable" onclick="sortLeads('grade_score')">Grade</th>
                                    <th>Assigned</th>
                                    <th>First Response</th>
                                    <th>Management</th>
                                </tr>
                            </thead>
                            <tbody><tr><td colspan="10" class="text-center py-5"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div><span class="text-body-tertiary ms-2">Loading...</span></td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics View -->
        <div id="view-analytics" class="dashboard-view" style="display:none">
            <div class="mb-4">
                <h1 class="h3 fw-bold mb-0">User Behavioral Insights</h1>
                <small class="text-body-tertiary">Deep behavioral analysis</small>
            </div>

            <div class="row g-4">
                <!-- Section Engagement -->
                <div class="col-md-6">
                    <div class="card bg-body-tertiary border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Section Engagement</h5>
                            <p class="text-body-tertiary mb-4" style="font-size:0.75rem;">Average time spent per section (seconds)</p>
                            <div id="section-engagement-list"><!-- Dynamic --></div>
                        </div>
                    </div>
                </div>

                <!-- Interaction Hotspots -->
                <div class="col-md-6">
                    <div class="card bg-body-tertiary border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Top Interactions</h5>
                            <p class="text-body-tertiary mb-4" style="font-size:0.75rem;">Most clicked buttons and CTAs</p>
                            <div id="top-interactions-list"><!-- Dynamic --></div>
                        </div>
                    </div>
                </div>

                <!-- Device Breakdown -->
                <div class="col-md-6">
                    <div class="card bg-body-tertiary border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Device Distribution</h5>
                            <div id="device-breakdown-list" class="mt-3"><!-- Dynamic --></div>
                        </div>
                    </div>
                </div>

                <!-- Traffic Trends -->
                <div class="col-md-6">
                    <div class="card bg-body-tertiary border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Recent Traffic Trend</h5>
                            <div id="traffic-trends-list" class="mt-3"><!-- Dynamic --></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Brokers View -->
        <div id="view-brokers" class="dashboard-view" style="display:none">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 fw-bold mb-0">Broker Management</h1>
                <button class="btn btn-primary" onclick="showBrokerModal()"><i class="bi bi-plus-lg me-1"></i>Add Broker</button>
            </div>
            <div class="card bg-body-tertiary border-0">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" style="background:transparent;" id="brokersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" class="text-center py-5 text-body-tertiary">Loading brokers...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Profile View (In-Page) -->
        <div id="view-lead-profile" class="dashboard-view" style="display:none">
            <div class="mb-3">
                <a href="#" class="text-decoration-none text-body-tertiary" onclick="event.preventDefault(); showView('leads')">
                    <i class="bi bi-arrow-left me-2"></i>Back to Leads
                </a>
            </div>
            <div id="inPageProfileContent">
                <!-- Dynamic Content -->
            </div>
        </div>

        <!-- Settings View -->
        <div id="view-settings" class="dashboard-view" style="display:none">
            <h1 class="h3 fw-bold mb-4">Settings</h1>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card bg-body-tertiary border-0 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-semibold mb-1">Notification Emails</h5>
                            <p class="text-white-50 small mb-3">Comma-separated list of email addresses that receive lead notifications and enrichment reports.</p>
                            <div class="mb-3">
                                <textarea class="form-control bg-dark border-secondary text-white" id="settingsNotificationEmails" rows="3" placeholder="email1@example.com, email2@example.com"></textarea>
                            </div>
                            <div id="settingsStatus" class="small mb-3" style="display:none"></div>
                            <button class="btn btn-primary" onclick="saveSettingsForm()">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.main-content -->

    <!-- Journey Slide-Out Panel -->
    <div id="journeyPanel">
        <!-- Content dynamic -->
    </div>

    <!-- Broker Modal -->
    <div class="modal fade" id="brokerModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content bg-dark">
          <div class="modal-header border-secondary">
            <h5 class="modal-title" id="brokerModalTitle">Add Broker</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="brokerEditId">
            <div class="mb-3">
              <label class="form-label small text-white-50">Name</label>
              <input type="text" class="form-control bg-dark border-secondary text-white" id="brokerName">
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Email</label>
              <input type="email" class="form-control bg-dark border-secondary text-white" id="brokerEmail">
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Phone</label>
              <input type="text" class="form-control bg-dark border-secondary text-white" id="brokerPhone">
            </div>
            <div class="mb-3">
              <label class="form-label small text-white-50">Role</label>
              <select class="form-select bg-dark border-secondary text-white" id="brokerRole">
                <option value="broker">Broker</option>
                <option value="owner">Owner</option>
              </select>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveBroker()">Save</button>
          </div>
        </div>
      </div>
    </div>

    <?php
    if (isset($_GET['logout'])) { logout(); }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── XSS Escape Helper ──
        function esc(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function safeUrl(url) {
            if (!url) return '';
            const s = String(url).trim();
            if (/^https?:\/\//i.test(s)) return s;
            return '';
        }

        // ── Navigation Logic ──
        function showView(view) {
            document.querySelectorAll('.nav-link').forEach(l => {
                l.classList.remove('active');
                if (l.getAttribute('data-view') === view) l.classList.add('active');
            });

            document.querySelectorAll('.dashboard-view').forEach(v => v.style.display = 'none');
            const target = document.getElementById('view-' + view);
            if (target) target.style.display = 'block';

            if (view === 'analytics') {
                fetchAnalytics();
            }
            if (view === 'brokers') {
                fetchBrokers();
            }
            if (view === 'settings') {
                loadSettings();
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

        // ── Lead Grading Algorithm (Leasing-Focused) ──
        function calculateLeadGrade(lead) {
            let insights = [];
            const source = (lead.source || lead.submission_type || '').toLowerCase();

            // 1. Affordability Check (30 pts max)
            // Industry standard: rent should be no more than 1/40th of annual salary
            const salary = lead.inferred_salary || '';
            const budget = parseFloat((lead.budget || '0').replace(/[^0-9.]/g, ''));
            if (salary && budget > 0) {
                // Parse salary range like "100,000-150,000" → use lower bound
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

        // ── Brokers ──
        let brokersCache = [];

        async function fetchBrokers() {
            try {
                const res = await fetch('../api/admin-api.php?action=get_brokers');
                const data = await res.json();
                brokersCache = Array.isArray(data) ? data : [];
                renderBrokersTable(brokersCache);
            } catch (err) {
                console.error('Error fetching brokers:', err);
                brokersCache = [];
            }
        }

        function renderBrokersTable(brokers) {
            const tbody = document.querySelector('#brokersTable tbody');
            if (!tbody) return;
            if (brokers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-body-tertiary py-5">No brokers added yet.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            brokers.forEach(function(broker) {
                const statusBadge = broker.status === 'inactive'
                    ? '<span class="badge bg-secondary">Inactive</span>'
                    : '<span class="badge bg-success">Active</span>';
                const row = document.createElement('tr');
                row.innerHTML = '<td class="fw-semibold text-white">' + esc(broker.name) + '</td>'
                    + '<td>' + esc(broker.email) + '</td>'
                    + '<td>' + esc(broker.phone || '-') + '</td>'
                    + '<td><span class="text-capitalize">' + esc(broker.role || 'broker') + '</span></td>'
                    + '<td>' + statusBadge + '</td>'
                    + '<td class="text-end">'
                    + '<button class="btn btn-sm btn-outline-primary me-1" onclick="showBrokerModal(' + esc(String(broker.id)) + ')"><i class="bi bi-pencil"></i></button>'
                    + '<button class="btn btn-sm btn-outline-danger" onclick="deleteBroker(' + esc(String(broker.id)) + ')"><i class="bi bi-trash"></i></button>'
                    + '</td>';
                tbody.appendChild(row);
            });
        }

        function showBrokerModal(brokerId) {
            const modal = new bootstrap.Modal(document.getElementById('brokerModal'));
            document.getElementById('brokerEditId').value = '';
            document.getElementById('brokerName').value = '';
            document.getElementById('brokerEmail').value = '';
            document.getElementById('brokerPhone').value = '';
            document.getElementById('brokerRole').value = 'broker';
            document.getElementById('brokerModalTitle').textContent = 'Add Broker';

            if (brokerId) {
                const broker = brokersCache.find(b => b.id == brokerId);
                if (broker) {
                    document.getElementById('brokerEditId').value = broker.id;
                    document.getElementById('brokerName').value = broker.name || '';
                    document.getElementById('brokerEmail').value = broker.email || '';
                    document.getElementById('brokerPhone').value = broker.phone || '';
                    document.getElementById('brokerRole').value = broker.role || 'broker';
                    document.getElementById('brokerModalTitle').textContent = 'Edit Broker';
                }
            }
            modal.show();
        }

        async function saveBroker() {
            const id = document.getElementById('brokerEditId').value;
            const payload = {
                name: document.getElementById('brokerName').value.trim(),
                email: document.getElementById('brokerEmail').value.trim(),
                phone: document.getElementById('brokerPhone').value.trim(),
                role: document.getElementById('brokerRole').value
            };

            if (!payload.name || !payload.email) {
                alert('Name and email are required.');
                return;
            }

            try {
                const action = id ? 'update_broker' : 'add_broker';
                if (id) payload.id = id;
                const res = await fetch('../api/admin-api.php?action=' + action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('brokerModal')).hide();
                    fetchBrokers();
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Connection error while saving broker.');
            }
        }

        async function deleteBroker(id) {
            if (!confirm('Are you sure you want to delete this broker?')) return;
            try {
                const res = await fetch('../api/admin-api.php?action=delete_broker', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await res.json();
                if (result.success) {
                    fetchBrokers();
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Connection error while deleting broker.');
            }
        }

        async function assignLead(email, source, brokerId) {
            try {
                const res = await fetch('../api/admin-api.php?action=assign_lead', {
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

        async function respondLead(email, source, method) {
            try {
                const res = await fetch('../api/admin-api.php?action=respond_lead', {
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

        function formatElapsed(ms) {
            if (ms < 60000) return '< 1m';
            if (ms < 3600000) return Math.floor(ms / 60000) + 'm';
            if (ms < 86400000) return Math.floor(ms / 3600000) + 'h ' + Math.floor((ms % 3600000) / 60000) + 'm';
            return Math.floor(ms / 86400000) + 'd ' + Math.floor((ms % 86400000) / 3600000) + 'h';
        }

        // ── Sorting ──
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

        // ── Fetch Data ──
        async function fetchData() {
            try {
                const statsResponse = await fetch('../api/admin-api.php?action=stats');
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

                // Fetch brokers for assignment dropdowns
                try {
                    const brokersRes = await fetch('../api/admin-api.php?action=get_brokers');
                    const brokersData = await brokersRes.json();
                    brokersCache = Array.isArray(brokersData) ? brokersData : [];
                } catch (e) { /* brokers fetch is non-critical */ }

                const leadsResponse = await fetch('../api/admin-api.php?action=leads');
                if (!leadsResponse.ok) throw new Error("API Error");

                let leads = await leadsResponse.json();

                leads = leads.map(l => ({
                    ...l,
                    grade: calculateLeadGrade(l),
                    grade_score: calculateLeadGrade(l).score
                }));

                currentLeads = leads;

                const sortVal = document.getElementById('overview-sort')?.value || 'date';

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

                if (document.getElementById('view-overview').style.display !== 'none') {
                    if (sortVal === 'grade') {
                        leads.sort((a, b) => b.grade.score - a.grade.score);
                    } else {
                        leads.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    }
                }

                document.querySelectorAll('.leadsTable').forEach(table => {
                    const tbody = table.querySelector('tbody');
                    const isOverview = table.closest('#view-overview') !== null;

                    if (!isOverview) {
                        table.querySelectorAll('th.sortable').forEach(th => {
                            th.classList.remove('active');
                            if (th.getAttribute('onclick')?.includes("'" + sortConfig.column + "'")) {
                                th.classList.add('active');
                            }
                        });
                    }

                    if (leads.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="' + (isOverview ? 8 : 10) + '" class="text-center text-body-tertiary py-5">No leads recorded yet.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = '';
                    leads.forEach(lead => {
                        const row = document.createElement('tr');
                        row.style.cursor = 'pointer';
                        row.onclick = () => {
                            const activeView = document.querySelector('.nav-link.active').getAttribute('data-view');
                            if (activeView === 'leads') {
                                viewInPageProfile(lead.tracking_id, lead.email);
                            } else {
                                viewJourney(lead.tracking_id, lead.email);
                            }
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
                        const contactInfo = '<div>'
                            + '<span class="fw-semibold text-white">' + esc(lead.first_name) + ' ' + esc(lead.last_name) + '</span>'
                            + '<div class="d-flex align-items-center gap-2">'
                            + '<small class="text-body-tertiary">' + escapedEmail + '</small>'
                            + '<button class="mini-copy-btn" onclick="event.stopPropagation(); copyToClipboard(\'' + escapedEmail.replace(/'/g, "\\'") + '\', this)">'
                            + '<i class="bi bi-clipboard" style="font-size:0.7rem"></i>'
                            + '</button>'
                            + '</div></div>';

                        const identityCombined = '<div class="d-flex align-items-center gap-2">' + avatar + contactInfo + '</div>';

                        const companyLogoUrl = safeUrl(lead.company_logo);
                        const enrichment = lead.job_title
                            ? '<div class="d-flex align-items-center gap-2">'
                                + (companyLogoUrl ? '<img src="' + esc(companyLogoUrl) + '" class="company-logo">' : '')
                                + '<span class="fw-medium" style="font-size:0.85rem;">' + esc(lead.job_title) + ' @ ' + esc(lead.company) + '</span>'
                                + '</div>'
                            : '<span class="text-body-tertiary">Pending...</span>';

                        const activityLabel = lead.event_count > 10 ? '<span class="text-success"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Hot</span>' :
                                             lead.event_count > 5 ? '<span class="text-primary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Active</span>' :
                                             lead.event_count > 0 ? '<span class="text-body-tertiary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Quiet</span>' :
                                             '<span class="text-body-tertiary"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i> No Activity</span>';

                        const engagementLabel = '<span style="font-size:0.8rem;" class="fw-semibold">' + activityLabel + '</span>';

                        const gradeClass = lead.grade.score >= 80 ? 'elite' : '';

                        // Build intent column
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

                        // Contact column with email + phone
                        const phoneDisplay = lead.phone ? '<div style="font-size:0.8rem;color:rgba(255,255,255,0.6)">' + esc(lead.phone) + '</div>' : '';
                        const contactHtml = '<div>'
                            + '<div class="d-flex align-items-center gap-1"><small>' + escapedEmail + '</small>'
                            + '<button class="mini-copy-btn" onclick="event.stopPropagation(); copyToClipboard(\'' + escapedEmail.replace(/'/g, "\\'") + '\', this)"><i class="bi bi-clipboard" style="font-size:0.65rem"></i></button></div>'
                            + phoneDisplay + '</div>';

                        // Build Assigned column
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

                        // Build First Response column
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
                                + '<div class="dropdown" onclick="event.stopPropagation()">'
                                + '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" style="font-size:0.7rem;padding:0.15rem 0.4rem;" data-bs-toggle="dropdown">Mark</button>'
                                + '<ul class="dropdown-menu dropdown-menu-dark">'
                                + '<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); respondLead(\'' + escapedLeadEmail + '\', \'' + escapedLeadSource + '\', \'SMS\')">SMS</a></li>'
                                + '<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); respondLead(\'' + escapedLeadEmail + '\', \'' + escapedLeadSource + '\', \'Email\')">Email</a></li>'
                                + '<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); respondLead(\'' + escapedLeadEmail + '\', \'' + escapedLeadSource + '\', \'Phone\')">Phone</a></li>'
                                + '</ul></div></div>';
                        }

                        let rowContent;
                        if (isOverview) {
                            rowContent = '<td>' + timestamp + '</td>'
                                + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<div><span class="fw-semibold text-white">' + esc(lead.first_name) + ' ' + esc(lead.last_name) + '</span></div></div></td>'
                                + '<td>' + contactHtml + '</td>'
                                + '<td>' + intentHtml + '</td>'
                                + '<td>' + engagementLabel + '</td>'
                                + '<td class="text-center"><div class="grade-pill ' + gradeClass + '">' + esc(lead.grade.letter) + '</div></td>'
                                + '<td>' + assignedHtml + '</td>'
                                + '<td>' + firstResponseHtml + '</td>';
                        } else {
                            // Badges removed — grade speaks for itself

                            const escapedEmailForDelete = esc(lead.email || '').replace(/'/g, "\\'");
                            const escapedSourceForDelete = esc(lead.source || '').replace(/'/g, "\\'");

                            rowContent = '<td>' + timestamp + '</td>'
                                + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<div><span class="fw-semibold text-white">' + esc(lead.first_name) + ' ' + esc(lead.last_name) + '</span></div></div></td>'
                                + '<td>' + contactHtml + '</td>'
                                + '<td>' + intentHtml + '</td>'
                                + '<td>' + engagementLabel + '</td>'
                                + '<td class="text-center"><div class="grade-pill ' + gradeClass + '">' + esc(lead.grade.letter) + '</div></td>'
                                + '<td>' + assignedHtml + '</td>'
                                + '<td>' + firstResponseHtml + '</td>'
                                + '<td class="text-end"><button class="delete-btn" onclick="event.stopPropagation(); deleteLead(\'' + escapedEmailForDelete + '\', \'' + escapedSourceForDelete + '\')">Delete</button></td>';
                        }
                        row.innerHTML = rowContent;
                        tbody.appendChild(row);
                    });
                });

            } catch (err) {
                console.error("Dashboard error:", err);
            }
        }

        // ── Delete Lead ──
        async function deleteLead(email, source) {
            if (!confirm('Are you sure you want to permanently delete this lead from the ' + source + ' list?')) return;

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
                    fetchData();
                } else {
                    alert('Error deleting lead: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Connection error while deleting lead');
            }
        }

        // ── Fetch Analytics ──
        async function fetchAnalytics() {
            try {
                const response = await fetch('../api/admin-api.php?action=analytics');
                const data = await response.json();

                // 1. Section Engagement
                const sectionList = document.getElementById('section-engagement-list');
                sectionList.innerHTML = '';
                const engagementData = data.sectionEngagement.filter(s => s.section && s.section !== 'unknown');
                const maxSeconds = Math.max(...engagementData.map(s => s.avg_seconds), 1);

                engagementData.forEach(s => {
                    const pct = (s.avg_seconds / maxSeconds) * 100;
                    const name = esc(s.section.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
                    sectionList.innerHTML += '<div class="metric-row">'
                        + '<div class="metric-info"><span class="metric-name">' + name + '</span><span class="metric-val">' + Math.round(s.avg_seconds) + 's <small class="text-body-tertiary">avg</small></span></div>'
                        + '<div class="progress" style="height:6px;background:rgba(255,255,255,0.06)"><div class="progress-bar bg-primary" style="width:' + pct + '%"></div></div>'
                        + '</div>';
                });

                // 2. Top Interactions
                const interactionList = document.getElementById('top-interactions-list');
                interactionList.innerHTML = '';
                const maxClicks = Math.max(...data.topInteractions.map(i => i.click_count), 1);

                data.topInteractions.forEach(i => {
                    const pct = (i.click_count / maxClicks) * 100;
                    interactionList.innerHTML += '<div class="metric-row">'
                        + '<div class="metric-info"><span class="metric-name">' + esc(i.button_text) + '</span><span class="metric-badge">' + esc(String(i.click_count)) + '</span></div>'
                        + '<div class="progress" style="height:6px;background:rgba(255,255,255,0.06)"><div class="progress-bar bg-info" style="width:' + pct + '%"></div></div>'
                        + '</div>';
                });

                // 3. Device Breakdown
                const deviceList = document.getElementById('device-breakdown-list');
                deviceList.innerHTML = '';
                const totalDevices = data.deviceBreakdown.reduce((sum, d) => sum + parseInt(d.count), 0);

                data.deviceBreakdown.forEach(d => {
                    const pct = (d.count / totalDevices) * 100;
                    deviceList.innerHTML += '<div class="metric-row">'
                        + '<div class="metric-info"><span class="metric-name">' + esc(d.device_type) + '</span><span class="metric-val">' + Math.round(pct) + '%</span></div>'
                        + '<div class="progress" style="height:6px;background:rgba(255,255,255,0.06)"><div class="progress-bar bg-warning" style="width:' + pct + '%"></div></div>'
                        + '</div>';
                });

                // 4. Traffic Trends
                const trafficList = document.getElementById('traffic-trends-list');
                trafficList.innerHTML = '';
                const maxSessions = Math.max(...data.trafficTrends.map(t => t.sessions), 1);

                data.trafficTrends.forEach(t => {
                    const pct = (t.sessions / maxSessions) * 100;
                    const date = new Date(t.date).toLocaleDateString([], { month: 'short', day: 'numeric' });
                    trafficList.innerHTML += '<div class="metric-row">'
                        + '<div class="metric-info"><span class="metric-name">' + esc(date) + '</span><span class="metric-val">' + esc(String(t.sessions)) + ' <small class="text-body-tertiary">visits</small> / ' + esc(String(t.leads)) + ' <small class="text-success">leads</small></span></div>'
                        + '<div class="progress" style="height:6px;background:rgba(255,255,255,0.06)"><div class="progress-bar bg-success" style="width:' + pct + '%"></div></div>'
                        + '</div>';
                });

            } catch (err) {
                console.error("Analytics Error:", err);
            }
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

        // ── In-Page Lead Profile ──
        async function viewInPageProfile(sessionId, email) {
            showView('lead-profile');
            const container = document.getElementById('inPageProfileContent');
            container.innerHTML = '<div class="text-center text-body-tertiary py-5">Generating intelligence profile...</div>';

            try {
                const { intel, logs } = await fetchLeadData(email);
                renderLeadProfile(intel, logs, container, false);
            } catch (err) {
                container.innerHTML = '<div class="alert alert-danger">Error loading profile.</div>';
            }
        }

        async function fetchLeadData(email) {
            const intelRes = await fetch('../api/admin-api.php?action=lead_detail&email=' + encodeURIComponent(email));
            const intel = await intelRes.json();
            let logs = [];
            if (email) {
                const logsRes = await fetch('../api/admin-api.php?action=lead_activity&email=' + encodeURIComponent(email));
                logs = await logsRes.json();
            }
            return { intel, logs };
        }

        // ── Render Lead Profile ──
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
            const roleTenure = gradeInfo.roleTenure;
            const companyTenure = gradeInfo.companyTenure;

            const escapedEmail = esc(intel.email || '');
            const escapedName = esc(name);
            const escapedTitle = esc(intel.job_title || person.title || 'Private Individual');

            // Badges removed

            // Prepare AI summary data attribute
            const logsForAI = logs.map(function(l) { return { event: l.event_name, time: l.created_at }; });
            const insightsLabels = insights.map(function(i) { return i.label; }).join('|');

            // Close button for panel
            let closeBtn = '';
            if (isPanel) {
                closeBtn = '<button onclick="closeJourney()" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index:10"></button>';
            }

            // LinkedIn / Twitter links
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

            // AI Summary removed — not relevant for leasing workflow

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

            // Elite social signals section
            let eliteSectionHtml = '';
            if (education.length > 0 || boardRoles.length > 0) {
                eliteSectionHtml = '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
                    + '<div class="section-label">Elite Social Signals</div>'
                    + '<div class="intel-grid">' + educationHtml + boardHtml + '</div>'
                    + '</div></div>';
            }

            // Career Journey
            let careerHtml = '';
            if (employment.length > 0) {
                careerHtml = '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
                    + '<div class="section-label">Career Journey</div>'
                    + '<div class="d-flex flex-column gap-3 mt-3">';
                employment.forEach(function(job, idx) {
                    const dotColor = idx === 0 ? '#6366f1' : 'rgba(255,255,255,0.1)';
                    careerHtml += '<div class="d-flex gap-3 align-items-start">'
                        + '<div style="width:10px;height:10px;border-radius:50%;background:' + dotColor + ';margin-top:0.4rem;flex-shrink:0"></div>'
                        + '<div><div class="fw-medium text-white" style="font-size:0.9rem">' + esc(job.title) + '</div>'
                        + '<small class="text-primary">' + esc(job.organization_name) + '</small></div></div>';
                });
                careerHtml += '</div></div></div>';
            }

            // User Journey (activity logs)
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
            const layoutClass = isPanel ? '' : 'row g-4 mt-2';
            const colClass = isPanel ? '' : 'col-lg-6';

            let html = closeBtn
                + '<div class="score-circle ' + (totalScore >= 80 ? 'score-high' : '') + '">'
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
                + '<div class="' + layoutClass + '">'
                + '<div class="' + colClass + '">'
                    + '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
                    + '<div class="section-label">Submission Details</div>'
                    + '<table class="table table-sm table-dark mb-0" style="font-size:0.85rem">'
                    + '<tbody>'
                    + '<tr><td class="text-white-50" style="width:40%">Form</td><td class="text-primary fw-semibold">' + esc(intel.submission_type || 'General Lead') + '</td></tr>'
                    + '<tr><td class="text-white-50">Submitted</td><td>' + esc(intel.created_at ? new Date(intel.created_at).toLocaleString() : 'N/A') + '</td></tr>'
                    + (intel.phone || intel.phone_number ? '<tr><td class="text-white-50">Phone</td><td>' + esc(intel.phone || intel.phone_number) + '</td></tr>' : '')
                    + (intel.unit ? '<tr><td class="text-white-50">Unit</td><td>' + esc(intel.unit) + '</td></tr>' : '')
                    + (intel.unit_type ? '<tr><td class="text-white-50">Unit Type</td><td>' + esc(intel.unit_type) + '</td></tr>' : '')
                    + (intel.budget ? '<tr><td class="text-white-50">Budget</td><td>' + esc(intel.budget) + '</td></tr>' : '')
                    + (intel.move_in_date ? '<tr><td class="text-white-50">Move-In Date</td><td>' + esc(intel.move_in_date) + '</td></tr>' : '')
                    + (intel.hear_about_us ? '<tr><td class="text-white-50">How They Found Us</td><td>' + esc(intel.hear_about_us) + '</td></tr>' : '')
                    + (intel.interests ? '<tr><td class="text-white-50">Interests</td><td>' + esc(intel.interests) + '</td></tr>' : '')
                    + (intel.message ? '<tr><td class="text-white-50">Message</td><td style="white-space:pre-wrap">' + esc(intel.message) + '</td></tr>' : '')
                    + '</tbody></table>'
                    + '</div></div>'
                    + '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
                    + '<div class="section-label">Executive Intelligence</div>'
                    + '<div class="intel-grid">'
                    + '<div class="intel-item"><span class="intel-label">Seniority</span><span class="intel-val">' + esc(intel.seniority || 'Unknown') + '</span></div>'
                    + '<div class="intel-item"><span class="intel-label">Role Tenure</span><span class="intel-val">' + esc(formatTenure(roleTenure)) + '</span></div>'
                    + '<div class="intel-item"><span class="intel-label">Company</span><span class="intel-val">' + esc(intel.company || 'Not specified') + '</span></div>'
                    + '<div class="intel-item"><span class="intel-label">Annual Revenue</span><span class="intel-val">' + esc(intel.annual_revenue || org.annual_revenue_printed || 'Under $1M') + '</span></div>'
                    + '<div class="intel-item" style="grid-column:span 2;margin-top:1rem"><span class="intel-label">About ' + esc(intel.company || 'the Company') + '</span>'
                    + '<div class="p-3 rounded-3 mt-1" style="background:rgba(255,255,255,0.03);font-size:0.85rem;line-height:1.6;color:rgba(255,255,255,0.7)">'
                    + esc(intel.company_description || org.short_description || 'No description available.') + '</div></div>'
                    + '</div></div></div>'
                    + eliteSectionHtml
                    + careerHtml
                + '</div>'
                + '<div class="' + colClass + '">'
                    + '<div class="card bg-body-tertiary border-0 mb-3"><div class="card-body p-4">'
                    + '<div class="section-label">User Journey</div>'
                    + '<div class="mt-3">' + journeyHtml + '</div>'
                    + '</div></div>'
                + '</div>'
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

        // AI Summary removed — not relevant for leasing workflow

        // ── Settings ──
        async function loadSettings() {
            try {
                const res = await fetch('/api/admin-api.php?action=get_settings');
                const settings = await res.json();
                document.getElementById('settingsNotificationEmails').value = settings.notification_emails || '';
            } catch (e) {
                console.error('Failed to load settings:', e);
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

        // ── Auto Refresh every 30s ──
        fetchData();
        setInterval(fetchData, 30000);
    </script>
</body>
</html>
