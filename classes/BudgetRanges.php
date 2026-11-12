<?php
declare(strict_types=1);

final class BudgetRanges
{
    public const RANGES = [
        'under_1000' => ['label' => 'Under 1,000 TND', 'min' => 0.0, 'max' => 1000.0],
        '1000_1500' => ['label' => '1,000 – 1,500 TND', 'min' => 1000.0, 'max' => 1500.0],
        '1500_2000' => ['label' => '1,500 – 2,000 TND', 'min' => 1500.0, 'max' => 2000.0],
        '2000_2500' => ['label' => '2,000 – 2,500 TND', 'min' => 2000.0, 'max' => 2500.0],
        '2500_3000' => ['label' => '2,500 – 3,000 TND', 'min' => 2500.0, 'max' => 3000.0],
        '3000_4000' => ['label' => '3,000 – 4,000 TND', 'min' => 3000.0, 'max' => 4000.0],
        '4000_5000' => ['label' => '4,000 – 5,000 TND', 'min' => 4000.0, 'max' => 5000.0],
        '5000_7000' => ['label' => '5,000 – 7,000 TND', 'min' => 5000.0, 'max' => 7000.0],
        'over_7000' => ['label' => 'More than 7,000 TND', 'min' => 7000.0, 'max' => 20000.0],
    ];

    public static function all(): array
    {
        return self::RANGES;
    }

    public static function get(string $key): ?array
    {
        return self::RANGES[$key] ?? null;
    }
}
