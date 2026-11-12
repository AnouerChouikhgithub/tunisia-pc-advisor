<?php
declare(strict_types=1);
if (!defined('APP_ROOT') || empty($product)) {
    return;
}
$fresh = freshness($product['verified_at'] ?? null);
?>
<article class="product-mini card" data-reveal>
    <a class="product-mini-media" href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>" aria-label="View <?= e($product['product_name']) ?>">
        <img src="<?= e($product['image_url'] ?: product_placeholder()) ?>"
             alt="<?= e($product['product_name']) ?>"
             loading="lazy"
             data-fallback="<?= e(product_placeholder()) ?>">
        <span class="badge"><?= e(ucfirst($product['product_category'])) ?></span>
    </a>
    <div class="product-mini-body">
        <div class="product-mini-brand"><?= e($product['brand_name']) ?></div>
        <h3><a href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>"><?= e(truncate_text($product['product_name'], 78)) ?></a></h3>
        <div class="mini-specs">
            <span class="spec-chip"><i data-lucide="cpu"></i><?= e(truncate_text($product['processor_model'], 28)) ?></span>
            <span class="spec-chip"><i data-lucide="memory-stick"></i><?= (int) $product['ram_gb'] ?> GB</span>
            <span class="spec-chip"><i data-lucide="hard-drive"></i><?= number_format((int) $product['storage_gb']) ?> GB</span>
            <?php if ($product['product_category'] === 'laptop' && $product['screen_size']): ?>
                <span class="spec-chip"><i data-lucide="monitor"></i><?= e(rtrim(rtrim(number_format((float) $product['screen_size'], 1), '0'), '.')) ?>&Prime;</span>
            <?php endif; ?>
        </div>
        <div class="product-mini-foot">
            <div>
                <span class="price-label">Listed price</span>
                <span class="price-value"><?= e(format_price($product['price_tnd'])) ?></span>
                <div class="retailer-name"><?= e($product['retailer_name']) ?></div>
            </div>
            <span class="badge badge-success"><i data-lucide="check-circle-2"></i><?= e(stock_label($product['stock_status'])) ?></span>
        </div>
    </div>
</article>
