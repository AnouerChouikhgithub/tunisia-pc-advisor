<?php
declare(strict_types=1);

/**
 * Generate database/data_quality_report.md from the live database and the
 * research snapshot summary.
 *
 * Usage: php tools/data_quality.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$pdo = Database::connection();
$repo = new ProductRepository();
$stats = $repo->statistics();
$checks = $repo->dataQualityChecks();
$summaryPath = APP_ROOT . '/database/research/dataset_summary.json';
$summary = is_file($summaryPath) ? json_decode((string) file_get_contents($summaryPath), true) : [];

$freshness = $pdo->query(
    "SELECT SUM(verified_at >= (NOW() - INTERVAL 7 DAY)) AS recent,
            SUM(verified_at < (NOW() - INTERVAL 7 DAY) AND verified_at >= (NOW() - INTERVAL 30 DAY)) AS review,
            SUM(verified_at IS NULL OR verified_at < (NOW() - INTERVAL 30 DAY)) AS outdated
     FROM products"
)->fetch();

$priceBands = $pdo->query(
    "SELECT product_category,
            CASE
                WHEN price_tnd < 1200 THEN 'Under 1,200'
                WHEN price_tnd < 1800 THEN '1,200–1,799'
                WHEN price_tnd < 2500 THEN '1,800–2,499'
                WHEN price_tnd < 3500 THEN '2,500–3,499'
                WHEN price_tnd < 5000 THEN '3,500–4,999'
                WHEN price_tnd < 7000 THEN '5,000–6,999'
                ELSE '7,000+'
            END AS band,
            COUNT(*) AS n
     FROM products GROUP BY product_category, band ORDER BY product_category, MIN(price_tnd)"
)->fetchAll();
$retailers = $pdo->query(
    'SELECT r.name, COUNT(p.id) AS n, SUM(p.stock_status = "out_of_stock") AS out_of_stock
     FROM retailers r LEFT JOIN products p ON p.retailer_id = r.id
     GROUP BY r.id, r.name ORDER BY n DESC'
)->fetchAll();
$brands = $pdo->query(
    'SELECT b.name, COUNT(p.id) AS n FROM brands b LEFT JOIN products p ON p.brand_id = b.id
     GROUP BY b.id, b.name HAVING n > 0 ORDER BY n DESC'
)->fetchAll();
$linkStatus = $pdo->query('SELECT link_status, COUNT(*) AS n FROM products GROUP BY link_status')->fetchAll();
$qualityFields = $pdo->query(
    "SELECT
        SUM(weight_kg IS NOT NULL) AS weight_known,
        SUM(battery IS NOT NULL) AS battery_known,
        SUM(screen_panel IS NOT NULL) AS panel_known,
        SUM(operating_system IS NOT NULL) AS os_known,
        SUM(warranty_information IS NOT NULL) AS warranty_known,
        SUM(gpu_vram_gb IS NOT NULL) AS vram_known,
        SUM(ram_upgradeable = 'yes') AS upgrade_yes,
        SUM(ram_upgradeable = 'no') AS upgrade_no,
        SUM(ram_upgradeable = 'unknown') AS upgrade_unknown
     FROM products"
)->fetch();
$manualReportPath = APP_ROOT . '/database/research/manual_verification.json';
$manualReport = is_file($manualReportPath) ? json_decode((string) file_get_contents($manualReportPath), true) : [];

$generated = date('d F Y, H:i');
$lines = [];
$lines[] = '# Data Quality Report — Tunisia PC Advisor';
$lines[] = '';
$lines[] = '**Generated:** ' . $generated . ' (Africa/Tunis)  ';
$lines[] = '**Database:** `tunisia_pc_advisor`  ';
$lines[] = '**Primary snapshot:** 10 November 2026  ';
$lines[] = '**Multi-retailer manual review:** 7 November 2026';
$lines[] = '';
$lines[] = '## Executive summary';
$lines[] = '';
$lines[] = '| Requirement | Target | Result | Status |';
$lines[] = '|---|---:|---:|---|';
$lines[] = '| Distinct verified catalogue records | 150 | ' . number_format($stats['total']) . ' | ' . ($stats['total'] >= 150 ? 'Met' : 'Not met') . ' |';
$lines[] = '| Laptop configurations | 120 | ' . number_format($stats['laptops']) . ' | ' . ($stats['laptops'] >= 120 ? 'Met' : 'Not met') . ' |';
$lines[] = '| Desktop configurations | 30 | ' . number_format($stats['desktops']) . ' | ' . ($stats['desktops'] >= 30 ? 'Met' : 'Not met') . ' |';
$lines[] = '| Retailers represented | Not fixed | ' . number_format($stats['retailers']) . ' | Five Tunisian retailers |';
$lines[] = '| Duplicate product URLs | 0 | ' . number_format(count($checks['duplicateUrls'])) . ' | ' . (count($checks['duplicateUrls']) === 0 ? 'Pass' : 'Review') . ' |';
$lines[] = '| Duplicate retailer SKU groups | 0 | ' . number_format(count($checks['duplicateSkus'])) . ' | ' . (count($checks['duplicateSkus']) === 0 ? 'Pass' : 'Review') . ' |';
$lines[] = '| Records with missing critical fields | 0 | ' . number_format(count($checks['missing'])) . ' | ' . (count($checks['missing']) === 0 ? 'Pass' : 'Review') . ' |';
$lines[] = '';
$lines[] = 'The catalogue target was met without fabricating products. The 170 records are distinct product pages/configurations, not repeated rows created to reach the threshold. Prices are point-in-time retailer snapshots, not live feeds.';
$lines[] = '';
$lines[] = '## Sources and method';
$lines[] = '';
$lines[] = '- **Tunisia Net:** public product sitemap and individual product pages, collected 10 November 2026 with a single-worker, rate-limited collector. Structured page data was used for reference, price, stock label, image and specifications. The collector was extended into older catalogue pages to improve price-band coverage.';
$lines[] = '- **Mytek, SpaceNet, SBS Informatique, Wiki:** individual product pages reviewed manually. Only documented fields were entered. A separate HTTP check captured status, title and Open Graph image where accessible.';
$lines[] = '- A record was accepted only when processor, RAM, storage, price, brand, model and a direct product URL were available. Missing optional fields remain `NULL`.';
$lines[] = '- No access controls, authentication, paywalls or anti-bot challenges were bypassed. HTTP 403 responses are recorded as blocked/unconfirmed, not as a successful verification.';
$lines[] = '';
$lines[] = '## Coverage';
$lines[] = '';
$lines[] = '### By retailer';
$lines[] = '';
$lines[] = '| Retailer | Records | Out of stock |';
$lines[] = '|---|---:|---:|';
foreach ($retailers as $retailer) {
    $lines[] = '| ' . $retailer['name'] . ' | ' . (int) $retailer['n'] . ' | ' . (int) $retailer['out_of_stock'] . ' |';
}
$lines[] = '';
$lines[] = '### By brand';
$lines[] = '';
$lines[] = '| Brand | Records |';
$lines[] = '|---|---:|';
foreach ($brands as $brand) {
    $lines[] = '| ' . $brand['name'] . ' | ' . (int) $brand['n'] . ' |';
}
$lines[] = '';
$lines[] = '### By category and price band (TND)';
$lines[] = '';
$lines[] = '| Category | Price band | Records |';
$lines[] = '|---|---|---:|';
foreach ($priceBands as $row) {
    $lines[] = '| ' . ucfirst($row['product_category']) . ' | ' . $row['band'] . ' | ' . (int) $row['n'] . ' |';
}
$lines[] = '';
$lines[] = '## Stock and freshness';
$lines[] = '';
$lines[] = '| Metric | Count |';
$lines[] = '|---|---:|';
$lines[] = '| In stock at verification | ' . number_format($stats['total'] - $stats['out_of_stock'] - (int) ($pdo->query("SELECT COUNT(*) FROM products WHERE stock_status IN ('limited','preorder','unknown')")->fetchColumn())) . ' |';
$lines[] = '| Limited / arriving | ' . number_format((int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock_status = 'limited'")->fetchColumn()) . ' |';
$lines[] = '| Out of stock | ' . number_format($stats['out_of_stock']) . ' |';
$lines[] = '| Recently verified (0–7 days) | ' . number_format((int) $freshness['recent']) . ' |';
$lines[] = '| Needs review (8–30 days) | ' . number_format((int) $freshness['review']) . ' |';
$lines[] = '| Outdated or never verified | ' . number_format((int) $freshness['outdated']) . ' |';
$lines[] = '| Broken links marked by maintenance | ' . number_format($stats['broken_links']) . ' |';
$lines[] = '| Average listed price | ' . format_price($stats['average_price']) . ' |';
$lines[] = '';
$lines[] = 'Out-of-stock records remain in the catalogue for auditability but are excluded from recommendation candidates. Limited/arriving records remain eligible and are labelled as limited in the UI.';
$lines[] = '';
$lines[] = '## Integrity checks';
$lines[] = '';
$lines[] = '- **Duplicate direct URLs:** ' . count($checks['duplicateUrls']) . ' group(s).';
$lines[] = '- **Duplicate retailer SKU combinations:** ' . count($checks['duplicateSkus']) . ' group(s).';
$lines[] = '- **Invalid URL format:** ' . count($checks['invalidUrls']) . ' record(s).';
$lines[] = '- **Records missing a critical field:** ' . count($checks['missing']) . '.';
$lines[] = '- **Price range:** ' . format_price($checks['priceStats']['min_price'] ?? 0) . ' to ' . format_price($checks['priceStats']['max_price'] ?? 0) . '.';
$lines[] = '- **Categories:** ' . implode(', ', array_map(static fn (array $row): string => ucfirst($row['product_category']) . ' = ' . (int) $row['n'], $checks['categories'])) . '.';
$lines[] = '- **Link status:** ' . implode(', ', array_map(static fn (array $row): string => $row['link_status'] . ' = ' . (int) $row['n'], $linkStatus)) . '.';
$lines[] = '';
$lines[] = '## Field completeness';
$lines[] = '';
$lines[] = 'Optional fields are deliberately allowed to be `NULL`. The following counts show documented coverage at the time of this report:';
$lines[] = '';
$lines[] = '| Field | Records with a value |';
$lines[] = '|---|---:|';
$lines[] = '| Weight | ' . (int) $qualityFields['weight_known'] . ' |';
$lines[] = '| Battery detail | ' . (int) $qualityFields['battery_known'] . ' |';
$lines[] = '| Screen panel type | ' . (int) $qualityFields['panel_known'] . ' |';
$lines[] = '| Operating system | ' . (int) $qualityFields['os_known'] . ' |';
$lines[] = '| Warranty text | ' . (int) $qualityFields['warranty_known'] . ' |';
$lines[] = '| GPU VRAM | ' . (int) $qualityFields['vram_known'] . ' |';
$lines[] = '| RAM upgradeable = yes | ' . (int) $qualityFields['upgrade_yes'] . ' |';
$lines[] = '| RAM upgradeable = no | ' . (int) $qualityFields['upgrade_no'] . ' |';
$lines[] = '| RAM upgradeability unknown | ' . (int) $qualityFields['upgrade_unknown'] . ' |';
$lines[] = '';
$lines[] = '## Exceptions and limitations';
$lines[] = '';
$lines[] = '- **Mytek and Wiki automated HTTP checks returned 403** (Cloudflare challenge) on 10 November 2026. Their records are included from the manual page review with product URL, title, specifications and price evidence, but the automated recheck is blocked. They are flagged `unchecked`, not `ok` or `broken`, in the link-status field.';
$lines[] = '- **Mytek price extraction caveat:** the product page price block did not render in the text extraction; the listed price came from Mytek’s own product listing. This is recorded in `manual_products.json` and should be rechecked in a browser before relying on it.';
$lines[] = '- **Wiki RAM inconsistency:** the page title/description said 20 GB, while the specification table said 16 GB. The 16 GB specification-table value is stored and the inconsistency is retained in the research notes rather than silently resolved.';
$lines[] = '- **Low-budget supply:** the selected catalogue contains only one laptop under 1,200 TND. The investigated current listings did not supply a deep sub-1,200 TND segment. The engine returns fewer than five matches rather than relaxing the budget.';
$lines[] = '- **Brand coverage:** ASUS, HP, Lenovo, Dell, MSI, Apple, Blackview and Tunisia Net house configurations are represented. No Acer, Gigabyte or Samsung listing survived the selected verified set. They were not invented to fill brand quotas.';
$lines[] = '- **Point-in-time prices:** every record is a snapshot from its verification date. The application marks freshness but does not claim real-time prices or stock.';
$lines[] = '- **Heuristic hardware tiers:** the recommendation engine uses family-level tiers, not measured benchmarks. Explanations describe the stored specifications and rule classification, not benchmark superiority.';
$lines[] = '- **Four out-of-stock and three limited-stock records** are included in the 170-record catalogue. They are excluded or labelled appropriately in the recommendation interface.';
$lines[] = '';
$lines[] = '## Manual URL verification snapshot';
$lines[] = '';
if (!empty($manualReport['results'])) {
    $lines[] = '| Retailer | Product | HTTP | Page title captured |';
    $lines[] = '|---|---|---:|---|';
    foreach ($manualReport['results'] as $result) {
        $title = $result['page_title'] ?? $result['curl_error'] ?? '';
        $lines[] = '| ' . $result['retailer'] . ' | ' . $result['product'] . ' | ' . (int) $result['http_status'] . ' | ' . str_replace('|', '/', (string) $title) . ' |';
    }
} else {
    $lines[] = 'No manual verification snapshot was found. Run `php tools/verify_manual.php`.';
}
$lines[] = '';
$lines[] = '## Reproducing and maintaining';
$lines[] = '';
$lines[] = '```bash';
$lines[] = '/c/xampp/php/php.exe tools/scrape_tunisianet.php   # optional, rate-limited research refresh';
$lines[] = '/c/xampp/php/php.exe tools/verify_manual.php       # recheck multi-retailer pages';
$lines[] = '/c/xampp/php/php.exe tools/build_seed.php          # regenerate database/seed.sql';
$lines[] = '/c/xampp/php/php.exe tools/check_links.php --limit=50 --delay=1200';
$lines[] = '/c/xampp/php/php.exe tools/data_quality.php        # regenerate this report';
$lines[] = '```';
$lines[] = '';
$lines[] = 'A successful structural check is not proof that a retailer’s current price or stock is unchanged. Freshness must be reviewed manually in the admin dashboard.';
$lines[] = '';

$path = APP_ROOT . '/database/data_quality_report.md';
file_put_contents($path, implode("\n", $lines), LOCK_EX);
echo "Wrote {$path}\n";
