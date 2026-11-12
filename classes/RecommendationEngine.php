<?php
declare(strict_types=1);

final class RecommendationEngine
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly ProductRepository $products,
        private readonly RecommendationExplanationGenerator $explanationGenerator
    ) {
    }

    /**
     * @return array{results: array<int, array>, meta: array<string, mixed>}
     */
    public function recommend(RecommendationRequest $request): array
    {
        $activity = $this->activities->find($request->activityId);
        if (!$activity) {
            throw new InvalidArgumentException('The selected activity does not exist.');
        }

        $workloads = $this->activities->workloadsByIds($request->workloadIds);
        $relevanceMap = $this->activities->workloadRelevanceMap($request->activityId);
        $candidates = $this->products->candidatesFor($request);

        if ($candidates === []) {
            return [
                'results' => [],
                'meta' => $this->buildMeta($request, $activity, $workloads, 0, 0, []),
            ];
        }

        $preferredBrandNames = [];
        if ($request->preferredBrandIds !== []) {
            foreach ($this->products->brands() as $brand) {
                if (in_array((int) $brand['id'], $request->preferredBrandIds, true)) {
                    $preferredBrandNames[] = $brand['name'];
                }
            }
        }

        $scored = [];
        foreach ($candidates as $product) {
            $score = $this->scoreProduct($product, $request, $activity, $workloads, $relevanceMap, $preferredBrandNames);
            $scored[] = $score;
        }

        $ratios = array_column($scored, '_value_ratio');
        $minRatio = min($ratios);
        $maxRatio = max($ratios);

        foreach ($scored as &$row) {
            $product = $row['product'];
            $price = (float) $product['price_tnd'];
            $headroomRatio = $request->budgetMax > 0
                ? max(0.0, min(1.0, ($request->budgetMax - $price) / $request->budgetMax))
                : 0.0;
            $affordability = 0.60 + 0.40 * $headroomRatio;
            $valueNorm = $maxRatio > $minRatio
                ? 0.15 + 0.85 * (($row['_value_ratio'] - $minRatio) / ($maxRatio - $minRatio))
                : 0.65;
            $budgetScore = 0.55 * $affordability + 0.45 * $valueNorm;

            $weights = $this->weights($activity);
            $components = [
                'cpu' => $row['_cpu_score'],
                'ram' => $row['_ram_score'],
                'gpu' => $row['_gpu_score'],
                'storage' => $row['_storage_score'],
                'budget' => $budgetScore,
                'activity' => $row['_activity_score'],
                'preferences' => $row['_preferences_score'],
            ];

            $total = 0.0;
            $breakdown = [];
            $labels = [
                'cpu' => 'CPU suitability',
                'ram' => 'RAM suitability',
                'gpu' => 'GPU suitability',
                'storage' => 'Storage suitability',
                'budget' => 'Budget and value',
                'activity' => 'Activity and workload fit',
                'preferences' => 'User preferences',
            ];
            $details = [
                'cpu' => $row['_cpu_detail'],
                'ram' => $row['_ram_detail'],
                'gpu' => $row['_gpu_detail'],
                'storage' => $row['_storage_detail'],
                'budget' => sprintf(
                    'Listed at %s, %s below your maximum. Relative value index %.0f/100 across this candidate set.',
                    format_price($price),
                    format_price(max(0, $request->budgetMax - $price)),
                    $valueNorm * 100
                ),
                'activity' => $row['_activity_detail'],
                'preferences' => $row['_preferences_detail'],
            ];
            foreach ($weights as $key => $weight) {
                $points = $components[$key] * $weight;
                $total += $points;
                $breakdown[] = [
                    'key' => $key,
                    'label' => $labels[$key],
                    'weight' => $weight,
                    'score' => round($components[$key] * 100, 1),
                    'points' => round($points, 2),
                    'detail' => $details[$key],
                ];
            }

            $row['compatibility_score'] = round(max(0, min(100, $total)), 2);
            $row['score_breakdown'] = $breakdown;
            $row['_family_key'] = self::familyKey($product);
            $row['_brand_key'] = mb_strtolower((string) $product['brand_name']);
            unset($row['_cpu_score'], $row['_ram_score'], $row['_gpu_score'], $row['_storage_score'], $row['_activity_score'], $row['_preferences_score']);
        }
        unset($row);

        usort($scored, static function (array $a, array $b): int {
            return [$b['compatibility_score'], $b['_value_ratio'], (float) $a['product']['price_tnd']]
                <=> [$a['compatibility_score'], $a['_value_ratio'], (float) $b['product']['price_tnd']];
        });

        $selected = $this->selectDiverse($scored, 5);
        usort($selected, static function (array $a, array $b): int {
            return [$b['compatibility_score'], $b['_value_ratio'], (float) $a['product']['price_tnd']]
                <=> [$a['compatibility_score'], $a['_value_ratio'], (float) $b['product']['price_tnd']];
        });

        $results = [];
        foreach ($selected as $index => $row) {
            $product = $row['product'];
            $rank = $index + 1;
            $explanation = $this->explanationGenerator->generate(
                $product,
                $request,
                $activity,
                $workloads,
                $row['score_breakdown'],
                $row['compatibility_score'],
                $rank
            );
            $product['compatibility_score'] = $row['compatibility_score'];
            $product['score_breakdown'] = $row['score_breakdown'];
            $product['explanation'] = $explanation;
            $product['rank_position'] = $rank;
            $product['_value_ratio'] = round($row['_value_ratio'], 4);
            $results[] = $product;
        }

        return [
            'results' => $results,
            'meta' => $this->buildMeta($request, $activity, $workloads, count($candidates), count($results), $preferredBrandNames, $selected),
        ];
    }

    private function scoreProduct(
        array $product,
        RecommendationRequest $request,
        array $activity,
        array $workloads,
        array $relevanceMap,
        array $preferredBrandNames
    ): array {
        $cpuTier = HardwareEvaluator::cpuTier($product['processor_model']);
        $minCpu = (float) $activity['minimum_cpu_level'];
        $prefCpu = (float) $activity['preferred_cpu_level'];
        if ($cpuTier <= 0) {
            $cpuScore = 0.25;
            $cpuDetail = 'Processor model could not be mapped to a rule tier; treated as unclassified.';
        } elseif ($cpuTier >= $prefCpu) {
            $cpuScore = 1.0;
            $cpuDetail = "{$product['processor_model']} is {$this->tierLabel($cpuTier, $activity, 'cpu')}; meets the preferred class for this activity.";
        } elseif ($cpuTier >= $minCpu) {
            $cpuScore = 0.68 + 0.32 * (($cpuTier - $minCpu) / max(0.1, $prefCpu - $minCpu));
            $cpuDetail = "{$product['processor_model']} meets the minimum processor class but is below the preferred class for this activity.";
        } else {
            $cpuScore = max(0.10, 0.50 * ($cpuTier / max(0.1, $minCpu)));
            $cpuDetail = "{$product['processor_model']} is below the minimum processor class for this activity.";
        }

        $effectiveMinRam = max((int) $activity['minimum_ram_gb'], $request->minRamGb);
        $effectivePrefRam = max((int) $activity['preferred_ram_gb'], $effectiveMinRam, $request->minRamGb);
        $ramScore = HardwareEvaluator::ramFit(
            (int) $product['ram_gb'],
            $effectiveMinRam,
            $effectivePrefRam,
            $product['ram_type'] ?? null,
            $product['ram_upgradeable'] ?? null
        );
        $ramDetail = sprintf(
            '%d GB %s; target is %d GB minimum and %d GB preferred.',
            (int) $product['ram_gb'],
            $product['ram_type'] ? $product['ram_type'] . ' memory' : 'memory',
            $effectiveMinRam,
            $effectivePrefRam
        );

        $storageScore = HardwareEvaluator::storageFit(
            (int) $product['storage_gb'],
            (string) $product['storage_type'],
            (int) $activity['minimum_storage_gb'],
            (int) $activity['preferred_storage_gb']
        );
        $storageDetail = sprintf(
            '%s %s; target is %s minimum and %s preferred.',
            number_format((int) $product['storage_gb']),
            $product['storage_type'],
            number_format((int) $activity['minimum_storage_gb']) . ' GB',
            number_format((int) $activity['preferred_storage_gb']) . ' GB'
        );

        $gpuFit = HardwareEvaluator::gpuFit(
            (string) $product['gpu_type'],
            $product['gpu_model'] ?? null,
            isset($product['gpu_vram_gb']) ? (int) $product['gpu_vram_gb'] : null,
            (string) $activity['gpu_requirement'],
            (int) $activity['minimum_gpu_vram_gb']
        );

        $activityScore = $this->workloadFit($product, $workloads, $relevanceMap);
        $activityDetail = sprintf(
            'Weighted fit across %d selected workload%s.',
            count($workloads),
            count($workloads) === 1 ? '' : 's'
        );

        $performance = HardwareEvaluator::performanceComposite($cpuScore, $ramScore, $gpuFit['score'], $storageScore);
        $preferences = $this->preferenceScore($product, $request, $activity, $preferredBrandNames, $performance);

        $price = max(0.001, (float) $product['price_tnd']);

        return [
            'product' => $product,
            '_cpu_score' => $cpuScore,
            '_ram_score' => $ramScore,
            '_gpu_score' => $gpuFit['score'],
            '_storage_score' => $storageScore,
            '_activity_score' => $activityScore,
            '_preferences_score' => $preferences['score'],
            '_cpu_detail' => $cpuDetail,
            '_ram_detail' => $ramDetail,
            '_gpu_detail' => $gpuFit['detail'],
            '_storage_detail' => $storageDetail,
            '_activity_detail' => $activityDetail,
            '_preferences_detail' => $preferences['detail'],
            '_performance' => $performance,
            '_value_ratio' => ($performance * 1000) / $price,
        ];
    }

    private function workloadFit(array $product, array $workloads, array $relevanceMap): float
    {
        if ($workloads === []) {
            return 0.5;
        }
        $cpuTier = HardwareEvaluator::cpuTier($product['processor_model']);
        $weighted = 0.0;
        $weights = 0.0;

        foreach ($workloads as $workload) {
            $cpu = HardwareEvaluator::fitTier($cpuTier, (float) $workload['minimum_cpu_level']);
            $ramMin = max(1, (int) $workload['minimum_ram_gb']);
            $ram = (int) $product['ram_gb'] >= $ramMin
                ? 1.0
                : max(0.15, 0.55 * ((int) $product['ram_gb'] / $ramMin));
            $gpu = HardwareEvaluator::gpuFit(
                (string) $product['gpu_type'],
                $product['gpu_model'] ?? null,
                isset($product['gpu_vram_gb']) ? (int) $product['gpu_vram_gb'] : null,
                (string) $workload['gpu_requirement'],
                (int) $workload['minimum_gpu_vram_gb']
            )['score'];
            $storage = HardwareEvaluator::storageFit(
                (int) $product['storage_gb'],
                (string) $product['storage_type'],
                (int) $workload['minimum_storage_gb'],
                (int) $workload['minimum_storage_gb'] * 2
            );
            $fit = 0.35 * $cpu + 0.30 * $ram + 0.20 * $gpu + 0.15 * $storage;
            $relevance = $relevanceMap[(int) $workload['id']] ?? 1.5;
            $weighted += $fit * $relevance;
            $weights += $relevance;
        }

        return $weights > 0 ? round($weighted / $weights, 4) : 0.5;
    }

    private function preferenceScore(
        array $product,
        RecommendationRequest $request,
        array $activity,
        array $preferredBrandNames,
        float $performance
    ): array {
        $components = [];
        $details = [];

        if ($preferredBrandNames !== []) {
            $match = in_array($product['brand_name'], $preferredBrandNames, true);
            $components[] = [$match ? 1.0 : 0.35, 2.0];
            $details[] = $match ? 'Preferred brand matched' : 'Outside preferred brands';
        }

        $portabilityImportance = $request->portability !== 'any'
            ? $request->portability
            : (string) $activity['portability_importance'];
        if ($portabilityImportance !== 'any') {
            if ($product['product_category'] === 'desktop') {
                $portability = 0.20;
            } elseif ($product['weight_kg'] !== null) {
                $weight = (float) $product['weight_kg'];
                $portability = match (true) {
                    $weight <= 1.4 => 1.0,
                    $weight <= 1.8 => 0.85,
                    $weight <= 2.2 => 0.65,
                    $weight <= 2.6 => 0.45,
                    default => 0.25,
                };
                $details[] = "Weight " . number_format($weight, 2) . ' kg';
            } else {
                $portability = 0.55;
                $details[] = 'Weight not stated';
            }
            $components[] = [$portability, 2.0];
        }

        if ($request->performance !== 'any') {
            $components[] = [$performance, 2.0];
        }

        if ($request->battery !== 'any') {
            if ($product['product_category'] === 'desktop') {
                $battery = 0.20;
            } elseif (!$product['battery']) {
                $battery = 0.45;
                $details[] = 'Battery detail not stated';
            } else {
                $batteryText = $product['battery'];
                $battery = 0.60;
                if (preg_match('/(\d{2,3})\s*wh/i', $batteryText, $m)) {
                    $wh = (int) $m[1];
                    $battery = $wh >= 70 ? 0.90 : ($wh >= 50 ? 0.75 : ($wh >= 40 ? 0.60 : 0.45));
                }
                $details[] = 'Battery detail present';
            }
            $components[] = [$battery, 1.0];
        }

        if (in_array($activity['slug'], ['graphic-designer', 'ui-ux-designer', 'video-editor'], true)) {
            $screen = 0.40;
            if ($product['product_category'] === 'laptop' && $product['screen_resolution']) {
                $screen += 0.25;
            }
            if ($product['screen_panel'] && in_array($product['screen_panel'], ['IPS', 'OLED'], true)) {
                $screen += 0.20;
            }
            $components[] = [min(1.0, $screen), 1.0];
            if ($product['screen_panel']) {
                $details[] = $product['screen_panel'] . ' panel listed';
            }
        }

        if ($components === []) {
            return ['score' => 0.70, 'detail' => 'No optional preference was active.'];
        }

        $weighted = 0.0;
        $weightTotal = 0.0;
        foreach ($components as [$score, $weight]) {
            $weighted += $score * $weight;
            $weightTotal += $weight;
        }

        return [
            'score' => $weightTotal > 0 ? round($weighted / $weightTotal, 4) : 0.70,
            'detail' => $details ? implode('; ', $details) . '.' : 'Optional preferences evaluated.',
        ];
    }

    private function weights(array $activity): array
    {
        $weights = [
            'cpu' => (int) $activity['cpu_weight'],
            'ram' => (int) $activity['ram_weight'],
            'gpu' => (int) $activity['gpu_weight'],
            'storage' => (int) $activity['storage_weight'],
            'budget' => (int) $activity['budget_weight'],
            'activity' => (int) $activity['activity_weight'],
            'preferences' => (int) $activity['preferences_weight'],
        ];
        $total = array_sum($weights);
        if ($total !== 100 && $total > 0) {
            foreach ($weights as &$weight) {
                $weight = round($weight * 100 / $total, 1);
            }
            unset($weight);
        }
        return $weights;
    }

    private function tierLabel(float $tier, array $activity, string $dimension): string
    {
        $label = $dimension === 'cpu' ? HardwareEvaluator::cpuLabel($tier) : HardwareEvaluator::gpuLabel($tier);
        return $label . ' class';
    }

    private function selectDiverse(array $ranked, int $limit): array
    {
        if (count($ranked) <= $limit) {
            return $ranked;
        }

        $selected = [];
        $selectedIds = [];
        $familyCounts = [];
        $brandCounts = [];

        foreach ($ranked as $row) {
            if (count($selected) >= $limit) {
                break;
            }
            $family = $row['_family_key'];
            $brand = $row['_brand_key'];
            if (($familyCounts[$family] ?? 0) >= 1) {
                continue;
            }
            if (($brandCounts[$brand] ?? 0) >= 3) {
                continue;
            }
            $selected[] = $row;
            $selectedIds[(int) $row['product']['id']] = true;
            $familyCounts[$family] = ($familyCounts[$family] ?? 0) + 1;
            $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
        }

        if (count($selected) < $limit) {
            foreach ($ranked as $row) {
                if (count($selected) >= $limit) {
                    break;
                }
                if (isset($selectedIds[(int) $row['product']['id']])) {
                    continue;
                }
                $brand = $row['_brand_key'];
                if (($brandCounts[$brand] ?? 0) >= 4) {
                    continue;
                }
                $selected[] = $row;
                $selectedIds[(int) $row['product']['id']] = true;
                $familyCounts[$row['_family_key']] = ($familyCounts[$row['_family_key']] ?? 0) + 1;
                $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
            }
        }

        if (count($selected) < $limit) {
            foreach ($ranked as $row) {
                if (count($selected) >= $limit) {
                    break;
                }
                if (isset($selectedIds[(int) $row['product']['id']])) {
                    continue;
                }
                $selected[] = $row;
                $selectedIds[(int) $row['product']['id']] = true;
            }
        }

        return $selected;
    }

    private function buildMeta(
        RecommendationRequest $request,
        array $activity,
        array $workloads,
        int $candidateCount,
        int $returnedCount,
        array $preferredBrandNames,
        array $selected = []
    ): array {
        $notes = [];
        if ($candidateCount === 0) {
            $notes[] = sprintf(
                'No active, in-stock listings at or below %s matched every hard filter (category, minimum RAM/storage, dedicated GPU where requested).',
                format_price($request->budgetMax)
            );
        } elseif ($returnedCount < 5) {
            $notes[] = sprintf(
                'Only %d qualifying listing%s remained after the budget and availability filters, so no products were invented to reach five.',
                $returnedCount,
                $returnedCount === 1 ? '' : 's'
            );
        }
        if ($candidateCount > 0) {
            $notes[] = 'The budget ceiling was applied before scoring; over-budget products were not recommended.';
        }

        $brandNames = [];
        foreach ($selected as $row) {
            $brandNames[$row['product']['brand_name']] = true;
        }

        return [
            'activity' => $activity,
            'workloads' => array_map(static fn (array $workload): array => [
                'id' => (int) $workload['id'],
                'name' => $workload['name'],
                'slug' => $workload['slug'],
            ], $workloads),
            'candidate_count' => $candidateCount,
            'returned_count' => $returnedCount,
            'preferred_brands' => $preferredBrandNames,
            'weights' => $this->weights($activity),
            'budget_label' => $request->budgetLabel,
            'budget_max' => $request->budgetMax,
            'category' => $request->category,
            'notes' => $notes,
            'brand_count' => count($brandNames),
            'generated_at' => date('c'),
        ];
    }

    private static function familyKey(array $product): string
    {
        $model = mb_strtolower((string) $product['model']);
        $model = preg_replace('/[^a-z0-9]+/', ' ', $model) ?? $model;
        $tokens = preg_split('/\s+/', trim($model)) ?: [];
        $generic = ['pc', 'portable', 'gaming', 'gamer', 'laptop', 'notebook', 'desktop'];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== '' && !in_array($token, $generic, true)));
        return mb_strtolower((string) $product['brand_name']) . '|' . implode(' ', array_slice($tokens, 0, 3));
    }
}
