<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$productRepo = new ProductRepository();
$recRepo = new RecommendationRepository();
$retailerRepo = new RetailerRepository();

$stats = $productRepo->statistics();
$recentProducts = $productRepo->search([], 1, 8)['items'];
$recentSessions = $recRepo->recent(6);
$recStats = $recRepo->stats();
$retailers = $retailerRepo->all();

$pdo = Database::connection();
$freshness = $pdo->query(
    "SELECT SUM(verified_at >= (NOW() - INTERVAL 7 DAY)) AS recent,
            SUM(verified_at < (NOW() - INTERVAL 7 DAY) AND verified_at >= (NOW() - INTERVAL 30 DAY)) AS review,
            SUM(verified_at IS NULL OR verified_at < (NOW() - INTERVAL 30 DAY)) AS outdated
     FROM products"
)->fetch();
$recentCount = (int) $freshness['recent'];
$reviewCount = (int) $freshness['review'];
$outdatedCount = (int) $freshness['outdated'];
$totalForBars = max(1, $recentCount + $reviewCount + $outdatedCount);

$adminPageTitle = 'Dashboard';
$adminActive = 'dashboard';
$adminPageSubtitle = 'Catalogue health, verification status and recommendation activity.';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Good to see you, <?= e(explode(' ', $admin['display_name'])[0]) ?>.</h1>
        <p>This is the operational view of the local catalogue. Prices and stock are snapshots; freshness is calculated from each record’s verification timestamp.</p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/data_quality.php')) ?>"><i data-lucide="shield-check"></i> Data checks</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/add_product.php')) ?>"><i data-lucide="plus"></i> Add product</a>
    </div>
</div>

<?php if ($stats['total'] === 0): ?>
    <div class="admin-alert admin-alert-warning">
        <i data-lucide="database"></i>
        <div><strong>The catalogue is empty.</strong> Import <code>database/schema.sql</code> and <code>database/seed.sql</code> with phpMyAdmin or the MySQL CLI, then reload this dashboard.</div>
    </div>
<?php endif; ?>

<div class="admin-stats">
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="package"></i></span>
        <strong><?= number_format($stats['total']) ?></strong>
        <span>Total products</span>
        <small><?= number_format($stats['inactive']) ?> inactive</small>
    </div>
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="laptop"></i></span>
        <strong><?= number_format($stats['laptops']) ?></strong>
        <span>Laptops</span>
        <small><?= number_format($stats['desktops']) ?> desktops</small>
    </div>
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="store"></i></span>
        <strong><?= number_format($stats['retailers']) ?></strong>
        <span>Active retailers</span>
        <small><?= count($retailers) ?> retailer records</small>
    </div>
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="banknote"></i></span>
        <strong><?= e(format_price($stats['average_price'])) ?></strong>
        <span>Average listed price</span>
        <small>Across all catalogue records</small>
    </div>
    <div class="admin-stat <?= $stats['needing_verification'] > 0 ? 'is-warning' : '' ?>">
        <span class="admin-stat-icon"><i data-lucide="clock-alert"></i></span>
        <strong><?= number_format($stats['needing_verification']) ?></strong>
        <span>Need verification</span>
        <small>Older than 30 days or never verified</small>
    </div>
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="circle-slash"></i></span>
        <strong><?= number_format($stats['out_of_stock']) ?></strong>
        <span>Out of stock</span>
        <small>Retained but excluded from recommendations</small>
    </div>
    <div class="admin-stat <?= $stats['broken_links'] > 0 ? 'is-danger' : '' ?>">
        <span class="admin-stat-icon"><i data-lucide="link-2-off"></i></span>
        <strong><?= number_format($stats['broken_links']) ?></strong>
        <span>Broken links</span>
        <small>Marked by the link checker</small>
    </div>
    <div class="admin-stat">
        <span class="admin-stat-icon"><i data-lucide="sparkles"></i></span>
        <strong><?= number_format($recStats['total']) ?></strong>
        <span>Recommendation sessions</span>
        <small><?= number_format($recStats['last_7_days']) ?> in the last 7 days</small>
    </div>
</div>

<div class="admin-grid">
    <div class="admin-stack">
        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><h2>Recently updated products</h2><p>Newest database edits, not a claim of live retailer stock.</p></div>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/products.php')) ?>">View all <i data-lucide="arrow-right"></i></a>
            </div>
            <div class="admin-panel-body no-pad">
                <?php if ($recentProducts === []): ?>
                    <div class="empty-admin">
                        <i data-lucide="package-open"></i>
                        <h3>No products yet</h3>
                        <p>Import the seed file or add a product manually.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Verified</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($recentProducts as $product): $fresh = freshness($product['verified_at']); ?>
                                    <tr>
                                        <td>
                                            <div class="table-product">
                                                <img src="<?= e($product['image_url'] ?: product_placeholder()) ?>" alt="" loading="lazy">
                                                <div><strong><?= e(truncate_text($product['product_name'], 58)) ?></strong><small><?= e($product['brand_name']) ?> · <?= e($product['retailer_name']) ?></small></div>
                                            </div>
                                        </td>
                                        <td><?= e(format_price($product['price_tnd'])) ?></td>
                                        <td><span class="<?= e(stock_class($product['stock_status'])) ?>"><?= e(stock_label($product['stock_status'])) ?></span></td>
                                        <td><span class="<?= e($fresh['class']) ?>"><?= e($fresh['label']) ?></span><br><span class="muted"><?= e(format_date($product['verified_at'])) ?></span></td>
                                        <td><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/edit_product.php?id=' . (int) $product['id'])) ?>">Edit</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><h2>Recent recommendation sessions</h2><p>Anonymous submissions, stored so result links remain reproducible.</p></div>
            </div>
            <div class="admin-panel-body no-pad">
                <?php if ($recentSessions === []): ?>
                    <div class="empty-admin">
                        <i data-lucide="sparkles"></i>
                        <h3>No recommendation sessions yet</h3>
                        <p>Run the public questionnaire to create one.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Activity</th><th>Budget cap</th><th>Results</th><th>Submitted</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($recentSessions as $session): ?>
                                    <tr>
                                        <td><strong><?= e($session['activity_name']) ?></strong><br><span class="muted"><?= e(ucfirst($session['product_category'])) ?></span></td>
                                        <td><?= e(format_price($session['budget_max'])) ?></td>
                                        <td><?= (int) $session['result_count'] ?> / 5</td>
                                        <td><?= e(format_date($session['created_at'], true)) ?></td>
                                        <td><a class="btn btn-secondary btn-sm" href="<?= e(url('results.php?token=' . urlencode($session['token']))) ?>" target="_blank" rel="noopener">Open</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="admin-stack">
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Data freshness</h2><p>Based on stored verification dates.</p></div></div>
            <div class="admin-panel-body">
                <div class="admin-freshness">
                    <div class="freshness-row">
                        <div>
                            <div class="freshness-row-head"><span>Recently verified (0–7 days)</span><span><?= number_format($recentCount) ?></span></div>
                            <div class="freshness-track"><span class="recent" style="width:<?= round($recentCount / $totalForBars * 100) ?>%"></span></div>
                        </div>
                    </div>
                    <div class="freshness-row">
                        <div>
                            <div class="freshness-row-head"><span>Needs review (8–30 days)</span><span><?= number_format($reviewCount) ?></span></div>
                            <div class="freshness-track"><span class="review" style="width:<?= round($reviewCount / $totalForBars * 100) ?>%"></span></div>
                        </div>
                    </div>
                    <div class="freshness-row">
                        <div>
                            <div class="freshness-row-head"><span>Outdated (30+ days / never)</span><span><?= number_format($outdatedCount) ?></span></div>
                            <div class="freshness-track"><span class="outdated" style="width:<?= round($outdatedCount / $totalForBars * 100) ?>%"></span></div>
                        </div>
                    </div>
                </div>
                <p class="small muted" style="margin:15px 0 0;font-size:0.66rem;">These labels are maintenance hints, not guarantees of live availability.</p>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Quick actions</h2></div></div>
            <div class="admin-panel-body">
                <div class="admin-quick">
                    <a href="<?= e(url('admin/add_product.php')) ?>"><i data-lucide="plus-circle"></i> Add a product</a>
                    <a href="<?= e(url('admin/products.php?freshness=outdated')) ?>"><i data-lucide="clock-alert"></i> Review outdated</a>
                    <a href="<?= e(url('admin/products.php?link_status=broken')) ?>"><i data-lucide="link-2-off"></i> Broken links</a>
                    <a href="<?= e(url('admin/retailers.php')) ?>"><i data-lucide="store"></i> Manage retailers</a>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>Maintenance</h2><p>Run from the project folder.</p></div></div>
            <div class="admin-panel-body">
                <p class="small muted" style="margin-top:0;">Recheck retailer links with a rate-limited CLI job. It updates link status but does not alter prices automatically.</p>
                <code style="display:block;padding:8px 10px;border-radius:7px;background:rgba(7,12,23,0.6);color:#C7D2FE;font-size:0.66rem;margin-bottom:8px;">php tools/check_links.php</code>
                <p class="small muted" style="margin-bottom:8px;">Remove anonymous recommendation sessions older than 180 days:</p>
                <code style="display:block;padding:8px 10px;border-radius:7px;background:rgba(7,12,23,0.6);color:#C7D2FE;font-size:0.66rem;">php tools/prune_sessions.php</code>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
