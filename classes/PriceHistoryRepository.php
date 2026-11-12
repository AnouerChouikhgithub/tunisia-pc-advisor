<?php
declare(strict_types=1);

final class PriceHistoryRepository
{
    public function record(int $productId, float $price, string $stockStatus, ?string $recordedAt = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO product_price_history (product_id, price_tnd, stock_status, recorded_at)
             VALUES (?, ?, ?, COALESCE(?, NOW()))'
        );
        $stmt->execute([$productId, $price, $stockStatus, $recordedAt]);
    }

    public function forProduct(int $productId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT id, price_tnd, stock_status, recorded_at
             FROM product_price_history
             WHERE product_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT $limit"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /**
     * Record a price snapshot only when the price or stock label changed.
     */
    public function recordChange(int $productId, float $price, string $stockStatus, float $oldPrice, string $oldStock): bool
    {
        if (abs($price - $oldPrice) < 0.0005 && $stockStatus === $oldStock) {
            return false;
        }
        $this->record($productId, $price, $stockStatus);
        return true;
    }
}
