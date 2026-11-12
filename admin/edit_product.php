<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$productRepo = new ProductRepository();
$priceRepo = new PriceHistoryRepository();
$product = $productRepo->findAdmin($id);

if (!$product) {
    http_response_code(404);
    $adminPageTitle = 'Product not found';
    require __DIR__ . '/_header.php';
    ?>
    <div class="empty-admin">
        <i data-lucide="package-x"></i>
        <h3>Product not found</h3>
        <p>The record may have been removed.</p>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/products.php')) ?>" style="margin-top:12px;">Back to products</a>
    </div>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$brands = $productRepo->brands();
$retailers = $productRepo->retailers();
$errors = [];
$formData = $product;

if (is_post()) {
    Security::requireCsrf();
    $formData = array_merge($product, $_POST);
    $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $validator = new ProductValidator();
    $validation = $validator->validate($_POST, $id);
    $errors = $validation['errors'];

    if ($errors === []) {
        try {
            $productRepo->update($id, $validation['data']);
            $priceRepo->recordChange(
                $id,
                (float) $validation['data']['price_tnd'],
                (string) $validation['data']['stock_status'],
                (float) $product['price_tnd'],
                (string) $product['stock_status']
            );
            flash('success', 'Product updated. Any price or stock change was added to the history.');
            redirect('admin/edit_product.php?id=' . $id);
        } catch (Throwable $exception) {
            error_log('Admin edit product failed: ' . $exception->getMessage());
            $errors['general'] = 'The product could not be updated. Check the database and try again.';
        }
    } else {
        flash('error', 'The product was not saved. Correct the highlighted fields.');
    }
}

$history = $priceRepo->forProduct($id, 15);
$adminPageTitle = 'Edit product';
$adminActive = 'products';
$adminPageSubtitle = 'Update the record only from verified retailer information.';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Edit product <span class="muted" style="font-size:0.9rem;font-family:var(--font-body);">#<?= (int) $id ?></span></h1>
        <p><?= e(truncate_text($product['product_name'], 100)) ?> · <?= e($product['retailer_name']) ?></p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/products.php')) ?>"><i data-lucide="arrow-left"></i> Products</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('product.php?id=' . (int) $id)) ?>" target="_blank" rel="noopener"><i data-lucide="external-link"></i> View public page</a>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><div><?= e($errors['general']) ?></div></div>
<?php endif; ?>

<?php
$formAction = url('admin/edit_product.php?id=' . $id);
$submitLabel = 'Save changes';
$productId = $id;
$isEdit = true;
include __DIR__ . '/_product_form.php';
?>

<div class="admin-grid" style="margin-top:16px;">
    <section class="admin-panel">
        <div class="admin-panel-head">
            <div><h2>Price and stock history</h2><p>Append-only snapshots. A new row is created when price or stock changes.</p></div>
        </div>
        <div class="admin-panel-body">
            <?php if ($history === []): ?>
                <p class="small muted">No history rows yet.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead><tr><th>Recorded</th><th>Price</th><th>Stock</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?= e(format_date($row['recorded_at'], true)) ?></td>
                                <td><?= e(format_price($row['price_tnd'])) ?></td>
                                <td><span class="<?= e(stock_class($row['stock_status'])) ?>"><?= e(stock_label($row['stock_status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Record status</h2></div></div>
        <div class="admin-panel-body">
            <p class="small muted" style="margin-top:0;">Deactivating preserves the record and its history but removes it from recommendations and public product pages. The public URL will return a 404.</p>
            <form method="post" action="<?= e(url('admin/products.php')) ?>">
                <?= Security::csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="action" value="toggle_active">
                <button class="btn <?= (int) $product['is_active'] === 1 ? 'btn-secondary' : 'btn-primary' ?> btn-sm" type="submit" data-confirm="Toggle visibility for this product?">
                    <i data-lucide="<?= (int) $product['is_active'] === 1 ? 'eye-off' : 'eye' ?>"></i>
                    <?= (int) $product['is_active'] === 1 ? 'Deactivate product' : 'Reactivate product' ?>
                </button>
            </form>
            <div class="admin-quick" style="margin-top:14px;grid-template-columns:1fr;">
                <?php if ($product['product_url']): ?>
                    <a href="<?= e($product['product_url']) ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="external-link"></i> Open retailer listing</a>
                <?php endif; ?>
                <a href="<?= e(url('admin/data_quality.php')) ?>"><i data-lucide="shield-check"></i> Run catalogue quality checks</a>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
