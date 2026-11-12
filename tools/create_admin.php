<?php
declare(strict_types=1);

/**
 * Create or reset an administrator without putting credentials in source code.
 *
 * Usage:
 *   php tools/create_admin.php --username=admin --name="Project Admin"
 *   php tools/create_admin.php --username=admin --password="..." --force
 *
 * If a password is omitted, it is read from the terminal (visible input on
 * Windows). Use the --password flag only in a trusted local shell.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$options = getopt('', ['username::', 'password::', 'name::', 'force']);
$username = trim((string) ($options['username'] ?? ''));
$password = (string) ($options['password'] ?? '');
$name = trim((string) ($options['name'] ?? ''));
$force = array_key_exists('force', $options);

if ($username === '') {
    fwrite(STDOUT, 'Username [admin]: ');
    $line = trim((string) fgets(STDIN));
    $username = $line !== '' ? $line : 'admin';
}
if ($name === '') {
    $name = $username === 'admin' ? 'Project Administrator' : $username;
}
if ($password === '') {
    fwrite(STDOUT, 'Password (minimum 10 characters, input is visible): ');
    $password = trim((string) fgets(STDIN));
    fwrite(STDOUT, 'Confirm password: ');
    $confirmation = trim((string) fgets(STDIN));
    if ($password !== $confirmation) {
        fwrite(STDERR, "Passwords do not match.\n");
        exit(1);
    }
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
    fwrite(STDERR, "Username must be 3-60 characters: letters, numbers, dot, underscore or hyphen.\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

try {
    $pdo = Database::connection();
    $existing = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
    $existing->execute([$username]);
    $row = $existing->fetch();

    if ($row && !$force) {
        fwrite(STDERR, "Administrator '{$username}' already exists. Use --force to reset its password.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($row) {
        $stmt = $pdo->prepare('UPDATE admin_users SET display_name = ?, password_hash = ?, is_active = 1 WHERE id = ?');
        $stmt->execute([$name, $hash, (int) $row['id']]);
        fwrite(STDOUT, "Password reset for administrator '{$username}'.\n");
    } else {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, display_name, password_hash, is_active) VALUES (?, ?, ?, 1)');
        $stmt->execute([$username, $name, $hash]);
        fwrite(STDOUT, "Administrator '{$username}' created.\n");
    }
    fwrite(STDOUT, "Sign in at /admin/login.php. Change the password from My account after the first sign-in.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Could not create administrator: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
