<?php
declare(strict_types=1);

/**
 * Rate-limited product-link checker. It never bypasses anti-bot challenges and
 * does not rewrite prices or stock. HTTP 403/429 means "blocked/unknown", not
 * "broken". 404/410 marks the link broken; other network/server failures are
 * left unchecked for manual review.
 *
 * Usage: php tools/check_links.php --limit=50 --offset=0 --delay=1200
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$options = getopt('', ['limit::', 'offset::', 'delay::', 'only::']);
$limit = isset($options['limit']) ? max(1, min(500, (int) $options['limit'])) : 50;
$offset = isset($options['offset']) ? max(0, (int) $options['offset']) : 0;
$delayMs = isset($options['delay']) ? max(500, (int) $options['delay']) : 1200;
$only = (string) ($options['only'] ?? 'unchecked');

$pdo = Database::connection();
$where = "is_active = 1";
$params = [];
if ($only === 'unchecked') {
    $where .= " AND link_status = 'unchecked'";
} elseif ($only === 'broken') {
    $where .= " AND link_status = 'broken'";
}
$stmt = $pdo->prepare("SELECT id, product_name, product_url FROM products WHERE $where ORDER BY id ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

if ($products === []) {
    fwrite(STDOUT, "No products matched.\n");
    exit(0);
}

$update = $pdo->prepare('UPDATE products SET link_status = ?, link_checked_at = NOW() WHERE id = ?');
$counts = ['ok' => 0, 'broken' => 0, 'unchecked' => 0];

foreach ($products as $product) {
    $ch = curl_init($product['product_url']);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_RANGE => '0-2048',
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 28,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'TunisiaPCAdvisorLinkCheck/1.0 (local maintenance; project owner)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,*/*;q=0.8'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($status >= 200 && $status < 400) {
        $result = 'ok';
    } elseif (in_array($status, [404, 410], true)) {
        $result = 'broken';
    } else {
        $result = 'unchecked';
    }
    $update->execute([$result, (int) $product['id']]);
    $counts[$result]++;
    fwrite(STDOUT, sprintf("[%s] HTTP %d %s%s\n", $result, $status, $product['product_name'], $error ? ' — ' . $error : ''));
    usleep($delayMs * 1000);
}

fwrite(STDOUT, sprintf("\nChecked %d: %d ok, %d broken, %d left unchecked (blocked/unknown).\n", count($products), $counts['ok'], $counts['broken'], $counts['unchecked']));
