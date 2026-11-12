<?php
declare(strict_types=1);

/**
 * Delete old anonymous recommendation sessions and their cascading results.
 *
 * Usage: php tools/prune_sessions.php --days=180 --yes
 * Without --yes it prints the count and asks for confirmation.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/bootstrap.php';

$options = getopt('', ['days::', 'yes', 'dry-run']);
$days = isset($options['days']) ? max(1, (int) $options['days']) : 180;
$yes = array_key_exists('yes', $options);
$dryRun = array_key_exists('dry-run', $options);

$pdo = Database::connection();
$stmt = $pdo->query('SELECT COUNT(*) FROM recommendation_sessions WHERE created_at < (NOW() - INTERVAL ' . $days . ' DAY)');
$count = (int) $stmt->fetchColumn();

if ($count === 0) {
    fwrite(STDOUT, "No sessions older than {$days} days.\n");
    exit(0);
}

fwrite(STDOUT, "{$count} session(s) older than {$days} days.\n");
if ($dryRun) {
    fwrite(STDOUT, "Dry run: nothing deleted.\n");
    exit(0);
}
if (!$yes) {
    fwrite(STDOUT, 'Delete these sessions and their results? [y/N]: ');
    $answer = strtolower(trim((string) fgets(STDIN)));
    if (!in_array($answer, ['y', 'yes'], true)) {
        fwrite(STDOUT, "Cancelled.\n");
        exit(0);
    }
}

$delete = $pdo->exec('DELETE FROM recommendation_sessions WHERE created_at < (NOW() - INTERVAL ' . $days . ' DAY)');
fwrite(STDOUT, 'Deleted ' . (int) $delete . " session(s).\n");
