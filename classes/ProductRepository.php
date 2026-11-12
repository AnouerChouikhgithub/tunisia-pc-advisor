<?php
declare(strict_types=1);

final class ProductRepository
{
    private const SELECT = 'SELECT p.*, b.name AS brand_name, r.name AS retailer_name,
                                   r.website_url AS retailer_website, r.slug AS retailer_slug
                            FROM products p
                            INNER JOIN brands b ON b.id = p.brand_id
                            INNER JOIN retailers r ON r.id = p.retailer_id';

    public function find(int $id, bool $activeOnly = true): ?array
    {
        $sql = self::SELECT . ' WHERE p.id = ?' . ($activeOnly ? ' AND p.is_active = 1' : '') . ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function findManyByIds(array $ids, bool $activeOnly = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = self::SELECT . " WHERE p.id IN ($placeholders)" . ($activeOnly ? ' AND p.is_active = 1' : '');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    /**
     * Mandatory filters only: active, purchasable, budget ceiling, category and
     * explicit user minimums. Soft preferences are scored, never silently dropped.
     */
    public function candidatesFor(RecommendationRequest $request, int $limit = 600): array
    {
        $sql = self::SELECT . " WHERE p.is_active = 1
                AND p.price_tnd <= :budget
                AND p.stock_status IN ('in_stock', 'limited')";
        $params = [':budget' => $request->budgetMax];

        if ($request->category !== 'both') {
            $sql .= ' AND p.product_category = :category';
            $params[':category'] = $request->category;
        }
        if ($request->minRamGb > 0) {
            $sql .= ' AND p.ram_gb >= :min_ram';
            $params[':min_ram'] = $request->minRamGb;
        }
        if ($request->minStorageGb > 0) {
            $sql .= ' AND p.storage_gb >= :min_storage';
            $params[':min_storage'] = $request->minStorageGb;
        }
        if ($request->dedicatedGpu === 'yes') {
            $sql .= " AND p.gpu_type = 'dedicated'";
        }

        $limit = max(1, min(1000, $limit));
        $sql .= " ORDER BY p.price_tnd ASC LIMIT $limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function latest(int $limit = 3): array
    {
        $limit = max(1, min(24, $limit));
        $sql = self::SELECT . " WHERE p.is_active = 1 AND p.stock_status IN ('in_stock', 'limited')
                AND p.image_url IS NOT NULL
                ORDER BY p.verified_at DESC, p.id DESC LIMIT $limit";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function relatedFor(array $product, int $limit = 3): array
    {
        $limit = max(1, min(6, $limit));
        $price = (float) $product['price_tnd'];
        $sql = self::SELECT . " WHERE p.is_active = 1
                AND p.stock_status IN ('in_stock', 'limited')
                AND p.id <> :id
                AND p.product_category = :category
                AND p.price_tnd BETWEEN :low AND :high
                ORDER BY (p.brand_id = :brand_id) DESC, ABS(p.price_tnd - :price) ASC
                LIMIT $limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':id' => (int) $product['id'],
            ':category' => $product['product_category'],
            ':low' => $price * 0.65,
            ':high' => $price * 1.35,
            ':brand_id' => (int) $product['brand_id'],
            ':price' => $price,
        ]);
        return $stmt->fetchAll();
    }

    public function statistics(): array
    {
        $pdo = Database::connection();
        $row = $pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(product_category = 'laptop') AS laptops,
                    SUM(product_category = 'desktop') AS desktops,
                    AVG(price_tnd) AS average_price,
                    SUM(stock_status = 'out_of_stock') AS out_of_stock,
                    SUM(link_status = 'broken') AS broken_links,
                    SUM(is_active = 0) AS inactive,
                    SUM(verified_at IS NULL OR verified_at < (NOW() - INTERVAL 30 DAY)) AS needing_verification,
                    SUM(verified_at >= (NOW() - INTERVAL 7 DAY)) AS recently_verified
             FROM products"
        )->fetch();
        $retailers = (int) $pdo->query('SELECT COUNT(*) FROM retailers WHERE is_active = 1')->fetchColumn();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'laptops' => (int) ($row['laptops'] ?? 0),
            'desktops' => (int) ($row['desktops'] ?? 0),
            'retailers' => $retailers,
            'average_price' => (float) ($row['average_price'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
            'broken_links' => (int) ($row['broken_links'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'needing_verification' => (int) ($row['needing_verification'] ?? 0),
            'recently_verified' => (int) ($row['recently_verified'] ?? 0),
        ];
    }

    public function brands(): array
    {
        return Database::connection()
            ->query('SELECT b.id, b.name, COUNT(p.id) AS product_count
                     FROM brands b LEFT JOIN products p ON p.brand_id = b.id AND p.is_active = 1
                     GROUP BY b.id, b.name ORDER BY b.name ASC')
            ->fetchAll();
    }

    public function retailers(): array
    {
        return Database::connection()
            ->query('SELECT r.*, COUNT(p.id) AS product_count
                     FROM retailers r LEFT JOIN products p ON p.retailer_id = r.id AND p.is_active = 1
                     GROUP BY r.id ORDER BY r.name ASC')
            ->fetchAll();
    }

    public function activeRetailers(): array
    {
        return Database::connection()
            ->query('SELECT * FROM retailers WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    public function findAdmin(int $id): ?array
    {
        return $this->find($id, false);
    }

    public function findByUrlHash(string $hash, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id, product_name, product_url FROM products WHERE product_url_hash = ?';
        $params = [$hash];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySku(int $retailerId, string $sku, ?int $excludeId = null): ?array
    {
        if ($sku === '') {
            return null;
        }
        $sql = 'SELECT id, product_name FROM products WHERE retailer_id = ? AND retailer_sku = ?';
        $params = [$retailerId, $sku];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $columns = [
            'brand_id', 'retailer_id', 'model', 'product_name', 'product_category',
            'processor_brand', 'processor_model', 'processor_generation', 'processor_cores',
            'ram_gb', 'ram_type', 'ram_upgradeable', 'storage_type', 'storage_gb',
            'gpu_brand', 'gpu_model', 'gpu_type', 'gpu_vram_gb',
            'screen_size', 'screen_resolution', 'screen_panel', 'refresh_rate',
            'operating_system', 'weight_kg', 'battery', 'price_tnd',
            'retailer_sku', 'product_url', 'product_url_hash', 'image_url',
            'stock_status', 'warranty_information', 'source_url', 'verified_at',
            'link_status', 'link_checked_at', 'is_active',
        ];
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $data[$column] ?? null;
        }
        $values['product_url_hash'] = hash('sha256', (string) $values['product_url']);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = Database::connection()->prepare(
            'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );
        $stmt->execute(array_values($values));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $columns = [
            'brand_id', 'retailer_id', 'model', 'product_name', 'product_category',
            'processor_brand', 'processor_model', 'processor_generation', 'processor_cores',
            'ram_gb', 'ram_type', 'ram_upgradeable', 'storage_type', 'storage_gb',
            'gpu_brand', 'gpu_model', 'gpu_type', 'gpu_vram_gb',
            'screen_size', 'screen_resolution', 'screen_panel', 'refresh_rate',
            'operating_system', 'weight_kg', 'battery', 'price_tnd',
            'retailer_sku', 'product_url', 'image_url',
            'stock_status', 'warranty_information', 'source_url', 'verified_at',
            'link_status', 'link_checked_at', 'is_active',
        ];
        $sets = [];
        $values = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = "$column = ?";
                $values[] = $data[$column];
            }
        }
        if (array_key_exists('product_url', $data)) {
            $sets[] = 'product_url_hash = ?';
            $values[] = hash('sha256', (string) $data['product_url']);
        }
        if ($sets === []) {
            return;
        }
        $values[] = $id;
        $stmt = Database::connection()->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE products SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function updateStockAndVerification(int $id, string $stockStatus, ?string $verifiedAt = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE products SET stock_status = ?, verified_at = COALESCE(?, NOW()) WHERE id = ?'
        );
        $stmt->execute([$stockStatus, $verifiedAt, $id]);
    }

    public function setStockStatus(int $id, string $stockStatus): void
    {
        $stmt = Database::connection()->prepare('UPDATE products SET stock_status = ? WHERE id = ?');
        $stmt->execute([$stockStatus, $id]);
    }

    public function updateLinkStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE products SET link_status = ?, link_checked_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
    }

    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(p.product_name LIKE ? OR p.model LIKE ? OR p.retailer_sku LIKE ?)';
            $needle = '%' . $filters['q'] . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }
        if (!empty($filters['brand_id'])) {
            $where[] = 'p.brand_id = ?';
            $params[] = (int) $filters['brand_id'];
        }
        if (!empty($filters['retailer_id'])) {
            $where[] = 'p.retailer_id = ?';
            $params[] = (int) $filters['retailer_id'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'p.product_category = ?';
            $params[] = $filters['category'];
        }
        if (isset($filters['price_min']) && $filters['price_min'] !== '') {
            $where[] = 'p.price_tnd >= ?';
            $params[] = (float) $filters['price_min'];
        }
        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $where[] = 'p.price_tnd <= ?';
            $params[] = (float) $filters['price_max'];
        }
        if (!empty($filters['stock_status'])) {
            $where[] = 'p.stock_status = ?';
            $params[] = $filters['stock_status'];
        }
        if (isset($filters['active']) && $filters['active'] !== '') {
            $where[] = 'p.is_active = ?';
            $params[] = (int) $filters['active'];
        }
        if (!empty($filters['link_status'])) {
            $where[] = 'p.link_status = ?';
            $params[] = $filters['link_status'];
        }
        if (!empty($filters['freshness'])) {
            if ($filters['freshness'] === 'recent') {
                $where[] = 'p.verified_at >= (NOW() - INTERVAL 7 DAY)';
            } elseif ($filters['freshness'] === 'review') {
                $where[] = 'p.verified_at < (NOW() - INTERVAL 7 DAY) AND p.verified_at >= (NOW() - INTERVAL 30 DAY)';
            } elseif ($filters['freshness'] === 'outdated') {
                $where[] = 'p.verified_at < (NOW() - INTERVAL 30 DAY)';
            } elseif ($filters['freshness'] === 'never') {
                $where[] = 'p.verified_at IS NULL';
            }
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = Database::connection()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $sql = self::SELECT . " WHERE $whereSql ORDER BY p.updated_at DESC, p.id DESC LIMIT $perPage OFFSET $offset";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function dataQualityChecks(): array
    {
        $pdo = Database::connection();
        $duplicateUrls = $pdo->query(
            'SELECT product_url, COUNT(*) AS n FROM products GROUP BY product_url HAVING n > 1'
        )->fetchAll();
        $duplicateSkus = $pdo->query(
            "SELECT retailer_id, retailer_sku, COUNT(*) AS n
             FROM products WHERE retailer_sku IS NOT NULL AND retailer_sku <> ''
             GROUP BY retailer_id, retailer_sku HAVING n > 1"
        )->fetchAll();
        $missing = $pdo->query(
            "SELECT id, product_name,
                    (processor_model IS NULL OR processor_model = '') AS missing_cpu,
                    (ram_gb IS NULL OR ram_gb = 0) AS missing_ram,
                    (storage_gb IS NULL OR storage_gb = 0) AS missing_storage,
                    (gpu_model IS NULL OR gpu_model = '') AS missing_gpu,
                    (screen_size IS NULL AND product_category = 'laptop') AS missing_screen,
                    (source_url IS NULL OR source_url = '') AS missing_source,
                    (verified_at IS NULL) AS missing_verification
             FROM products
             HAVING missing_cpu + missing_ram + missing_storage + missing_gpu + missing_screen + missing_source + missing_verification > 0"
        )->fetchAll();
        $stale = (int) $pdo->query(
            'SELECT COUNT(*) FROM products WHERE verified_at IS NULL OR verified_at < (NOW() - INTERVAL 30 DAY)'
        )->fetchColumn();
        $invalidUrls = $pdo->query(
            "SELECT id, product_name, product_url FROM products
             WHERE product_url NOT LIKE 'https://%' AND product_url NOT LIKE 'http://%'"
        )->fetchAll();
        $priceStats = $pdo->query(
            'SELECT MIN(price_tnd) AS min_price, MAX(price_tnd) AS max_price, AVG(price_tnd) AS avg_price FROM products'
        )->fetch();
        $categories = $pdo->query(
            'SELECT product_category, COUNT(*) AS n FROM products GROUP BY product_category'
        )->fetchAll();
        $retailerCounts = $pdo->query(
            'SELECT r.name, COUNT(p.id) AS n FROM retailers r
             LEFT JOIN products p ON p.retailer_id = r.id
             GROUP BY r.id, r.name ORDER BY n DESC'
        )->fetchAll();

        return compact('duplicateUrls', 'duplicateSkus', 'missing', 'stale', 'invalidUrls', 'priceStats', 'categories', 'retailerCounts');
    }
}
