<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_ATTEMPTS = 6;
    private const WINDOW_SECONDS = 600;

    public static function attempt(string $username, string $password): bool
    {
        if (self::isThrottled()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, username, display_name, password_hash, is_active FROM admin_users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            self::recordFailure();
            return false;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = Database::connection()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION[(string) app('admin_session_key', 'tpa_admin_id')] = (int) $user['id'];
        $_SESSION['_admin_started_at'] = time();
        $_SESSION['_admin_last_seen'] = time();
        unset($_SESSION['_login_attempts']);
        Security::regenerateCsrf();

        $update = Database::connection()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
        $update->execute([(int) $user['id']]);

        return true;
    }

    public static function check(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $id = $_SESSION[(string) app('admin_session_key', 'tpa_admin_id')] ?? null;
        if (!$id) {
            return false;
        }

        $lastSeen = (int) ($_SESSION['_admin_last_seen'] ?? 0);
        if ($lastSeen > 0 && time() - $lastSeen > 7200) {
            self::logout();
            return false;
        }
        $_SESSION['_admin_last_seen'] = time();
        return true;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT id, username, display_name, last_login_at FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION[(string) app('admin_session_key', 'tpa_admin_id')]]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function id(): ?int
    {
        return self::check()
            ? (int) $_SESSION[(string) app('admin_session_key', 'tpa_admin_id')]
            : null;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[(string) app('admin_session_key', 'tpa_admin_id')]);
            unset($_SESSION['_admin_started_at'], $_SESSION['_admin_last_seen']);
            session_regenerate_id(true);
            Security::regenerateCsrf();
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::check()) {
            $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? 'admin/index.php';
            flash('error', 'Please sign in to access the administration area.');
            redirect('admin/login.php');
        }
    }

    public static function isThrottled(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $attempts = $_SESSION['_login_attempts'] ?? [];
        $cutoff = time() - self::WINDOW_SECONDS;
        $attempts = array_values(array_filter($attempts, static fn ($ts) => is_int($ts) && $ts >= $cutoff));
        $_SESSION['_login_attempts'] = $attempts;
        return count($attempts) >= self::MAX_ATTEMPTS;
    }

    public static function attemptsRemaining(): int
    {
        $attempts = $_SESSION['_login_attempts'] ?? [];
        return max(0, self::MAX_ATTEMPTS - count($attempts));
    }

    private static function recordFailure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_login_attempts'][] = time();
        }
    }

    private function __construct()
    {
    }
}
