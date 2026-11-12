<?php
declare(strict_types=1);
if (!defined('APP_ROOT') || empty($admin)) {
    http_response_code(403);
    exit;
}

$adminPageTitle = $adminPageTitle ?? 'Dashboard';
$adminPageSubtitle = $adminPageSubtitle ?? '';
$adminActive = $adminActive ?? 'dashboard';
$adminFlashes = consume_flashes();
$adminNav = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'admin/index.php', 'icon' => 'layout-dashboard'],
    'products' => ['label' => 'Products', 'href' => 'admin/products.php', 'icon' => 'package'],
    'add-product' => ['label' => 'Add product', 'href' => 'admin/add_product.php', 'icon' => 'plus-circle'],
    'retailers' => ['label' => 'Retailers', 'href' => 'admin/retailers.php', 'icon' => 'store'],
    'quality' => ['label' => 'Data quality', 'href' => 'admin/data_quality.php', 'icon' => 'shield-check'],
    'account' => ['label' => 'My account', 'href' => 'admin/account.php', 'icon' => 'user-cog'],
];
$initials = '';
foreach (preg_split('/\s+/', trim((string) $admin['display_name'])) ?: [] as $part) {
    $initials .= mb_substr($part, 0, 1);
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($adminPageTitle) ?> — Administration — <?= e(app('name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Administration navigation">
        <a class="brand" href="<?= e(url('admin/index.php')) ?>">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 44 44" fill="none">
                    <rect x="7" y="9" width="30" height="21" rx="4" stroke="currentColor" stroke-width="2.4"/>
                    <path d="M4 35h36" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" opacity=".65"/>
                    <path d="M22 30v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    <circle cx="22" cy="19.5" r="4.5" fill="currentColor"/>
                </svg>
            </span>
            <span class="brand-copy"><strong>Tunisia PC</strong><span>Advisor</span></span>
        </a>

        <div class="admin-nav-label">Workspace</div>
        <nav class="admin-nav">
            <?php foreach ($adminNav as $key => $item): ?>
                <a href="<?= e(url($item['href'])) ?>" class="<?= $adminActive === $key ? 'is-active' : '' ?>" <?= $adminActive === $key ? 'aria-current="page"' : '' ?>>
                    <i data-lucide="<?= e($item['icon']) ?>"></i>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-nav-label" style="margin-top:20px;">Public site</div>
        <nav class="admin-nav">
            <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i data-lucide="external-link"></i> View website</a>
        </nav>

        <div class="admin-sidebar-foot">
            <p>Prices and stock are verification-time snapshots. Record changes in the price history.</p>
            <a href="<?= e(url('admin/data_quality.php')) ?>"><i data-lucide="shield-check"></i> Catalogue health checks</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-sidebar-toggle" data-admin-sidebar-toggle aria-expanded="false" aria-label="Toggle administration navigation">
                    <i data-lucide="menu"></i>
                </button>
                <div class="admin-topbar-title">
                    <span>Administration</span>
                    <strong><?= e($adminPageTitle) ?></strong>
                </div>
            </div>
            <div class="admin-user">
                <span class="admin-avatar" aria-hidden="true"><?= e($initials ?: 'A') ?></span>
                <div class="admin-user-meta">
                    <strong><?= e($admin['display_name']) ?></strong>
                    <span>@<?= e($admin['username']) ?></span>
                </div>
                <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="admin-logout-form">
                    <?= Security::csrfField() ?>
                    <button type="submit" aria-label="Sign out"><i data-lucide="log-out"></i></button>
                </form>
            </div>
        </header>

        <main class="admin-content">
            <?php foreach ($adminFlashes as $flash): ?>
                <div class="admin-alert admin-alert-<?= e($flash['type']) ?>" data-flash>
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>"></i>
                    <div><?= e($flash['message']) ?></div>
                </div>
            <?php endforeach; ?>
