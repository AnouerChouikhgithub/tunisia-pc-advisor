<?php
declare(strict_types=1);

final class RecommendationService
{
    public function __construct(private readonly RecommendationEngine $engine)
    {
    }

    /**
     * @return array{token: string, results: array, meta: array, session_id: int}
     */
    public function generate(RecommendationRequest $request): array
    {
        $outcome = $this->engine->recommend($request);
        $meta = $outcome['meta'];
        $activity = $meta['activity'];
        $token = bin2hex(random_bytes(32));
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO recommendation_sessions
                    (token, activity_id, activity_name, workloads_json, product_category,
                     budget_min, budget_max, min_ram_gb, min_storage_gb, dedicated_gpu,
                     preferred_brands_json, portability_importance, performance_importance,
                     battery_importance, result_count, candidate_count)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $token,
                $request->activityId,
                $activity['name'],
                json_encode($meta['workloads'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $request->category,
                $request->budgetMin,
                $request->budgetMax,
                $request->minRamGb,
                $request->minStorageGb,
                $request->dedicatedGpu,
                json_encode($meta['preferred_brands'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $request->portability,
                $request->performance,
                $request->battery,
                count($outcome['results']),
                $meta['candidate_count'],
            ]);
            $sessionId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare(
                'INSERT INTO recommendation_results
                    (session_id, product_id, compatibility_score, explanation, rank_position, score_breakdown_json)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($outcome['results'] as $result) {
                $insert->execute([
                    $sessionId,
                    (int) $result['id'],
                    $result['compatibility_score'],
                    $result['explanation'],
                    (int) $result['rank_position'],
                    json_encode($result['score_breakdown'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $tokens = $_SESSION['recommendation_tokens'] ?? [];
            $tokens[] = $token;
            $_SESSION['recommendation_tokens'] = array_slice(array_values(array_unique($tokens)), -12);
        }

        return [
            'token' => $token,
            'results' => $outcome['results'],
            'meta' => $meta,
            'session_id' => $sessionId,
        ];
    }
}
