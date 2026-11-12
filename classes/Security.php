<?php
declare(strict_types=1);

final class Security
{
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A session is required for CSRF protection.');
        }
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['_csrf']) || !is_string($token)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }

    public static function requireCsrf(): void
    {
        if (!self::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            require APP_ROOT . '/includes/header.php';
            echo '<main class="section"><div class="container"><div class="alert alert-error"><strong>Your form session expired.</strong> Please go back, refresh the page and submit the form again.</div><p><a class="btn btn-primary" href="' . e(url('questionnaire.php')) . '">Return to the questionnaire</a></p></div></main>';
            require APP_ROOT . '/includes/footer.php';
            exit;
        }
    }

    public static function regenerateCsrf(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['_csrf']);
        }
    }

    public static function requirePost(): void
    {
        if (!is_post()) {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }
    }
}
