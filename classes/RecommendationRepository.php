<?php
declare(strict_types=1);

final class RecommendationRepository
{
    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT rs.*, a.slug AS activity_slug
             FROM recommendation_sessions rs
             INNER JOIN activities a ON a.id = rs.activity_id
             WHERE rs.token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        return $session ?: null;
    }

    public function resultsFor(int $sessionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT rr.compatibility_score, rr.explanation, rr.rank_position, rr.score_breakdown_json,
                    p.*, b.name AS brand_name, r.name AS retailer_name,
                    r.website_url AS retailer_website, r.slug AS retailer_slug
             FROM recommendation_results rr
             INNER JOIN products p ON p.id = rr.product_id
             INNER JOIN brands b ON b.id = p.brand_id
             INNER JOIN retailers r ON r.id = p.retailer_id
             WHERE rr.session_id = ?
             ORDER BY rr.rank_position ASC'
        );
        $stmt->execute([$sessionId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['score_breakdown'] = $row['score_breakdown_json']
                ? (json_decode($row['score_breakdown_json'], true) ?: [])
                : [];
            unset($row['score_breakdown_json']);
        }
        unset($row);
        return $rows;
    }

    public function stats(): array
    {
        $pdo = Database::connection();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM recommendation_sessions')->fetchColumn();
        $week = (int) $pdo->query(
            'SELECT COUNT(*) FROM recommendation_sessions WHERE created_at >= (NOW() - INTERVAL 7 DAY)'
        )->fetchColumn();
        $avgResults = (float) ($pdo->query('SELECT AVG(result_count) FROM recommendation_sessions')->fetchColumn() ?: 0);
        return ['total' => $total, 'last_7_days' => $week, 'average_results' => $avgResults];
    }

    public function recent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return Database::connection()->query(
            "SELECT token, activity_name, product_category, budget_max, result_count, created_at
             FROM recommendation_sessions ORDER BY created_at DESC LIMIT $limit"
        )->fetchAll();
    }
}
