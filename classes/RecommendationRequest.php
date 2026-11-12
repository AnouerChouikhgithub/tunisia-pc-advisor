<?php
declare(strict_types=1);

final class RecommendationRequest
{
    public function __construct(
        public readonly int $activityId,
        public readonly array $workloadIds,
        public readonly string $category,
        public readonly float $budgetMin,
        public readonly float $budgetMax,
        public readonly string $budgetLabel,
        public readonly int $minRamGb,
        public readonly int $minStorageGb,
        public readonly string $dedicatedGpu,
        public readonly array $preferredBrandIds,
        public readonly string $portability,
        public readonly string $performance,
        public readonly string $battery
    ) {
    }

    public function toSessionArray(): array
    {
        return [
            'activity_id' => $this->activityId,
            'workload_ids' => $this->workloadIds,
            'category' => $this->category,
            'budget_min' => $this->budgetMin,
            'budget_max' => $this->budgetMax,
            'budget_label' => $this->budgetLabel,
            'min_ram_gb' => $this->minRamGb,
            'min_storage_gb' => $this->minStorageGb,
            'dedicated_gpu' => $this->dedicatedGpu,
            'preferred_brand_ids' => $this->preferredBrandIds,
            'portability' => $this->portability,
            'performance' => $this->performance,
            'battery' => $this->battery,
        ];
    }
}
