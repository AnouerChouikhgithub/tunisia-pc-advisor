<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$errors = [];
$success = false;
$displayName = (string) $admin['display_name'];

if (is_post()) {
    Security::requireCsrf();
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');

    if ($displayName === '' || mb_strlen($displayName) > 120) {
        $errors['display_name'] = 'Display name is required and must be 120 characters or fewer.';
    }

    $stmt = Database::connection()->prepare('SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $admin['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($currentPassword, $hash)) {
        $errors['current_password'] = 'Your current password is not correct.';
    }
    if (mb_strlen($newPassword) < 10) {
        $errors['new_password'] = 'Use a new password of at least 10 characters.';
    }
    if ($newPassword !== $confirmation) {
        $errors['new_password_confirmation'] = 'The new passwords do not match.';
    }

    if ($errors === []) {
        $update = Database::connection()->prepare(
            'UPDATE admin_users SET display_name = ?, password_hash = ? WHERE id = ?'
        );
        $update->execute([$displayName, password_hash($newPassword, PASSWORD_DEFAULT), (int) $admin['id']]);
        flash('success', 'Account details updated.');
        redirect('admin/account.php');
    }
}

$adminPageTitle = 'My account';
$adminActive = 'account';
$adminPageSubtitle = 'Update the administrator display name or rotate the password.';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>My account</h1>
        <p>Administrator credentials are stored as a password hash. There is no password recovery flow in this local build; use the CLI maintenance tool if you lose access and have database access.</p>
    </div>
</div>

<div class="admin-grid">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Account details</h2><p>Signed in as @<?= e($admin['username']) ?></p></div></div>
        <div class="admin-panel-body">
            <form method="post" action="<?= e(url('admin/account.php')) ?>" class="admin-form">
                <?= Security::csrfField() ?>
                <div class="form-field">
                    <label for="display_name">Display name</label>
                    <input class="input<?= isset($errors['display_name']) ? ' has-error' : '' ?>" id="display_name" name="display_name" type="text" maxlength="120" value="<?= e($displayName) ?>" required>
                    <?php if (isset($errors['display_name'])): ?><p class="field-error"><?= e($errors['display_name']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="current_password">Current password <span class="req">*</span></label>
                    <input class="input<?= isset($errors['current_password']) ? ' has-error' : '' ?>" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= e($errors['current_password']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="new_password">New password <span class="req">*</span></label>
                    <input class="input<?= isset($errors['new_password']) ? ' has-error' : '' ?>" id="new_password" name="new_password" type="password" minlength="10" autocomplete="new-password" required>
                    <span class="hint">Minimum 10 characters. Use a unique password.</span>
                    <?php if (isset($errors['new_password'])): ?><p class="field-error"><?= e($errors['new_password']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="new_password_confirmation">Confirm new password <span class="req">*</span></label>
                    <input class="input<?= isset($errors['new_password_confirmation']) ? ' has-error' : '' ?>" id="new_password_confirmation" name="new_password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                    <?php if (isset($errors['new_password_confirmation'])): ?><p class="field-error"><?= e($errors['new_password_confirmation']) ?></p><?php endif; ?>
                </div>
                <button class="btn btn-primary" type="submit"><i data-lucide="key-round"></i> Save account changes</button>
            </form>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Session & security</h2></div></div>
        <div class="admin-panel-body">
            <div class="spec-table">
                <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                    <dt>Username</dt>
                    <dd>@<?= e($admin['username']) ?></dd>
                </div>
                <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                    <dt>Last sign-in</dt>
                    <dd><?= e(format_date($admin['last_login_at'], true)) ?></dd>
                </div>
                <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                    <dt>Session inactivity limit</dt>
                    <dd>2 hours</dd>
                </div>
                <div class="spec-table-row" style="grid-template-columns:1fr;gap:2px;">
                    <dt>Password algorithm</dt>
                    <dd>PHP password_hash() / bcrypt-compatible default</dd>
                </div>
            </div>
            <p class="small muted" style="margin:14px 0 0;">Signing out rotates the session and invalidates the CSRF token. The sign-out button is in the top bar.</p>
        </div>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
