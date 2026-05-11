</div><!-- /.main-content -->

<!-- Custom Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-body text-center py-4">
                <div id="confirmIcon" class="mb-3" style="font-size:2.5rem"></div>
                <div class="fw-semibold text-white mb-2" id="confirmTitle"></div>
                <div class="small text-white-50" id="confirmMessage"></div>
            </div>
            <div class="modal-footer border-secondary justify-content-center gap-2 pb-4 pt-0 border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm px-4" id="confirmActionBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($useCalendar)): ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js"></script>
<?php
// Cache-bust local /admin/js/*.js by appending ?v={filemtime}. Skips CDN URLs.
function assetUrl($path) {
    if (strpos($path, '://') !== false) return $path;
    $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
    $v = @filemtime($abs);
    return htmlspecialchars($path) . ($v ? '?v=' . $v : '');
}
?>
<script src="<?= assetUrl('/admin/js/admin-shared.js') ?>"></script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= assetUrl($js) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
