<?php
declare(strict_types=1);

final class RecommendationExplanationGenerator
{
    public function generate(
        array $product,
        RecommendationRequest $request,
        array $activity,
        array $workloads,
        array $breakdown,
        float $score,
        int $rank
    ): string {
        $sentences = [];
        $matchWord = $score >= 85 ? 'very strong' : ($score >= 70 ? 'strong' : ($score >= 55 ? 'reasonable' : 'lower-scoring'));
        $sentences[] = sprintf(
            'For your %s profile, the %s is a %s match at %s%% compatibility.',
            mb_strtolower((string) $activity['name']),
            $product['product_name'],
            $matchWord,
            number_format($score, 1)
        );

        $cpuTier = HardwareEvaluator::cpuTier($product['processor_model']);
        $minCpu = (float) $activity['minimum_cpu_level'];
        $prefCpu = (float) $activity['preferred_cpu_level'];
        if ($cpuTier >= $prefCpu) {
            $cpuStatement = 'meets the preferred processor class for this profile';
        } elseif ($cpuTier >= $minCpu) {
            $cpuStatement = 'meets the minimum processor class but is below the preferred class for this profile';
        } else {
            $cpuStatement = 'is below the minimum processor class for this profile';
        }
        $sentences[] = sprintf(
            'Its processor, the %s, is classified as %s in our rule set and %s.',
            $product['processor_model'],
            HardwareEvaluator::cpuLabel($cpuTier),
            $cpuStatement
        );

        $minRam = max((int) $activity['minimum_ram_gb'], $request->minRamGb);
        $prefRam = max((int) $activity['preferred_ram_gb'], $minRam);
        $ramType = $product['ram_type'] ? ' ' . $product['ram_type'] : '';
        if ((int) $product['ram_gb'] >= $prefRam) {
            $ramSentence = sprintf(
                'The installed %d GB of%s memory meets the preferred memory target.',
                (int) $product['ram_gb'],
                $ramType
            );
        } elseif ((int) $product['ram_gb'] >= $minRam) {
            $ramSentence = sprintf(
                'The installed %d GB of%s memory meets the minimum target but is below the preferred %d GB.',
                (int) $product['ram_gb'],
                $ramType,
                $prefRam
            );
            if (($product['ram_upgradeable'] ?? 'unknown') === 'yes') {
                $ramSentence .= ' The listing states that the memory is upgradeable.';
            } elseif (($product['ram_upgradeable'] ?? 'unknown') === 'no') {
                $ramSentence .= ' The listing indicates that the memory is not upgradeable.';
            } else {
                $ramSentence .= ' The listing does not confirm memory upgradeability.';
            }
        } else {
            $ramSentence = sprintf(
                'The installed %d GB of%s memory is below the %d GB minimum for this profile.',
                (int) $product['ram_gb'],
                $ramType,
                $minRam
            );
        }
        $sentences[] = $ramSentence;

        $minStorage = (int) $activity['minimum_storage_gb'];
        $prefStorage = (int) $activity['preferred_storage_gb'];
        if ((int) $product['storage_gb'] >= $prefStorage) {
            $storageSentence = sprintf(
                'Its %s GB %s storage meets the preferred storage target.',
                number_format((int) $product['storage_gb']),
                $product['storage_type']
            );
        } elseif ((int) $product['storage_gb'] >= $minStorage) {
            $storageSentence = sprintf(
                'Its %s GB %s storage meets the minimum target but is below the preferred %s GB.',
                number_format((int) $product['storage_gb']),
                $product['storage_type'],
                number_format($prefStorage)
            );
        } else {
            $storageSentence = sprintf(
                'Its %s GB %s storage is below the %s GB minimum for this profile.',
                number_format((int) $product['storage_gb']),
                $product['storage_type'],
                number_format($minStorage)
            );
        }
        $sentences[] = $storageSentence;

        if ($product['gpu_model']) {
            if ($product['gpu_type'] === 'dedicated') {
                $vram = $product['gpu_vram_gb'] !== null ? ' with ' . (int) $product['gpu_vram_gb'] . ' GB VRAM' : '';
                $sentences[] = sprintf(
                    'The dedicated %s%s provides the graphics hardware this profile can use for accelerated work.',
                    $product['gpu_model'],
                    $vram
                );
            } elseif (in_array($activity['gpu_requirement'], ['none', 'integrated_ok'], true)) {
                $sentences[] = sprintf(
                    'The graphics are integrated (%s), which is appropriate because this profile does not require a dedicated GPU.',
                    $product['gpu_model']
                );
            } else {
                $sentences[] = sprintf(
                    'The graphics are integrated (%s), while this profile prefers a dedicated GPU.',
                    $product['gpu_model']
                );
            }
        }

        if ($product['product_category'] === 'laptop' && $product['screen_size']) {
            $screen = rtrim(rtrim(number_format((float) $product['screen_size'], 1), '0'), '.') . '-inch';
            if ($product['screen_resolution']) {
                $screen .= ' ' . $product['screen_resolution'];
            }
            if ($product['screen_panel']) {
                $screen .= ' ' . $product['screen_panel'];
            }
            $sentences[] = 'The listed display is ' . $screen . '.';
        }

        $headroom = $request->budgetMax - (float) $product['price_tnd'];
        if ($headroom > 0) {
            $sentences[] = sprintf(
                'At %s it leaves %s below your maximum budget; the listed price was recorded from %s on %s.',
                format_price($product['price_tnd']),
                format_price($headroom),
                $product['retailer_name'],
                format_date($product['verified_at'])
            );
        } else {
            $sentences[] = sprintf(
                'At %s it uses your full budget; the listed price was recorded from %s on %s.',
                format_price($product['price_tnd']),
                $product['retailer_name'],
                format_date($product['verified_at'])
            );
        }

        $limitations = [];
        if ($cpuTier < $minCpu) {
            $limitations[] = 'the processor class';
        }
        if ((int) $product['ram_gb'] < $minRam) {
            $limitations[] = 'the memory capacity';
        }
        if ((int) $product['storage_gb'] < $minStorage) {
            $limitations[] = 'the storage capacity';
        }
        if ($activity['gpu_requirement'] === 'dedicated_required' && $product['gpu_type'] !== 'dedicated') {
            $limitations[] = 'the absence of a dedicated GPU';
        } elseif ($activity['gpu_requirement'] === 'dedicated_preferred' && $product['gpu_type'] !== 'dedicated') {
            $limitations[] = 'the integrated rather than dedicated graphics';
        }
        if ($headroom <= 0) {
            $limitations[] = 'the use of the full budget';
        }

        if ($limitations !== []) {
            $sentences[] = 'The main trade-off is ' . $this->joinList($limitations) . '.';
        } else {
            $sentences[] = 'No mandatory specification gap was found for this profile, although availability and price should still be confirmed with the retailer before purchase.';
        }

        if ($workloads !== []) {
            $names = array_map(static fn (array $workload): string => $workload['name'], array_slice($workloads, 0, 3));
            $sentences[] = 'It was evaluated against your selected ' . $this->joinList($names) . ' workload' . (count($names) === 1 ? '' : 's') . '.';
        }

        return implode(' ', $sentences);
    }

    private function joinList(array $items): string
    {
        $items = array_values($items);
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }
}
