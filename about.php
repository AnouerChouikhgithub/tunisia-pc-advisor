<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'About the project';
$pageDescription = 'Tunisia PC Advisor is an independent, rule-based PC recommendation platform built for the Tunisian market.';
$currentPage = 'about';

$stats = ['total' => 0, 'laptops' => 0, 'desktops' => 0, 'retailers' => 0];
try {
    $stats = (new ProductRepository())->statistics();
} catch (Throwable) {
}

require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow">About us</span>
            <h1>Built for the Tunisian market, not the global average.</h1>
            <p>Most PC buying advice assumes US prices, endless stock and software workloads that do not match local study or work realities. Tunisia PC Advisor starts with the local market and makes the reasoning visible.</p>
        </div>
    </section>

    <section class="section">
        <div class="container content-grid">
            <div class="prose">
                <h2>Why this exists</h2>
                <p>Choosing a computer in Tunisia often means opening several retailer tabs, comparing configurations with different names, and trying to work out whether 8 GB of memory or an entry-level graphics card will be enough for the next few years. Generic reviews rarely answer the question: “Which of these available listings is right for my budget and my work?”</p>
                <p>This platform answers that question with a structured questionnaire, a local product catalogue and an explicitly documented scoring engine. It is deliberately not an AI chatbot. The recommendation is reproducible: the same answers and the same catalogue produce the same ranking.</p>

                <h2>What the platform does differently</h2>
                <ul>
                    <li><strong>It separates requirements from preferences.</strong> Budget, category and explicit minimums are hard filters. Preferred CPU, RAM, storage and graphics classes influence the score without silently excluding every imperfect option.</li>
                    <li><strong>It respects the budget.</strong> Products above your ceiling are not included as “alternatives” unless the interface explicitly says so. In this build, they are excluded entirely.</li>
                    <li><strong>It names the trade-offs.</strong> A product that meets the minimum memory target but misses the preferred target says so. A product without the preferred discrete GPU says so.</li>
                    <li><strong>It distinguishes listing data from guarantees.</strong> Prices and stock are verification-time snapshots. The interface never calls them live.</li>
                    <li><strong>It links to the exact listing.</strong> Purchase buttons open the retailer’s product page, not a homepage or an affiliate redirect.</li>
                </ul>

                <h2>How the catalogue is maintained</h2>
                <p>The local MySQL catalogue currently contains <strong><?= number_format($stats['total']) ?> records</strong> across <strong><?= number_format($stats['retailers']) ?> active retailers</strong>, including <?= number_format($stats['laptops']) ?> laptops and <?= number_format($stats['desktops']) ?> desktops. Product updates are handled through the protected administration area, which records price history rather than overwriting it.</p>
                <p>Freshness is visible to administrators and, where relevant, to visitors. A record older than 30 days is marked outdated. The <a href="<?= e(url('data-sources.php')) ?>">data sources page</a> explains collection and verification in more detail.</p>

                <h2>Technical character</h2>
                <p>The application is server-rendered PHP with PDO prepared statements and a normalised MySQL schema. The interface uses HTML, CSS and vanilla JavaScript only. There are no JavaScript frameworks, no build pipeline and no WordPress installation. It is designed to run under XAMPP/Apache on a standard local machine.</p>

                <h2>Limitations and honesty</h2>
                <p>This is an academic project, not a consumer shopping service. The catalogue is a point-in-time snapshot and cannot guarantee retailer stock. Hardware tiers are rule-based heuristics, not laboratory benchmarks. Panel colour accuracy, battery endurance and cooling quality are only used when the retailer documents them — which is often not the case. The appropriate response to missing data is to say so, not to fill the gap with a plausible-sounding claim.</p>

                <h2 id="contact">Contact</h2>
                <p>For questions about this submission, corrections to a listing, or a retailer take-down request, use the contact details supplied with the project submission or contact the project owner through the associated course/institution channel.</p>
                <p>This local installation deliberately does not publish an email address or transmit contact-form messages from the browser. Administrators can deactivate or correct a record directly from the protected dashboard, and price changes are retained in the audit history.</p>
            </div>

            <aside class="side-card card">
                <h2>Project facts</h2>
                <ul>
                    <li><i data-lucide="code-2"></i> PHP <?= e(PHP_VERSION) ?> + PDO</li>
                    <li><i data-lucide="database"></i> MySQL 8+ / MariaDB</li>
                    <li><i data-lucide="layout-template"></i> HTML, CSS, vanilla JS</li>
                    <li><i data-lucide="brain-circuit"></i> Deterministic rule engine</li>
                    <li><i data-lucide="ban"></i> No AI or recommendation API</li>
                    <li><i data-lucide="shield-check"></i> CSRF and password hashing</li>
                </ul>
                <a class="btn btn-primary btn-block" href="<?= e(url('questionnaire.php')) ?>">Find your PC <i data-lucide="arrow-right"></i></a>
                <a class="btn btn-ghost btn-block" href="<?= e(url('privacy.php')) ?>">Privacy policy</a>
            </aside>
        </div>
    </section>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
