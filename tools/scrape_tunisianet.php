<?php
declare(strict_types=1);

/**
 * Tunisia Net catalogue research collector.
 *
 * This is a deliberately slow, single-worker public-page collector. It reads the
 * retailer's public sitemap and product pages, stores a local audit snapshot and
 * never bypasses authentication, anti-bot controls or robots.txt. Review the
 * retailer's terms before running it again. The generated dataset is a
 * point-in-time snapshot, not a live price feed.
 *
 * Usage:
 *   php tools/scrape_tunisianet.php --scan-laptops=240 --scan-desktops=120
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$options = getopt('', [
    'scan-laptops::',
    'scan-desktops::',
    'laptops::',
    'desktops::',
    'delay::',
    'laptop-offset::',
    'desktop-offset::',
    'merge',
    'refresh-sitemap',
]);

$scanLaptops = isset($options['scan-laptops']) ? max(1, (int) $options['scan-laptops']) : 240;
$scanDesktops = isset($options['scan-desktops']) ? max(1, (int) $options['scan-desktops']) : 120;
$targetLaptops = isset($options['laptops']) ? max(1, (int) $options['laptops']) : 120;
$targetDesktops = isset($options['desktops']) ? max(1, (int) $options['desktops']) : 40;
$delayMs = isset($options['delay']) ? max(250, (int) $options['delay']) : 900;
$laptopOffset = isset($options['laptop-offset']) ? max(0, (int) $options['laptop-offset']) : 0;
$desktopOffset = isset($options['desktop-offset']) ? max(0, (int) $options['desktop-offset']) : 0;
$mergeExisting = array_key_exists('merge', $options) || $laptopOffset > 0 || $desktopOffset > 0;
$refreshSitemap = array_key_exists('refresh-sitemap', $options);

$researchDir = APP_ROOT . '/database/research';
if (!is_dir($researchDir)) {
    mkdir($researchDir, 0775, true);
}
$sitemapCache = $researchDir . '/tunisianet_sitemap_urls.txt';
$rawOutput = $researchDir . '/tunisianet_candidates.json';
$selectedOutput = $researchDir . '/products.json';
$logFile = $researchDir . '/scrape.log';

function log_line(string $file, string $message): void
{
    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    echo rtrim($line) . PHP_EOL;
}

function http_get(string $url, int $attempts = 3): ?string
{
    $lastError = '';
    for ($i = 1; $i <= $attempts; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'TunisiaPCAdvisorResearch/1.0 (local catalogue verification; contact: project owner)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: fr-FR,fr;q=0.9,en;q=0.7',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $lastError = curl_error($ch);
        curl_close($ch);

        if (is_string($body) && $status >= 200 && $status < 300 && strlen($body) > 1000) {
            return $body;
        }
        $lastError = 'HTTP ' . $status . ' ' . $lastError;
        usleep(($delayMs + 400 * $i) * 1000);
    }
    log_line($GLOBALS['logFile'], "Fetch failed: {$url} ({$lastError})");
    return null;
}

function norm_key(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(['’', '‘', '´'], "'", $value);
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

function feature_value(array $features, array $candidates): ?string
{
    $normalized = [];
    foreach ($features as $name => $value) {
        $normalized[norm_key((string) $name)] = trim((string) $value);
    }
    foreach ($candidates as $candidate) {
        $key = norm_key($candidate);
        if (isset($normalized[$key]) && $normalized[$key] !== '') {
            return $normalized[$key];
        }
        foreach ($normalized as $name => $value) {
            if ($value !== '' && str_contains($name, $key)) {
                return $value;
            }
        }
    }
    return null;
}

function parse_gb(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $value = str_replace(',', '.', $value);
    if (preg_match('/(\d+(?:\.\d+)?)\s*(to|tb|go|gb)/i', $value, $m)) {
        $number = (float) $m[1];
        $unit = strtolower($m[2]);
        return (int) round(in_array($unit, ['to', 'tb'], true) ? $number * 1024 : $number);
    }
    return null;
}

function parse_capacity_gb(?string $value): ?int
{
    return parse_gb($value);
}

function parse_ram_gb(?string $value): ?int
{
    return parse_gb($value);
}

function parse_kg(?string $value): ?float
{
    if (!$value) {
        return null;
    }
    $value = str_replace(',', '.', $value);
    if (preg_match('/(\d+(?:\.\d+)?)\s*(kg|kgs|kilogrammes?)/i', $value, $m)) {
        return round((float) $m[1], 2);
    }
    if (preg_match('/(\d{3,4})\s*(g|gr|grammes?)/i', $value, $m)) {
        return round(((float) $m[1]) / 1000, 2);
    }
    return null;
}

function parse_screen_size(?string $description, array $features): ?float
{
    $sources = [];
    if ($description) {
        $sources[] = $description;
    }
    $sizeFeature = feature_value($features, ['Taille Ecran', 'Taille de l\'écran', 'Taille écran']);
    if ($sizeFeature) {
        $sources[] = $sizeFeature;
    }
    foreach ($sources as $source) {
        if (preg_match('/(\d{2}(?:[.,]\d)?)\s*(?:"|″|pouces?|inches?)/i', $source, $m)) {
            return round((float) str_replace(',', '.', $m[1]), 1);
        }
    }
    return null;
}

function parse_resolution(?string $typeEcran, ?string $description): ?string
{
    $haystack = mb_strtolower(trim(($typeEcran ?? '') . ' ' . ($description ?? '')));
    $map = [
        '3840 × 2160' => ['4k uhd', 'uhd 4k', '4k'],
        '2560 × 1600' => ['2.5k', 'wqxga'],
        '2880 × 1800' => ['2.8k', '3k oled'],
        '2560 × 1440' => ['qhd', '2k'],
        '1920 × 1200' => ['wuxga'],
        '1920 × 1080' => ['full hd', 'fhd', '1920x1080'],
        '1366 × 768' => ['hd ready', '1366x768'],
        '1280 × 800' => ['wxga'],
    ];
    foreach ($map as $resolution => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return $resolution;
            }
        }
    }
    return null;
}

function parse_panel(?string $typeEcran, ?string $description, array $features): ?string
{
    $explicit = feature_value($features, ['Type de Dalle', 'Type Dalle', 'Dalle', 'Panneau']);
    $haystack = mb_strtoupper(trim(($explicit ?? '') . ' ' . ($typeEcran ?? '') . ' ' . ($description ?? '')));
    foreach (['OLED', 'IPS', 'VA', 'TN'] as $panel) {
        if (preg_match('/\b' . $panel . '\b/', $haystack)) {
            return $panel;
        }
    }
    return null;
}

function parse_generation(?string $cpu, ?string $family): ?string
{
    if (!$cpu) {
        return null;
    }
    $cpu = trim($cpu);
    if (preg_match('/Apple\s+M(\d)/i', $cpu, $m)) {
        return 'Apple M' . $m[1];
    }
    if (preg_match('/Core\s+Ultra\s+[3579]\s+(?:Processor\s+)?(\d{3})/i', $cpu, $m)) {
        $series = ((int) $m[1]) >= 200 ? 2 : 1;
        return 'Core Ultra Series ' . $series;
    }
    if (preg_match('/i[3579]-(\d{2})\d{2,3}/i', $cpu, $m)) {
        return (int) $m[1] . 'th gen';
    }
    if (preg_match('/Ryzen\s+[3579]\s+(\d{4})/i', $cpu, $m)) {
        $series = (int) substr($m[1], 0, 1) * 1000;
        return $series . ' series';
    }
    $family = $family ?? '';
    if (preg_match('/Intel.*?(\d{1,2})(?:th|ème|eme)\s*(?:gen|gén)/i', $family, $m)) {
        return (int) $m[1] . 'th gen';
    }
    return null;
}

function parse_cores(array $features): ?int
{
    $value = feature_value($features, ['Nombre de cœurs', 'Nombre de coeurs', 'Cœurs', 'Coeurs']);
    if ($value && preg_match('/(\d+)/', $value, $m)) {
        return (int) $m[1];
    }
    return null;
}

function parse_ram_type(?string $memory, array $features): ?string
{
    $explicit = feature_value($features, ['Type de mémoire', 'Type Mémoire', 'Type de RAM', 'Technologie mémoire']);
    $haystack = trim(($explicit ?? '') . ' ' . ($memory ?? ''));
    if (preg_match('/LPDDR\d(?:X|x)?(?:\s*-\s*\d{4})?/i', $haystack, $m)) {
        return strtoupper(str_replace(' ', '', $m[0]));
    }
    if (preg_match('/DDR\d(?:X|x)?/i', $haystack, $m)) {
        return strtoupper($m[0]);
    }
    return null;
}

function parse_upgradeable(array $features): ?string
{
    $extensible = feature_value($features, ['Mémoire extensible', 'Extension mémoire', 'Mémoire upgradable']);
    if ($extensible !== null) {
        return preg_match('/^(oui|yes|1)$/i', trim($extensible)) ? 'yes'
            : (preg_match('/^(non|no|0)$/i', trim($extensible)) ? 'no' : 'unknown');
    }
    $soldered = feature_value($features, ['Mémoire soudée', 'RAM soudée']);
    if ($soldered !== null) {
        return preg_match('/^(oui|yes|1)$/i', trim($soldered)) ? 'no'
            : (preg_match('/^(non|no|0)$/i', trim($soldered)) ? 'yes' : 'unknown');
    }
    return null;
}

function parse_storage_type(?string $storage): string
{
    $storage = mb_strtolower($storage ?? '');
    $hasSsd = str_contains($storage, 'ssd') || str_contains($storage, 'nvme');
    $hasHdd = str_contains($storage, 'hdd') || str_contains($storage, 'disque dur mécanique');
    if ($hasSsd && $hasHdd) {
        return 'SSD + HDD';
    }
    if (str_contains($storage, 'nvme')) {
        return 'NVMe SSD';
    }
    if (str_contains($storage, 'ssd')) {
        return 'SSD';
    }
    if (str_contains($storage, 'emmc')) {
        return 'eMMC';
    }
    if ($hasHdd) {
        return 'HDD';
    }
    return 'unknown';
}

function parse_gpu_type(?string $model): string
{
    if ($model === null || $model === '') {
        return 'unknown';
    }
    if (preg_match('/nvidia|geforce|rtx|gtx|quadro|radeon\s+rx|arc\s+a\d|apple\s+gpu/i', $model)) {
        return 'dedicated';
    }
    if (preg_match('/intel|iris|uhd|radeon|graphique intégrée|integrated/i', $model)) {
        return 'integrated';
    }
    return 'unknown';
}

function parse_gpu_brand(?string $model, ?string $cpu): ?string
{
    if (!$model) {
        return null;
    }
    if (preg_match('/nvidia|geforce|rtx|gtx|quadro/i', $model)) {
        return 'NVIDIA';
    }
    if (preg_match('/amd|radeon/i', $model)) {
        return 'AMD';
    }
    if (preg_match('/intel|iris|uhd/i', $model)) {
        return 'Intel';
    }
    if (preg_match('/apple/i', $model) || ($cpu && preg_match('/Apple/i', $cpu))) {
        return 'Apple';
    }
    return null;
}

function parse_gpu_vram(?string $model): ?int
{
    if (!$model) {
        return null;
    }
    if (preg_match('/(\d+)\s*(?:go|gb)\b/i', $model, $m)) {
        return (int) $m[1];
    }
    return null;
}

function canonical_brand(?string $name): string
{
    $name = trim((string) $name);
    $map = [
        'asus' => 'ASUS',
        'hp' => 'HP',
        'lenovo' => 'Lenovo',
        'dell' => 'Dell',
        'acer' => 'Acer',
        'msi' => 'MSI',
        'apple' => 'Apple',
        'gigabyte' => 'Gigabyte',
        'samsung' => 'Samsung',
        'blackview' => 'Blackview',
        'chuwi' => 'Chuwi',
        'honor' => 'Honor',
        'infinix' => 'Infinix',
        'teclast' => 'Teclast',
        'microsoft' => 'Microsoft',
        'razer' => 'Razer',
        'tunisianet' => 'Tunisia Net',
    ];
    return $map[mb_strtolower($name)] ?? ($name !== '' ? $name : 'Unknown');
}

function model_from_title(string $title, string $brand): string
{
    $model = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $model = preg_replace('/^(pc|ordinateur)\s+(portable|de bureau)\s*/i', '', $model) ?? $model;
    $model = preg_replace('/^mini\s+pc\s*/i', '', $model) ?? $model;
    $model = preg_replace('/^station\s+de\s+travail\s*/i', '', $model) ?? $model;
    $model = preg_replace('/^(gamer|gaming)\s+/i', '', $model) ?? $model;
    if ($brand !== '' && str_starts_with(mb_strtolower($model), mb_strtolower($brand))) {
        $model = mb_substr($model, mb_strlen($brand));
        $model = ltrim($model, " \t-");
    }
    $parts = preg_split('/\s+\/\s+/', $model) ?: [$model];
    $model = trim($parts[0]);
    return $model !== '' ? $model : trim($title);
}

function extract_stock(string $html, array $data): array
{
    $text = '';
    if (preg_match('/<div[^>]+id="stock_availability"[^>]*>(.*?)<\/div>/is', $html, $m)) {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])) ?? '');
    }
    $text = str_replace('Disponibilté', 'Disponibilité', $text);
    if (preg_match('/hors stock|rupture|indisponible/i', $text)) {
        return ['out_of_stock', $text];
    }
    if (preg_match('/en arrivage|sur commande|derniers articles/i', $text)) {
        return ['limited', $text];
    }
    if (preg_match('/en stock|disponible/i', $text)) {
        return ['in_stock', $text];
    }
    if (isset($data['quantity']) && is_numeric($data['quantity']) && (int) $data['quantity'] > 0) {
        return ['in_stock', 'Quantity listed'];
    }
    return ['unknown', $text !== '' ? $text : 'No stock label found'];
}

function extract_card(string $html, string $url, string $category): ?array
{
    if (!preg_match('/data-product="([^"]+)"/s', $html, $m)) {
        return null;
    }
    $json = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    if (!preg_match('/<h1[^>]*itemprop="name"[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        return null;
    }
    $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($title === '') {
        return null;
    }

    $features = [];
    foreach (($data['features'] ?? []) as $feature) {
        if (isset($feature['name'], $feature['value'])) {
            $features[trim((string) $feature['name'])] = trim((string) $feature['value']);
        }
    }

    $description = '';
    if (preg_match('/<div[^>]+id="product-description-short-[^"]+"[^>]*>(.*?)<\/div>/is', $html, $m)) {
        $description = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $metaDescription = '';
    if (preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $html, $m)) {
        $metaDescription = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $brand = canonical_brand($data['manufacturer_name'] ?? null);
    $cpu = feature_value($features, ['Réf processeur', 'Reference processeur', 'Processeur']);
    if ($cpu && str_contains($cpu, '(')) {
        $cpu = trim(strtok($cpu, '('));
    }
    $cpuFamily = feature_value($features, ['Processeur']);
    $ramText = feature_value($features, ['Mémoire', 'Mémoire RAM', 'RAM']);
    $storageText = feature_value($features, ['Disque Dur', 'Stockage', 'Disque dur']);
    $gpuModel = feature_value($features, ['Réf Carte graphique', 'Reference carte graphique', 'Carte Graphique', 'Carte graphique']);
    $os = feature_value($features, ['Système d\'exploitation', 'Systeme d\'exploitation', 'OS']);
    $screenType = feature_value($features, ['Type Ecran', 'Type d\'écran', 'Résolution']);
    $sizeFeature = feature_value($features, ['Taille Ecran', 'Taille de l\'écran']);
    $refreshText = feature_value($features, ['Taux de Rafraîchissement', 'Fréquence de rafraîchissement']);
    $warranty = feature_value($features, ['Garantie', 'Garantie ']);
    $weightText = feature_value($features, ['Poids', 'Poids (kg)']);
    $battery = feature_value($features, ['Batterie', 'Capacité batterie', 'Autonomie']);
    $ramType = parse_ram_type($ramText, $features);

    $price = isset($data['price_amount']) && is_numeric($data['price_amount'])
        ? (float) $data['price_amount']
        : (isset($data['price_without_reduction']) ? (float) $data['price_without_reduction'] : 0.0);
    if ($price <= 0 && preg_match('/itemprop="price"\s+content="([0-9.]+)"/i', $html, $m)) {
        $price = (float) $m[1];
    }

    $image = $data['cover']['bySize']['large']['url']
        ?? $data['images'][0]['bySize']['large']['url']
        ?? null;
    if ($image) {
        $image = str_replace('http://', 'https://', $image);
    }

    $stock = extract_stock($html, $data);
    $reference = trim((string) ($data['reference'] ?? ''));
    $productId = (int) ($data['id_product'] ?? 0);
    $gpuType = parse_gpu_type($gpuModel);
    $screenSize = $category === 'laptop' ? parse_screen_size($description ?: $metaDescription, $features) : null;
    $ramGb = parse_ram_gb($ramText);
    $storageGb = parse_capacity_gb($storageText);

    $product = [
        'retailer' => 'Tunisia Net',
        'retailer_sku' => $reference !== '' ? $reference : 'TN-' . $productId,
        'source_product_id' => $productId,
        'brand' => $brand,
        'model' => model_from_title($title, $brand),
        'product_name' => $title,
        'product_category' => $category,
        'processor_brand' => $cpu ? (preg_match('/AMD|Ryzen/i', $cpu) ? 'AMD' : (preg_match('/Apple/i', $cpu) ? 'Apple' : 'Intel')) : null,
        'processor_model' => $cpu,
        'processor_generation' => parse_generation($cpu, $cpuFamily),
        'processor_cores' => parse_cores($features),
        'ram_gb' => $ramGb,
        'ram_type' => $ramType,
        'ram_upgradeable' => parse_upgradeable($features),
        'storage_type' => parse_storage_type($storageText),
        'storage_gb' => $storageGb,
        'gpu_brand' => parse_gpu_brand($gpuModel, $cpu),
        'gpu_model' => $gpuModel,
        'gpu_type' => $gpuType,
        'gpu_vram_gb' => $gpuType === 'dedicated' ? parse_gpu_vram($gpuModel) : null,
        'screen_size' => $screenSize,
        'screen_resolution' => $category === 'laptop' ? parse_resolution($screenType, $description ?: $metaDescription) : null,
        'screen_panel' => $category === 'laptop' ? parse_panel($screenType, $description ?: $metaDescription, $features) : null,
        'refresh_rate' => $refreshText && preg_match('/(\d{2,3})/', $refreshText, $rm) ? (int) $rm[1] : null,
        'operating_system' => $os ?: (preg_match('/Windows\s+1[01]/i', $metaDescription, $wm) ? $wm[0] : null),
        'weight_kg' => $weightText ? parse_kg($weightText) : null,
        'battery' => $battery,
        'price_tnd' => $price,
        'product_url' => $url,
        'image_url' => $image,
        'stock_status' => $stock[0],
        'stock_text' => $stock[1],
        'warranty_information' => $warranty,
        'source_url' => $url,
        'verified_at' => date('c'),
        'raw_features' => $features,
        'screen_size_feature' => $sizeFeature,
    ];

    $critical = ['processor_model', 'ram_gb', 'storage_gb', 'price_tnd', 'brand', 'model', 'product_url'];
    foreach ($critical as $field) {
        if (empty($product[$field])) {
            return null;
        }
    }
    if (!in_array($product['gpu_type'], ['integrated', 'dedicated', 'unknown'], true)) {
        return null;
    }
    if ($product['price_tnd'] < 500 || $product['price_tnd'] > 30000) {
        return null;
    }
    if (preg_match('/barebone|sans processeur|sans mémoire|sans memoire|boîtier seul|boitier seul/i', $title)) {
        return null;
    }
    if ($category === 'laptop' && $product['screen_size'] === null) {
        return null;
    }
    if ($product['ram_gb'] < 4 || $product['storage_gb'] < 120) {
        return null;
    }

    return $product;
}

function signature(array $product): string
{
    $parts = [
        mb_strtolower($product['brand']),
        mb_strtolower($product['model']),
        mb_strtolower((string) $product['processor_model']),
        (string) $product['ram_gb'],
        (string) $product['storage_gb'],
        mb_strtolower((string) $product['gpu_model']),
        (string) ($product['screen_size'] ?? ''),
    ];
    return preg_replace('/[^a-z0-9]+/', '-', implode('|', $parts)) ?? implode('|', $parts);
}

function price_band(float $price): string
{
    return match (true) {
        $price < 1200 => '0-1200',
        $price < 1800 => '1200-1800',
        $price < 2500 => '1800-2500',
        $price < 3500 => '2500-3500',
        $price < 5000 => '3500-5000',
        $price < 7000 => '5000-7000',
        default => '7000+',
    };
}

/**
 * Select a varied set across price bands and brands while preserving every real
 * listing. Out-of-stock rows remain in the catalogue but rank behind in-stock
 * rows during selection.
 */
function select_products(array $products, int $target, array $bandQuota): array
{
    usort($products, static function (array $a, array $b): int {
        $stockOrder = ['in_stock' => 0, 'limited' => 1, 'preorder' => 2, 'unknown' => 3, 'out_of_stock' => 4];
        return [$stockOrder[$a['stock_status']] ?? 5, -($a['source_product_id'] ?? 0)]
            <=> [$stockOrder[$b['stock_status']] ?? 5, -($b['source_product_id'] ?? 0)];
    });

    $buckets = [];
    foreach ($products as $product) {
        $buckets[price_band((float) $product['price_tnd'])][] = $product;
    }

    $selected = [];
    $seen = [];
    $brandCounts = [];

    foreach ($bandQuota as $band => $quota) {
        $bucket = $buckets[$band] ?? [];
        // Round-robin across brands inside a band for visual and recommendation variety.
        $byBrand = [];
        foreach ($bucket as $product) {
            $byBrand[$product['brand']][] = $product;
        }
        $cursor = 0;
        while ($quota > 0 && $cursor < 40) {
            $added = false;
            foreach ($byBrand as $brand => &$brandProducts) {
                if ($quota <= 0) {
                    break;
                }
                while ($brandProducts) {
                    $candidate = array_shift($brandProducts);
                    $sig = signature($candidate);
                    if (isset($seen[$sig])) {
                        continue;
                    }
                    if (($brandCounts[$brand] ?? 0) >= 30) {
                        continue;
                    }
                    $seen[$sig] = true;
                    $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                    $selected[] = $candidate;
                    $quota--;
                    $added = true;
                    break;
                }
            }
            unset($brandProducts);
            $cursor++;
            if (!$added) {
                break;
            }
        }
    }

    // If one band lacked supply, fill from the remaining valid products.
    foreach ($products as $product) {
        if (count($selected) >= $target) {
            break;
        }
        $sig = signature($product);
        if (isset($seen[$sig])) {
            continue;
        }
        $seen[$sig] = true;
        $selected[] = $product;
    }

    usort($selected, static fn (array $a, array $b): int => strcmp($a['product_name'], $b['product_name']));
    return $selected;
}

$GLOBALS['logFile'] = $logFile;
log_line($logFile, 'Collector started.');

$urls = [];
if (!$refreshSitemap && is_file($sitemapCache)) {
    $urls = array_values(array_filter(array_map('trim', file($sitemapCache) ?: [])));
    log_line($logFile, 'Using cached sitemap URLs: ' . count($urls));
}

if (!$urls) {
    log_line($logFile, 'Fetching public sitemap.');
    $sitemap = http_get('https://www.tunisianet.com.tn/1_fr_0_sitemap.xml');
    if ($sitemap === null) {
        log_line($logFile, 'Could not fetch sitemap. Stopping.');
        exit(1);
    }
    if (preg_match_all('/<loc><!\[CDATA\[([^\]]+)\]\]><\/loc>/', $sitemap, $matches)) {
        $urls = array_map('trim', $matches[1]);
    }
    $urls = array_values(array_unique($urls));
    file_put_contents($sitemapCache, implode(PHP_EOL, $urls) . PHP_EOL, LOCK_EX);
    log_line($logFile, 'Sitemap URLs cached: ' . count($urls));
}

$laptopUrls = [];
$desktopUrls = [];
foreach ($urls as $url) {
    if (preg_match('~/pc-portable-tunisie/(\d+)-~', $url, $m)) {
        $laptopUrls[(int) $m[1]] = $url;
    } elseif (preg_match('~/pc-de-bureau/(\d+)-~', $url, $m)) {
        $desktopUrls[(int) $m[1]] = $url;
    }
}
krsort($laptopUrls);
krsort($desktopUrls);
$laptopUrls = array_slice(array_values($laptopUrls), $laptopOffset, $scanLaptops);
$desktopUrls = array_slice(array_values($desktopUrls), $desktopOffset, $scanDesktops);

log_line($logFile, sprintf('Scanning %d laptop and %d desktop pages (offsets %d/%d).', count($laptopUrls), count($desktopUrls), $laptopOffset, $desktopOffset));

$candidates = [];
if ($mergeExisting && is_file($rawOutput)) {
    $existing = json_decode((string) file_get_contents($rawOutput), true);
    if (is_array($existing) && isset($existing['candidates']) && is_array($existing['candidates'])) {
        $candidates = $existing['candidates'];
        log_line($logFile, 'Merged existing candidates: ' . count($candidates));
    }
}
$failures = 0;
$scanned = 0;
foreach ([['laptop', $laptopUrls], ['desktop', $desktopUrls]] as [$category, $list]) {
    foreach ($list as $url) {
        $scanned++;
        $html = http_get($url);
        if ($html === null) {
            $failures++;
        } else {
            $product = extract_card($html, $url, $category);
            if ($product !== null) {
                $candidates[] = $product;
            } else {
                log_line($logFile, 'Skipped (incomplete specification): ' . basename($url));
            }
        }
        if ($scanned % 20 === 0) {
            log_line($logFile, sprintf('Progress: %d scanned, %d candidates in collection.', $scanned, count($candidates)));
        }
        usleep($delayMs * 1000);
    }
}

$dedupedCandidates = [];
foreach ($candidates as $candidate) {
    $dedupedCandidates[$candidate['product_url']] = $candidate;
}
$candidates = array_values($dedupedCandidates);

file_put_contents(
    $rawOutput,
    json_encode([
        'generated_at' => date('c'),
        'source' => 'https://www.tunisianet.com.tn/',
        'method' => 'Public sitemap + public product pages; one request at a time; no access-control bypass.',
        'scanned' => $scanned,
        'failures' => $failures,
        'candidates' => $candidates,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);

$laptops = array_values(array_filter($candidates, static fn (array $p): bool => $p['product_category'] === 'laptop'));
$desktops = array_values(array_filter($candidates, static fn (array $p): bool => $p['product_category'] === 'desktop'));

$laptopQuota = [
    '0-1200' => 15,
    '1200-1800' => 20,
    '1800-2500' => 25,
    '2500-3500' => 25,
    '3500-5000' => 20,
    '5000-7000' => 10,
    '7000+' => 5,
];
$desktopQuota = [
    '0-1200' => 5,
    '1200-1800' => 8,
    '1800-2500' => 8,
    '2500-3500' => 8,
    '3500-5000' => 6,
    '5000-7000' => 3,
    '7000+' => 2,
];

$selectedLaptops = select_products($laptops, $targetLaptops, $laptopQuota);
$selectedDesktops = select_products($desktops, $targetDesktops, $desktopQuota);
$selected = array_merge($selectedLaptops, $selectedDesktops);

usort($selected, static function (array $a, array $b): int {
    return [$a['product_category'], $a['price_tnd']] <=> [$b['product_category'], $b['price_tnd']];
});

$summary = [
    'generated_at' => date('c'),
    'source' => 'https://www.tunisianet.com.tn/',
    'scanned' => $scanned,
    'fetch_failures' => $failures,
    'valid_candidates' => count($candidates),
    'valid_laptops' => count($laptops),
    'valid_desktops' => count($desktops),
    'selected' => count($selected),
    'selected_laptops' => count($selectedLaptops),
    'selected_desktops' => count($selectedDesktops),
    'in_stock_selected' => count(array_filter($selected, static fn (array $p): bool => $p['stock_status'] === 'in_stock')),
    'stock_breakdown' => array_count_values(array_column($selected, 'stock_status')),
    'price_min' => $selected ? min(array_column($selected, 'price_tnd')) : null,
    'price_max' => $selected ? max(array_column($selected, 'price_tnd')) : null,
    'retailer' => 'Tunisia Net',
    'products' => $selected,
];

file_put_contents(
    $selectedOutput,
    json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);

log_line($logFile, sprintf(
    'Finished. Valid=%d (laptops=%d, desktops=%d). Selected=%d (laptops=%d, desktops=%d).',
    count($candidates),
    count($laptops),
    count($desktops),
    count($selected),
    count($selectedLaptops),
    count($selectedDesktops)
));
