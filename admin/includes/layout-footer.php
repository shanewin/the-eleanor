</div><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($useCalendar)): ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<?php endif; ?>
<script src="/admin/js/admin-shared.js"></script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
