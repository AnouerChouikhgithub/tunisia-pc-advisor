<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'Tunisia PC Advisor';
$pageDescription = 'Find the best laptop or desktop available in Tunisia based on your profession, activities, specifications and budget.';
$currentPage = 'home';
$stats = ['total' => 0, 'laptops' => 0, 'desktops' => 0, 'retailers' => 0];
$featured = [];
$brands = [];
$activities = [];
$setupError = null;

try {
    $productRepo = new ProductRepository();
    $activityRepo = new ActivityRepository();
    $stats = $productRepo->statistics();
    $featured = $productRepo->latest(3);
    $brands = array_values(array_filter(
        $productRepo->brands(),
        static fn (array $brand): bool => (int) $brand['product_count'] > 0
    ));
    $activities = $activityRepo->all();
} catch (Throwable $exception) {
    $setupError = $exception->getMessage();
}

$iconActivities = [];
$preferredSlugs = ['software-developer', 'graphic-designer', 'video-editor', '3d-designer', 'architect', 'gamer', 'ai-ml', 'office-worker', 'student', 'content-creator', 'data-scientist', 'mobile-developer'];
foreach ($preferredSlugs as $slug) {
    foreach ($activities as $activity) {
        if ($activity['slug'] === $slug) {
            $iconActivities[] = $activity;
            break;
        }
    }
}

$scoringAnatomy = [
    ['label' => 'CPU suitability', 'weight' => 24, 'icon' => 'cpu', 'detail' => 'Processor class against your profile'],
    ['label' => 'RAM suitability', 'weight' => 22, 'icon' => 'memory-stick', 'detail' => 'Installed memory versus workload needs'],
    ['label' => 'GPU suitability', 'weight' => 8, 'icon' => 'gpu', 'detail' => 'Integrated or discrete graphics fit'],
    ['label' => 'Storage suitability', 'weight' => 12, 'icon' => 'hard-drive', 'detail' => 'Capacity and SSD technology'],
    ['label' => 'Budget and value', 'weight' => 18, 'icon' => 'wallet', 'detail' => 'Headroom and performance per dinar'],
    ['label' => 'Activity fit', 'weight' => 11, 'icon' => 'target', 'detail' => 'Your selected workloads'],
    ['label' => 'Preferences', 'weight' => 5, 'icon' => 'sliders-horizontal', 'detail' => 'Brand and portability choices'],
];

require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="pill"><i data-lucide="shield-check"></i> Rule-based matching. No AI guesswork.</span>
                <h1 class="gradient-text">Find the perfect PC for your needs.</h1>
                <p class="hero-subtitle">Discover the best computers available in Tunisia based on your profession, activities, and budget. Every match is scored against real retailer listings and explained in plain language.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('questionnaire.php')) ?>">
                        Find My Perfect PC
                        <i data-lucide="arrow-right"></i>
                    </a>
                    <a class="btn btn-secondary btn-lg" href="<?= e(url('how-it-works.php')) ?>">
                        <i data-lucide="play-circle"></i>
                        See how matching works
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <strong><?= number_format($stats['total']) ?></strong>
                        <span>verified listings</span>
                    </div>
                    <div class="hero-stat">
                        <strong><?= number_format($stats['retailers']) ?></strong>
                        <span>Tunisian retailers</span>
                    </div>
                    <div class="hero-stat">
                        <strong><?= number_format(count($activities)) ?></strong>
                        <span>activity profiles</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="engine-card">
                    <div class="engine-head">
                        <div class="engine-title">
                            <span class="engine-icon"><i data-lucide="scan-search"></i></span>
                            <div>
                                <strong>Scoring anatomy</strong>
                                <span>Example weights for a software developer</span>
                            </div>
                        </div>
                        <span class="engine-live">transparent</span>
                    </div>

                    <?php foreach ($scoringAnatomy as $row): ?>
                        <div class="score-row">
                            <span class="score-icon"><i data-lucide="<?= e($row['icon']) ?>"></i></span>
                            <div>
                                <div class="score-meta">
                                    <span><?= e($row['label']) ?></span>
                                    <span><?= e($row['detail']) ?></span>
                                </div>
                                <div class="score-track">
                                    <div class="score-fill" style="width: <?= (int) $row['weight'] ?>%"></div>
                                </div>
                            </div>
                            <span class="score-value"><?= (int) $row['weight'] ?>%</span>
                        </div>
                    <?php endforeach; ?>

                    <div class="engine-footer">
                        <i data-lucide="info"></i>
                        <span>Weights adapt to the activity. Every result exposes its full score breakdown.</span>
                    </div>
                </div>
                <div class="engine-float">
                    <i data-lucide="badge-check"></i>
                    <div>
                        <strong>Database first</strong>
                        <span>Specs and prices come from listings</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="trust-strip">
        <div class="container trust-inner">
            <span class="trust-label">Brands represented</span>
            <div class="brand-list">
                <?php if ($brands !== []): ?>
                    <?php foreach (array_slice($brands, 0, 9) as $brand): ?>
                        <span><?= e($brand['name']) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span>ASUS</span><span>HP</span><span>Lenovo</span><span>Dell</span><span>MSI</span><span>Acer</span><span>Apple</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($setupError): ?>
        <section class="section-tight">
            <div class="container">
                <div class="alert alert-warning">
                    <i data-lucide="database"></i>
                    <div><strong>Database setup required.</strong> <?= e($setupError) ?></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section" id="how-it-works">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="eyebrow">How it works</span>
                    <h2>From “I need a PC” to five defensible matches.</h2>
                    <p>No black-box answers. We combine your requirements with a structured hardware profile and rank only products that fit your budget.</p>
                </div>
                <a class="link-arrow" href="<?= e(url('how-it-works.php')) ?>">Read the methodology <i data-lucide="arrow-up-right"></i></a>
            </div>

            <div class="steps-grid">
                <article class="step-card card" data-reveal>
                    <span class="step-number">01</span>
                    <h3>Tell us what you need</h3>
                    <p>Choose your occupation and the workloads that matter, from programming and CAD to gaming and office work.</p>
                </article>
                <article class="step-card card" data-reveal>
                    <span class="step-number">02</span>
                    <h3>Define your budget</h3>
                    <p>Set a range or an exact maximum in Tunisian dinars. Over-budget products are excluded, never quietly recommended.</p>
                </article>
                <article class="step-card card" data-reveal>
                    <span class="step-number">03</span>
                    <h3>Let the engine compare</h3>
                    <p>The PHP engine scores CPU, RAM, GPU, storage, value, activity fit and preferences against the catalogue.</p>
                </article>
                <article class="step-card card" data-reveal>
                    <span class="step-number">04</span>
                    <h3>Discover your best options</h3>
                    <p>Review the five highest-scoring qualifying PCs, compare them side by side and open the exact retailer listing.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section-tight" id="profiles">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Built around real work</span>
                    <h2>One engine. <?= number_format(count($activities)) ?> different realities.</h2>
                    <p>A gamer, an architect and an office manager need different hardware. Each profile carries its own minimums, preferred targets and scoring weights.</p>
                </div>
                <a class="link-arrow" href="<?= e(url('questionnaire.php')) ?>">Choose your profile <i data-lucide="arrow-right"></i></a>
            </div>

            <div class="activities-grid">
                <?php foreach ($iconActivities as $activity): ?>
                    <a class="activity-tile" href="<?= e(url('questionnaire.php?activity=' . (int) $activity['id'])) ?>" data-reveal>
                        <span class="activity-tile-icon"><i data-lucide="<?= e($activity['icon'] ?: 'monitor') ?>"></i></span>
                        <span><?= e($activity['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container split-feature">
            <div>
                <span class="eyebrow">Explainable by design</span>
                <h2>Every percentage has a reason.</h2>
                <p class="muted">The recommendation engine is deterministic PHP. It uses activity requirements, workload minimums, hardware tiers and the product’s real specifications — not generated prose or a hosted AI model.</p>
                <ul class="feature-list">
                    <li>
                        <span class="feature-check"><i data-lucide="check"></i></span>
                        <div><strong>Hard filters stay hard</strong><p>Budget, category, explicit RAM/storage minimums and a required discrete GPU are applied before scoring.</p></div>
                    </li>
                    <li>
                        <span class="feature-check"><i data-lucide="check"></i></span>
                        <div><strong>Explanations quote the listing</strong><p>CPU, memory, storage, graphics, screen and price statements are built from database fields that actually exist.</p></div>
                    </li>
                    <li>
                        <span class="feature-check"><i data-lucide="check"></i></span>
                        <div><strong>Missing data is not invented</strong><p>If a retailer does not state weight, VRAM or battery capacity, the explanation stays silent instead of guessing.</p></div>
                    </li>
                    <li>
                        <span class="feature-check"><i data-lucide="check"></i></span>
                        <div><strong>Diversity is considered</strong><p>When scores are close, the final five are spread across families and brands rather than repeating one model.</p></div>
                    </li>
                </ul>
            </div>

            <div class="transparency-panel">
                <span class="pill"><i data-lucide="lock-keyhole"></i> No sponsored ranking</span>
                <h3 class="mt-0" style="margin-top:18px;">What the engine will never do</h3>
                <div class="transparency-grid">
                    <div class="transparency-item">
                        <i data-lucide="ban"></i>
                        <strong>Call an AI API</strong>
                        <p>No ChatGPT, Gemini or recommendation service is contacted.</p>
                    </div>
                    <div class="transparency-item">
                        <i data-lucide="ban"></i>
                        <strong>Recommend over budget</strong>
                        <p>Products above your maximum are excluded, not quietly re-labelled.</p>
                    </div>
                    <div class="transparency-item">
                        <i data-lucide="ban"></i>
                        <strong>Fill empty slots</strong>
                        <p>If fewer than five products qualify, the result set is honestly smaller.</p>
                    </div>
                    <div class="transparency-item">
                        <i data-lucide="ban"></i>
                        <strong>Claim live availability</strong>
                        <p>Stock and price are verification-time snapshots, clearly dated.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($featured !== []): ?>
        <section class="section-tight">
            <div class="container">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">From the catalogue</span>
                        <h2>Recently verified listings.</h2>
                        <p>A small sample of the actual products in the local database. These are not paid placements, and they are not pre-ranked for you.</p>
                    </div>
                    <a class="link-arrow" href="<?= e(url('data-sources.php')) ?>">See data sources <i data-lucide="arrow-up-right"></i></a>
                </div>
                <div class="product-grid">
                    <?php foreach ($featured as $product): ?>
                        <?php include __DIR__ . '/includes/product-mini.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section">
        <div class="container">
            <div class="cta-band">
                <div>
                    <span class="eyebrow">Your next computer</span>
                    <h2>Stop comparing twenty tabs. Start with five real matches.</h2>
                    <p>Five minutes of questions. A transparent score, a personalised explanation and a direct link to the Tunisian retailer listing.</p>
                </div>
                <a class="btn btn-primary btn-lg" href="<?= e(url('questionnaire.php')) ?>">
                    Find My 5 Best PCs
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
