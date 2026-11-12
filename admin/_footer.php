<?php
declare(strict_types=1);
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}
?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/lucide.min.js')) ?>"></script>
<script>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons({ attrs: { 'stroke-width': 1.8, 'aria-hidden': 'true' } });
    }
</script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
