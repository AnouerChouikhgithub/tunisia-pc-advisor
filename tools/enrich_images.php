<?php
declare(strict_types=1);

/**
 * Fill missing product images from the Open Graph image on each exact product
 * page. Rate-limited; it does not bypass challenges or change prices/stock.
 *
 * Usage: php tools/enrich_images.php --delay=700
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$path = dirname(__DIR__) . '/database/research/products.json';
$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data) || empty($data['products'])) {
    fwrite(STDERR, "No primary products found.\n");
    exit(1);
}

$options = getopt('', ['delay::']);
$delayMs = isset($options['delay']) ? max(500, (int) $options['delay']) : 700;
$found = 0;
$missing = 0;
$failed = 0;

foreach ($data['products'] as $index => &$product) {
    if (!empty($product['image_url'])) {
        continue;
    }
    $url = (string) ($product['product_url'] ?? '');
    if ($url === '') {
        $failed++;
        continue;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'TunisiaPCAdvisorResearch/1.0 (local project; image enrichment)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $image = null;
    if (is_string($html) && $status === 200) {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $match)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $match)) {
            $image = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    if ($image && preg_match('#^https?://#i', $image)) {
        $product['image_url'] = $image;
        $found++;
        fwrite(STDOUT, sprintf("[%d/%d] image found: %s\n", $index + 1, count($data['products']), $image));
    } else {
        $missing++;
        fwrite(STDOUT, sprintf("[%d/%d] no image (HTTP %d): %s\n", $index + 1, count($data['products']), $status, $url));
    }
    usleep($delayMs * 1000);
}
unset($product);

$data['images_enriched_at'] = date('c');
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
fwrite(STDOUT, "\nAdded {$found} images; {$missing} pages had no image; {$failed} rows had no URL.\nRun: php tools/build_seed.php\n");
