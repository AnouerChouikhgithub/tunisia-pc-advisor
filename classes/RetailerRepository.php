<?php
declare(strict_types=1);

final class RetailerRepository
{
    public function all(): array
    {
        return Database::connection()->query(
            'SELECT r.*, COUNT(p.id) AS product_count
             FROM retailers r
             LEFT JOIN products p ON p.retailer_id = r.id
             GROUP BY r.id ORDER BY r.name ASC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM retailers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO retailers (name, slug, website_url, logo_url, is_active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['website_url'],
            $data['logo_url'] ?: null,
            (int) $data['is_active'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE retailers SET name = ?, slug = ?, website_url = ?, logo_url = ?, is_active = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['website_url'],
            $data['logo_url'] ?: null,
            (int) $data['is_active'],
            $id,
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE retailers SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));
        $website = trim((string) ($input['website_url'] ?? ''));
        $logo = trim((string) ($input['logo_url'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Retailer name is required.';
        }
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors['slug'] = 'Use a lowercase slug containing letters, numbers and hyphens.';
        }
        if (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $website)) {
            $errors['website_url'] = 'Enter the retailer website as a full http(s) URL.';
        }
        if ($logo !== '' && !filter_var($logo, FILTER_VALIDATE_URL)) {
            $errors['logo_url'] = 'The logo URL is not valid.';
        }

        $pdo = Database::connection();
        $sql = 'SELECT id FROM retailers WHERE (name = ? OR slug = ?)' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $params = [$name, $slug];
        if ($excludeId) {
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors['name'] = 'A retailer with this name or slug already exists.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'slug' => $slug,
                'website_url' => $website,
                'logo_url' => $logo,
                'is_active' => !empty($input['is_active']) ? 1 : 0,
            ],
        ];
    }
}
