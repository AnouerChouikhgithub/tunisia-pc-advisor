<?php
declare(strict_types=1);

final class QuestionnaireValidator
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly ProductRepository $products
    ) {
    }

    /**
     * @return array{request: ?RecommendationRequest, errors: array<string, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];

        $activityId = filter_var($input['activity_id'] ?? null, FILTER_VALIDATE_INT);
        $activity = $activityId ? $this->activities->find((int) $activityId) : null;
        if (!$activity) {
            $errors['activity_id'] = 'Choose the activity that best describes your main work.';
        }

        $rawWorkloads = $input['workloads'] ?? [];
        if (!is_array($rawWorkloads)) {
            $rawWorkloads = [];
        }
        $validWorkloadIds = $this->activities->validWorkloadIds();
        $workloadIds = [];
        foreach ($rawWorkloads as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id && in_array((int) $id, $validWorkloadIds, true)) {
                $workloadIds[] = (int) $id;
            }
        }
        $workloadIds = array_values(array_unique($workloadIds));
        if ($workloadIds === []) {
            $errors['workloads'] = 'Select at least one intended use.';
        }

        $category = (string) ($input['category'] ?? '');
        if (!in_array($category, ['laptop', 'desktop', 'both'], true)) {
            $errors['category'] = 'Choose laptop, desktop, or both / no preference.';
        }

        $budgetMin = 0.0;
        $budgetMax = 0.0;
        $budgetLabel = '';
        $customBudget = trim((string) ($input['custom_budget'] ?? ''));
        $budgetRangeKey = (string) ($input['budget_range'] ?? '');

        if ($customBudget !== '') {
            if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $customBudget)) {
                $errors['custom_budget'] = 'Enter a maximum budget as a number, for example 2500.';
            } else {
                $customValue = (float) $customBudget;
                if ($customValue < 300 || $customValue > 20000) {
                    $errors['custom_budget'] = 'Enter a maximum budget between 300 and 20,000 TND.';
                } else {
                    $budgetMax = round($customValue, 3);
                    $budgetMin = 0.0;
                    $budgetLabel = 'Up to ' . format_price($budgetMax);
                }
            }
        } elseif ($budgetRangeKey !== '') {
            $range = BudgetRanges::get($budgetRangeKey);
            if (!$range) {
                $errors['budget_range'] = 'Choose a valid budget range.';
            } else {
                $budgetMin = (float) $range['min'];
                $budgetMax = (float) $range['max'];
                $budgetLabel = (string) $range['label'];
            }
        } else {
            $errors['budget_range'] = 'Choose a budget range or enter a custom maximum budget.';
        }

        $minRam = (int) ($input['min_ram'] ?? 0);
        if (!in_array($minRam, [0, 8, 16, 32, 64], true)) {
            $minRam = 0;
        }
        $minStorage = (int) ($input['min_storage'] ?? 0);
        if (!in_array($minStorage, [0, 256, 512, 1024, 2048], true)) {
            $minStorage = 0;
        }

        $dedicatedGpu = (string) ($input['dedicated_gpu'] ?? 'any');
        if (!in_array($dedicatedGpu, ['yes', 'no', 'any'], true)) {
            $dedicatedGpu = 'any';
        }

        $rawBrands = $input['brand_ids'] ?? [];
        if (!is_array($rawBrands)) {
            $rawBrands = [];
        }
        $validBrandIds = array_map(static fn (array $brand): int => (int) $brand['id'], $this->products->brands());
        $brandIds = [];
        foreach ($rawBrands as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id && in_array((int) $id, $validBrandIds, true)) {
                $brandIds[] = (int) $id;
            }
        }
        $brandIds = array_values(array_unique($brandIds));

        $importance = static function (mixed $value, string $fallback = 'any'): string {
            return in_array($value, ['low', 'medium', 'high', 'any'], true) ? (string) $value : $fallback;
        };
        $portability = $importance($input['portability'] ?? 'any');
        $performance = $importance($input['performance'] ?? 'any');
        $battery = $importance($input['battery'] ?? 'any');

        if ($errors !== []) {
            return ['request' => null, 'errors' => $errors];
        }

        $request = new RecommendationRequest(
            activityId: (int) $activityId,
            workloadIds: $workloadIds,
            category: $category,
            budgetMin: $budgetMin,
            budgetMax: $budgetMax,
            budgetLabel: $budgetLabel,
            minRamGb: $minRam,
            minStorageGb: $minStorage,
            dedicatedGpu: $dedicatedGpu,
            preferredBrandIds: $brandIds,
            portability: $portability,
            performance: $performance,
            battery: $battery
        );

        return ['request' => $request, 'errors' => []];
    }

    public static function oldInput(array $input): array
    {
        return [
            'activity_id' => (string) ($input['activity_id'] ?? ''),
            'workloads' => is_array($input['workloads'] ?? null) ? array_map('strval', $input['workloads']) : [],
            'category' => (string) ($input['category'] ?? ''),
            'budget_range' => (string) ($input['budget_range'] ?? ''),
            'custom_budget' => (string) ($input['custom_budget'] ?? ''),
            'min_ram' => (string) ($input['min_ram'] ?? ''),
            'min_storage' => (string) ($input['min_storage'] ?? ''),
            'dedicated_gpu' => (string) ($input['dedicated_gpu'] ?? 'any'),
            'brand_ids' => is_array($input['brand_ids'] ?? null) ? array_map('strval', $input['brand_ids']) : [],
            'portability' => (string) ($input['portability'] ?? 'any'),
            'performance' => (string) ($input['performance'] ?? 'any'),
            'battery' => (string) ($input['battery'] ?? 'any'),
        ];
    }
}
