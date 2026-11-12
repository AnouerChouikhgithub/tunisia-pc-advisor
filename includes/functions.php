<?php
declare(strict_types=1);

function app(string $key, mixed $default = null): mixed
{
    return $GLOBALS['app_config'][$key] ?? $default;
}

function url(string $path = ''): string
{
    $base = (string) app('url', '');
    $base = str_replace(' ', '%20', $base);
    if ($path === '') {
        return $base . '/';
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('~^https?://~i', $path) ? $path : url($path)));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_price(float|int|string|null $amount): string
{
    if ($amount === null || $amount === '') {
        return 'Price unavailable';
    }
    return number_format((float) $amount, 3, ',', ' ') . ' TND';
}

function format_price_plain(float|int|string|null $amount): string
{
    if ($amount === null || $amount === '') {
        return '';
    }
    return number_format((float) $amount, 3, ',', ' ') . ' TND';
}

function format_date(?string $value, bool $withTime = false): string
{
    if (!$value) {
        return 'Not recorded';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return 'Not recorded';
    }
    return date($withTime ? 'd M Y, H:i' : 'd M Y', $ts);
}

function freshness(?string $verifiedAt): array
{
    if (!$verifiedAt) {
        return ['key' => 'unknown', 'label' => 'Never verified', 'days' => null, 'class' => 'fresh-unknown'];
    }
    $ts = strtotime($verifiedAt);
    if ($ts === false) {
        return ['key' => 'unknown', 'label' => 'Never verified', 'days' => null, 'class' => 'fresh-unknown'];
    }
    $days = max(0, (int) floor((time() - $ts) / 86400));
    if ($days <= 7) {
        return ['key' => 'recent', 'label' => 'Recently verified', 'days' => $days, 'class' => 'fresh-recent'];
    }
    if ($days <= 30) {
        return ['key' => 'review', 'label' => 'Needs review', 'days' => $days, 'class' => 'fresh-review'];
    }
    return ['key' => 'outdated', 'label' => 'Outdated', 'days' => $days, 'class' => 'fresh-outdated'];
}

function stock_label(?string $status): string
{
    return match ($status) {
        'in_stock' => 'In stock',
        'limited' => 'Limited / arriving',
        'out_of_stock' => 'Out of stock',
        'preorder' => 'Pre-order',
        default => 'Stock not confirmed',
    };
}

function stock_class(?string $status): string
{
    return match ($status) {
        'in_stock' => 'stock-in',
        'limited', 'preorder' => 'stock-limited',
        'out_of_stock' => 'stock-out',
        default => 'stock-unknown',
    };
}

function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['_flashes'])) {
        return [];
    }
    $flashes = $_SESSION['_flashes'];
    unset($_SESSION['_flashes']);
    return $flashes;
}

function old(string $key, mixed $default = ''): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return $default;
    }
    return $_SESSION['_old'][$key] ?? $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function query_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

function truncate_text(string $text, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function product_placeholder(): string
{
    return asset('images/product-placeholder.svg');
}

/**
 * Read one named specification from a product row without inventing values.
 */
function spec_or_null(array $product, string $key): mixed
{
    $value = $product[$key] ?? null;
    return ($value === '' || $value === null) ? null : $value;
}
