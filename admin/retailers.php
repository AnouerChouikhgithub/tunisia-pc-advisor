<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$repo = new RetailerRepository();
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$editRetailer = $editId > 0 ? $repo->find($editId) : null;
$formData = $editRetailer ?: ['name' => '', 'slug' => '', 'website_url' => '', 'logo_url' => '', 'is_active' => 1];

if (is_post()) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $retailer = $repo->find($id);
        if ($retailer) {
            $repo->setActive($id, (int) $retailer['is_active'] !== 1);
            flash('success', (int) $retailer['is_active'] === 1 ? 'Retailer deactivated.' : 'Retailer activated.');
        } else {
            flash('error', 'Retailer not found.');
        }
        redirect('admin/retailers.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $validation = $repo->validate($_POST, $id > 0 ? $id : null);
    $errors = $validation['errors'];
    $formData = array_merge($formData, $_POST, ['is_active' => isset($_POST['is_active']) ? 1 : 0]);

    if ($errors === []) {
        try {
            if ($id > 0) {
                $repo->update($id, $validation['data']);
                flash('success', 'Retailer updated.');
            } else {
                $repo->create($validation['data']);
                flash('success', 'Retailer added.');
            }
            redirect('admin/retailers.php');
        } catch (Throwable $exception) {
            error_log('Retailer save failed: ' . $exception->getMessage());
            $errors['general'] = 'The retailer could not be saved.';
        }
    }
}

$retailers = $repo->all();
$adminPageTitle = 'Retailers';
$adminActive = 'retailers';
$adminPageSubtitle = 'Manage the stores whose product pages supply catalogue prices and specifications.';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Retailers</h1>
        <p>A retailer must be active before its listings can appear in recommendations. Inactive retailers keep their products for audit but those products should also be deactivated.</p>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><div><?= e($errors['general']) ?></div></div>
<?php endif; ?>

<div class="admin-grid">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>All retailers</h2><p><?= count($retailers) ?> records</p></div></div>
        <div class="admin-panel-body no-pad">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Retailer</th><th>Website</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($retailers as $retailer): ?>
                            <tr>
                                <td><strong><?= e($retailer['name']) ?></strong><br><span class="muted"><?= e($retailer['slug']) ?></span></td>
                                <td><a href="<?= e($retailer['website_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(parse_url($retailer['website_url'], PHP_URL_HOST) ?: $retailer['website_url']) ?> <i data-lucide="external-link" style="width:11px;height:11px;"></i></a></td>
                                <td><?= number_format((int) $retailer['product_count']) ?></td>
                                <td><?= (int) $retailer['is_active'] === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/retailers.php?edit=' . (int) $retailer['id'])) ?>">Edit</a>
                                        <form method="post" action="<?= e(url('admin/retailers.php')) ?>">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $retailer['id'] ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit" data-confirm="Toggle this retailer's active status?"><i data-lucide="<?= (int) $retailer['is_active'] === 1 ? 'eye-off' : 'eye' ?>"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <div><h2><?= $editRetailer ? 'Edit retailer' : 'Add retailer' ?></h2><p>Website is required for product-domain validation.</p></div>
            <?php if ($editRetailer): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/retailers.php')) ?>">Cancel edit</a><?php endif; ?>
        </div>
        <div class="admin-panel-body">
            <form method="post" action="<?= e(url('admin/retailers.php')) ?>" class="admin-form">
                <?= Security::csrfField() ?>
                <?php if ($editRetailer): ?><input type="hidden" name="id" value="<?= (int) $editRetailer['id'] ?>"><?php endif; ?>

                <div class="form-field">
                    <label for="name">Name <span class="req">*</span></label>
                    <input class="input<?= isset($errors['name']) ? ' has-error' : '' ?>" id="name" name="name" type="text" required maxlength="120" value="<?= e($formData['name'] ?? '') ?>">
                    <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="slug">Slug <span class="req">*</span></label>
                    <input class="input<?= isset($errors['slug']) ? ' has-error' : '' ?>" id="slug" name="slug" type="text" required maxlength="120" value="<?= e($formData['slug'] ?? '') ?>" placeholder="tunisia-net">
                    <span class="hint">Lowercase letters, numbers and hyphens.</span>
                    <?php if (isset($errors['slug'])): ?><p class="field-error"><?= e($errors['slug']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="website_url">Website URL <span class="req">*</span></label>
                    <input class="input<?= isset($errors['website_url']) ? ' has-error' : '' ?>" id="website_url" name="website_url" type="url" required maxlength="500" value="<?= e($formData['website_url'] ?? '') ?>" placeholder="https://www.example.tn/">
                    <?php if (isset($errors['website_url'])): ?><p class="field-error"><?= e($errors['website_url']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="logo_url">Logo URL</label>
                    <input class="input<?= isset($errors['logo_url']) ? ' has-error' : '' ?>" id="logo_url" name="logo_url" type="url" maxlength="500" value="<?= e($formData['logo_url'] ?? '') ?>">
                    <?php if (isset($errors['logo_url'])): ?><p class="field-error"><?= e($errors['logo_url']) ?></p><?php endif; ?>
                </div>
                <div class="form-field">
                    <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= !empty($formData['is_active']) ? 'checked' : '' ?>> Active retailer</label>
                </div>
                <button class="btn btn-primary" type="submit"><i data-lucide="save"></i> <?= $editRetailer ? 'Save retailer' : 'Add retailer' ?></button>
            </form>
        </div>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
