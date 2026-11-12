<?php
declare(strict_types=1);

/**
 * Build database/seed.sql from the verified research snapshot and the static
 * reference data in database/seed_base.sql.
 *
 * Usage: php tools/build_seed.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$research = APP_ROOT . '/database/research';
$primaryFile = $research . '/products.json';
$manualFile = $research . '/manual_products_verified.json';
$manualFallback = $research . '/manual_products.json';
$output = APP_ROOT . '/database/seed.sql';
$summaryPath = $research . '/dataset_summary.json';

$primary = json_decode((string) file_get_contents($primaryFile), true);
if (!is_array($primary) || empty($primary['products'])) {
    fwrite(STDERR, "No primary products found.\n");
    exit(1);
}

$manualSource = is_file($manualFile) ? $manualFile : $manualFallback;
$manual = json_decode((string) file_get_contents($manualSource), true);
$manualProducts = is_array($manual) ? ($manual['products'] ?? []) : [];

$products = array_merge($primary['products'], $manualProducts);

$brandNames = [
    1 => 'ASUS', 2 => 'HP', 3 => 'Lenovo', 4 => 'Dell', 5 => 'Acer',
    6 => 'MSI', 7 => 'Apple', 8 => 'Gigabyte', 9 => 'Samsung', 10 => 'Other',
];
$brandIdByName = [];
foreach ($brandNames as $id => $name) {
    $brandIdByName[mb_strtolower($name)] = $id;
}

$retailerNames = [
    'tunisia-net' => 1,
    'mytek' => 2,
    'spacenet' => 3,
    'sbs-informatique' => 4,
    'wiki' => 5,
];

$clean = [];
$seenUrls = [];
$seenConfigs = [];
foreach ($products as $product) {
    $url = trim((string) ($product['product_url'] ?? ''));
    if ($url === '' || isset($seenUrls[$url])) {
        continue;
    }
    if (empty($product['product_name']) || empty($product['processor_model']) || empty($product['price_tnd'])) {
        continue;
    }
    $seenUrls[$url] = true;

    $retailerSlug = (string) ($product['retailer_slug'] ?? '');
    if ($retailerSlug === '' && ($product['retailer'] ?? '') === 'Tunisia Net') {
        $retailerSlug = 'tunisia-net';
    }
    if (!isset($retailerNames[$retailerSlug])) {
        continue;
    }
    $product['_retailer_slug'] = $retailerSlug;
    $product['_retailer_id'] = $retailerNames[$retailerSlug];

    $brand = trim((string) ($product['brand'] ?? 'Other'));
    if ($brand === '') {
        $brand = 'Other';
    }
    $key = mb_strtolower($brand);
    if (!isset($brandIdByName[$key])) {
        $nextId = max(array_keys($brandNames)) + 1;
        $brandNames[$nextId] = $brand;
        $brandIdByName[$key] = $nextId;
    }
    $product['_brand_id'] = $brandIdByName[$key];
    $product['_brand_name'] = $brand;

    $signature = mb_strtolower(implode('|', [
        $brand,
        (string) $product['model'],
        (string) $product['processor_model'],
        (string) $product['ram_gb'],
        (string) $product['storage_gb'],
        (string) ($product['gpu_model'] ?? ''),
        (string) ($product['screen_size'] ?? ''),
        $retailerSlug,
    ]));
    if (isset($seenConfigs[$signature])) {
        continue;
    }
    $seenConfigs[$signature] = true;
    $clean[] = $product;
}

usort($clean, static function (array $a, array $b): int {
    return [$a['product_category'], (float) $a['price_tnd'], $a['product_name']]
        <=> [$b['product_category'], (float) $b['price_tnd'], $b['product_name']];
});

$q = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return 'NULL';
    }
    return "'" . strtr((string) $value, [
        '\\' => '\\\\',
        "\0" => '\\0',
        "\n" => '\\n',
        "\r" => '\\r',
        "'" => "\\'",
        '"' => '\\"',
        "\x1a" => '\\Z',
    ]) . "'";
};
$num = static function (mixed $value, int $decimals = 0): string {
    if ($value === null || $value === '') {
        return 'NULL';
    }
    return number_format((float) $value, $decimals, '.', '');
};
$modelText = static function (mixed $value): ?string {
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    // Retailer pages often append clock speed, cache or VRAM after a comma.
    // Keep the model portion; the structured fields carry the additional facts.
    $parts = explode(',', (string) $value, 2);
    $model = trim($parts[0]);
    return $model === '' ? null : $model;
};
$dt = static function (?string $value): string {
    if (!$value) {
        return 'NULL';
    }
    $ts = strtotime($value);
    return $ts ? "'" . date('Y-m-d H:i:s', $ts) . "'" : 'NULL';
};

$sql = "-- Tunisia PC Advisor seed data\n";
$sql .= "-- Generated " . date('c') . "\n";
$sql .= "-- Primary source snapshot: " . $primary['generated_at'] . "\n";
$sql .= "-- Multi-retailer records: " . ($manual['generated_at'] ?? 'unknown') . "\n";
$sql .= "-- Import database/schema.sql first, then this file.\n\n";
$sql .= file_get_contents(APP_ROOT . '/database/seed_base.sql');
$sql .= "\n\n-- Additional brands referenced by the verified dataset.\n";
$additionalBrands = [];
foreach ($brandNames as $id => $name) {
    if ($id > 10) {
        $additionalBrands[] = '(' . $id . ', ' . $q($name) . ')';
    }
}
$sql .= $additionalBrands
    ? 'INSERT INTO brands (id, name) VALUES ' . implode(', ', $additionalBrands) . ";\n"
    : "-- None.\n";

$sql .= "\n-- Products (" . count($clean) . " distinct records)\n";
$columns = [
    'id', 'brand_id', 'retailer_id', 'model', 'product_name', 'product_category',
    'processor_brand', 'processor_model', 'processor_generation', 'processor_cores',
    'ram_gb', 'ram_type', 'ram_upgradeable', 'storage_type', 'storage_gb',
    'gpu_brand', 'gpu_model', 'gpu_type', 'gpu_vram_gb',
    'screen_size', 'screen_resolution', 'screen_panel', 'refresh_rate',
    'operating_system', 'weight_kg', 'battery', 'price_tnd',
    'retailer_sku', 'product_url', 'product_url_hash', 'image_url',
    'stock_status', 'warranty_information', 'source_url',
    'link_status', 'link_checked_at', 'verified_at', 'raw_specs_json', 'is_active',
    'created_at', 'updated_at',
];
$histories = [];

$productId = 0;
foreach ($clean as $product) {
    $productId++;
    $verifiedAt = $dt($product['verified_at'] ?? null);
    $httpStatus = isset($product['http_status']) ? (int) $product['http_status'] : null;
    $linkStatus = 'unchecked';
    $linkCheckedAt = 'NULL';
    if (($product['_retailer_slug'] === 'tunisia-net') || $httpStatus === 200) {
        $linkStatus = 'ok';
        $linkCheckedAt = $verifiedAt;
    } elseif ($httpStatus !== null && !in_array($httpStatus, [200, 403, 429], true)) {
        $linkStatus = 'broken';
    }
    $notes = $product['notes'] ?? null;
    $raw = $notes !== null
        ? ['verification_notes' => $notes, 'http_status' => $httpStatus]
        : ['source_features' => $product['raw_features'] ?? null];
    $stock = (string) ($product['stock_status'] ?? 'unknown');
    if (!in_array($stock, ['in_stock', 'out_of_stock', 'limited', 'preorder', 'unknown'], true)) {
        $stock = 'unknown';
    }
    $category = (string) $product['product_category'];
    $screen = $category === 'laptop' ? ($product['screen_size'] ?? null) : null;
    $values = [
        $productId,
        $product['_brand_id'],
        $product['_retailer_id'],
        $q($product['model'] ?? ''),
        $q($product['product_name'] ?? ''),
        $q($category),
        $q($product['processor_brand'] ?? null),
        $q($modelText($product['processor_model'] ?? null)),
        $q($product['processor_generation'] ?? null),
        $product['processor_cores'] !== null && $product['processor_cores'] !== '' ? (int) $product['processor_cores'] : 'NULL',
        (int) $product['ram_gb'],
        $q($product['ram_type'] ?? null),
        $q($product['ram_upgradeable'] ?? 'unknown'),
        $q($product['storage_type'] ?? 'unknown'),
        (int) $product['storage_gb'],
        $q($product['gpu_brand'] ?? null),
        $q($modelText($product['gpu_model'] ?? null)),
        $q($product['gpu_type'] ?? 'unknown'),
        $product['gpu_vram_gb'] !== null && $product['gpu_vram_gb'] !== '' ? (int) $product['gpu_vram_gb'] : 'NULL',
        $screen !== null && $screen !== '' ? $num($screen, 1) : 'NULL',
        $q($category === 'laptop' ? ($product['screen_resolution'] ?? null) : null),
        $q($category === 'laptop' ? ($product['screen_panel'] ?? null) : null),
        $category === 'laptop' && !empty($product['refresh_rate']) ? (int) $product['refresh_rate'] : 'NULL',
        $q($product['operating_system'] ?? null),
        $category === 'laptop' && !empty($product['weight_kg']) ? $num($product['weight_kg'], 2) : 'NULL',
        $q($category === 'laptop' ? ($product['battery'] ?? null) : null),
        $num($product['price_tnd'], 3),
        $q($product['retailer_sku'] ?? null),
        $q($product['product_url']),
        $q(hash('sha256', $product['product_url'])),
        $q($product['image_url'] ?? null),
        $q($stock),
        $q($product['warranty_information'] ?? null),
        $q($product['source_url'] ?? $product['product_url']),
        $q($linkStatus),
        $linkCheckedAt,
        $verifiedAt,
        $q(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        1,
        $verifiedAt,
        $verifiedAt,
    ];
    $sql .= 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
    $histories[] = '(' . $productId . ', ' . $num($product['price_tnd'], 3) . ', ' . $q($stock) . ', ' . ($verifiedAt === 'NULL' ? 'NOW()' : $verifiedAt) . ')';
}

$sql .= "\n-- Initial append-only price and stock snapshots.\n";
foreach (array_chunk($histories, 100) as $chunk) {
    $sql .= 'INSERT INTO product_price_history (product_id, price_tnd, stock_status, recorded_at) VALUES ' . implode(",\n", $chunk) . ";\n";
}

$sql .= "\n-- No administrator is seeded. Run: php tools/create_admin.php\n";
file_put_contents($output, $sql, LOCK_EX);

$byRetailer = [];
$byBrand = [];
$byStock = [];
$byBand = [];
$missingScreen = 0;
foreach ($clean as $product) {
    $byRetailer[$product['_retailer_slug']] = ($byRetailer[$product['_retailer_slug']] ?? 0) + 1;
    $byBrand[$product['_brand_name']] = ($byBrand[$product['_brand_name']] ?? 0) + 1;
    $byStock[$product['stock_status'] ?? 'unknown'] = ($byStock[$product['stock_status'] ?? 'unknown'] ?? 0) + 1;
    $band = (float) $product['price_tnd'] < 1200 ? '<1200' : ((float) $product['price_tnd'] < 1800 ? '1200-1800' : ((float) $product['price_tnd'] < 2500 ? '1800-2500' : ((float) $product['price_tnd'] < 3500 ? '2500-3500' : ((float) $product['price_tnd'] < 5000 ? '3500-5000' : ((float) $product['price_tnd'] < 7000 ? '5000-7000' : '7000+')))));
    $byBand[$product['product_category'] . ' ' . $band] = ($byBand[$product['product_category'] . ' ' . $band] ?? 0) + 1;
    if ($product['product_category'] === 'laptop' && empty($product['screen_size'])) {
        $missingScreen++;
    }
}
ksort($byBand);
arsort($byBrand);
ksort($byRetailer);

$summary = [
    'generated_at' => date('c'),
    'total_records' => count($clean),
    'laptops' => count(array_filter($clean, static fn (array $p): bool => $p['product_category'] === 'laptop')),
    'desktops' => count(array_filter($clean, static fn (array $p): bool => $p['product_category'] === 'desktop')),
    'by_retailer' => $byRetailer,
    'by_brand' => $byBrand,
    'by_stock' => $byStock,
    'by_price_band' => $byBand,
    'missing_laptop_screen_size' => $missingScreen,
    'distinct_product_urls' => count($seenUrls),
    'primary_source' => $primary['source'],
    'primary_scanned_pages' => $primary['scanned'],
    'primary_valid_candidates' => $primary['valid_candidates'],
    'manual_http_403_records' => count(array_filter($manualProducts, static fn (array $p): bool => (int) ($p['http_status'] ?? 0) === 403)),
    'seed_path' => 'database/seed.sql',
];
file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);

echo sprintf(
    "Wrote %s with %d records (%d laptops, %d desktops).\nSummary: %s\n",
    $output,
    count($clean),
    $summary['laptops'],
    $summary['desktops'],
    $summaryPath
);
