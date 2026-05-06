<?php
$pageTitle = 'Lead Profile';
$activePage = 'leads';
$extraJs = ['/admin/js/lead-profile.js'];
include __DIR__ . '/includes/layout-header.php';
?>

<div class="mb-3">
    <a href="/admin/leads.php" class="text-decoration-none text-body-tertiary">
        <i class="bi bi-arrow-left me-2"></i>All Leads
    </a>
</div>
<div id="leadProfileContent">
    <div class="text-center text-body-tertiary py-5">
        <div class="spinner-border spinner-border-sm text-secondary me-2"></div>Loading full profile...
    </div>
</div>

<script>const LEAD_EMAIL = <?= json_encode($_GET['email'] ?? '') ?>;</script>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
