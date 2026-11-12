<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$productRepo = new ProductRepository();
$product = $productRepo->find($id);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require APP_ROOT . '/includes/header.php';
    ?>
    <main id="main-content" class="section">
        <div class="container">
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="package-x"></i></div>
                <h2>This listing is not available.</h2>
                <p>The product may have been deactivated or the link may be incomplete. Return to the recommendation results or start a new questionnaire.</p>
                <a class="btn btn-primary" href="<?= e(url('questionnaire.php')) ?>">Find a PC</a>
            </div>
        </div>
    </main>
    <?php
    require APP_ROOT . '/includes/footer.php';
    exit;
}

$token = (string) ($_GET['token'] ?? '');
$recommendation = null;
$session = null;
if ($token !== '') {
    $recRepo = new RecommendationRepository();
    $session = $recRepo->findByToken($token);
    if ($session) {
        foreach ($recRepo->resultsFor((int) $session['id']) as $row) {
            if ((int) $row['id'] === (int) $product['id']) {
                $recommendation = $row;
                break;
            }
        }
    }
}

$history = (new PriceHistoryRepository())->forProduct((int) $product['id'], 12);
$related = $productRepo->relatedFor($product, 3);
$fresh = freshness($product['verified_at']);
$pageTitle = $product['product_name'];
$pageDescription = $product['brand_name'] . ' ' . $product['model'] . ' at ' . $product['retailer_name'] . ' — ' . format_price($product['price_tnd']) . '.';
$currentPage = 'product';
$extraCss = ['results.css'];

$displayParts = [];
if ($product['screen_size']) {
    $displayParts[] = rtrim(rtrim(number_format((float) $product['screen_size'], 1), '0'), '.') . '″';
}
if ($product['screen_resolution']) {
    $displayParts[] = $product['screen_resolution'];
}
if ($product['screen_panel']) {
    $displayParts[] = $product['screen_panel'];
}
if ($product['refresh_rate']) {
    $displayParts[] = (int) $product['refresh_rate'] . ' Hz';
}
$display = implode(' · ', $displayParts);
$upgradeability = match ($product['ram_upgradeable']) {
    'yes' => 'Upgradeable',
    'no' => 'Not upgradeable / soldered',
    default => 'Not stated by retailer',
};
$gpuDescription = trim((string) ($product['gpu_model'] ?? ''));
if ($gpuDescription !== '' && $product['gpu_type'] === 'dedicated' && $product['gpu_vram_gb']) {
    $gpuDescription .= ' · ' . (int) $product['gpu_vram_gb'] . ' GB VRAM';
}

$specSections = [
    'Overview' => [
        'Brand' => $product['brand_name'],
        'Model' => $product['model'],
        'Category' => ucfirst($product['product_category']),
        'Operating system' => $product['operating_system'],
        'Warranty' => $product['warranty_information'],
    ],
    'Processor' => [
        'Manufacturer' => $product['processor_brand'],
        'Model' => $product['processor_model'],
        'Generation / family' => $product['processor_generation'],
        'Cores' => $product['processor_cores'] ? (int) $product['processor_cores'] . ' cores' : null,
    ],
    'Memory' => [
        'Capacity' => (int) $product['ram_gb'] . ' GB',
        'Type' => $product['ram_type'],
        'Upgradeability' => $upgradeability,
    ],
    'Storage' => [
        'Capacity' => number_format((int) $product['storage_gb']) . ' GB',
        'Technology' => $product['storage_type'],
    ],
    'Graphics' => [
        'Type' => ucfirst((string) $product['gpu_type']),
        'Brand' => $product['gpu_brand'],
        'Model' => $gpuDescription ?: null,
        'VRAM' => $product['gpu_vram_gb'] ? (int) $product['gpu_vram_gb'] . ' GB' : null,
    ],
];

if ($product['product_category'] === 'laptop') {
    $specSections['Display'] = [
        'Screen size' => $product['screen_size'] ? rtrim(rtrim(number_format((float) $product['screen_size'], 1), '0'), '.') . ' inches' : null,
        'Resolution' => $product['screen_resolution'],
        'Panel' => $product['screen_panel'],
        'Refresh rate' => $product['refresh_rate'] ? (int) $product['refresh_rate'] . ' Hz' : null,
    ];
}

$specSections['Physical'] = [
    'Weight' => $product['weight_kg'] ? number_format((float) $product['weight_kg'], 2) . ' kg' : null,
    'Battery detail' => $product['battery'],
];

$specSections['Listing'] = [
    'Retailer' => $product['retailer_name'],
    'Retailer SKU' => $product['retailer_sku'],
    'Listed price' => format_price($product['price_tnd']),
    'Stock at verification' => stock_label($product['stock_status']),
    'Verified' => format_date($product['verified_at'], true),
];

require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="product-page-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <a href="<?= e(url('index.php')) ?>">Home</a>
                <i data-lucide="chevron-right"></i>
                <a href="<?= e(url('questionnaire.php')) ?>">Recommendations</a>
                <i data-lucide="chevron-right"></i>
                <span><?= e($product['brand_name']) ?> <?= e($product['model']) ?></span>
            </nav>

            <div class="product-hero-grid">
                <div class="product-image-panel">
                    <img src="<?= e($product['image_url'] ?: product_placeholder()) ?>"
                         alt="<?= e($product['product_name']) ?>"
                         data-fallback="<?= e(product_placeholder()) ?>">
                    <div class="product-image-meta">
                        <span><i data-lucide="camera"></i> Retailer product image</span>
                        <span><i data-lucide="<?= $product['stock_status'] === 'in_stock' ? 'check-circle-2' : 'clock-3' ?>"></i> <?= e(stock_label($product['stock_status'])) ?></span>
                    </div>
                </div>

                <div class="product-info-panel">
                    <div class="product-brand-line"><?= e($product['brand_name']) ?> <span>·</span> <?= e(ucfirst($product['product_category'])) ?></div>
                    <h1><?= e($product['product_name']) ?></h1>
                    <div class="product-meta-row">
                        <i data-lucide="store"></i> <?= e($product['retailer_name']) ?>
                        <span class="dot-separator">·</span>
                        <span class="<?= e($fresh['class']) ?>"><?= e($fresh['label']) ?> (<?= e(format_date($product['verified_at'])) ?>)</span>
                        <span class="dot-separator">·</span>
                        <span class="<?= e(stock_class($product['stock_status'])) ?>"><?= e(stock_label($product['stock_status'])) ?></span>
                    </div>

                    <div class="product-price-box">
                        <span>Listed price</span>
                        <strong><?= e(format_price($product['price_tnd'])) ?></strong>
                        <small>At <b><?= e($product['retailer_name']) ?></b> · price recorded <?= e(format_date($product['verified_at'], true)) ?></small>
                        <a class="btn btn-success btn-lg btn-block" href="<?= e($product['product_url']) ?>" target="_blank" rel="noopener noreferrer">
                            Buy from <?= e($product['retailer_name']) ?>
                            <i data-lucide="external-link"></i>
                        </a>
                        <div class="product-actions">
                            <a class="btn btn-secondary btn-sm" href="<?= e($product['source_url']) ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="link-2"></i> Open retailer listing</a>
                            <?php if ($recommendation && $session): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('results.php?token=' . urlencode($token))) ?>"><i data-lucide="arrow-left"></i> Back to matches</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="verification-box">
                        <i data-lucide="badge-check"></i>
                        <div><strong>Point-in-time data.</strong> The specifications and price above were recorded from the retailer’s product page on <?= e(format_date($product['verified_at'], true)) ?>. Stock and price can change; confirm them on the retailer page before buying.</div>
                    </div>

                    <?php if ($recommendation): ?>
                        <div class="explanation-panel" style="margin-top:16px;">
                            <div class="explanation-head">
                                <span><i data-lucide="sparkles"></i> Your personalised match</span>
                                <span class="badge badge-accent"><?= number_format((float) $recommendation['compatibility_score'], 1) ?>% match</span>
                            </div>
                            <p><?= e($recommendation['explanation']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section-tight">
        <div class="container product-layout">
            <div class="product-main">
                <?php if (!$recommendation): ?>
                    <div class="detail-card card">
                        <h2>Why this PC?</h2>
                        <p class="muted" style="margin-bottom:16px;">This product page is a catalogue record. Personalised explanations are generated only after the questionnaire, so the engine can compare the specifications to your activity, workloads and budget.</p>
                        <a class="btn btn-primary" href="<?= e(url('questionnaire.php')) ?>">Get my five best matches <i data-lucide="arrow-right"></i></a>
                    </div>
                <?php endif; ?>

                <div class="detail-card card">
                    <h2>Full specifications</h2>
                    <div class="spec-table">
                        <?php foreach ($specSections as $section => $rows): ?>
                            <div class="spec-table-row" style="padding-top:18px;">
                                <dt style="color:var(--accent-2-bright);font-weight:600;"><?= e($section) ?></dt>
                                <dd></dd>
                            </div>
                            <?php foreach ($rows as $label => $value): ?>
                                <div class="spec-table-row">
                                    <dt><?= e($label) ?></dt>
                                    <dd><?= $value !== null && $value !== '' ? e($value) : '<span class="muted">Not stated by retailer</span>' ?></dd>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="detail-card card">
                    <h2>Price and stock history</h2>
                    <?php if ($history !== []): ?>
                        <div class="spec-table" role="table" aria-label="Price history">
                            <div class="spec-table-row" role="row" style="font-weight:600;color:var(--muted);font-size:0.68rem;text-transform:uppercase;letter-spacing:0.06em;">
                                <dt>Recorded</dt>
                                <dd>Price / stock</dd>
                            </div>
                            <?php foreach ($history as $entry): ?>
                                <div class="spec-table-row" role="row">
                                    <dt><?= e(format_date($entry['recorded_at'], true)) ?></dt>
                                    <dd><?= e(format_price($entry['price_tnd'])) ?> · <span class="<?= e(stock_class($entry['stock_status'])) ?>"><?= e(stock_label($entry['stock_status'])) ?></span></dd>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No historical price snapshots have been recorded yet.</p>
                    <?php endif; ?>
                    <p class="small muted" style="margin:14px 0 0;">History is append-only. An administrator price update creates a new snapshot rather than overwriting an earlier one.</p>
                </div>
            </div>

            <aside class="product-aside">
                <div class="aside-card card">
                    <h2>Retailer record</h2>
                    <div class="spec-table">
                        <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                            <dt>Retailer</dt>
                            <dd><strong><?= e($product['retailer_name']) ?></strong></dd>
                        </div>
                        <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                            <dt>Retailer SKU</dt>
                            <dd><?= e($product['retailer_sku'] ?: 'Not recorded') ?></dd>
                        </div>
                        <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                            <dt>Link status</dt>
                            <dd><?= e(ucfirst($product['link_status'])) ?><?= $product['link_checked_at'] ? ' · ' . e(format_date($product['link_checked_at'])) : '' ?></dd>
                        </div>
                        <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                            <dt>Last verified</dt>
                            <dd><?= e(format_date($product['verified_at'], true)) ?></dd>
                        </div>
                    </div>
                    <a class="btn btn-secondary btn-sm btn-block" href="<?= e($product['product_url']) ?>" target="_blank" rel="noopener noreferrer">Verify on retailer site <i data-lucide="external-link"></i></a>
                </div>

                <div class="aside-card card">
                    <h2>Independent by design</h2>
                    <p>Tunisia PC Advisor does not receive a commission from these links and does not rank products by retailer. The price shown is the listing captured at verification time.</p>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('data-sources.php')) ?>">Data sources <i data-lucide="arrow-up-right"></i></a>
                </div>
            </aside>
        </div>
    </section>

    <?php if ($related !== []): ?>
        <section class="section-tight" style="padding-bottom:80px;">
            <div class="container">
                <div class="section-head">
                    <div><span class="eyebrow">Nearby options</span><h2>Similar listings in the catalogue.</h2></div>
                </div>
                <div class="product-grid">
                    <?php foreach ($related as $relatedProduct): ?>
                        <?php $product = $relatedProduct; include __DIR__ . '/includes/product-mini.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
