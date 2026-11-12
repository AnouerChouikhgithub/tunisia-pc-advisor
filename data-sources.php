<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'Data sources and verification';
$pageDescription = 'Retailers, collection method, verification dates and limitations of the Tunisia PC Advisor product catalogue.';
$currentPage = 'data-sources';
$extraCss = ['results.css'];

$repo = new ProductRepository();
$stats = $repo->statistics();
$retailers = $repo->retailers();
$brands = array_values(array_filter($repo->brands(), static fn (array $brand): bool => (int) $brand['product_count'] > 0));

require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow">Evidence first</span>
            <h1>The catalogue is only as good as its sources.</h1>
            <p>Every record points to a real product page. Prices, stock labels and specifications are captured at a verification time and clearly marked as point-in-time data.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="info-grid" style="margin-bottom:42px;">
                <div class="info-card card">
                    <i data-lucide="database"></i>
                    <h3><?= number_format($stats['total']) ?> catalogue records</h3>
                    <p><?= number_format($stats['laptops']) ?> laptops and <?= number_format($stats['desktops']) ?> desktops, including out-of-stock listings retained for transparency.</p>
                </div>
                <div class="info-card card">
                    <i data-lucide="store"></i>
                    <h3><?= number_format($stats['retailers']) ?> active retailers</h3>
                    <p>Listings are attributed to the retailer whose page supplied the price and configuration.</p>
                </div>
                <div class="info-card card">
                    <i data-lucide="calendar-check-2"></i>
                    <h3><?= number_format($stats['recently_verified']) ?> recently verified</h3>
                    <p>Within seven days at the time this page was loaded. <?= number_format($stats['needing_verification']) ?> need verification or are older than 30 days.</p>
                </div>
            </div>

            <div class="content-grid">
                <div class="prose">
                    <h2>Retailers in the catalogue</h2>
                    <p>We investigated the major Tunisian computer retailers identified in the brief. Direct product pages are the primary evidence for a listing; a retailer homepage or search page is never used as a product URL.</p>
                    <div class="card" style="overflow-x:auto;margin:22px 0;">
                        <table class="compare-table" style="min-width:600px;">
                            <thead><tr><th scope="col">Retailer</th><th scope="col">Website</th><th scope="col">Catalogue records</th></tr></thead>
                            <tbody>
                                <?php foreach ($retailers as $retailer): ?>
                                    <tr>
                                        <th scope="row"><?= e($retailer['name']) ?></th>
                                        <td><a href="<?= e($retailer['website_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($retailer['website_url']) ?> <i data-lucide="external-link" style="width:12px;height:12px;"></i></a></td>
                                        <td><?= number_format((int) $retailer['product_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h2>Collection method</h2>
                    <p>The Tunisia Net portion of the catalogue was collected from the retailer’s public product sitemap and public product pages on 10 November 2026. The collection was single-worker and rate-limited. For each page, the structured product payload was used for the product name, reference, price, stock label, image and specification fields. A product was only accepted when the CPU, memory, storage, price, brand and direct URL were present.</p>
                    <p>Additional listings from Mytek, SpaceNet, SBS Informatique and Wiki were manually reviewed from their individual product pages and entered with the same field rules. Fields that the page did not document — for example a battery capacity, weight, panel type or warranty — were left empty rather than inferred.</p>
                    <p>The collection respects public access controls. It does not bypass authentication, paywalls or anti-bot challenges, and the resulting files are a local research snapshot. Re-running the collector is an administrative maintenance action and should be checked against each retailer’s current terms and robots.txt.</p>

                    <h2>What verification means here</h2>
                    <ul>
                        <li><strong>Direct URL:</strong> the product URL points to the specific retailer page. The admin validator also checks that the host belongs to the selected retailer and that the path is not a homepage.</li>
                        <li><strong>Price:</strong> the price is the retailer’s listed TND amount at capture time. It is not converted from an international currency.</li>
                        <li><strong>Stock:</strong> the stock label is the retailer’s label at capture time. “In stock” on this site does not guarantee current availability.</li>
                        <li><strong>Specifications:</strong> missing values stay missing. The recommendation engine and explanation generator skip them instead of guessing.</li>
                        <li><strong>Freshness:</strong> records are labelled recently verified (0–7 days), needs review (8–30 days) or outdated (>30 days) based on the stored timestamp.</li>
                    </ul>

                    <h2>Known limitations</h2>
                    <ul>
                        <li>Tunisian retail prices and availability change frequently; a future page load can differ from the stored snapshot.</li>
                        <li>Some product pages list a configuration family with a range of screen sizes or omit memory upgradeability. Those fields remain unconfirmed.</li>
                        <li>Retailer page markup can contain internal inconsistencies. Where a title and specification table disagreed, the record was treated conservatively and the relevant field was left unconfirmed.</li>
                        <li>The current collection is strongest in the 1,200–5,000 TND laptop range. Supply below 1,200 TND is limited on the investigated pages.</li>
                    </ul>

                    <h2>Independence</h2>
                    <p>Tunisia PC Advisor is an academic project. It is not affiliated with the retailers, and the purchase links are not affiliate links. Ranking is based on the deterministic compatibility score, not on retailer or brand relationships.</p>
                </div>

                <aside class="side-card card">
                    <h2>At a glance</h2>
                    <ul>
                        <li><i data-lucide="calendar"></i> Snapshot: <?= e(format_date(app('research_date'))) ?></li>
                        <li><i data-lucide="laptop"></i> <?= number_format($stats['laptops']) ?> laptop records</li>
                        <li><i data-lucide="pc-case"></i> <?= number_format($stats['desktops']) ?> desktop records</li>
                        <li><i data-lucide="triangle-alert"></i> <?= number_format($stats['out_of_stock']) ?> out-of-stock records</li>
                        <li><i data-lucide="clock-alert"></i> <?= number_format($stats['needing_verification']) ?> need verification</li>
                        <li><i data-lucide="tags"></i> <?= number_format(count($brands)) ?> represented brands</li>
                    </ul>
                    <a class="btn btn-secondary btn-block" href="<?= e(url('how-it-works.php')) ?>">Recommendation methodology</a>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
