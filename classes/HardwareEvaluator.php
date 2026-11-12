<?php
declare(strict_types=1);

/**
 * Deterministic hardware tiers used by the recommendation engine.
 *
 * These are transparent family-level heuristics, not benchmark results. They
 * intentionally avoid claims that a particular model is faster than another
 * based only on its name. Product pages remain the source of the specifications.
 */
final class HardwareEvaluator
{
    public static function cpuTier(?string $cpu): float
    {
        if (!$cpu) {
            return 0.0;
        }
        $c = mb_strtolower(str_replace(['®', '™'], '', $cpu));

        if (preg_match('/apple\s+m4/', $c)) {
            return 9.4;
        }
        if (preg_match('/apple\s+m3/', $c)) {
            return 8.6;
        }
        if (preg_match('/apple\s+m2/', $c)) {
            return 7.6;
        }
        if (preg_match('/apple\s+m1/', $c)) {
            return 6.6;
        }
        if (preg_match('/snapdragon\s+x\s+elite/', $c)) {
            return 7.4;
        }
        if (preg_match('/snapdragon\s+x\s+plus/', $c)) {
            return 6.8;
        }
        if (preg_match('/core\s+ultra\s+9/', $c)) {
            return preg_match('/\b2\d{2}\b/', $c) ? 9.3 : 8.8;
        }
        if (preg_match('/core\s+ultra\s+7/', $c)) {
            return preg_match('/\b2\d{2}\b/', $c) ? 8.4 : 7.9;
        }
        if (preg_match('/core\s+ultra\s+5/', $c)) {
            return preg_match('/\b2\d{2}\b/', $c) ? 7.0 : 6.5;
        }
        if (preg_match('/ryzen\s+ai\s+9/', $c)) {
            return 8.8;
        }
        if (preg_match('/ryzen\s+ai\s+7/', $c)) {
            return 7.9;
        }
        if (preg_match('/ryzen\s+ai\s+5/', $c)) {
            return 6.5;
        }

        $tier = 0.0;
        if (preg_match('/\bi9\b/', $c)) {
            $tier = 8.8;
        } elseif (preg_match('/\bi7\b/', $c)) {
            $tier = 7.5;
        } elseif (preg_match('/\bi5\b/', $c)) {
            $tier = 6.1;
        } elseif (preg_match('/\bi3\b/', $c)) {
            $tier = 3.9;
        } elseif (preg_match('/\bryzen\s+9\b/', $c)) {
            $tier = 8.4;
        } elseif (preg_match('/\bryzen\s+7\b/', $c)) {
            $tier = 7.2;
        } elseif (preg_match('/\bryzen\s+5\b/', $c)) {
            $tier = 5.8;
        } elseif (preg_match('/\bryzen\s+3\b/', $c)) {
            $tier = 3.7;
        } elseif (preg_match('/\bcore\s+9\b/', $c)) {
            $tier = 8.6;
        } elseif (preg_match('/\bcore\s+7\b/', $c)) {
            $tier = 7.3;
        } elseif (preg_match('/\bcore\s+5\b/', $c)) {
            $tier = 5.7;
        } elseif (preg_match('/\bcore\s+3\b/', $c)) {
            $tier = 3.6;
        } elseif (preg_match('/celeron|n100|n200|n305|n4020|n4120|n4500|n5095|n5105/', $c)) {
            $tier = 2.0;
        } elseif (preg_match('/pentium/', $c)) {
            $tier = 2.8;
        } elseif (preg_match('/athlon|silver/', $c)) {
            $tier = 2.7;
        } elseif (preg_match('/xeon/', $c)) {
            $tier = 7.0;
        }

        if ($tier === 0.0) {
            return 0.0;
        }

        if (preg_match('/i[3579]-(\d{2})\d{2,3}/', $c, $m)) {
            $generation = (int) $m[1];
            $tier += match (true) {
                $generation >= 13 => 0.4,
                $generation >= 12 => 0.25,
                $generation >= 11 => 0.1,
                $generation <= 8 => -0.8,
                $generation === 9 => -0.5,
                default => -0.2,
            };
        }
        if (preg_match('/ryzen\s+[3579]\s+(\d{4})/', $c, $m)) {
            $series = (int) substr($m[1], 0, 1);
            $tier += match ($series) {
                9, 8 => 0.35,
                7 => 0.3,
                6 => 0.2,
                5 => 0.1,
                4 => -0.1,
                default => -0.4,
            };
        }
        if (preg_match('/\bhx\b/', $c)) {
            $tier += 0.3;
        } elseif (preg_match('/\bh\b/', $c)) {
            $tier += 0.1;
        }

        return round(max(0.0, min(10.0, $tier)), 2);
    }

    public static function cpuLabel(float $tier): string
    {
        return match (true) {
            $tier >= 8.5 => 'high-performance',
            $tier >= 7.0 => 'performance',
            $tier >= 5.5 => 'mainstream',
            $tier >= 3.5 => 'everyday',
            $tier > 0 => 'entry-level',
            default => 'unclassified',
        };
    }

    public static function gpuTier(?string $gpu): float
    {
        if (!$gpu) {
            return 0.0;
        }
        $g = mb_strtolower(str_replace(['®', '™'], '', $gpu));

        if (preg_match('/apple\s+gpu/', $g)) {
            return 5.0;
        }
        if (preg_match('/rtx\s*5090/', $g)) {
            return 10.0;
        }
        if (preg_match('/rtx\s*5080/', $g)) {
            return 9.6;
        }
        if (preg_match('/rtx\s*5070\s*ti/', $g)) {
            return 9.3;
        }
        if (preg_match('/rtx\s*5070/', $g)) {
            return 8.8;
        }
        if (preg_match('/rtx\s*5060/', $g)) {
            return 8.0;
        }
        if (preg_match('/rtx\s*5050/', $g)) {
            return 7.1;
        }
        if (preg_match('/rtx\s*4090/', $g)) {
            return 10.0;
        }
        if (preg_match('/rtx\s*4080/', $g)) {
            return 9.5;
        }
        if (preg_match('/rtx\s*4070/', $g)) {
            return 8.7;
        }
        if (preg_match('/rtx\s*4060/', $g)) {
            return 8.0;
        }
        if (preg_match('/rtx\s*4050/', $g)) {
            return 7.0;
        }
        if (preg_match('/rtx\s*3090/', $g)) {
            return 9.3;
        }
        if (preg_match('/rtx\s*3080/', $g)) {
            return 8.9;
        }
        if (preg_match('/rtx\s*3070/', $g)) {
            return 8.3;
        }
        if (preg_match('/rtx\s*3060/', $g)) {
            return 7.3;
        }
        if (preg_match('/rtx\s*3050\s*ti/', $g)) {
            return 6.2;
        }
        if (preg_match('/rtx\s*3050/', $g)) {
            return 5.8;
        }
        if (preg_match('/rtx\s*2080/', $g)) {
            return 7.7;
        }
        if (preg_match('/rtx\s*2070/', $g)) {
            return 7.1;
        }
        if (preg_match('/rtx\s*2060/', $g)) {
            return 6.5;
        }
        if (preg_match('/gtx\s*1660\s*ti/', $g)) {
            return 5.6;
        }
        if (preg_match('/gtx\s*1660/', $g)) {
            return 5.2;
        }
        if (preg_match('/gtx\s*1650/', $g)) {
            return 4.5;
        }
        if (preg_match('/gtx\s*1050/', $g)) {
            return 3.3;
        }
        if (preg_match('/rtx\s*a\d{4}/', $g)) {
            return 6.5;
        }
        if (preg_match('/quadro/', $g)) {
            return 5.5;
        }
        if (preg_match('/mx\s*570/', $g)) {
            return 4.2;
        }
        if (preg_match('/mx\s*550/', $g)) {
            return 3.7;
        }
        if (preg_match('/mx\s*450/', $g)) {
            return 3.2;
        }
        if (preg_match('/mx\s*350/', $g)) {
            return 2.8;
        }
        if (preg_match('/mx\s*250/', $g)) {
            return 2.5;
        }
        if (preg_match('/mx\s*150/', $g)) {
            return 2.2;
        }

        if (preg_match('/rx\s*7900/', $g)) {
            return 9.3;
        }
        if (preg_match('/rx\s*7800/', $g)) {
            return 8.7;
        }
        if (preg_match('/rx\s*7700/', $g)) {
            return 8.1;
        }
        if (preg_match('/rx\s*7600/', $g)) {
            return 6.9;
        }
        if (preg_match('/rx\s*6900/', $g)) {
            return 8.7;
        }
        if (preg_match('/rx\s*6800/', $g)) {
            return 8.3;
        }
        if (preg_match('/rx\s*6700/', $g)) {
            return 7.6;
        }
        if (preg_match('/rx\s*6600/', $g)) {
            return 6.3;
        }
        if (preg_match('/rx\s*6500/', $g)) {
            return 4.9;
        }
        if (preg_match('/rx\s*5500/', $g)) {
            return 3.4;
        }
        if (preg_match('/radeon\s+890m/', $g)) {
            return 3.1;
        }
        if (preg_match('/radeon\s+880m/', $g)) {
            return 2.9;
        }
        if (preg_match('/radeon\s+780m/', $g)) {
            return 2.7;
        }
        if (preg_match('/radeon\s+680m/', $g)) {
            return 2.4;
        }
        if (preg_match('/radeon\s+660m/', $g)) {
            return 2.0;
        }
        if (preg_match('/arc\s*a770/', $g)) {
            return 7.1;
        }
        if (preg_match('/arc\s*a750/', $g)) {
            return 6.7;
        }
        if (preg_match('/arc\s*a580/', $g)) {
            return 6.0;
        }
        if (preg_match('/arc\s*a380/', $g)) {
            return 4.1;
        }
        if (preg_match('/arc\s*140v/', $g)) {
            return 2.8;
        }
        if (preg_match('/iris\s*xe/', $g)) {
            return 1.7;
        }
        if (preg_match('/radeon\s+graphics/', $g)) {
            return 1.2;
        }
        if (preg_match('/intel.*uhd|uhd\s+graphics/', $g)) {
            return 0.8;
        }
        if (preg_match('/integrated|graphique intégrée/', $g)) {
            return 0.9;
        }

        return 0.5;
    }

    public static function gpuLabel(float $tier): string
    {
        return match (true) {
            $tier >= 8.5 => 'high-end graphics',
            $tier >= 6.5 => 'performance graphics',
            $tier >= 4.0 => 'mid-range graphics',
            $tier >= 2.0 => 'entry discrete graphics',
            $tier > 0 => 'integrated graphics',
            default => 'unclassified graphics',
        };
    }

    public static function fitTier(float $tier, float $minimum): float
    {
        if ($minimum <= 0) {
            return 1.0;
        }
        if ($tier >= $minimum) {
            return 1.0;
        }
        if ($tier <= 0) {
            return 0.1;
        }
        return round(max(0.1, 0.6 * ($tier / $minimum)), 4);
    }

    public static function ramFit(int $ramGb, int $minimum, int $preferred, ?string $ramType = null, ?string $upgradeable = null): float
    {
        $minimum = max(1, $minimum);
        $preferred = max($minimum, $preferred);
        if ($ramGb >= $preferred) {
            $score = 1.0;
        } elseif ($ramGb >= $minimum) {
            $score = 0.70 + 0.30 * (($ramGb - $minimum) / max(1, $preferred - $minimum));
        } else {
            $score = max(0.1, 0.45 * ($ramGb / $minimum));
        }

        if ($ramType) {
            $type = mb_strtoupper($ramType);
            if (str_contains($type, 'DDR5') || str_contains($type, 'LPDDR5')) {
                $score += 0.02;
            }
        }
        if ($upgradeable === 'yes') {
            $score += 0.01;
        }
        return round(min(1.0, $score), 4);
    }

    public static function storageFit(int $storageGb, string $storageType, int $minimum, int $preferred): float
    {
        $minimum = max(1, $minimum);
        $preferred = max($minimum, $preferred);
        if ($storageGb >= $preferred) {
            $capacity = 1.0;
        } elseif ($storageGb >= $minimum) {
            $capacity = 0.70 + 0.30 * (($storageGb - $minimum) / max(1, $preferred - $minimum));
        } else {
            $capacity = max(0.10, 0.45 * ($storageGb / $minimum));
        }

        $type = mb_strtolower($storageType);
        $typeFactor = match (true) {
            str_contains($type, 'nvme') => 1.0,
            str_contains($type, 'ssd') => 0.90,
            str_contains($type, 'emmc') => 0.60,
            str_contains($type, 'hdd') => 0.35,
            default => 0.55,
        };

        return round(min(1.0, 0.80 * $capacity + 0.20 * $typeFactor), 4);
    }

    /**
     * @return array{score: float, detail: string}
     */
    public static function gpuFit(
        string $gpuType,
        ?string $gpuModel,
        ?int $vramGb,
        string $requirement,
        int $minimumVramGb
    ): array {
        $tier = self::gpuTier($gpuModel);
        $isDedicated = $gpuType === 'dedicated';
        $isApple = $gpuModel !== null && preg_match('/apple/i', $gpuModel) === 1;
        $label = self::gpuLabel($tier);

        switch ($requirement) {
            case 'dedicated_required':
                if ($isDedicated) {
                    $score = 0.70 + 0.30 * min(1.0, $tier / 10);
                    $detail = "Dedicated {$label}";
                } elseif ($isApple && $tier >= 4) {
                    $score = 0.35;
                    $detail = 'Integrated Apple GPU; the profile asks for a discrete GPU';
                } else {
                    $score = 0.10;
                    $detail = 'Integrated graphics; the profile asks for a discrete GPU';
                }
                break;
            case 'dedicated_preferred':
                if ($isDedicated) {
                    $score = 0.68 + 0.32 * min(1.0, $tier / 10);
                    $detail = "Dedicated {$label}";
                } elseif ($isApple && $tier >= 4) {
                    $score = 0.72;
                    $detail = 'Integrated Apple GPU, which this profile can use for acceleration';
                } elseif ($gpuType === 'integrated') {
                    $score = 0.48 + 0.10 * min(1.0, $tier / 4);
                    $detail = 'Integrated graphics; a discrete GPU is preferred';
                } else {
                    $score = 0.35;
                    $detail = 'Graphics type not confirmed';
                }
                break;
            case 'integrated_ok':
                $score = $isDedicated ? 1.0 : ($gpuType === 'integrated' ? 0.96 : 0.55);
                $detail = $isDedicated ? "Dedicated {$label}" : 'Integrated graphics are sufficient';
                break;
            case 'none':
            default:
                $score = $isDedicated ? 1.0 : ($gpuType === 'integrated' ? 0.95 : 0.55);
                $detail = $isDedicated ? "Dedicated {$label}" : 'Graphics capability not a priority';
                break;
        }

        if ($isDedicated && $minimumVramGb > 0) {
            if ($vramGb === null) {
                $score *= 0.92;
                $detail .= '; VRAM not stated';
            } elseif ($vramGb < $minimumVramGb) {
                $score *= 0.85;
                $detail .= "; {$vramGb} GB VRAM vs {$minimumVramGb} GB preferred";
            }
        }

        return ['score' => round(max(0.0, min(1.0, $score)), 4), 'detail' => $detail];
    }

    public static function performanceComposite(float $cpu, float $ram, float $gpu, float $storage): float
    {
        return round(0.40 * $cpu + 0.25 * $ram + 0.25 * $gpu + 0.10 * $storage, 4);
    }
}
