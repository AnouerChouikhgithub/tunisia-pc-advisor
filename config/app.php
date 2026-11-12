<?php
declare(strict_types=1);

$root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$basePath = '';

if ($docRoot && str_starts_with(strtolower($root), strtolower($docRoot))) {
    $basePath = substr($root, strlen($docRoot));
    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$detectedUrl = $scheme . '://' . $host . $basePath;
$appUrl = rtrim((string) env('APP_URL', $detectedUrl), '/');

return [
    'name' => 'Tunisia PC Advisor',
    'tagline' => 'Find the perfect PC for your needs.',
    'url' => $appUrl,
    'base_path' => $basePath,
    'env' => (string) env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'timezone' => 'Africa/Tunis',
    'session_name' => 'tpa_session',
    'currency' => 'TND',
    'max_budget' => 20000,
    'min_budget' => 300,
    'admin_session_key' => 'tpa_admin_id',
    'admin_per_page' => 20,
    'research_date' => '2026-11-10',
];
