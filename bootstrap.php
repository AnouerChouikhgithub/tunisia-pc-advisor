<?php
declare(strict_types=1);

/**
 * Tunisia PC Advisor bootstrap.
 * Works under Apache/XAMPP and under PHP's built-in development server.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (!function_exists('env')) {
    /**
     * Minimal .env loader. Real environment variables always win.
     */
    function env(string $key, mixed $default = null): mixed
    {
        static $loaded = false;

        if (!$loaded) {
            $loaded = true;
            $file = APP_ROOT . '/.env';
            if (is_file($file) && is_readable($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    if ($name === '') {
                        continue;
                    }
                    if (strlen($value) >= 2) {
                        $first = $value[0];
                        $last = $value[strlen($value) - 1];
                        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                            $value = substr($value, 1, -1);
                        }
                    }
                    if (getenv($name) === false) {
                        putenv($name . '=' . $value);
                        $_ENV[$name] = $value;
                    }
                }
            }
        }

        $value = getenv($key);
        return $value === false || $value === '' ? $default : $value;
    }
}

$config = require APP_ROOT . '/config/app.php';
$GLOBALS['app_config'] = $config;

date_default_timezone_set($config['timezone'] ?? 'Africa/Tunis');

require_once APP_ROOT . '/includes/functions.php';

spl_autoload_register(static function (string $class): void {
    $file = APP_ROOT . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name((string) $config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// A small security header set that is safe for a plain PHP application.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}
