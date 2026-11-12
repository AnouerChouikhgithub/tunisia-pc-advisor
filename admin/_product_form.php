<?php
declare(strict_types=1);
if (!defined('APP_ROOT') || empty($formData)) {
    http_response_code(403);
    exit;
}

$value = static fn (string $key, string $default = ''): string => (string) ($formData[$key] ?? $default);
$selected = static fn (string $key, string $expected): string => (string) ($formData[$key] ?? '') === $expected ? 'selected' : '';
$errorFor = static fn (string $key): ?string => $errors[$key] ?? null;
$fieldClass = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$dateValue = '';
if (!empty($formData['verified_at'])) {
    $timestamp = strtotime((string) $formData['verified_at']);
    if ($timestamp !== false) {
        $dateValue = date('Y-m-d\TH:i', $timestamp);
    }
}
?>
<?php if ($errors !== []): ?>
    <div class="admin-alert admin-alert-error" role="alert">
        <i data-lucide="alert-circle"></i>
        <div><strong><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> need attention.</strong> Correct the highlighted values and save again.</div>
    </div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= e($formAction) ?>" enctype="application/x-www-form-urlencoded">
    <?= Security::csrfField() ?>
    <?php if (!empty($productId)): ?>
        <input type="hidden" name="id" value="<?= (int) $productId ?>">
    <?php endif; ?>

    <section class="form-panel">
        <div class="form-panel-head"><span>1</span><h2>Product identity</h2></div>
        <div class="form-panel-body">
            <div class="form-field">
                <label for="brand_id">Brand <span class="req">*</span></label>
                <select class="select<?= $fieldClass('brand_id') ?>" id="brand_id" name="brand_id" required>
                    <option value="">Choose brand</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= (int) $brand['id'] ?>" <?= $selected('brand_id', (string) $brand['id']) ?>><?= e($brand['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('brand_id')): ?><p class="field-error"><?= e($errorFor('brand_id')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="retailer_id">Retailer <span class="req">*</span></label>
                <select class="select<?= $fieldClass('retailer_id') ?>" id="retailer_id" name="retailer_id" required>
                    <option value="">Choose retailer</option>
                    <?php foreach ($retailers as $retailer): ?>
                        <option value="<?= (int) $retailer['id'] ?>" <?= $selected('retailer_id', (string) $retailer['id']) ?>><?= e($retailer['name']) ?><?= (int) $retailer['is_active'] === 0 ? ' (inactive)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('retailer_id')): ?><p class="field-error"><?= e($errorFor('retailer_id')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="product_category">Category <span class="req">*</span></label>
                <select class="select<?= $fieldClass('product_category') ?>" id="product_category" name="product_category" required>
                    <option value="laptop" <?= $selected('product_category', 'laptop') ?>>Laptop</option>
                    <option value="desktop" <?= $selected('product_category', 'desktop') ?>>Desktop PC</option>
                </select>
                <?php if ($errorFor('product_category')): ?><p class="field-error"><?= e($errorFor('product_category')) ?></p><?php endif; ?>
            </div>
            <div class="form-field span-2">
                <label for="product_name">Complete product name <span class="req">*</span></label>
                <input class="input<?= $fieldClass('product_name') ?>" id="product_name" name="product_name" type="text" maxlength="255" value="<?= e($value('product_name')) ?>" required>
                <?php if ($errorFor('product_name')): ?><p class="field-error"><?= e($errorFor('product_name')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="model">Model <span class="req">*</span></label>
                <input class="input<?= $fieldClass('model') ?>" id="model" name="model" type="text" maxlength="180" value="<?= e($value('model')) ?>" required>
                <?php if ($errorFor('model')): ?><p class="field-error"><?= e($errorFor('model')) ?></p><?php endif; ?>
            </div>
            <div class="form-field span-3">
                <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= !empty($formData['is_active']) ? 'checked' : '' ?>> Active and eligible for recommendations</label>
                <span class="hint">Inactive records remain available for audit and price history.</span>
            </div>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-panel-head"><span>2</span><h2>Processor</h2></div>
        <div class="form-panel-body">
            <div class="form-field">
                <label for="processor_brand">Processor manufacturer</label>
                <input class="input" id="processor_brand" name="processor_brand" type="text" list="cpu-brands" maxlength="48" value="<?= e($value('processor_brand')) ?>" placeholder="Intel, AMD, Apple">
                <datalist id="cpu-brands"><option value="Intel"><option value="AMD"><option value="Apple"><option value="Qualcomm"></datalist>
            </div>
            <div class="form-field span-2">
                <label for="processor_model">Processor model <span class="req">*</span></label>
                <input class="input<?= $fieldClass('processor_model') ?>" id="processor_model" name="processor_model" type="text" maxlength="180" value="<?= e($value('processor_model')) ?>" placeholder="Intel Core i7-13620H" required>
                <?php if ($errorFor('processor_model')): ?><p class="field-error"><?= e($errorFor('processor_model')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="processor_generation">Generation / family</label>
                <input class="input" id="processor_generation" name="processor_generation" type="text" maxlength="40" value="<?= e($value('processor_generation')) ?>" placeholder="13th gen, 7000 series">
            </div>
            <div class="form-field">
                <label for="processor_cores">Cores</label>
                <input class="input" id="processor_cores" name="processor_cores" type="number" min="1" max="128" value="<?= e($value('processor_cores')) ?>">
                <?php if ($errorFor('processor_cores')): ?><p class="field-error"><?= e($errorFor('processor_cores')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-panel-head"><span>3</span><h2>Memory and storage</h2></div>
        <div class="form-panel-body">
            <div class="form-field">
                <label for="ram_gb">RAM (GB) <span class="req">*</span></label>
                <input class="input<?= $fieldClass('ram_gb') ?>" id="ram_gb" name="ram_gb" type="number" min="1" max="512" value="<?= e($value('ram_gb')) ?>" required>
                <?php if ($errorFor('ram_gb')): ?><p class="field-error"><?= e($errorFor('ram_gb')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="ram_type">RAM type</label>
                <input class="input" id="ram_type" name="ram_type" type="text" maxlength="32" value="<?= e($value('ram_type')) ?>" placeholder="DDR5, LPDDR5x">
            </div>
            <div class="form-field">
                <label for="ram_upgradeable">Memory upgradeability</label>
                <select class="select" id="ram_upgradeable" name="ram_upgradeable">
                    <option value="unknown" <?= $selected('ram_upgradeable', 'unknown') ?>>Unknown / not stated</option>
                    <option value="yes" <?= $selected('ram_upgradeable', 'yes') ?>>Upgradeable</option>
                    <option value="no" <?= $selected('ram_upgradeable', 'no') ?>>Soldered / not upgradeable</option>
                </select>
            </div>
            <div class="form-field">
                <label for="storage_gb">Storage (GB) <span class="req">*</span></label>
                <input class="input<?= $fieldClass('storage_gb') ?>" id="storage_gb" name="storage_gb" type="number" min="1" max="10000" value="<?= e($value('storage_gb')) ?>" required>
                <?php if ($errorFor('storage_gb')): ?><p class="field-error"><?= e($errorFor('storage_gb')) ?></p><?php endif; ?>
            </div>
            <div class="form-field span-2">
                <label for="storage_type">Storage technology <span class="req">*</span></label>
                <input class="input<?= $fieldClass('storage_type') ?>" id="storage_type" name="storage_type" type="text" maxlength="32" value="<?= e($value('storage_type')) ?>" placeholder="NVMe SSD, SSD, HDD" required>
                <?php if ($errorFor('storage_type')): ?><p class="field-error"><?= e($errorFor('storage_type')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-panel-head"><span>4</span><h2>Graphics, display and physical details</h2></div>
        <div class="form-panel-body">
            <div class="form-field">
                <label for="gpu_type">Graphics type <span class="req">*</span></label>
                <select class="select<?= $fieldClass('gpu_type') ?>" id="gpu_type" name="gpu_type" required>
                    <option value="unknown" <?= $selected('gpu_type', 'unknown') ?>>Unknown / not confirmed</option>
                    <option value="integrated" <?= $selected('gpu_type', 'integrated') ?>>Integrated</option>
                    <option value="dedicated" <?= $selected('gpu_type', 'dedicated') ?>>Dedicated</option>
                </select>
                <?php if ($errorFor('gpu_type')): ?><p class="field-error"><?= e($errorFor('gpu_type')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="gpu_brand">Graphics manufacturer</label>
                <input class="input" id="gpu_brand" name="gpu_brand" type="text" maxlength="48" value="<?= e($value('gpu_brand')) ?>" placeholder="NVIDIA, AMD, Intel, Apple">
            </div>
            <div class="form-field">
                <label for="gpu_model">Graphics model</label>
                <input class="input" id="gpu_model" name="gpu_model" type="text" maxlength="180" value="<?= e($value('gpu_model')) ?>" placeholder="NVIDIA GeForce RTX 4050">
            </div>
            <div class="form-field">
                <label for="gpu_vram_gb">VRAM (GB)</label>
                <input class="input<?= $fieldClass('gpu_vram_gb') ?>" id="gpu_vram_gb" name="gpu_vram_gb" type="number" min="0" max="64" value="<?= e($value('gpu_vram_gb')) ?>">
                <?php if ($errorFor('gpu_vram_gb')): ?><p class="field-error"><?= e($errorFor('gpu_vram_gb')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="screen_resolution">Screen resolution</label>
                <input class="input" id="screen_resolution" name="screen_resolution" type="text" maxlength="40" value="<?= e($value('screen_resolution')) ?>" placeholder="1920 × 1080">
            </div>
            <div class="form-field">
                <label for="operating_system">Operating system</label>
                <input class="input" id="operating_system" name="operating_system" type="text" maxlength="80" value="<?= e($value('operating_system')) ?>" placeholder="Windows 11 Pro, FreeDOS">
            </div>
            <div class="form-field laptop-only" data-laptop-only>
                <label for="screen_size">Screen size (inches)</label>
                <input class="input<?= $fieldClass('screen_size') ?>" id="screen_size" name="screen_size" type="number" min="8" max="32" step="0.1" value="<?= e($value('screen_size')) ?>" data-required-for-laptop="1">
                <?php if ($errorFor('screen_size')): ?><p class="field-error"><?= e($errorFor('screen_size')) ?></p><?php endif; ?>
            </div>
            <div class="form-field laptop-only" data-laptop-only>
                <label for="screen_panel">Panel type</label>
                <select class="select" id="screen_panel" name="screen_panel">
                    <option value="">Not stated</option>
                    <?php foreach (['IPS', 'OLED', 'VA', 'TN'] as $panel): ?>
                        <option value="<?= e($panel) ?>" <?= $selected('screen_panel', $panel) ?>><?= e($panel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field laptop-only" data-laptop-only>
                <label for="refresh_rate">Refresh rate (Hz)</label>
                <input class="input<?= $fieldClass('refresh_rate') ?>" id="refresh_rate" name="refresh_rate" type="number" min="30" max="360" value="<?= e($value('refresh_rate')) ?>">
                <?php if ($errorFor('refresh_rate')): ?><p class="field-error"><?= e($errorFor('refresh_rate')) ?></p><?php endif; ?>
            </div>
            <div class="form-field laptop-only" data-laptop-only>
                <label for="weight_kg">Weight (kg)</label>
                <input class="input<?= $fieldClass('weight_kg') ?>" id="weight_kg" name="weight_kg" type="number" min="0.3" max="20" step="0.01" value="<?= e($value('weight_kg')) ?>">
                <?php if ($errorFor('weight_kg')): ?><p class="field-error"><?= e($errorFor('weight_kg')) ?></p><?php endif; ?>
            </div>
            <div class="form-field laptop-only" data-laptop-only>
                <label for="battery">Battery detail</label>
                <input class="input" id="battery" name="battery" type="text" maxlength="120" value="<?= e($value('battery')) ?>" placeholder="56 Wh, 3-cell">
            </div>
            <div class="form-field">
                <label for="warranty_information">Warranty</label>
                <input class="input" id="warranty_information" name="warranty_information" type="text" maxlength="180" value="<?= e($value('warranty_information')) ?>" placeholder="1 year">
            </div>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-panel-head"><span>5</span><h2>Retailer listing and price</h2></div>
        <div class="form-panel-body">
            <div class="form-field">
                <label for="price_tnd">Price (TND) <span class="req">*</span></label>
                <input class="input<?= $fieldClass('price_tnd') ?>" id="price_tnd" name="price_tnd" type="number" min="1" max="50000" step="0.001" value="<?= e($value('price_tnd')) ?>" required>
                <?php if ($errorFor('price_tnd')): ?><p class="field-error"><?= e($errorFor('price_tnd')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="stock_status">Stock status <span class="req">*</span></label>
                <select class="select<?= $fieldClass('stock_status') ?>" id="stock_status" name="stock_status" required>
                    <?php foreach (['in_stock', 'limited', 'out_of_stock', 'preorder', 'unknown'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $selected('stock_status', $status) ?>><?= e(stock_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('stock_status')): ?><p class="field-error"><?= e($errorFor('stock_status')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="retailer_sku">Retailer SKU / reference</label>
                <input class="input<?= $fieldClass('retailer_sku') ?>" id="retailer_sku" name="retailer_sku" type="text" maxlength="120" value="<?= e($value('retailer_sku')) ?>">
                <?php if ($errorFor('retailer_sku')): ?><p class="field-error"><?= e($errorFor('retailer_sku')) ?></p><?php endif; ?>
            </div>
            <div class="form-field span-3">
                <label for="product_url">Direct product page URL <span class="req">*</span></label>
                <input class="input<?= $fieldClass('product_url') ?>" id="product_url" name="product_url" type="url" maxlength="1000" value="<?= e($value('product_url')) ?>" placeholder="https://www.retailer.tn/product-page" required>
                <span class="hint">Must be the exact product page on the selected retailer’s domain — not its homepage or a search page.</span>
                <?php if ($errorFor('product_url')): ?><p class="field-error"><?= e($errorFor('product_url')) ?></p><?php endif; ?>
            </div>
            <div class="form-field span-3">
                <label for="source_url">Source URL <span class="req">*</span></label>
                <input class="input<?= $fieldClass('source_url') ?>" id="source_url" name="source_url" type="url" maxlength="1000" value="<?= e($value('source_url')) ?>" placeholder="https://..." required>
                <span class="hint">The page used to verify the specifications and price. It may be the same as the retailer listing.</span>
                <?php if ($errorFor('source_url')): ?><p class="field-error"><?= e($errorFor('source_url')) ?></p><?php endif; ?>
            </div>
            <div class="form-field span-2">
                <label for="image_url">Product image URL</label>
                <input class="input<?= $fieldClass('image_url') ?>" id="image_url" name="image_url" type="url" maxlength="1000" value="<?= e($value('image_url')) ?>" placeholder="https://...">
                <?php if ($errorFor('image_url')): ?><p class="field-error"><?= e($errorFor('image_url')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label>Preview</label>
                <div class="image-preview" data-image-preview><span>No image URL</span></div>
            </div>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-panel-head"><span>6</span><h2>Verification and link health</h2></div>
        <div class="form-panel-body">
            <div class="form-field">
                <label for="verified_at">Last verified <span class="req">*</span></label>
                <input class="input<?= $fieldClass('verified_at') ?>" id="verified_at" name="verified_at" type="datetime-local" value="<?= e($dateValue ?: date('Y-m-d\TH:i')) ?>" required>
                <?php if ($errorFor('verified_at')): ?><p class="field-error"><?= e($errorFor('verified_at')) ?></p><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="link_status">Link status</label>
                <select class="select" id="link_status" name="link_status">
                    <option value="unchecked" <?= $selected('link_status', 'unchecked') ?>>Unchecked</option>
                    <option value="ok" <?= $selected('link_status', 'ok') ?>>Working</option>
                    <option value="broken" <?= $selected('link_status', 'broken') ?>>Broken</option>
                </select>
            </div>
            <div class="form-field">
                <label>Record</label>
                <p class="hint" style="margin-top:9px;">
                    <?php if (!empty($formData['created_at'])): ?>Created <?= e(format_date($formData['created_at'], true)) ?><br><?php endif; ?>
                    <?php if (!empty($formData['updated_at'])): ?>Updated <?= e(format_date($formData['updated_at'], true)) ?><?php endif; ?>
                </p>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
        <a class="btn btn-secondary" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
        <?php if (!empty($productId)): ?>
            <a class="btn btn-ghost push-right" href="<?= e(url('product.php?id=' . (int) $productId)) ?>" target="_blank" rel="noopener"><i data-lucide="external-link"></i> View public page</a>
        <?php endif; ?>
    </div>
</form>
