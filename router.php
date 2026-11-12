<?php
declare(strict_types=1);

/**
 * Optional router for PHP's built-in development server:
 *   php -S 127.0.0.1:8080 router.php
 *
 * Apache uses the .htaccess files instead. This router keeps the same
 * sensitive paths out of reach when the app is previewed with the built-in
 * server, which otherwise serves dotfiles and SQL files as static content.
 */

$path = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$blockedPath = preg_match('#(^|/)\.#', $path) === 1
    || preg_match('#^/(?:config|classes|database|tools|includes|docs)(?:/|$)#i', $path) === 1
    || in_array($path, ['/bootstrap.php', '/router.php'], true)
    || preg_match('#\.(?:sql|md|log|ini|env|json|lock|bak|sh)$#i', $path) === 1;

if ($blockedPath) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return true;
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

return false;
