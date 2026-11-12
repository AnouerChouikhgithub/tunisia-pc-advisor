<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'How the recommendation engine works';
$pageDescription = 'The deterministic scoring method, hard filters, activity profiles and explanation rules behind Tunisia PC Advisor.';
$currentPage = 'how-it-works';

$activityRepo = new ActivityRepository();
$all = $activityRepo->all();
$bySlug = [];
foreach ($all as $activity) {
    $bySlug[$activity['slug']] = $activity;
}
$profileSlugs = ['office-worker', 'software-developer', 'graphic-designer', 'gamer', 'video-editor', 'ai-ml'];

require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow">Methodology</span>
            <h1>Five matches, and a reason for every one.</h1>
            <p>A deterministic PHP engine compares real catalogue specifications to a structured profile. There is no language model, no external recommendation API and no paid placement.</p>
        </div>
    </section>

    <section class="section">
        <div class="container content-grid">
            <div class="prose">
                <h2>The pipeline</h2>
                <ol>
                    <li><strong>Validate the questionnaire.</strong> The server checks the activity, at least one workload, device category, budget, explicit minimums, brands and importance values. Unknown or tampered IDs are rejected.</li>
                    <li><strong>Apply hard filters.</strong> Only active, purchasable listings at or below your maximum budget are loaded. If you chose a laptop, a desktop or “both”, the category is enforced. Explicit minimum RAM, minimum storage and “dedicated GPU required” are also hard filters.</li>
                    <li><strong>Score the survivors.</strong> Each product is scored from 0 to 100 across CPU, RAM, GPU, storage, budget/value, activity fit and preferences. The weights change by activity; a gamer and an office worker do not use the same importance for graphics.</li>
                    <li><strong>Rank and diversify.</strong> Products are ordered by compatibility, then by performance value and finally by lower price. A small family/brand diversity pass prevents five near-identical variants when equally suitable alternatives exist.</li>
                    <li><strong>Explain from the database.</strong> The explanation generator composes factual statements from the stored processor, memory, storage, GPU, screen, price, retailer and verification date. Missing fields are omitted rather than guessed.</li>
                </ol>

                <h2>Hard filters versus preferences</h2>
                <p><strong>Mandatory filters</strong> are conditions that remove a product from consideration: budget ceiling, selected category, your explicit RAM/storage minimums, and a required discrete GPU. <strong>Recommended specifications</strong> — an activity’s preferred CPU class, preferred 32 GB of memory, preferred 1 TB SSD — affect the score but do not automatically eliminate a product. This distinction lets the engine adapt to the Tunisian market without pretending that a budget product meets a premium profile.</p>

                <h2>The 100-point score</h2>
                <p>Weights are stored in the database for each activity and always sum to 100. The software developer profile, for example, prioritises CPU and memory while keeping graphics light. A gaming profile shifts weight to the GPU. Every result exposes its component scores and points in the “Why this score?” panel.</p>

                <div class="weight-matrix card" style="overflow-x:auto;margin:24px 0;">
                    <table class="compare-table" style="min-width:720px;">
                        <thead>
                            <tr>
                                <th scope="col">Profile</th>
                                <th scope="col">CPU</th>
                                <th scope="col">RAM</th>
                                <th scope="col">GPU</th>
                                <th scope="col">Storage</th>
                                <th scope="col">Budget/value</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Preferences</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profileSlugs as $slug): ?>
                                <?php if (!isset($bySlug[$slug])) continue; $profile = $bySlug[$slug]; ?>
                                <tr>
                                    <th scope="row"><?= e($profile['name']) ?></th>
                                    <td><?= (int) $profile['cpu_weight'] ?>%</td>
                                    <td><?= (int) $profile['ram_weight'] ?>%</td>
                                    <td><?= (int) $profile['gpu_weight'] ?>%</td>
                                    <td><?= (int) $profile['storage_weight'] ?>%</td>
                                    <td><?= (int) $profile['budget_weight'] ?>%</td>
                                    <td><?= (int) $profile['activity_weight'] ?>%</td>
                                    <td><?= (int) $profile['preferences_weight'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small muted">Open any result’s score breakdown to see its exact activity weights, component score, points and the specification comparison used to calculate them.</p>

                <h2>How hardware suitability is evaluated</h2>
                <h3>Processor</h3>
                <p>We maintain a family-level tier for Intel Core, Core Ultra, AMD Ryzen, Apple M-series and entry processors. It is a rule-based classification, not a benchmark. The engine gives more credit when the processor class meets or exceeds the activity’s preferred tier, and penalises a product below the minimum without claiming that a higher model number always wins in every application.</p>
                <h3>Memory and storage</h3>
                <p>Memory is scored against activity and workload minimums and preferences. DDR5/LPDDR5 receives a small bonus when the listing documents it, and an explicitly upgradeable memory configuration receives a tiny bonus. Storage combines capacity with technology: NVMe and SSD configurations score above eMMC or mechanical drives of the same size.</p>
                <h3>Graphics</h3>
                <p>The engine distinguishes integrated, dedicated and unconfirmed graphics. Some activities only need integrated graphics; gaming and 3D profiles weight a discrete GPU heavily. The dedicated-GPU requirement you can select is a hard filter. A GPU is never described as superior merely because of its model number, and VRAM is only mentioned when the retailer states it.</p>
                <h3>Budget and value</h3>
                <p>Affordability and performance-per-dinar are combined within the budget component. A product that leaves more headroom scores better on affordability, while a strong configuration at a lower price scores better on relative value. Over-budget products never enter the candidate set, so a high value score cannot override your ceiling.</p>
                <h3>Activity and workload fit</h3>
                <p>Each selected workload has its own minimum CPU class, memory, graphics expectation and storage. The engine computes a weighted fit across your selected workloads. Workloads that are central to the chosen activity carry more weight than peripheral ones, but every selected workload is considered.</p>

                <h2>Explanations</h2>
                <p>The <code>RecommendationExplanationGenerator</code> builds a paragraph from conditional rules. For example, it states that memory meets the preferred target only when the stored number is at least the target. If memory is below the preferred level, it says so and mentions upgradeability only if the retailer documented it. If a GPU is absent but the profile prefers one, the limitation is stated explicitly.</p>
                <blockquote>The generator cannot mention a component that is missing from the product record. It has no free-form generation step and no external model to call.</blockquote>

                <h2>What “five best” means</h2>
                <p>The engine returns up to five qualifying products. If fewer than five meet the hard filters, it returns fewer and explains why. It never relaxes the budget, invents a listing, or inserts an unsuitable product to fill the grid. Prices and stock are verification-time snapshots, not a live feed.</p>
            </div>

            <aside class="side-card card">
                <h2>On this page</h2>
                <ul>
                    <li><i data-lucide="workflow"></i> Filtering and scoring pipeline</li>
                    <li><i data-lucide="scale"></i> Mandatory vs preferred specs</li>
                    <li><i data-lucide="table-2"></i> Activity weight matrix</li>
                    <li><i data-lucide="cpu"></i> Hardware heuristics</li>
                    <li><i data-lucide="message-square-text"></i> Explanation rules</li>
                </ul>
                <a class="btn btn-primary btn-block" href="<?= e(url('questionnaire.php')) ?>">Try the questionnaire <i data-lucide="arrow-right"></i></a>
                <a class="btn btn-ghost btn-block" href="<?= e(url('data-sources.php')) ?>">See the data sources</a>
            </aside>
        </div>
    </section>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
