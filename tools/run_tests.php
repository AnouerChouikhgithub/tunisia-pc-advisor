<?php
declare(strict_types=1);

/**
 * Automated database, engine, explanation, validation and security checks.
 * Usage: php tools/run_tests.php
 * Exit code 0 means every check passed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$passed = 0;
$failed = 0;
$failures = [];
$sections = [];

function section(string $name): void
{
    global $sections;
    $sections[] = $name;
    echo "\n== {$name} ==\n";
}

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $failures;
    if ($condition) {
        $passed++;
        echo "  PASS  {$name}\n";
    } else {
        $failed++;
        $failures[] = $name . ($detail !== '' ? ' — ' . $detail : '');
        echo "  FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function scenarioRequest(int $activityId, array $workloadIds, float $budget, array $options = []): RecommendationRequest
{
    return new RecommendationRequest(
        activityId: $activityId,
        workloadIds: $workloadIds,
        category: $options['category'] ?? 'both',
        budgetMin: 0.0,
        budgetMax: $budget,
        budgetLabel: 'Up to ' . format_price($budget),
        minRamGb: $options['min_ram'] ?? 0,
        minStorageGb: $options['min_storage'] ?? 0,
        dedicatedGpu: $options['dedicated_gpu'] ?? 'any',
        preferredBrandIds: $options['brand_ids'] ?? [],
        portability: $options['portability'] ?? 'any',
        performance: $options['performance'] ?? 'any',
        battery: $options['battery'] ?? 'any'
    );
}

$pdo = Database::connection();
$activities = new ActivityRepository();
$products = new ProductRepository();
$engine = new RecommendationEngine($activities, $products, new RecommendationExplanationGenerator());

section('Database schema and integrity');
$expectedTables = [
    'activities', 'activity_requirements', 'activity_workloads', 'admin_users',
    'brands', 'product_price_history', 'products', 'recommendation_results',
    'recommendation_sessions', 'retailers', 'workloads',
];
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
check('All required tables exist', count(array_diff($expectedTables, $tables)) === 0, implode(', ', array_diff($expectedTables, $tables)));
$fkCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()"
)->fetchColumn();
check('Foreign keys are defined', $fkCount >= 8, "found {$fkCount}");
$stats = $products->statistics();
check('Catalogue has at least 150 distinct records', $stats['total'] >= 150, "found {$stats['total']}");
check('At least 120 laptop records', $stats['laptops'] >= 120, "found {$stats['laptops']}");
check('At least 30 desktop records', $stats['desktops'] >= 30, "found {$stats['desktops']}");
check('All five retailers are present', $stats['retailers'] === 5, "found {$stats['retailers']}");
check('No duplicate direct product URLs', count($products->dataQualityChecks()['duplicateUrls']) === 0);
check('No duplicate retailer SKU groups', count($products->dataQualityChecks()['duplicateSkus']) === 0);
check('No missing critical fields', count($products->dataQualityChecks()['missing']) === 0);
check('All product URLs use HTTP(S)', count($products->dataQualityChecks()['invalidUrls']) === 0);
$hashMismatch = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE product_url_hash <> SHA2(product_url, 256)')->fetchColumn();
check('Stored URL hashes match product URLs', $hashMismatch === 0, "{$hashMismatch} mismatches");
$orphanProducts = (int) $pdo->query(
    'SELECT COUNT(*) FROM products p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN retailers r ON r.id=p.retailer_id WHERE b.id IS NULL OR r.id IS NULL'
)->fetchColumn();
check('Products reference valid brands and retailers', $orphanProducts === 0, "{$orphanProducts} orphans");
$historyCoverage = (int) $pdo->query(
    'SELECT COUNT(*) FROM products p LEFT JOIN product_price_history h ON h.product_id=p.id WHERE h.id IS NULL'
)->fetchColumn();
check('Every product has an initial price history row', $historyCoverage === 0, "{$historyCoverage} missing");
$negativePrices = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE price_tnd <= 0')->fetchColumn();
check('All prices are positive', $negativePrices === 0, "{$negativePrices} invalid");
$badCategories = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE product_category NOT IN ('laptop','desktop')")->fetchColumn();
check('All categories are laptop/desktop', $badCategories === 0);
$duplicateConfigs = (int) $pdo->query(
    "SELECT COUNT(*) FROM (
        SELECT retailer_id, brand_id, model, processor_model, ram_gb, storage_gb, COALESCE(gpu_model,''), COALESCE(screen_size,0), COUNT(*) n
        FROM products GROUP BY retailer_id, brand_id, model, processor_model, ram_gb, storage_gb, COALESCE(gpu_model,''), COALESCE(screen_size,0)
        HAVING n > 1
    ) dup"
)->fetchColumn();
check('No repeated configuration inside one retailer', $duplicateConfigs === 0, "{$duplicateConfigs} groups");
$priced = $products->find((int) $pdo->query('SELECT id FROM products ORDER BY price_tnd ASC LIMIT 1')->fetchColumn());
check('Single product retrieval works', $priced !== null && isset($priced['brand_name'], $priced['retailer_name']));
$missingRequirements = (int) $pdo->query(
    'SELECT COUNT(*) FROM activities a LEFT JOIN activity_requirements r ON r.activity_id = a.id WHERE r.id IS NULL'
)->fetchColumn();
check('Every activity has requirements', $missingRequirements === 0, "{$missingRequirements} missing");
$badWeights = (int) $pdo->query(
    'SELECT COUNT(*) FROM activity_requirements WHERE cpu_weight + ram_weight + gpu_weight + storage_weight + budget_weight + activity_weight + preferences_weight <> 100'
)->fetchColumn();
check('Every activity weight set totals 100', $badWeights === 0, "{$badWeights} invalid");
$unmappedActivities = (int) $pdo->query(
    'SELECT COUNT(*) FROM activities a LEFT JOIN activity_workloads aw ON aw.activity_id = a.id WHERE aw.activity_id IS NULL'
)->fetchColumn();
check('Every activity has mapped workloads', $unmappedActivities === 0, "{$unmappedActivities} unmapped");
$allProducts = $pdo->query('SELECT product_url, retailer_id, image_url FROM products')->fetchAll();
$hostMismatch = 0;
$badImage = 0;
foreach ($allProducts as $row) {
    $retailer = $pdo->prepare('SELECT website_url FROM retailers WHERE id = ?');
    $retailer->execute([(int) $row['retailer_id']]);
    $website = (string) $retailer->fetchColumn();
    $normalize = static fn (string $host): string => preg_replace('/^www\./', '', mb_strtolower($host)) ?? $host;
    if ($normalize((string) parse_url($row['product_url'], PHP_URL_HOST)) !== $normalize((string) parse_url($website, PHP_URL_HOST))) {
        $hostMismatch++;
    }
    if ($row['image_url'] !== null && !preg_match('#^https?://#i', (string) $row['image_url'])) {
        $badImage++;
    }
}
check('Every product URL uses its retailer domain', $hostMismatch === 0, "{$hostMismatch} mismatches");
check('Every stored image URL is HTTP(S)', $badImage === 0, "{$badImage} invalid");
$orphanResults = (int) $pdo->query('SELECT COUNT(*) FROM recommendation_results rr LEFT JOIN recommendation_sessions s ON s.id=rr.session_id LEFT JOIN products p ON p.id=rr.product_id WHERE s.id IS NULL OR p.id IS NULL')->fetchColumn();
$orphanHistory = (int) $pdo->query('SELECT COUNT(*) FROM product_price_history h LEFT JOIN products p ON p.id=h.product_id WHERE p.id IS NULL')->fetchColumn();
check('Recommendation results have valid sessions and products', $orphanResults === 0, "{$orphanResults} orphans");
check('Price history rows reference valid products', $orphanHistory === 0, "{$orphanHistory} orphans");

section('Price history');
$historyProduct = $pdo->query('SELECT id, price_tnd, stock_status FROM products ORDER BY id ASC LIMIT 1')->fetch();
$historyRepo = new PriceHistoryRepository();
$historyCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM product_price_history WHERE product_id = ' . (int) $historyProduct['id'])->fetchColumn();
$changed = $historyRepo->recordChange((int) $historyProduct['id'], (float) $historyProduct['price_tnd'], (string) $historyProduct['stock_status'], (float) $historyProduct['price_tnd'], (string) $historyProduct['stock_status']);
$historyCountSame = (int) $pdo->query('SELECT COUNT(*) FROM product_price_history WHERE product_id = ' . (int) $historyProduct['id'])->fetchColumn();
check('Unchanged price/stock does not append history', $changed === false && $historyCountSame === $historyCountBefore);
$changed = $historyRepo->recordChange((int) $historyProduct['id'], (float) $historyProduct['price_tnd'] + 1, (string) $historyProduct['stock_status'], (float) $historyProduct['price_tnd'], (string) $historyProduct['stock_status']);
$historyCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM product_price_history WHERE product_id = ' . (int) $historyProduct['id'])->fetchColumn();
check('Changed price appends a new history row', $changed === true && $historyCountAfter === $historyCountBefore + 1);
$pdo->exec('DELETE FROM product_price_history WHERE product_id = ' . (int) $historyProduct['id'] . ' ORDER BY id DESC LIMIT 1');
$historyRestored = (int) $pdo->query('SELECT COUNT(*) FROM product_price_history WHERE product_id = ' . (int) $historyProduct['id'])->fetchColumn();
check('Temporary history test row cleaned up', $historyRestored === $historyCountBefore);

section('Candidate filtering');
$all = scenarioRequest(1, [1], 20000.0);
$candidates = $products->candidatesFor($all);
$purchasable = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_status IN ('in_stock','limited')")->fetchColumn();
check('Candidates exclude out-of-stock products', count($candidates) === $purchasable, count($candidates) . " vs {$purchasable}");
check('No candidate exceeds the budget ceiling', count(array_filter($candidates, static fn (array $p): bool => (float) $p['price_tnd'] > 20000.0)) === 0);
$laptopOnly = scenarioRequest(1, [1], 2500.0, ['category' => 'laptop', 'min_ram' => 16, 'min_storage' => 512]);
$laptopCandidates = $products->candidatesFor($laptopOnly);
check('Category, RAM and storage filters are applied', count(array_filter($laptopCandidates, static fn (array $p): bool => $p['product_category'] === 'laptop' && (int) $p['ram_gb'] >= 16 && (int) $p['storage_gb'] >= 512)) === count($laptopCandidates));
$gpuOnly = scenarioRequest(16, [14], 6000.0, ['dedicated_gpu' => 'yes']);
$gpuCandidates = $products->candidatesFor($gpuOnly);
check('Dedicated-GPU filter is applied', $gpuCandidates !== [] && count(array_filter($gpuCandidates, static fn (array $p): bool => $p['gpu_type'] === 'dedicated')) === count($gpuCandidates));
$ordered = $products->findManyByIds(array_slice(array_map(static fn (array $p): int => (int) $p['id'], $candidates), 0, 3));
check('Batch retrieval preserves requested order', array_map(static fn (array $p): int => (int) $p['id'], $ordered) === array_slice(array_map(static fn (array $p): int => (int) $p['id'], $candidates), 0, 3));

section('Questionnaire validation');
$validator = new QuestionnaireValidator($activities, $products);
$baseInput = [
    'activity_id' => 2,
    'workloads' => [1, 5, 6],
    'category' => 'both',
    'budget_range' => '2000_2500',
    'custom_budget' => '',
    'min_ram' => '16',
    'min_storage' => '512',
    'dedicated_gpu' => 'any',
    'brand_ids' => [],
    'portability' => 'medium',
    'performance' => 'high',
    'battery' => 'any',
];
$valid = $validator->validate($baseInput);
check('Valid questionnaire input produces a request', $valid['request'] instanceof RecommendationRequest && $valid['errors'] === []);
check('Budget range maps to correct ceiling', $valid['request'] && abs($valid['request']->budgetMax - 2500.0) < 0.001);
$custom = $validator->validate(array_merge($baseInput, ['budget_range' => '', 'custom_budget' => '2750.500']));
check('Custom budget is accepted', $custom['request'] instanceof RecommendationRequest && abs($custom['request']->budgetMax - 2750.5) < 0.001);
$invalid = $validator->validate(['activity_id' => '999', 'workloads' => ['abc'], 'category' => 'server', 'custom_budget' => '-5']);
check('Invalid activity, workloads, category and budget are rejected', count($invalid['errors']) >= 4, implode(', ', array_keys($invalid['errors'])));
$tooHigh = $validator->validate(array_merge($baseInput, ['budget_range' => '', 'custom_budget' => '99999']));
check('Budget above 20,000 TND is rejected', isset($tooHigh['errors']['custom_budget']));
$badWorkload = $validator->validate(array_merge($baseInput, ['workloads' => [9999]]));
check('Unknown workload ID is rejected', isset($badWorkload['errors']['workloads']));

section('Recommendation scenarios');
$scenarios = [
    ['Student, 1,200 TND', 'student', [7, 8, 18], 1200.0, ['category' => 'both', 'portability' => 'high']],
    ['Software developer, 2,500 TND', 'software-developer', [1, 5, 6], 2500.0, ['min_ram' => 16]],
    ['Graphic designer, 3,500 TND', 'graphic-designer', [9, 10, 18], 3500.0, ['category' => 'both', 'performance' => 'high']],
    ['Gamer, 4,000 TND', 'gamer', [14, 15], 4000.0, ['dedicated_gpu' => 'yes', 'performance' => 'high']],
    ['Video editor, 5,000 TND', 'video-editor', [11, 15, 18], 5000.0, ['performance' => 'high']],
    ['AI developer, 6,000 TND', 'ai-ml', [17, 16, 6, 1], 6000.0, ['min_ram' => 16, 'performance' => 'high']],
    ['Office worker, 1,500 TND', 'office-worker', [7, 8, 18], 1500.0, ['portability' => 'medium']],
    ['Architect, 4,500 TND', 'architect', [13, 12, 9], 4500.0, ['performance' => 'high']],
];
$scenarioSummaries = [];
foreach ($scenarios as [$label, $slug, $workloadIds, $budget, $options]) {
    $activity = $activities->findBySlug($slug);
    if (!$activity) {
        check("{$label}: activity exists", false, $slug);
        continue;
    }
    $request = scenarioRequest((int) $activity['id'], $workloadIds, $budget, $options);
    try {
        $outcome = $engine->recommend($request);
    } catch (Throwable $exception) {
        check("{$label}: engine runs", false, $exception->getMessage());
        continue;
    }
    $results = $outcome['results'];
    $meta = $outcome['meta'];
    echo '  · ' . count($results) . ' results; top: ' . ($results[0]['product_name'] ?? 'none') . ' @ ' . ($results[0]['compatibility_score'] ?? 0) . "%\n";
    $scenarioSummaries[] = [
        'label' => $label,
        'count' => count($results),
        'candidate_count' => $meta['candidate_count'],
        'top' => $results[0]['product_name'] ?? null,
        'top_score' => $results[0]['compatibility_score'] ?? null,
        'top_price' => $results[0]['price_tnd'] ?? null,
        'budget' => $budget,
    ];
    check("{$label}: returns at most five", count($results) <= 5);
    check("{$label}: returns at least one when qualifying stock exists", count($results) >= 1, "candidate count {$meta['candidate_count']}");
    check("{$label}: no product exceeds the budget", count(array_filter($results, static fn (array $p): bool => (float) $p['price_tnd'] > $budget + 0.001)) === 0);
    check("{$label}: no out-of-stock product recommended", count(array_filter($results, static fn (array $p): bool => !in_array($p['stock_status'], ['in_stock', 'limited'], true))) === 0);
    check("{$label}: products are active", count(array_filter($results, static fn (array $p): bool => (int) $p['is_active'] !== 1)) === 0);
    $ids = array_map(static fn (array $p): int => (int) $p['id'], $results);
    check("{$label}: no duplicate products", count($ids) === count(array_unique($ids)));
    $scores = array_map(static fn (array $p): float => (float) $p['compatibility_score'], $results);
    $sorted = $scores;
    rsort($sorted);
    check("{$label}: results are ranked by score", $scores === $sorted, implode(', ', $scores));
    $scoreValid = true;
    $breakdownValid = true;
    foreach ($results as $product) {
        if ($product['compatibility_score'] < 0 || $product['compatibility_score'] > 100) {
            $scoreValid = false;
        }
        $points = array_sum(array_column($product['score_breakdown'], 'points'));
        if (abs($points - $product['compatibility_score']) > 0.2) {
            $breakdownValid = false;
        }
    }
    check("{$label}: scores are within 0–100", $scoreValid);
    check("{$label}: score breakdown adds up", $breakdownValid);
    $weights = $meta['weights'];
    check("{$label}: scoring weights total 100", abs(array_sum($weights) - 100) < 0.2, (string) array_sum($weights));
    if (!empty($options['category']) && $options['category'] !== 'both') {
        check("{$label}: category filter respected", count(array_filter($results, static fn (array $p): bool => $p['product_category'] !== $options['category'])) === 0);
    }
    if (!empty($options['min_ram'])) {
        check("{$label}: minimum RAM respected", count(array_filter($results, static fn (array $p): bool => (int) $p['ram_gb'] < $options['min_ram'])) === 0);
    }
    if (($options['dedicated_gpu'] ?? 'any') === 'yes') {
        check("{$label}: dedicated GPU respected", count(array_filter($results, static fn (array $p): bool => $p['gpu_type'] !== 'dedicated')) === 0);
    }
    $linksValid = true;
    $hostsValid = true;
    foreach ($results as $product) {
        if (!preg_match('#^https://#', (string) $product['product_url'])) {
            $linksValid = false;
        }
        $host = parse_url((string) $product['product_url'], PHP_URL_HOST) ?: '';
        $retailerHost = parse_url((string) $product['retailer_website'], PHP_URL_HOST) ?: '';
        $normalize = static fn (string $h): string => preg_replace('/^www\./', '', mb_strtolower($h)) ?? $h;
        if ($normalize($host) !== $normalize($retailerHost)) {
            $hostsValid = false;
        }
    }
    check("{$label}: purchase links are HTTPS", $linksValid);
    check("{$label}: purchase links use the retailer domain", $hostsValid);
    $explanationsValid = true;
    $explanationsFactual = true;
    foreach ($results as $product) {
        $text = (string) $product['explanation'];
        if (strlen($text) < 120 || preg_match('/\b(null|undefined|Array|N\/A)\b/i', $text)) {
            $explanationsValid = false;
        }
        if (!str_contains($text, (string) $product['processor_model'])) {
            $explanationsFactual = false;
        }
        $ramNeedle = (int) $product['ram_gb'] . ' GB';
        if (!str_contains($text, $ramNeedle)) {
            $explanationsFactual = false;
        }
        $storageNeedle = number_format((int) $product['storage_gb']) . ' GB';
        if (!str_contains($text, $storageNeedle)) {
            $explanationsFactual = false;
        }
        if ($product['gpu_type'] === 'dedicated' && !str_contains($text, 'dedicated')) {
            $explanationsFactual = false;
        }
    }
    check("{$label}: explanations are substantive", $explanationsValid);
    check("{$label}: explanations quote stored CPU, RAM, storage and GPU facts", $explanationsFactual);
    if (count($results) < 5) {
        check("{$label}: fewer-than-five case explains the shortfall", $meta['notes'] !== [] && str_contains(implode(' ', $meta['notes']), 'Only'));
    }
}

section('Hard filter edge cases');
$emptyRequest = scenarioRequest(14, [7], 300.0);
$emptyOutcome = $engine->recommend($emptyRequest);
check('No result is invented below the cheapest listing', $emptyOutcome['results'] === []);
check('Empty result includes a factual explanation', !empty($emptyOutcome['meta']['notes']));
$laptopRequest = scenarioRequest(1, [7], 2000.0, ['category' => 'laptop']);
$laptopOutcome = $engine->recommend($laptopRequest);
check('Laptop-only scenario returns only laptops', count(array_filter($laptopOutcome['results'], static fn (array $p): bool => $p['product_category'] !== 'laptop')) === 0);
$desktopRequest = scenarioRequest(10, [13], 8000.0, ['category' => 'desktop']);
$desktopOutcome = $engine->recommend($desktopRequest);
check('Desktop-only scenario returns only desktops when stock allows', count(array_filter($desktopOutcome['results'], static fn (array $p): bool => $p['product_category'] !== 'desktop')) === 0);

section('Security checks');
$injection = $products->search(['q' => "' OR 1=1 --"], 1, 20);
check('SQL injection payload is treated as text', $injection['total'] === 0, "total {$injection['total']}");
check('Output escaping encodes script tags', e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
$token = Security::csrfToken();
check('CSRF token can be generated', strlen($token) === 64);
check('CSRF token validates', Security::verifyCsrf($token));
check('Wrong CSRF token is rejected', !Security::verifyCsrf(str_repeat('a', 64)));
check('Missing CSRF token is rejected', !Security::verifyCsrf(null));
$hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
check('Password hashing and verification work', password_verify('correct horse battery staple', $hash) && !password_verify('wrong', $hash));
check('Unauthenticated admin check is false', Auth::check() === false);
check('Admin table stores hashes, not plaintext', $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'password_hash'")->fetch() !== false);
$cookies = session_get_cookie_params();
check('Session cookie is HttpOnly and SameSite=Lax', $cookies['httponly'] === true && $cookies['samesite'] === 'Lax');

section('Result summary');
echo "\n{$passed} passed, {$failed} failed.\n";
foreach ($scenarioSummaries as $summary) {
    echo sprintf(
        "%-34s %d results / %d candidates; top %s (%s%%) at %s\n",
        $summary['label'],
        $summary['count'],
        $summary['candidate_count'],
        $summary['top'] ?? 'none',
        $summary['top_score'] ?? '0',
        $summary['top_price'] !== null ? format_price($summary['top_price']) : 'n/a'
    );
}
if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
exit(0);
