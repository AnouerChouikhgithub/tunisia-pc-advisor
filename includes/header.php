<?php
declare(strict_types=1);
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

$pageTitle = $pageTitle ?? app('name');
$pageDescription = $pageDescription ?? 'Find the best laptop or desktop available in Tunisia based on your profession, activities, specifications and budget.';
$bodyClass = $bodyClass ?? '';
$currentPage = $currentPage ?? '';
$fullTitle = $pageTitle === app('name') ? $pageTitle : $pageTitle . ' — ' . app('name');
$flashes = consume_flashes();

$navItems = [
    'how-it-works' => ['label' => 'How it works', 'href' => 'how-it-works.php'],
    'data-sources' => ['label' => 'Data sources', 'href' => 'data-sources.php'],
    'about' => ['label' => 'About', 'href' => 'about.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#0B1120">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <?php if (!empty($extraCss)): foreach ((array) $extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e(asset('css/' . $css)) ?>">
    <?php endforeach; endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="site-glow" aria-hidden="true"></div>

<header class="site-header" data-header>
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e(app('name')) ?> home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 44 44" fill="none">
                    <rect x="7" y="9" width="30" height="21" rx="4" stroke="currentColor" stroke-width="2.4"/>
                    <path d="M4 35h36" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" opacity=".65"/>
                    <path d="M22 30v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    <circle cx="22" cy="19.5" r="4.5" fill="currentColor"/>
                </svg>
            </span>
            <span class="brand-copy">
                <strong>Tunisia PC</strong>
                <span>Advisor</span>
            </span>
        </a>

        <nav class="site-nav" id="site-nav" aria-label="Primary navigation">
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= e(url($item['href'])) ?>" <?= $currentPage === $key ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-primary btn-sm nav-cta" href="<?= e(url('questionnaire.php')) ?>">
                Find my PC
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </a>
        </nav>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
            <span class="sr-only">Open navigation</span>
            <i data-lucide="menu" aria-hidden="true"></i>
        </button>
    </div>
</header>

<?php if ($flashes !== []): ?>
    <div class="toast-stack" role="status" aria-live="polite">
        <?php foreach ($flashes as $flash): ?>
            <div class="toast toast-<?= e($flash['type']) ?>" data-toast>
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>" aria-hidden="true"></i>
                <span><?= e($flash['message']) ?></span>
                <button type="button" class="toast-close" data-toast-close aria-label="Dismiss notification"><i data-lucide="x" aria-hidden="true"></i></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
