<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$repository = new RecommendationRepository();
$session = $repository->findByToken($token);

if (!$session) {
    http_response_code(404);
    $pageTitle = 'Recommendation not found';
    require APP_ROOT . '/includes/header.php';
    ?>
    <main id="main-content" class="section">
        <div class="container">
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="file-question"></i></div>
                <h2>That result link is not valid.</h2>
                <p>Recommendation links are unique and must be copied in full. If this link is old or incomplete, run the questionnaire again to create a fresh set of matches.</p>
                <a class="btn btn-primary" href="<?= e(url('questionnaire.php')) ?>">Start the questionnaire</a>
            </div>
        </div>
    </main>
    <?php
    require APP_ROOT . '/includes/footer.php';
    exit;
}

$results = $repository->resultsFor((int) $session['id']);
$workloads = json_decode((string) $session['workloads_json'], true) ?: [];
$preferredBrands = json_decode((string) ($session['preferred_brands_json'] ?? '[]'), true) ?: [];
$count = count($results);
$candidateCount = (int) $session['candidate_count'];
$activityName = (string) $session['activity_name'];
$categoryLabel = match ($session['product_category']) {
    'laptop' => 'Laptop',
    'desktop' => 'Desktop PC',
    default => 'Laptop or desktop',
};
$resultTitle = $count === 5 ? 'Your 5 Best PC Matches' : 'Your Best PC Matches';
$pageTitle = $count === 5 ? 'Your 5 best PC matches' : 'Your best PC matches';
$pageDescription = 'Ranked computer recommendations based on your activity, workloads and budget.';
$currentPage = 'results';
$extraCss = ['results.css'];
$extraJs = ['results.js'];

$budgetNote = format_price($session['budget_max']);
$filterChips = [];
$filterChips[] = '<span class="filter-chip"><i data-lucide="wallet"></i> Up to ' . e($budgetNote) . '</span>';
$filterChips[] = '<span class="filter-chip"><i data-lucide="laptop"></i> ' . e($categoryLabel) . '</span>';
if ((int) $session['min_ram_gb'] > 0) {
    $filterChips[] = '<span class="filter-chip"><i data-lucide="memory-stick"></i> ' . (int) $session['min_ram_gb'] . ' GB RAM minimum</span>';
}
if ((int) $session['min_storage_gb'] > 0) {
    $filterChips[] = '<span class="filter-chip"><i data-lucide="hard-drive"></i> ' . number_format((int) $session['min_storage_gb']) . ' GB storage minimum</span>';
}
if ($session['dedicated_gpu'] === 'yes') {
    $filterChips[] = '<span class="filter-chip"><i data-lucide="circuit-board"></i> Dedicated GPU required</span>';
}
if ($preferredBrands !== []) {
    $filterChips[] = '<span class="filter-chip"><i data-lucide="tag"></i> ' . e(implode(', ', $preferredBrands)) . '</span>';
}

require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="results-hero">
        <div class="container">
            <span class="pill pill-success"><i data-lucide="sparkles"></i> Recommendation complete</span>
            <div class="results-title-row">
                <div>
                    <h1><?= e($resultTitle) ?></h1>
                    <p>Based on your requirements and the Tunisian market. Ranked by compatibility score, with value and price used only as tie-breakers.</p>
                </div>
                <div class="results-actions">
                    <a class="btn btn-secondary" href="<?= e(url('questionnaire.php')) ?>"><i data-lucide="rotate-ccw"></i> Restart questionnaire</a>
                    <a class="btn btn-primary" href="#matches"><i data-lucide="list-ordered"></i> View matches</a>
                </div>
            </div>

            <div class="result-summary">
                <div class="result-summary-main">
                    <div class="summary-profile">
                        <span class="summary-profile-icon"><i data-lucide="user-round-check"></i></span>
                        <div>
                            <span>Profile</span>
                            <strong><?= e($activityName) ?></strong>
                        </div>
                    </div>
                    <div class="summary-stat">
                        <span>Qualifying matches</span>
                        <strong><?= $count ?><small> / 5</small></strong>
                    </div>
                    <div class="summary-stat">
                        <span>Candidates considered</span>
                        <strong><?= $candidateCount ?></strong>
                    </div>
                    <div class="summary-stat">
                        <span>Selected workloads</span>
                        <strong><?= count($workloads) ?></strong>
                    </div>
                </div>
                <div class="filter-chips">
                    <?= implode('', $filterChips) ?>
                </div>
            </div>

            <?php if (!empty($workloads)): ?>
                <div class="workload-strip">
                    <span>Evaluated against</span>
                    <?php foreach ($workloads as $workload): ?>
                        <span class="workload-tag"><?= e($workload['name'] ?? '') ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section results-section" id="matches">
        <div class="container">
            <?php if ($count === 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i data-lucide="search-x"></i></div>
                    <h2>No product satisfied every hard filter.</h2>
                    <p>The catalogue contains real in-stock listings, but none matched your category, maximum budget and explicit minimums at the same time. We did not invent or silently include an over-budget alternative.</p>
                    <div class="empty-actions">
                        <a class="btn btn-primary" href="<?= e(url('questionnaire.php')) ?>">Adjust my answers</a>
                        <a class="btn btn-secondary" href="<?= e(url('data-sources.php')) ?>">Review the catalogue sources</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($count < 5): ?>
                    <div class="alert alert-warning">
                        <i data-lucide="alert-triangle"></i>
                        <div><strong>Only <?= $count ?> qualifying match<?= $count === 1 ? '' : 'es' ?> found.</strong> Every product shown is within your budget and meets the hard filters. We did not add unsuitable products just to fill five positions. Widening the budget, category or minimum specifications may produce more options.</div>
                    </div>
                <?php else: ?>
                    <div class="results-list-head">
                        <div>
                            <span class="eyebrow">Your ranking</span>
                            <h2>Five defensible options, ordered by fit.</h2>
                        </div>
                        <p>Scores are out of 100. Open “Why this score?” on any card to inspect the weights and component scores.</p>
                    </div>
                <?php endif; ?>

                <div class="result-list">
                    <?php foreach ($results as $index => $product): ?>
                        <?php
                        $rank = (int) $product['rank_position'];
                        $score = (float) $product['compatibility_score'];
                        $fresh = freshness($product['verified_at'] ?? null);
                        $breakdown = $product['score_breakdown'] ?? [];
                        $rankClass = $rank === 1 ? 'rank-first' : ($rank === 2 ? 'rank-second' : ($rank === 3 ? 'rank-third' : 'rank-other'));
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
                        $gpu = trim((string) ($product['gpu_model'] ?? ''));
                        if ($gpu !== '' && $product['gpu_type'] === 'dedicated' && $product['gpu_vram_gb']) {
                            $gpu .= ' · ' . (int) $product['gpu_vram_gb'] . ' GB';
                        }
                        $scoreStyle = '--score:' . number_format($score, 2, '.', '');
                        ?>
                        <article class="result-card<?= $rank === 1 ? ' is-top' : '' ?>" id="match-<?= $rank ?>">
                            <div class="result-rank <?= e($rankClass) ?>">
                                <span>#<?= $rank ?></span>
                                <?= $rank === 1 ? 'Top match' : 'Rank ' . $rank ?>
                            </div>

                            <div class="result-media">
                                <img src="<?= e($product['image_url'] ?: product_placeholder()) ?>"
                                     alt="<?= e($product['product_name']) ?>"
                                     loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                     data-fallback="<?= e(product_placeholder()) ?>">
                                <div class="score-ring" style="<?= e($scoreStyle) ?>" aria-label="Compatibility score <?= number_format($score, 1) ?> percent">
                                    <div class="score-ring-inner">
                                        <strong><?= number_format($score, 0) ?>%</strong>
                                        <span>match</span>
                                    </div>
                                </div>
                            </div>

                            <div class="result-body">
                                <div class="result-head">
                                    <div>
                                        <div class="result-eyebrow"><?= e($product['brand_name']) ?> <span>·</span> <?= e(ucfirst($product['product_category'])) ?></div>
                                        <h3><a href="<?= e(url('product.php?id=' . (int) $product['id'] . '&token=' . urlencode($token))) ?>"><?= e($product['product_name']) ?></a></h3>
                                        <div class="result-retailer">
                                            <i data-lucide="store"></i>
                                            <?= e($product['retailer_name']) ?>
                                            <span class="dot-separator">·</span>
                                            <span class="<?= e(stock_class($product['stock_status'])) ?>"><?= e(stock_label($product['stock_status'])) ?></span>
                                            <span class="dot-separator">·</span>
                                            <span class="<?= e($fresh['class']) ?>"><?= e($fresh['label']) ?></span>
                                        </div>
                                    </div>
                                    <label class="compare-check">
                                        <input type="checkbox" data-compare data-id="<?= (int) $product['id'] ?>" data-name="<?= e($product['product_name']) ?>" data-price="<?= e(format_price($product['price_tnd'])) ?>">
                                        <span><i data-lucide="git-compare"></i> Compare</span>
                                    </label>
                                </div>

                                <div class="result-specs">
                                    <div class="result-spec">
                                        <span class="result-spec-icon"><i data-lucide="cpu"></i></span>
                                        <div><span>Processor</span><strong><?= e($product['processor_model']) ?></strong><?php if ($product['processor_generation']): ?><small><?= e($product['processor_generation']) ?></small><?php endif; ?></div>
                                    </div>
                                    <div class="result-spec">
                                        <span class="result-spec-icon"><i data-lucide="memory-stick"></i></span>
                                        <div><span>Memory</span><strong><?= (int) $product['ram_gb'] ?> GB<?= $product['ram_type'] ? ' ' . e($product['ram_type']) : '' ?></strong><small><?= $product['ram_upgradeable'] === 'yes' ? 'Upgradeable' : ($product['ram_upgradeable'] === 'no' ? 'Soldered / not upgradeable' : 'Upgradeability not stated') ?></small></div>
                                    </div>
                                    <div class="result-spec">
                                        <span class="result-spec-icon"><i data-lucide="circuit-board"></i></span>
                                        <div><span>Graphics</span><strong><?= e($gpu ?: 'Not stated') ?></strong><small><?= e(ucfirst((string) $product['gpu_type'])) ?></small></div>
                                    </div>
                                    <div class="result-spec">
                                        <span class="result-spec-icon"><i data-lucide="hard-drive"></i></span>
                                        <div><span>Storage</span><strong><?= number_format((int) $product['storage_gb']) ?> GB</strong><small><?= e($product['storage_type']) ?></small></div>
                                    </div>
                                    <?php if ($product['product_category'] === 'laptop' && $display !== ''): ?>
                                        <div class="result-spec">
                                            <span class="result-spec-icon"><i data-lucide="monitor"></i></span>
                                            <div><span>Display</span><strong><?= e($display) ?></strong><small><?= $product['weight_kg'] ? e(number_format((float) $product['weight_kg'], 2)) . ' kg' : 'Weight not stated' ?></small></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($product['operating_system']): ?>
                                        <div class="result-spec">
                                            <span class="result-spec-icon"><i data-lucide="app-window"></i></span>
                                            <div><span>Operating system</span><strong><?= e($product['operating_system']) ?></strong><small>As listed by the retailer</small></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="explanation-panel">
                                    <div class="explanation-head">
                                        <span><i data-lucide="message-square-text"></i> Why this PC matches you</span>
                                        <span class="badge badge-accent">Rule-generated</span>
                                    </div>
                                    <p><?= e($product['explanation']) ?></p>
                                </div>

                                <details class="score-details">
                                    <summary>
                                        <span><i data-lucide="chart-no-axes-column"></i> Why this score?</span>
                                        <span class="score-summary-meta"><?= number_format($score, 1) ?> / 100 <i data-lucide="chevron-down"></i></span>
                                    </summary>
                                    <div class="breakdown-table">
                                        <?php foreach ($breakdown as $part): ?>
                                            <div class="breakdown-row">
                                                <div class="breakdown-label">
                                                    <strong><?= e($part['label']) ?></strong>
                                                    <span>Weight <?= e($part['weight']) ?>%</span>
                                                </div>
                                                <div class="breakdown-bar-wrap">
                                                    <div class="breakdown-bar"><span style="width: <?= (float) $part['score'] ?>%"></span></div>
                                                    <p><?= e($part['detail']) ?></p>
                                                </div>
                                                <div class="breakdown-points"><?= number_format((float) $part['points'], 1) ?> <small>pts</small></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>

                                <div class="result-purchase">
                                    <div class="result-price">
                                        <span>Listed price</span>
                                        <strong><?= e(format_price($product['price_tnd'])) ?></strong>
                                        <small>Available at <b><?= e($product['retailer_name']) ?></b> · recorded <?= e(format_date($product['verified_at'])) ?></small>
                                    </div>
                                    <div class="purchase-actions">
                                        <a class="btn btn-success" href="<?= e($product['product_url']) ?>" target="_blank" rel="noopener noreferrer">
                                            Buy from <?= e($product['retailer_name']) ?>
                                            <i data-lucide="external-link"></i>
                                        </a>
                                        <a class="btn btn-secondary" href="<?= e(url('product.php?id=' . (int) $product['id'] . '&token=' . urlencode($token))) ?>">
                                            <i data-lucide="scan-eye"></i> Full specifications
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="results-footnote">
                <i data-lucide="info"></i>
                <p>Compatibility scores are relative to your stated profile and the current local catalogue. They are not benchmark results, and they are not a guarantee of availability. Prices and stock can change after the recorded verification date. <a href="<?= e(url('how-it-works.php')) ?>">Read the scoring methodology</a>.</p>
            </div>
        </div>
    </section>

    <?php if ($count >= 2): ?>
        <div class="compare-dock" data-compare-dock hidden>
            <div class="compare-dock-info">
                <span class="compare-dock-icon"><i data-lucide="git-compare"></i></span>
                <div><strong><span data-compare-count>0</span> selected</strong><small>Choose up to 3 PCs</small></div>
            </div>
            <div class="compare-chips" data-compare-chips></div>
            <a class="btn btn-primary btn-sm" data-compare-button href="#" aria-disabled="true">Compare selected <i data-lucide="arrow-right"></i></a>
        </div>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
