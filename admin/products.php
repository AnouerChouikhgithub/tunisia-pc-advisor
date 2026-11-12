<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$productRepo = new ProductRepository();
$priceRepo = new PriceHistoryRepository();

if (is_post()) {
    Security::requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $product = $productRepo->findAdmin($id);

    if (!$product) {
        flash('error', 'That product no longer exists.');
    } else {
        switch ($action) {
            case 'toggle_active':
                $productRepo->setActive($id, (int) $product['is_active'] !== 1);
                flash('success', (int) $product['is_active'] === 1 ? 'Product deactivated. It is hidden from recommendations.' : 'Product reactivated.');
                break;
            case 'mark_out_of_stock':
                $productRepo->setStockStatus($id, 'out_of_stock');
                $priceRepo->record($id, (float) $product['price_tnd'], 'out_of_stock');
                flash('success', 'Stock status changed to out of stock and a price-history snapshot was recorded.');
                break;
            case 'mark_in_stock':
                $productRepo->setStockStatus($id, 'in_stock');
                $priceRepo->record($id, (float) $product['price_tnd'], 'in_stock');
                flash('success', 'Stock status changed to in stock and a price-history snapshot was recorded.');
                break;
            case 'link_ok':
                $productRepo->updateLinkStatus($id, 'ok');
                flash('success', 'Product link marked as working.');
                break;
            case 'link_broken':
                $productRepo->updateLinkStatus($id, 'broken');
                flash('warning', 'Product link marked as broken. The listing remains visible until deactivated.');
                break;
            default:
                flash('error', 'Unknown action.');
        }
    }
    redirect('admin/products.php');
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'brand_id' => (int) ($_GET['brand_id'] ?? 0),
    'retailer_id' => (int) ($_GET['retailer_id'] ?? 0),
    'category' => in_array($_GET['category'] ?? '', ['laptop', 'desktop'], true) ? $_GET['category'] : '',
    'price_min' => trim((string) ($_GET['price_min'] ?? '')),
    'price_max' => trim((string) ($_GET['price_max'] ?? '')),
    'stock_status' => in_array($_GET['stock_status'] ?? '', ['in_stock', 'out_of_stock', 'limited', 'preorder', 'unknown'], true) ? $_GET['stock_status'] : '',
    'active' => in_array($_GET['active'] ?? '', ['0', '1'], true) ? $_GET['active'] : '',
    'link_status' => in_array($_GET['link_status'] ?? '', ['unchecked', 'ok', 'broken'], true) ? $_GET['link_status'] : '',
    'freshness' => in_array($_GET['freshness'] ?? '', ['recent', 'review', 'outdated', 'never'], true) ? $_GET['freshness'] : '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$listing = $productRepo->search($filters, $page, 20);
$brands = $productRepo->brands();
$retailers = $productRepo->retailers();

$adminPageTitle = 'Products';
$adminActive = 'products';
$adminPageSubtitle = 'Search, filter, edit and deactivate catalogue records.';
require __DIR__ . '/_header.php';

$queryForPage = $_GET;
unset($queryForPage['page']);
$baseQuery = http_build_query($queryForPage);
$pageUrl = static fn (int $number): string => url('admin/products.php?' . http_build_query(array_merge($queryForPage, ['page' => $number])));
?>
<div class="admin-page-head">
    <div>
        <h1>Catalogue products</h1>
        <p><?= number_format($listing['total']) ?> matching record<?= $listing['total'] === 1 ? '' : 's' ?>. Inactive and out-of-stock records stay in the database for auditability but are excluded from recommendations.</p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/data_quality.php')) ?>"><i data-lucide="shield-check"></i> Data checks</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/add_product.php')) ?>"><i data-lucide="plus"></i> Add product</a>
    </div>
</div>

<section class="admin-panel">
    <form method="get" action="<?= e(url('admin/products.php')) ?>" class="admin-filter-bar">
        <div class="form-field filter-search">
            <label for="q">Search</label>
            <input class="input" id="q" name="q" type="search" placeholder="Product, model or SKU" value="<?= e($filters['q']) ?>">
        </div>
        <div class="form-field">
            <label for="brand_id">Brand</label>
            <select class="select" id="brand_id" name="brand_id">
                <option value="0">All brands</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?= (int) $brand['id'] ?>" <?= $filters['brand_id'] === (int) $brand['id'] ? 'selected' : '' ?>><?= e($brand['name']) ?> (<?= (int) $brand['product_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="retailer_id">Retailer</label>
            <select class="select" id="retailer_id" name="retailer_id">
                <option value="0">All retailers</option>
                <?php foreach ($retailers as $retailer): ?>
                    <option value="<?= (int) $retailer['id'] ?>" <?= $filters['retailer_id'] === (int) $retailer['id'] ? 'selected' : '' ?>><?= e($retailer['name']) ?> (<?= (int) $retailer['product_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="category">Category</label>
            <select class="select" id="category" name="category">
                <option value="">All categories</option>
                <option value="laptop" <?= $filters['category'] === 'laptop' ? 'selected' : '' ?>>Laptop</option>
                <option value="desktop" <?= $filters['category'] === 'desktop' ? 'selected' : '' ?>>Desktop</option>
            </select>
        </div>
        <div class="form-field">
            <label for="stock_status">Stock</label>
            <select class="select" id="stock_status" name="stock_status">
                <option value="">Any stock</option>
                <?php foreach (['in_stock', 'limited', 'out_of_stock', 'preorder', 'unknown'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['stock_status'] === $status ? 'selected' : '' ?>><?= e(stock_label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="freshness">Verification</label>
            <select class="select" id="freshness" name="freshness">
                <option value="">Any date</option>
                <option value="recent" <?= $filters['freshness'] === 'recent' ? 'selected' : '' ?>>Recent (0–7d)</option>
                <option value="review" <?= $filters['freshness'] === 'review' ? 'selected' : '' ?>>Needs review (8–30d)</option>
                <option value="outdated" <?= $filters['freshness'] === 'outdated' ? 'selected' : '' ?>>Outdated (30d+)</option>
                <option value="never" <?= $filters['freshness'] === 'never' ? 'selected' : '' ?>>Never verified</option>
            </select>
        </div>
        <div class="form-field">
            <label for="active">Visibility</label>
            <select class="select" id="active" name="active">
                <option value="">All records</option>
                <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="form-field">
            <label for="link_status">Link status</label>
            <select class="select" id="link_status" name="link_status">
                <option value="">Any link</option>
                <option value="ok" <?= $filters['link_status'] === 'ok' ? 'selected' : '' ?>>Working</option>
                <option value="broken" <?= $filters['link_status'] === 'broken' ? 'selected' : '' ?>>Broken</option>
                <option value="unchecked" <?= $filters['link_status'] === 'unchecked' ? 'selected' : '' ?>>Unchecked</option>
            </select>
        </div>
        <div class="form-field" style="max-width:105px;">
            <label for="price_min">Min TND</label>
            <input class="input" id="price_min" name="price_min" type="number" min="0" step="1" value="<?= e((string) $filters['price_min']) ?>">
        </div>
        <div class="form-field" style="max-width:105px;">
            <label for="price_max">Max TND</label>
            <input class="input" id="price_max" name="price_max" type="number" min="0" step="1" value="<?= e((string) $filters['price_max']) ?>">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary btn-sm" type="submit"><i data-lucide="filter"></i> Filter</button>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/products.php')) ?>">Reset</a>
        </div>
    </form>

    <?php if ($listing['items'] === []): ?>
        <div class="empty-admin">
            <i data-lucide="search-x"></i>
            <h3>No products match these filters</h3>
            <p>Try clearing one or more filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Verified</th>
                        <th>Link</th>
                        <th>Visibility</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listing['items'] as $product): $fresh = freshness($product['verified_at']); ?>
                        <tr>
                            <td>
                                <div class="table-product">
                                    <img src="<?= e($product['image_url'] ?: product_placeholder()) ?>" alt="" loading="lazy">
                                    <div>
                                        <strong><?= e(truncate_text($product['product_name'], 64)) ?></strong>
                                        <small><?= e($product['brand_name']) ?> · <?= e($product['retailer_name']) ?> · #<?= (int) $product['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= e(format_price($product['price_tnd'])) ?></td>
                            <td><span class="<?= e(stock_class($product['stock_status'])) ?>"><?= e(stock_label($product['stock_status'])) ?></span></td>
                            <td>
                                <span class="<?= e($fresh['class']) ?>"><?= e($fresh['label']) ?></span><br>
                                <span class="muted"><?= e(format_date($product['verified_at'])) ?></span>
                            </td>
                            <td>
                                <?php if ($product['link_status'] === 'ok'): ?><span class="badge badge-success">OK</span>
                                <?php elseif ($product['link_status'] === 'broken'): ?><span class="badge badge-danger">Broken</span>
                                <?php else: ?><span class="badge badge-neutral">Unchecked</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $product['is_active'] === 1): ?><span class="badge badge-success">Active</span>
                                <?php else: ?><span class="badge badge-neutral">Inactive</span><?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/edit_product.php?id=' . (int) $product['id'])) ?>">Edit</a>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>" target="_blank" rel="noopener" aria-label="View public product page"><i data-lucide="external-link"></i></a>
                                    <form method="post" action="<?= e(url('admin/products.php')) ?>">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <button class="btn btn-ghost btn-sm" type="submit" data-confirm="Toggle visibility for this product? Inactive products are excluded from recommendations." aria-label="Toggle product visibility"><i data-lucide="<?= (int) $product['is_active'] === 1 ? 'eye-off' : 'eye' ?>"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <p>Showing <?= count($listing['items']) ?> of <?= number_format($listing['total']) ?> · page <?= (int) $listing['page'] ?> of <?= (int) $listing['pages'] ?></p>
            <div class="pagination-links">
                <?php if ($listing['page'] > 1): ?>
                    <a href="<?= e($pageUrl($listing['page'] - 1)) ?>" aria-label="Previous page">‹</a>
                <?php endif; ?>
                <?php for ($number = max(1, $listing['page'] - 2); $number <= min($listing['pages'], $listing['page'] + 2); $number++): ?>
                    <?php if ($number === (int) $listing['page']): ?>
                        <span class="is-current"><?= $number ?></span>
                    <?php else: ?>
                        <a href="<?= e($pageUrl($number)) ?>"><?= $number ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($listing['page'] < $listing['pages']): ?>
                    <a href="<?= e($pageUrl($listing['page'] + 1)) ?>" aria-label="Next page">›</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
