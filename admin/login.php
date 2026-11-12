<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

if (Auth::check()) {
    redirect('admin/index.php');
}

$error = null;
$username = '';
$adminCount = 0;
try {
    $adminCount = (int) Database::connection()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
} catch (Throwable) {
}

if (is_post()) {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (Auth::isThrottled()) {
        $error = 'Too many failed sign-in attempts. Wait ten minutes before trying again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $error = 'Enter your username and password.';
        } elseif (Auth::attempt($username, $password)) {
            flash('success', 'Signed in to the administration area.');
            redirect('admin/index.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Administrator sign in — <?= e(app('name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body login-body">
    <div class="login-shell">
        <div class="login-brand">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 44 44" fill="none">
                        <rect x="7" y="9" width="30" height="21" rx="4" stroke="currentColor" stroke-width="2.4"/>
                        <path d="M4 35h36" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" opacity=".65"/>
                        <path d="M22 30v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <circle cx="22" cy="19.5" r="4.5" fill="currentColor"/>
                    </svg>
                </span>
                <span class="brand-copy"><strong>Tunisia PC</strong><span>Advisor</span></span>
            </a>
        </div>

        <div class="login-card">
            <span class="pill"><i data-lucide="lock-keyhole"></i> Protected area</span>
            <h1 style="margin-top:14px;">Administrator sign in.</h1>
            <p>Manage products, retailer listings, prices and verification status.</p>

            <?php if ($error): ?>
                <div class="admin-alert admin-alert-error login-error" role="alert">
                    <i data-lucide="alert-circle"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($adminCount === 0): ?>
                <div class="admin-alert admin-alert-warning">
                    <i data-lucide="terminal"></i>
                    <div><strong>No administrator exists yet.</strong> From the project folder, run <code>php tools/create_admin.php</code>, then sign in with the account you create.</div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('admin/login.php')) ?>" autocomplete="on">
                <?= Security::csrfField() ?>
                <div class="form-field">
                    <label for="username">Username</label>
                    <input class="input" id="username" name="username" type="text" value="<?= e($username) ?>" autocomplete="username" required autofocus>
                </div>
                <div class="form-field">
                    <label for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary btn-block" type="submit" <?= $adminCount === 0 ? 'disabled' : '' ?>>
                    Sign in <i data-lucide="arrow-right"></i>
                </button>
            </form>

            <div class="login-note">
                Sessions are rate-limited and expire after inactivity. There is no public administrator registration.
            </div>
            <a class="login-back" href="<?= e(url('index.php')) ?>">← Back to the public site</a>
        </div>
    </div>
    <script src="<?= e(asset('js/lucide.min.js')) ?>"></script>
    <script>
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ attrs: { 'stroke-width': 1.8, 'aria-hidden': 'true' } });
        }
    </script>
</body>
</html>
