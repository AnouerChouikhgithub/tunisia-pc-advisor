<?php
declare(strict_types=1);

return [
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => (string) env('DB_NAME', 'tunisia_pc_advisor'),
    'user' => (string) env('DB_USER', 'root'),
    'pass' => (string) env('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
