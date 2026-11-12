<?php
declare(strict_types=1);

final class ProductValidator
{
    /**
     * @return array{errors: array<string, string>, data: array<string, mixed>}
     */
    public function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];
        $string = static fn (string $key): string => trim((string) ($input[$key] ?? ''));
        $nullableString = static function (string $key) use ($string): ?string {
            $value = $string($key);
            return $value === '' ? null : $value;
        };
        $nullableInt = static function (string $key) use ($input): ?int {
            $value = trim((string) ($input[$key] ?? ''));
            return $value === '' ? null : (int) $value;
        };
        $nullableFloat = static function (string $key) use ($input): ?float {
            $value = trim((string) ($input[$key] ?? ''));
            return $value === '' ? null : (float) $value;
        };

        $brandId = (int) ($input['brand_id'] ?? 0);
        $retailerId = (int) ($input['retailer_id'] ?? 0);
        $brand = $brandId > 0 ? Database::connection()->prepare('SELECT id FROM brands WHERE id = ?') : null;
        if ($brand) {
            $brand->execute([$brandId]);
            if (!$brand->fetch()) {
                $errors['brand_id'] = 'Choose an existing brand.';
            }
        } else {
            $errors['brand_id'] = 'Choose a brand.';
        }

        $retailerStmt = Database::connection()->prepare('SELECT * FROM retailers WHERE id = ? LIMIT 1');
        $retailerStmt->execute([$retailerId]);
        $retailer = $retailerStmt->fetch();
        if (!$retailer) {
            $errors['retailer_id'] = 'Choose an existing retailer.';
        }

        $model = $string('model');
        if ($model === '') {
            $errors['model'] = 'Model is required.';
        }
        $productName = $string('product_name');
        if ($productName === '') {
            $errors['product_name'] = 'Product name is required.';
        }
        $category = $string('product_category');
        if (!in_array($category, ['laptop', 'desktop'], true)) {
            $errors['product_category'] = 'Choose laptop or desktop.';
        }

        $processorModel = $string('processor_model');
        if ($processorModel === '') {
            $errors['processor_model'] = 'Processor model is required.';
        }
        $ramGb = (int) ($input['ram_gb'] ?? 0);
        if ($ramGb < 1 || $ramGb > 512) {
            $errors['ram_gb'] = 'RAM must be between 1 and 512 GB.';
        }
        $storageGb = (int) ($input['storage_gb'] ?? 0);
        if ($storageGb < 1 || $storageGb > 10000) {
            $errors['storage_gb'] = 'Storage must be between 1 and 10,000 GB.';
        }
        $storageType = $string('storage_type');
        if ($storageType === '') {
            $errors['storage_type'] = 'Storage type is required (for example NVMe SSD).';
        }
        $ramUpgradeable = $string('ram_upgradeable');
        if (!in_array($ramUpgradeable, ['yes', 'no', 'unknown'], true)) {
            $errors['ram_upgradeable'] = 'Choose a valid RAM upgrade status.';
        }
        $gpuType = $string('gpu_type');
        if (!in_array($gpuType, ['dedicated', 'integrated', 'unknown'], true)) {
            $errors['gpu_type'] = 'Choose a valid graphics type.';
        }
        $gpuVram = $nullableInt('gpu_vram_gb');
        if ($gpuVram !== null && ($gpuVram < 0 || $gpuVram > 64)) {
            $errors['gpu_vram_gb'] = 'VRAM must be between 0 and 64 GB.';
        }
        $cores = $nullableInt('processor_cores');
        if ($cores !== null && ($cores < 1 || $cores > 128)) {
            $errors['processor_cores'] = 'Processor cores must be between 1 and 128.';
        }

        $screenSize = $nullableFloat('screen_size');
        if ($category === 'laptop' && $screenSize === null) {
            $errors['screen_size'] = 'Screen size is required for a laptop.';
        }
        if ($screenSize !== null && ($screenSize < 8 || $screenSize > 32)) {
            $errors['screen_size'] = 'Screen size must be between 8 and 32 inches.';
        }
        $refresh = $nullableInt('refresh_rate');
        if ($refresh !== null && ($refresh < 30 || $refresh > 360)) {
            $errors['refresh_rate'] = 'Refresh rate must be between 30 and 360 Hz.';
        }
        $weight = $nullableFloat('weight_kg');
        if ($weight !== null && ($weight < 0.3 || $weight > 20)) {
            $errors['weight_kg'] = 'Weight must be between 0.3 and 20 kg.';
        }

        $price = (float) ($input['price_tnd'] ?? 0);
        if ($price < 1 || $price > 50000) {
            $errors['price_tnd'] = 'Price must be a positive TND amount below 50,000.';
        }

        $retailerSku = $nullableString('retailer_sku');
        $productUrl = $string('product_url');
        $sourceUrl = $string('source_url');
        $imageUrl = $nullableString('image_url');

        foreach (['product_url' => $productUrl, 'source_url' => $sourceUrl] as $field => $url) {
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url)) {
                $errors[$field] = 'Enter a full http(s) URL.';
            }
        }
        if ($imageUrl !== null && (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $imageUrl))) {
            $errors['image_url'] = 'The image must be a full http(s) URL.';
        }

        if (!isset($errors['product_url']) && $retailer) {
            $parsed = parse_url($productUrl);
            $retailerParsed = parse_url((string) $retailer['website_url']);
            $host = strtolower((string) ($parsed['host'] ?? ''));
            $retailerHost = strtolower((string) ($retailerParsed['host'] ?? ''));
            $normalizeHost = static fn (string $value): string => preg_replace('/^www\./', '', $value) ?? $value;
            $host = $normalizeHost($host);
            $retailerHost = $normalizeHost($retailerHost);
            $path = trim((string) ($parsed['path'] ?? ''), '/');
            $segments = array_values(array_filter(explode('/', $path)));

            if ($host === '' || ($host !== $retailerHost && !str_ends_with($host, '.' . $retailerHost))) {
                $errors['product_url'] = 'The product URL must be on the selected retailer’s domain.';
            } elseif ($path === '' || count($segments) < 2) {
                $errors['product_url'] = 'Use the direct product page URL, not a homepage or category page.';
            }
        }

        $stockStatus = $string('stock_status');
        if (!in_array($stockStatus, ['in_stock', 'out_of_stock', 'limited', 'preorder', 'unknown'], true)) {
            $errors['stock_status'] = 'Choose a valid stock status.';
        }
        $linkStatus = $string('link_status');
        if (!in_array($linkStatus, ['unchecked', 'ok', 'broken'], true)) {
            $errors['link_status'] = 'Choose a valid link status.';
        }

        $verifiedRaw = $string('verified_at');
        $verifiedTimestamp = $verifiedRaw !== '' ? strtotime($verifiedRaw) : false;
        if ($verifiedTimestamp === false) {
            $errors['verified_at'] = 'Enter a valid verification date and time.';
        }

        if ($sourceUrl !== '' && $productUrl !== '' && $sourceUrl === $productUrl) {
            // Expected: the source is commonly the same retailer product page.
        }

        if ($retailer && $retailerSku !== null && $retailerSku !== '') {
            $dupSku = Database::connection()->prepare(
                'SELECT id FROM products WHERE retailer_id = ? AND retailer_sku = ?' .
                ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1'
            );
            $params = [$retailerId, $retailerSku];
            if ($excludeId) {
                $params[] = $excludeId;
            }
            $dupSku->execute($params);
            if ($dupSku->fetch()) {
                $errors['retailer_sku'] = 'This retailer SKU already exists for the selected retailer.';
            }
        }

        if ($productUrl !== '') {
            $hash = hash('sha256', $productUrl);
            $dupUrl = Database::connection()->prepare(
                'SELECT id FROM products WHERE product_url_hash = ?' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1'
            );
            $params = [$hash];
            if ($excludeId) {
                $params[] = $excludeId;
            }
            $dupUrl->execute($params);
            if ($dupUrl->fetch()) {
                $errors['product_url'] = 'This exact product URL is already in the catalogue.';
            }
        }

        $data = [
            'brand_id' => $brandId,
            'retailer_id' => $retailerId,
            'model' => $model,
            'product_name' => $productName,
            'product_category' => $category,
            'processor_brand' => $nullableString('processor_brand'),
            'processor_model' => $processorModel,
            'processor_generation' => $nullableString('processor_generation'),
            'processor_cores' => $cores,
            'ram_gb' => $ramGb,
            'ram_type' => $nullableString('ram_type'),
            'ram_upgradeable' => $ramUpgradeable,
            'storage_type' => $storageType,
            'storage_gb' => $storageGb,
            'gpu_brand' => $nullableString('gpu_brand'),
            'gpu_model' => $nullableString('gpu_model'),
            'gpu_type' => $gpuType,
            'gpu_vram_gb' => $gpuVram,
            'screen_size' => $category === 'desktop' ? null : $screenSize,
            'screen_resolution' => $nullableString('screen_resolution'),
            'screen_panel' => $nullableString('screen_panel'),
            'refresh_rate' => $category === 'desktop' ? null : $refresh,
            'operating_system' => $nullableString('operating_system'),
            'weight_kg' => $category === 'desktop' ? null : $weight,
            'battery' => $nullableString('battery'),
            'price_tnd' => round($price, 3),
            'retailer_sku' => $retailerSku,
            'product_url' => $productUrl,
            'image_url' => $imageUrl,
            'stock_status' => $stockStatus,
            'warranty_information' => $nullableString('warranty_information'),
            'source_url' => $sourceUrl,
            'verified_at' => date('Y-m-d H:i:s', (int) $verifiedTimestamp),
            'link_status' => $linkStatus,
            'link_checked_at' => $linkStatus === 'unchecked' ? null : date('Y-m-d H:i:s'),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];

        return ['errors' => $errors, 'data' => $data];
    }
}
