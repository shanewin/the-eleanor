<?php
require_once __DIR__ . '/../auth.php';
requireAdmin();
if (isset($_GET['logout'])) { logout(); }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Eleanor | <?= htmlspecialchars($pageTitle ?? 'Admin') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if (!empty($useCalendar)): ?>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="/admin/admin.css" rel="stylesheet">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
    <link href="<?= htmlspecialchars($css) ?>" rel="stylesheet">
    <?php endforeach; endif; ?>
    <script>
        const SUPABASE_URL = '<?= defined("SUPABASE_URL") ? SUPABASE_URL : "" ?>';
        const SUPABASE_KEY = '<?= defined("SUPABASE_PUBLISHABLE_KEY") ? SUPABASE_PUBLISHABLE_KEY : "" ?>';
        const USER_ROLE = '<?= getUserRole() ?>';
        const USER_EMAIL = '<?= htmlspecialchars($_SESSION["supabase_user"]["email"] ?? "") ?>';
    </script>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
