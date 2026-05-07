<div class="sidebar">
    <div class="brand">THE ELEANOR</div>
    <div class="brand-sub">Command Center</div>
    <nav class="nav nav-pills flex-column gap-1">
        <a href="/admin/" class="nav-link<?= ($activePage ?? '') === 'overview' ? ' active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="/admin/leads.php" class="nav-link<?= ($activePage ?? '') === 'leads' ? ' active' : '' ?>">
            <i class="bi bi-people"></i> Leads
        </a>
        <a href="/admin/communications.php" class="nav-link<?= ($activePage ?? '') === 'communications' ? ' active' : '' ?>">
            <i class="bi bi-chat-left-text"></i> Communications
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
        <a href="?logout=1" class="nav-link text-danger opacity-50" style="font-size:0.8rem;">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</div>
