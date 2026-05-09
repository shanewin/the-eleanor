<div class="sidebar">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="brand">THE ELEANOR</div>
            <div class="brand-sub">Command Center</div>
        </div>
        <div class="position-relative" style="margin-top:2px">
            <button class="btn btn-link text-white-50 p-0 position-relative" id="notifBellBtn" onclick="toggleNotifDropdown()" style="font-size:1.1rem" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="badge bg-danger rounded-pill position-absolute" id="notifBellBadge" style="display:none;top:-4px;right:-8px;font-size:0.55rem;padding:2px 5px"></span>
            </button>
        </div>
    </div>
    <div id="notifDropdown" class="notif-dropdown" style="display:none">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom border-secondary">
            <span class="small fw-semibold text-white">Notifications</span>
            <a href="#" class="small text-primary text-decoration-none" onclick="event.preventDefault(); markAllNotifRead()">Mark all read</a>
        </div>
        <div id="notifList" style="max-height:350px;overflow-y:auto">
            <div class="text-center text-white-50 small py-4">No notifications</div>
        </div>
    </div>
    <nav class="nav nav-pills flex-column gap-1 mt-3">
        <a href="/admin/" class="nav-link<?= ($activePage ?? '') === 'overview' ? ' active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="/admin/leads.php" class="nav-link<?= ($activePage ?? '') === 'leads' ? ' active' : '' ?>">
            <i class="bi bi-people"></i> Leads
        </a>
        <a href="/admin/communications.php" class="nav-link<?= ($activePage ?? '') === 'communications' ? ' active' : '' ?>" style="position:relative">
            <i class="bi bi-chat-left-text"></i> Communications
            <span class="badge bg-danger rounded-pill" id="sidebarUnreadBadge" style="display:none;position:absolute;right:10px;font-size:0.6rem"></span>
        </a>
        <?php if (isOwner()): ?>
        <a href="/admin/brokers.php" class="nav-link<?= ($activePage ?? '') === 'brokers' ? ' active' : '' ?>">
            <i class="bi bi-person-badge"></i> Brokers
        </a>
        <?php endif; ?>
        <a href="/admin/calendar.php" class="nav-link<?= ($activePage ?? '') === 'calendar' ? ' active' : '' ?>">
            <i class="bi bi-calendar3"></i> Calendar
        </a>
        <?php if (isOwner()): ?>
        <a href="/admin/settings.php" class="nav-link<?= ($activePage ?? '') === 'settings' ? ' active' : '' ?>">
            <i class="bi bi-gear"></i> Settings
        </a>
        <?php endif; ?>
    </nav>
    <div class="mt-auto pt-4">
        <a href="/admin/profile.php" class="nav-link<?= ($activePage ?? '') === 'profile' ? ' active' : '' ?>">
            <i class="bi bi-person-circle"></i> My Profile
        </a>
        <a href="?logout=1" class="nav-link text-danger opacity-50" style="font-size:0.8rem;">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</div>
