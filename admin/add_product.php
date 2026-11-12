<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$productRepo = new ProductRepository();
$priceRepo = new PriceHistoryRepository();
$brands = $productRepo->brands();
$retailers = $productRepo->retailers();
$errors = [];

$formData = [
    'brand_id' => '',
    'retailer_id' => '',
    'product_category' => 'laptop',
    'processor_brand' => '',
    'processor_model' => '',
    'processor_generation' => '',
    'processor_cores' => '',
    'ram_gb' => '',
    'ram_type' => '',
    'ram_upgradeable' => 'unknown',
    'storage_type' => '',
    'storage_gb' => '',
    'gpu_brand' => '',
    'gpu_model' => '',
    'gpu_type' => 'unknown',
    'gpu_vram_gb' => '',
    'screen_size' => '',
    'screen_resolution' => '',
    'screen_panel' => '',
    'refresh_rate' => '',
    'operating_system' => '',
    'weight_kg' => '',
    'battery' => '',
    'price_tnd' => '',
    'retailer_sku' => '',
    'product_url' => '',
    'image_url' => '',
    'stock_status' => 'in_stock',
    'warranty_information' => '',
    'source_url' => '',
    'verified_at' => date('Y-m-d\TH:i'),
    'link_status' => 'unchecked',
    'is_active' => 1,
];

if (is_post()) {
    Security::requireCsrf();
    $formData = array_merge($formData, $_POST);
    $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $validator = new ProductValidator();
    $validation = $validator->validate($_POST);
    $errors = $validation['errors'];

    if ($errors === []) {
        try {
            $id = $productRepo->create($validation['data']);
            $priceRepo->record($id, (float) $validation['data']['price_tnd'], (string) $validation['data']['stock_status'], (string) $validation['data']['verified_at']);
            flash('success', 'Product added successfully. Review the record and its price history below.');
            redirect('admin/edit_product.php?id=' . $id);
        } catch (Throwable $exception) {
            error_log('Admin add product failed: ' . $exception->getMessage());
            $errors['general'] = 'The product could not be saved. Check the database and try again.';
        }
    } else {
        flash('error', 'The product was not saved. Correct the highlighted fields.');
    }
}

$adminPageTitle = 'Add product';
$adminActive = 'add-product';
$adminPageSubtitle = 'Create a new catalogue record from a verified retailer listing.';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Add a product</h1>
        <p>Only record specifications and prices that you can verify on the source page. Leave fields empty when the retailer does not document them; do not guess.</p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/products.php')) ?>"><i data-lucide="arrow-left"></i> Back to products</a>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><div><?= e($errors['general']) ?></div></div>
<?php endif; ?>

<?php
$formAction = url('admin/add_product.php');
$submitLabel = 'Add product';
$productId = null;
$isEdit = false;
$history = [];
include __DIR__ . '/_product_form.php';
?>
<?php require __DIR__ . '/_footer.php'; ?>
