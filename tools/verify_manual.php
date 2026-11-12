<?php
declare(strict_types=1);

/**
 * Recheck the manually curated multi-retailer records against their public
 * product pages. It records HTTP status, final URL, page title and og:image.
 * It does not rewrite prices or specifications.
 *
 * Usage: php tools/verify_manual.php [--delay=1200]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$options = getopt('', ['delay::']);
$delayMs = isset($options['delay']) ? max(300, (int) $options['delay']) : 1100;
$input = APP_ROOT . '/database/research/manual_products.json';
$output = APP_ROOT . '/database/research/manual_products_verified.json';
$reportPath = APP_ROOT . '/database/research/manual_verification.json';

$data = json_decode((string) file_get_contents($input), true);
if (!is_array($data) || !isset($data['products'])) {
    fwrite(STDERR, "Could not read {$input}\n");
    exit(1);
}

$report = [];
foreach ($data['products'] as &$product) {
    $url = $product['product_url'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'TunisiaPCAdvisorResearch/1.0 (manual listing verification; project owner)',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.7',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);

    $title = null;
    $image = null;
    if (is_string($body) && $body !== '') {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $body, $m)) {
            $image = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($image, '//')) {
                $image = 'https:' . $image;
            }
        }
    }

    if ($image) {
        $product['image_url'] = $image;
    }
    $product['http_status'] = $status;

    $report[] = [
        'retailer' => $product['retailer_slug'],
        'product' => $product['product_name'],
        'url' => $url,
        'final_url' => $finalUrl,
        'http_status' => $status,
        'page_title' => $title,
        'og_image' => $image,
        'curl_error' => $error ?: null,
        'checked_at' => date('c'),
    ];

    echo sprintf("[%d] %s — %s\n", $status, $product['product_name'], $title ?? $error);
    usleep($delayMs * 1000);
}
unset($product);

$data['verified_at'] = date('c');
$data['http_check_note'] = 'HTTP status, title and og:image were rechecked; prices/specifications were not automatically rewritten.';
file_put_contents($output, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
file_put_contents($reportPath, json_encode(['checked_at' => date('c'), 'results' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);

echo 'Wrote ' . $output . PHP_EOL;
