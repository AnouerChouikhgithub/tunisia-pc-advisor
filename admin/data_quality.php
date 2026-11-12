<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$productRepo = new ProductRepository();
$stats = $productRepo->statistics();
$checks = $productRepo->dataQualityChecks();
$brokenList = $productRepo->search(['link_status' => 'broken'], 1, 8)['items'];

$pdo = Database::connection();
$allUrls = $pdo->query('SELECT id, product_name, product_url FROM products')->fetchAll();
$suspiciousUrls = [];
foreach ($allUrls as $row) {
    $path = trim((string) (parse_url($row['product_url'], PHP_URL_PATH) ?? ''), '/');
    $segments = array_values(array_filter(explode('/', $path)));
    if ($path === '' || count($segments) < 2) {
        $suspiciousUrls[] = $row;
    }
}

$duplicateUrlCount = count($checks['duplicateUrls']);
$duplicateSkuCount = count($checks['duplicateSkus']);
$missingCount = count($checks['missing']);
$verifiedCount = max(0, $stats['total'] - (int) $checks['stale']);
$priceStats = $checks['priceStats'];
$priceInRange = (float) ($priceStats['min_price'] ?? 0) >= 500 && (float) ($priceStats['max_price'] ?? 0) <= 30000;

$adminPageTitle = 'Data quality';
$adminActive = 'quality';
$adminPageSubtitle = 'Integrity checks, missing fields, duplicate detection and link health.';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Data quality</h1>
        <p>These checks run against the live local database. A passing check is not a guarantee that a retailer’s price or stock is still current — it only verifies the stored record’s structure and integrity.</p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/products.php?freshness=outdated')) ?>"><i data-lucide="clock-alert"></i> Review outdated</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/products.php')) ?>"><i data-lucide="package"></i> Products</a>
    </div>
</div>

<div class="admin-stats">
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="package"></i></span>
        <strong><?= number_format($stats['total']) ?></strong>
        <span>Distinct records</span>
        <small><?= number_format($stats['laptops']) ?> laptops · <?= number_format($stats['desktops']) ?> desktops</small>
    </div>
    <div class="admin-stat <?= $duplicateUrlCount + $duplicateSkuCount > 0 ? 'is-danger' : '' ?>">
        <span class="admin-stat-icon"><i data-lucide="copy"></i></span>
        <strong><?= number_format($duplicateUrlCount + $duplicateSkuCount) ?></strong>
        <span>Duplicate groups</span>
        <small><?= $duplicateUrlCount ?> URLs · <?= $duplicateSkuCount ?> SKUs</small>
    </div>
    <div class="admin-stat <?= $missingCount > 0 ? 'is-warning' : '' ?>">
        <span class="admin-stat-icon"><i data-lucide="file-warning"></i></span>
        <strong><?= number_format($missingCount) ?></strong>
        <span>Records with missing fields</span>
        <small>Critical fields only</small>
    </div>
    <div class="admin-stat <?= (int) $checks['stale'] > 0 ? 'is-warning' : '' ?>">
        <span class="admin-stat-icon"><i data-lucide="calendar-clock"></i></span>
        <strong><?= number_format($verifiedCount) ?></strong>
        <span>Verified within 30 days</span>
        <small><?= number_format($stats['total']) ?> total records</small>
    </div>
</div>

<div class="admin-grid">
    <div class="admin-stack">
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Integrity checks</h2><p>Structural results from the current dataset.</p></div></div>
            <div class="admin-panel-body no-pad">
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr><td>Distinct product URLs</td><td><?= number_format($duplicateUrlCount) ?> duplicate group(s)</td><td><?= $duplicateUrlCount === 0 ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-danger">Review</span>' ?></td></tr>
                            <tr><td>Retailer SKU uniqueness</td><td><?= number_format($duplicateSkuCount) ?> duplicate group(s)</td><td><?= $duplicateSkuCount === 0 ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-danger">Review</span>' ?></td></tr>
                            <tr><td>Critical specification fields</td><td><?= number_format($missingCount) ?> record(s) with missing data</td><td><?= $missingCount === 0 ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-warning">Review</span>' ?></td></tr>
                            <tr><td>Product URL format</td><td><?= number_format(count($checks['invalidUrls'])) ?> invalid · <?= number_format(count($suspiciousUrls)) ?> homepage/short path</td><td><?= count($checks['invalidUrls']) + count($suspiciousUrls) === 0 ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-danger">Review</span>' ?></td></tr>
                            <tr><td>Prices in plausible TND range</td><td><?= e(format_price($priceStats['min_price'] ?? 0)) ?> to <?= e(format_price($priceStats['max_price'] ?? 0)) ?></td><td><?= $priceInRange ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-warning">Review</span>' ?></td></tr>
                            <tr><td>Verification freshness</td><td><?= number_format((int) $checks['stale']) ?> outdated or never verified</td><td><?= (int) $checks['stale'] === 0 ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-warning">Review</span>' ?></td></tr>
                            <tr><td>Out-of-stock records</td><td><?= number_format($stats['out_of_stock']) ?> records</td><td><span class="badge badge-neutral">Informational</span></td></tr>
                            <tr><td>Broken product links</td><td><?= number_format($stats['broken_links']) ?> marked broken</td><td><?= $stats['broken_links'] === 0 ? '<span class="badge badge-success">Pass</span>' : '<span class="badge badge-danger">Review</span>' ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Records with missing critical fields</h2><p>NULL is acceptable for non-essential fields, but these affect scoring or display.</p></div></div>
            <div class="admin-panel-body no-pad">
                <?php if ($checks['missing'] === []): ?>
                    <div class="empty-admin"><i data-lucide="badge-check"></i><h3>No critical fields missing</h3><p>Every record has a processor, RAM, storage, source URL and verification timestamp.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Product</th><th>Missing</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($checks['missing'], 0, 25) as $row): ?>
                                    <?php
                                    $missingLabels = [];
                                    foreach (['missing_cpu' => 'CPU', 'missing_ram' => 'RAM', 'missing_storage' => 'Storage', 'missing_gpu' => 'GPU', 'missing_screen' => 'Laptop screen', 'missing_source' => 'Source URL', 'missing_verification' => 'Verification date'] as $key => $label) {
                                        if ((int) ($row[$key] ?? 0) === 1) {
                                            $missingLabels[] = $label;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><strong><?= e(truncate_text($row['product_name'], 70)) ?></strong><br><span class="muted">#<?= (int) $row['id'] ?></span></td>
                                        <td><?= e(implode(', ', $missingLabels)) ?></td>
                                        <td><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/edit_product.php?id=' . (int) $row['id'])) ?>">Fix</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($duplicateUrlCount + $duplicateSkuCount > 0): ?>
            <section class="admin-panel">
                <div class="admin-panel-head"><div><h2>Duplicate candidates</h2><p>Duplicates can make the catalogue look larger than the real distinct supply.</p></div></div>
                <div class="admin-panel-body">
                    <?php foreach ($checks['duplicateUrls'] as $duplicate): ?>
                        <p class="small" style="margin:0 0 8px;"><span class="badge badge-danger">URL</span> <?= e($duplicate['product_url']) ?> — <?= (int) $duplicate['n'] ?> rows</p>
                    <?php endforeach; ?>
                    <?php foreach ($checks['duplicateSkus'] as $duplicate): ?>
                        <p class="small" style="margin:0 0 8px;"><span class="badge badge-danger">SKU</span> Retailer #<?= (int) $duplicate['retailer_id'] ?> · <?= e($duplicate['retailer_sku']) ?> — <?= (int) $duplicate['n'] ?> rows</p>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="admin-stack">
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Category and retailer coverage</h2></div></div>
            <div class="admin-panel-body">
                <?php foreach ($checks['categories'] as $category): ?>
                    <div class="freshness-row" style="margin-bottom:10px;">
                        <div>
                            <div class="freshness-row-head"><span><?= e(ucfirst($category['product_category'])) ?></span><span><?= number_format((int) $category['n']) ?></span></div>
                            <div class="freshness-track"><span class="recent" style="width:<?= round((int) $category['n'] / max(1, $stats['total']) * 100) ?>%"></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:14px;padding-top:13px;border-top:1px solid var(--border);">
                    <?php foreach ($checks['retailerCounts'] as $retailer): ?>
                        <div style="display:flex;justify-content:space-between;gap:10px;padding:5px 0;color:var(--muted);font-size:0.7rem;">
                            <span><?= e($retailer['name']) ?></span><span><?= number_format((int) $retailer['n']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Broken link queue</h2><p><?= number_format($stats['broken_links']) ?> marked broken.</p></div></div>
            <div class="admin-panel-body">
                <?php if ($brokenList === []): ?>
                    <p class="small muted" style="margin:0;">No products are currently marked as broken. Run the CLI link checker after catalogue updates.</p>
                <?php else: ?>
                    <?php foreach ($brokenList as $product): ?>
                        <p class="small" style="margin:0 0 8px;"><a href="<?= e(url('admin/edit_product.php?id=' . (int) $product['id'])) ?>"><?= e(truncate_text($product['product_name'], 58)) ?></a><br><span class="muted"><?= e(format_date($product['link_checked_at'], true)) ?></span></p>
                    <?php endforeach; ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/products.php?link_status=broken')) ?>">Open broken-link filter</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>How to recheck links</h2></div></div>
            <div class="admin-panel-body">
                <p class="small muted" style="margin-top:0;">The maintenance script performs a polite, rate-limited GET request and updates each product’s link status. It does not change price or stock automatically.</p>
                <code style="display:block;padding:9px;border-radius:7px;background:rgba(7,12,23,0.6);color:#C7D2FE;font-size:0.68rem;">php tools/check_links.php --limit=50 --delay=1200</code>
                <p class="small muted" style="margin:11px 0 0;">Review every broken URL manually before marking a product out of stock or inactive.</p>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
