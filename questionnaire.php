<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'Find your PC';
$pageDescription = 'Answer five short questions about your work, workloads, preferred computer type, budget and specifications.';
$currentPage = 'questionnaire';
$bodyClass = 'quiz-page';
$extraCss = ['questionnaire.css'];
$extraJs = ['questionnaire.js'];

$activityRepo = new ActivityRepository();
$productRepo = new ProductRepository();
$activities = $activityRepo->all();
$workloads = $activityRepo->allWorkloads();
$brands = array_values(array_filter(
    $productRepo->brands(),
    static fn (array $brand): bool => (int) $brand['product_count'] > 0
));

$errors = $_SESSION['_errors'] ?? [];
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_errors'], $_SESSION['_old']);

if ($old === [] && isset($_GET['activity'])) {
    $requested = (int) $_GET['activity'];
    foreach ($activities as $activity) {
        if ((int) $activity['id'] === $requested) {
            $old['activity_id'] = (string) $requested;
            break;
        }
    }
}

$oldValue = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $default);
$oldArray = static fn (string $key): array => is_array($old[$key] ?? null) ? array_map('strval', $old[$key]) : [];
$isChecked = static fn (string $key, string $value): bool => $oldValue($key) === $value;
$isAmong = static fn (string $key, string $value): bool => in_array($value, $oldArray($key), true);
$errorFor = static fn (string $key): ?string => $errors[$key] ?? null;

$errorStepMap = [
    'activity_id' => 1,
    'workloads' => 2,
    'category' => 3,
    'budget_range' => 4,
    'custom_budget' => 4,
];
$firstErrorStep = 1;
foreach ($errorStepMap as $key => $step) {
    if (isset($errors[$key])) {
        $firstErrorStep = $step;
        break;
    }
}
$budgetRanges = BudgetRanges::all();

$renderImportance = static function (string $name, string $label, string $help, string $selected) use ($oldValue): void {
    $value = $oldValue($name, $selected);
    $options = ['any' => 'No preference', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
    ?>
    <div class="pref-row">
        <div class="pref-label">
            <strong><?= e($label) ?></strong>
            <span><?= e($help) ?></span>
        </div>
        <div class="segmented segmented-small" role="radiogroup" aria-label="<?= e($label) ?>">
            <?php foreach ($options as $option => $optionLabel): ?>
                <label class="segment">
                    <input type="radio" name="<?= e($name) ?>" value="<?= e($option) ?>" <?= $value === $option ? 'checked' : '' ?>>
                    <span><?= e($optionLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
};

require __DIR__ . '/includes/header.php';
?>
<main id="main-content" class="quiz-main-wrap">
    <div class="container">
        <div class="quiz-intro">
            <div>
                <span class="pill"><i data-lucide="sparkles"></i> Personalised in five steps</span>
                <h1>Tell us what you need.</h1>
                <p>This is not a search box. Your answers define the hardware profile, hard limits and scoring weights used to rank the catalogue.</p>
            </div>
            <div class="quiz-intro-note">
                <i data-lucide="clock-3"></i>
                <span><strong>About 3 minutes</strong><br>No account required</span>
            </div>
        </div>

        <div class="quiz-layout">
            <aside class="quiz-sidebar" aria-label="Questionnaire progress">
                <div class="quiz-progress-card">
                    <div class="quiz-progress-head">
                        <span>Your progress</span>
                        <strong data-progress-label>Step 1 of 5</strong>
                    </div>
                    <div class="progress-track" aria-hidden="true">
                        <div class="progress-fill" data-progress-fill style="width:20%"></div>
                    </div>
                    <ol class="quiz-step-list">
                        <li class="is-active" data-step-indicator="1">
                            <span class="step-dot">1</span>
                            <div><strong>Main activity</strong><small>What you do</small></div>
                        </li>
                        <li data-step-indicator="2">
                            <span class="step-dot">2</span>
                            <div><strong>Intended use</strong><small>Software and workloads</small></div>
                        </li>
                        <li data-step-indicator="3">
                            <span class="step-dot">3</span>
                            <div><strong>Device type</strong><small>Laptop or desktop</small></div>
                        </li>
                        <li data-step-indicator="4">
                            <span class="step-dot">4</span>
                            <div><strong>Budget</strong><small>Your maximum in TND</small></div>
                        </li>
                        <li data-step-indicator="5">
                            <span class="step-dot">5</span>
                            <div><strong>Preferences</strong><small>Optional details</small></div>
                        </li>
                    </ol>
                    <div class="quiz-privacy">
                        <i data-lucide="shield"></i>
                        <p>We store the anonymous recommendation session so the result link keeps working. No email or account is required.</p>
                    </div>
                </div>
            </aside>

            <div class="quiz-content">
                <form id="advisor-form" class="quiz-form" method="post" action="<?= e(url('actions/generate_recommendations.php')) ?>" novalidate data-error-step="<?= (int) $firstErrorStep ?>">
                    <?= Security::csrfField() ?>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert-error quiz-alert" role="alert">
                            <i data-lucide="alert-circle"></i>
                            <div>
                                <strong>Please correct the following:</strong>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <section class="quiz-card" data-step="1" aria-labelledby="step-1-title">
                        <div class="step-head">
                            <span class="step-kicker">Step 01</span>
                            <h2 id="step-1-title" tabindex="-1">What is your main activity?</h2>
                            <p>Choose the single option that best reflects how you will use the computer. This sets the baseline requirements.</p>
                        </div>
                        <?php if ($errorFor('activity_id')): ?>
                            <p class="field-error" role="alert"><?= e($errorFor('activity_id')) ?></p>
                        <?php endif; ?>
                        <fieldset class="activity-choice-grid">
                            <legend class="sr-only">Main activity</legend>
                            <?php foreach ($activities as $activity): ?>
                                <label class="choice activity-choice">
                                    <input class="choice-input" type="radio" name="activity_id" value="<?= (int) $activity['id'] ?>" data-activity-name="<?= e($activity['name']) ?>" <?= $oldValue('activity_id') === (string) $activity['id'] ? 'checked' : '' ?>>
                                    <span class="choice-surface">
                                        <span class="choice-icon"><i data-lucide="<?= e($activity['icon'] ?: 'monitor') ?>"></i></span>
                                        <span class="choice-copy">
                                            <strong><?= e($activity['name']) ?></strong>
                                            <small><?= e(truncate_text($activity['description'], 95)) ?></small>
                                        </span>
                                        <span class="choice-check"><i data-lucide="check"></i></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="field-error step-error" data-step-error="1" hidden>Choose one main activity to continue.</p>
                    </section>

                    <section class="quiz-card" data-step="2" aria-labelledby="step-2-title" hidden>
                        <div class="step-head">
                            <span class="step-kicker">Step 02</span>
                            <h2 id="step-2-title" tabindex="-1">What will you use it for?</h2>
                            <p>Select every workload that matters. We use these to weigh your actual software, not just your job title.</p>
                        </div>
                        <?php if ($errorFor('workloads')): ?>
                            <p class="field-error" role="alert"><?= e($errorFor('workloads')) ?></p>
                        <?php endif; ?>
                        <fieldset class="workload-grid">
                            <legend class="sr-only">Intended workloads</legend>
                            <?php foreach ($workloads as $workload): ?>
                                <label class="choice workload-choice">
                                    <input class="choice-input" type="checkbox" name="workloads[]" value="<?= (int) $workload['id'] ?>" <?= $isAmong('workloads', (string) $workload['id']) ? 'checked' : '' ?>>
                                    <span class="choice-surface">
                                        <span class="choice-check"><i data-lucide="check"></i></span>
                                        <span class="choice-copy">
                                            <strong><?= e($workload['name']) ?></strong>
                                            <small><?= e(truncate_text($workload['description'], 72)) ?></small>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="field-error step-error" data-step-error="2" hidden>Select at least one intended use to continue.</p>
                    </section>

                    <section class="quiz-card" data-step="3" aria-labelledby="step-3-title" hidden>
                        <div class="step-head">
                            <span class="step-kicker">Step 03</span>
                            <h2 id="step-3-title" tabindex="-1">What type of computer do you prefer?</h2>
                            <p>We only compare available configurations within the category you choose. Laptops and desktops are never treated as identical products.</p>
                        </div>
                        <?php if ($errorFor('category')): ?>
                            <p class="field-error" role="alert"><?= e($errorFor('category')) ?></p>
                        <?php endif; ?>
                        <fieldset class="category-grid">
                            <legend class="sr-only">Computer type</legend>
                            <label class="choice category-choice">
                                <input class="choice-input" type="radio" name="category" value="laptop" data-category-name="Laptop" <?= $isChecked('category', 'laptop') ? 'checked' : '' ?>>
                                <span class="choice-surface">
                                    <span class="choice-icon"><i data-lucide="laptop"></i></span>
                                    <strong>Laptop</strong>
                                    <small>Prioritise portability, battery information and screen quality where the listing provides it.</small>
                                    <span class="choice-check"><i data-lucide="check"></i></span>
                                </span>
                            </label>
                            <label class="choice category-choice">
                                <input class="choice-input" type="radio" name="category" value="desktop" data-category-name="Desktop PC" <?= $isChecked('category', 'desktop') ? 'checked' : '' ?>>
                                <span class="choice-surface">
                                    <span class="choice-icon"><i data-lucide="pc-case"></i></span>
                                    <strong>Desktop PC</strong>
                                    <small>Prioritise processor and graphics headroom, cooling and upgradeability where documented.</small>
                                    <span class="choice-check"><i data-lucide="check"></i></span>
                                </span>
                            </label>
                            <label class="choice category-choice">
                                <input class="choice-input" type="radio" name="category" value="both" data-category-name="Laptop or desktop" <?= $isChecked('category', 'both') ? 'checked' : '' ?>>
                                <span class="choice-surface">
                                    <span class="choice-icon"><i data-lucide="shuffle"></i></span>
                                    <strong>Both / No preference</strong>
                                    <small>Compare the best available laptop and desktop configurations in one ranking.</small>
                                    <span class="choice-check"><i data-lucide="check"></i></span>
                                </span>
                            </label>
                        </fieldset>
                        <p class="field-error step-error" data-step-error="3" hidden>Choose a computer type to continue.</p>
                    </section>

                    <section class="quiz-card" data-step="4" aria-labelledby="step-4-title" hidden>
                        <div class="step-head">
                            <span class="step-kicker">Step 04</span>
                            <h2 id="step-4-title" tabindex="-1">What is your maximum budget?</h2>
                            <p>Prices are in Tunisian dinars. Products above your maximum are excluded before scoring, so you will never receive a silent over-budget recommendation.</p>
                        </div>
                        <?php if ($errorFor('budget_range') || $errorFor('custom_budget')): ?>
                            <p class="field-error" role="alert"><?= e($errorFor('budget_range') ?: $errorFor('custom_budget')) ?></p>
                        <?php endif; ?>
                        <fieldset class="budget-grid">
                            <legend class="sr-only">Budget range</legend>
                            <?php foreach ($budgetRanges as $key => $range): ?>
                                <label class="choice budget-choice">
                                    <input class="choice-input" type="radio" name="budget_range" value="<?= e($key) ?>" data-budget-label="<?= e($range['label']) ?>" <?= $isChecked('budget_range', $key) ? 'checked' : '' ?>>
                                    <span class="choice-surface">
                                        <strong><?= e($range['label']) ?></strong>
                                        <small><?= $key === 'over_7000' ? 'Premium and workstation class' : 'Maximum ' . e(format_price($range['max'])) ?></small>
                                        <span class="choice-check"><i data-lucide="check"></i></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>

                        <div class="custom-budget-panel">
                            <div class="custom-budget-copy">
                                <span class="custom-icon"><i data-lucide="wallet"></i></span>
                                <div>
                                    <strong>Or set an exact maximum</strong>
                                    <small>A custom maximum overrides the selected range.</small>
                                </div>
                            </div>
                            <label class="budget-input-wrap">
                                <span class="sr-only">Custom maximum budget in TND</span>
                                <input type="number" inputmode="decimal" name="custom_budget" id="custom-budget" min="300" max="20000" step="1" placeholder="e.g. 2800" value="<?= e($oldValue('custom_budget')) ?>">
                                <span>TND</span>
                            </label>
                        </div>
                        <p class="field-error step-error" data-step-error="4" hidden>Choose a budget range or enter an exact maximum budget.</p>
                        <p class="field-hint"><i data-lucide="info"></i> Budget ranges are not a recommendation. The engine scores value within the ceiling you set.</p>
                    </section>

                    <section class="quiz-card" data-step="5" aria-labelledby="step-5-title" hidden>
                        <div class="step-head">
                            <span class="step-kicker">Step 05</span>
                            <h2 id="step-5-title" tabindex="-1">Any additional preferences?</h2>
                            <p>All optional. Leave anything you are unsure about on “No preference” and the engine will focus on the hardware profile instead.</p>
                        </div>

                        <div class="preference-group">
                            <h3>Minimum specifications</h3>
                            <div class="pref-row">
                                <div class="pref-label"><strong>Minimum RAM</strong><span>Only products with at least this much memory.</span></div>
                                <div class="segmented" role="radiogroup" aria-label="Minimum RAM">
                                    <?php foreach ([0 => 'No preference', 8 => '8 GB', 16 => '16 GB', 32 => '32 GB', 64 => '64 GB'] as $value => $label): ?>
                                        <label class="segment"><input type="radio" name="min_ram" value="<?= (int) $value ?>" <?= $oldValue('min_ram', '0') === (string) $value ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="pref-row">
                                <div class="pref-label"><strong>Minimum storage</strong><span>SSD or other storage capacity.</span></div>
                                <div class="segmented" role="radiogroup" aria-label="Minimum storage">
                                    <?php foreach ([0 => 'No preference', 256 => '256 GB', 512 => '512 GB', 1024 => '1 TB', 2048 => '2 TB'] as $value => $label): ?>
                                        <label class="segment"><input type="radio" name="min_storage" value="<?= (int) $value ?>" <?= $oldValue('min_storage', '0') === (string) $value ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="pref-row">
                                <div class="pref-label"><strong>Dedicated GPU</strong><span>“Required” removes integrated-graphics listings.</span></div>
                                <div class="segmented" role="radiogroup" aria-label="Dedicated GPU requirement">
                                    <?php foreach (['any' => 'No preference', 'no' => 'Not required', 'yes' => 'Required'] as $value => $label): ?>
                                        <label class="segment"><input type="radio" name="dedicated_gpu" value="<?= e($value) ?>" <?= $oldValue('dedicated_gpu', 'any') === $value ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="preference-group">
                            <h3>Preferred brands</h3>
                            <p class="pref-help">Optional. This affects scoring only; it does not hide other brands.</p>
                            <div class="brand-choice-grid">
                                <?php foreach ($brands as $brand): ?>
                                    <label class="choice brand-choice">
                                        <input class="choice-input" type="checkbox" name="brand_ids[]" value="<?= (int) $brand['id'] ?>" <?= $isAmong('brand_ids', (string) $brand['id']) ? 'checked' : '' ?>>
                                        <span class="choice-surface">
                                            <span class="brand-check"><i data-lucide="check"></i></span>
                                            <span><?= e($brand['name']) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="preference-group">
                            <h3>What should the engine prioritise?</h3>
                            <?php
                            $renderImportance('portability', 'Portability', 'Weight and travel-friendliness.', 'any');
                            $renderImportance('performance', 'Performance', 'Raw CPU, RAM, GPU and storage capability.', 'any');
                            $renderImportance('battery', 'Battery', 'Only uses battery data the listing actually provides.', 'any');
                            ?>
                        </div>

                        <div class="quiz-summary" aria-live="polite">
                            <div class="summary-head">
                                <span><i data-lucide="clipboard-check"></i> Your brief</span>
                                <span class="badge badge-accent">Review before submitting</span>
                            </div>
                            <dl class="summary-grid">
                                <div><dt>Activity</dt><dd data-summary-activity>Not selected</dd></div>
                                <div><dt>Intended use</dt><dd data-summary-workloads>0 selected</dd></div>
                                <div><dt>Device</dt><dd data-summary-category>Not selected</dd></div>
                                <div><dt>Budget</dt><dd data-summary-budget>Not selected</dd></div>
                            </dl>
                        </div>
                    </section>

                    <div class="quiz-nav">
                        <button class="btn btn-secondary" type="button" data-prev hidden>
                            <i data-lucide="arrow-left"></i> Back
                        </button>
                        <div class="quiz-nav-right">
                            <span class="quiz-nav-hint" data-nav-hint>Step 1 of 5</span>
                            <button class="btn btn-primary" type="button" data-next>
                                Continue <i data-lucide="arrow-right"></i>
                            </button>
                            <button class="btn btn-primary btn-lg" type="submit" data-submit hidden>
                                <i data-lucide="scan-search"></i> Find My 5 Best PCs
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
