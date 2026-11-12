<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$repository = new RecommendationRepository();
$session = $repository->findByToken($token);

$pageTitle = 'Compare PCs';
$pageDescription = 'Compare up to three recommended computers side by side on price, specifications and compatibility.';
$currentPage = 'results';
$extraCss = ['results.css'];

if (!$session) {
    http_response_code(404);
    require APP_ROOT . '/includes/header.php';
    ?>
    <main id="main-content" class="section">
        <div class="container">
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="git-compare-arrows"></i></div>
                <h2>This comparison link is not valid.</h2>
                <p>Comparisons are tied to one recommendation session. Return to your results and select the products again.</p>
                <a class="btn btn-primary" href="<?= e(url('questionnaire.php')) ?>">Start a new questionnaire</a>
            </div>
        </div>
    </main>
    <?php
    require APP_ROOT . '/includes/footer.php';
    exit;
}

$allResults = $repository->resultsFor((int) $session['id']);
$byId = [];
foreach ($allResults as $row) {
    $byId[(int) $row['id']] = $row;
}

$requestedIds = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))),
    static fn (int $id): bool => $id > 0
)));
$selected = [];
foreach (array_slice($requestedIds, 0, 3) as $id) {
    if (isset($byId[$id])) {
        $selected[] = $byId[$id];
    }
}

if (count($selected) < 2) {
    require APP_ROOT . '/includes/header.php';
    ?>
    <main id="main-content">
        <section class="compare-hero">
            <div class="container">
                <span class="pill"><i data-lucide="git-compare"></i> Comparison</span>
                <h1>Choose at least two matches.</h1>
                <p>A side-by-side comparison needs two or three products from the same recommendation session.</p>
            </div>
        </section>
        <section class="compare-section">
            <div class="container">
                <div class="empty-state">
                    <div class="empty-state-icon"><i data-lucide="mouse-pointer-click"></i></div>
                    <h2>No valid products were selected.</h2>
                    <p>Return to your results and tick “Compare” on two or three cards. Only products from that result set can be compared.</p>
                    <a class="btn btn-primary" href="<?= e(url('results.php?token=' . urlencode($token))) ?>">Back to my matches</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require APP_ROOT . '/includes/footer.php';
    exit;
}

$priceValues = array_map(static fn (array $p): float => (float) $p['price_tnd'], $selected);
$ramValues = array_map(static fn (array $p): int => (int) $p['ram_gb'], $selected);
$storageValues = array_map(static fn (array $p): int => (int) $p['storage_gb'], $selected);
$scoreValues = array_map(static fn (array $p): float => (float) $p['compatibility_score'], $selected);
$allLaptops = count(array_filter($selected, static fn (array $p): bool => $p['product_category'] !== 'laptop')) === 0;
$weightValues = array_map(static fn (array $p): ?float => $p['weight_kg'] !== null ? (float) $p['weight_kg'] : null, $selected);
$allWeightsKnown = $allLaptops && !in_array(null, $weightValues, true);

$uniqueBest = static function (array $values, bool $highest = true): ?int {
    $valid = array_filter($values, static fn ($value): bool => $value !== null);
    if (count($valid) !== count($values)) {
        return null;
    }
    $target = $highest ? max($values) : min($values);
    $indexes = array_keys($values, $target, true);
    return count($indexes) === 1 ? (int) $indexes[0] : null;
};

$sameCategory = count(array_unique(array_map(static fn (array $p): string => (string) $p['product_category'], $selected))) === 1;
$bestPrice = $sameCategory ? $uniqueBest($priceValues, false) : null;
$bestRam = $uniqueBest($ramValues, true);
$bestStorage = $uniqueBest($storageValues, true);
$bestScore = $uniqueBest($scoreValues, true);
$bestWeight = $allWeightsKnown ? $uniqueBest($weightValues, false) : null;

$renderRow = static function (string $label, array $values, ?int $bestIndex = null, string $hint = ''): void {
    ?>
    <tr>
        <th scope="row"><?= e($label) ?><?php if ($hint): ?><span style="display:block;font-size:0.58rem;text-transform:none;letter-spacing:0;color:#64748B;margin-top:2px;"><?= e($hint) ?></span><?php endif; ?></th>
        <?php foreach ($values as $index => $value): ?>
            <td class="<?= $bestIndex === $index ? 'best' : '' ?>">
                <?php if ($bestIndex === $index): ?><span class="sr-only">Best in this comparison: </span><?php endif; ?>
                <?= $value ?>
            </td>
        <?php endforeach; ?>
    </tr>
    <?php
};

$productNames = array_map(static fn (array $p): string => $p['product_name'], $selected);
$pageTitle = count($selected) . ' PC comparison';
require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="compare-hero">
        <div class="container">
            <span class="pill"><i data-lucide="git-compare"></i> Side-by-side comparison</span>
            <h1>Compare your matches.</h1>
            <p>Same session, same requirements, same scoring rules. Differences are shown as recorded; CPU and GPU rows are not declared “better” from model names alone.</p>
        </div>
    </section>

    <section class="compare-section">
        <div class="container">
            <div class="results-actions" style="margin-bottom:18px;">
                <a class="btn btn-secondary btn-sm" href="<?= e(url('results.php?token=' . urlencode($token))) ?>"><i data-lucide="arrow-left"></i> Back to my matches</a>
                <a class="btn btn-secondary btn-sm" href="<?= e(url('questionnaire.php')) ?>"><i data-lucide="rotate-ccw"></i> Restart questionnaire</a>
            </div>

            <div class="compare-scroll" tabindex="0" role="region" aria-label="Computer comparison table">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Specification</span></th>
                            <?php foreach ($selected as $product): ?>
                                <th scope="col">
                                    <div class="compare-product-head">
                                        <img src="<?= e($product['image_url'] ?: product_placeholder()) ?>" alt="<?= e($product['product_name']) ?>" loading="lazy" data-fallback="<?= e(product_placeholder()) ?>">
                                        <span><?= e($product['brand_name']) ?></span>
                                        <span class="compare-category"><?= e($product['product_category'] === 'laptop' ? 'Laptop' : 'Desktop PC') ?></span>
                                        <a href="<?= e(url('product.php?id=' . (int) $product['id'] . '&token=' . urlencode($token))) ?>"><?= e($product['product_name']) ?></a>
                                        <span class="badge badge-accent">Rank #<?= (int) $product['rank_position'] ?> · <?= number_format((float) $product['compatibility_score'], 1) ?>%</span>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $renderRow('Price', array_map(static fn (array $p): string => '<strong>' . e(format_price($p['price_tnd'])) . '</strong>', $selected), $bestPrice, $sameCategory ? 'Lower is better' : 'Mixed categories — no price winner');
                        $renderRow('Compatibility score', array_map(static fn (array $p): string => '<strong>' . number_format((float) $p['compatibility_score'], 1) . '%</strong>', $selected), $bestScore, 'Higher is better');
                        $renderRow('Processor', array_map(static fn (array $p): string => e($p['processor_model']), $selected), null, 'Not ranked by model name');
                        $renderRow('Memory', array_map(static fn (array $p): string => '<strong>' . (int) $p['ram_gb'] . ' GB</strong>' . ($p['ram_type'] ? ' ' . e($p['ram_type']) : ''), $selected), $bestRam, 'Higher capacity highlighted');
                        $renderRow('Storage', array_map(static fn (array $p): string => '<strong>' . number_format((int) $p['storage_gb']) . ' GB</strong> · ' . e($p['storage_type']), $selected), $bestStorage, 'Higher capacity highlighted');
                        $renderRow('Graphics', array_map(static fn (array $p): string => e($p['gpu_model'] ?: 'Not stated') . ($p['gpu_type'] === 'dedicated' ? ' · dedicated' : ($p['gpu_type'] === 'integrated' ? ' · integrated' : '')), $selected), null, 'Not ranked by model name');
                        $renderRow('Display', array_map(static function (array $p): string {
                            if ($p['product_category'] !== 'laptop') {
                                return 'Not applicable (desktop)';
                            }
                            $parts = [];
                            if ($p['screen_size']) {
                                $parts[] = rtrim(rtrim(number_format((float) $p['screen_size'], 1), '0'), '.') . '″';
                            }
                            if ($p['screen_resolution']) {
                                $parts[] = $p['screen_resolution'];
                            }
                            if ($p['screen_panel']) {
                                $parts[] = $p['screen_panel'];
                            }
                            if ($p['refresh_rate']) {
                                $parts[] = (int) $p['refresh_rate'] . ' Hz';
                            }
                            return e($parts ? implode(' · ', $parts) : 'Not stated');
                        }, $selected));
                        $renderRow('Weight', array_map(static fn (array $p): string => $p['weight_kg'] !== null ? e(number_format((float) $p['weight_kg'], 2)) . ' kg' : '<span class="muted">Not stated</span>', $selected), $bestWeight, $allLaptops ? 'Lower is better when stated' : 'Mixed device types');
                        $renderRow('Operating system', array_map(static fn (array $p): string => e($p['operating_system'] ?: 'Not stated'), $selected));
                        $renderRow('Warranty', array_map(static fn (array $p): string => e($p['warranty_information'] ?: 'Not stated'), $selected));
                        $renderRow('Retailer', array_map(static fn (array $p): string => e($p['retailer_name']), $selected));
                        $renderRow('Stock at verification', array_map(static fn (array $p): string => '<span class="' . e(stock_class($p['stock_status'])) . '">' . e(stock_label($p['stock_status'])) . '</span>', $selected));
                        $renderRow('Verified', array_map(static fn (array $p): string => e(format_date($p['verified_at'])), $selected));
                        $renderRow('Buy', array_map(static fn (array $p): string => '<a class="btn btn-success btn-sm" href="' . e($p['product_url']) . '" target="_blank" rel="noopener noreferrer">Buy at ' . e($p['retailer_name']) . '</a>', $selected));
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="compare-note">
                <i data-lucide="info"></i>
                <p>Green cells mark the strongest numeric value only where the comparison is unambiguous: lower price, higher memory or storage capacity, higher compatibility, or lower weight among laptops with recorded weights. Processor and graphics rows are intentionally not highlighted because a model name alone does not establish real-world superiority across different workloads.</p>
            </div>
        </div>
    </section>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
