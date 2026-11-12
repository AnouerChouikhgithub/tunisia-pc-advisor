<?php
declare(strict_types=1);

final class ActivityRepository
{
    private ?array $activitiesCache = null;
    private ?array $workloadsCache = null;

    public function all(): array
    {
        if ($this->activitiesCache !== null) {
            return $this->activitiesCache;
        }

        $sql = 'SELECT a.id, a.slug, a.name, a.description, a.icon,
                       r.minimum_cpu_level, r.preferred_cpu_level,
                       r.minimum_ram_gb, r.preferred_ram_gb,
                       r.minimum_storage_gb, r.preferred_storage_gb,
                       r.gpu_requirement, r.minimum_gpu_vram_gb, r.portability_importance,
                       r.cpu_weight, r.ram_weight, r.gpu_weight, r.storage_weight,
                       r.budget_weight, r.activity_weight, r.preferences_weight, r.notes
                FROM activities a
                INNER JOIN activity_requirements r ON r.activity_id = a.id
                ORDER BY a.id ASC';
        $this->activitiesCache = Database::connection()->query($sql)->fetchAll();
        return $this->activitiesCache;
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $activity) {
            if ((int) $activity['id'] === $id) {
                return $activity;
            }
        }
        return null;
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $activity) {
            if ($activity['slug'] === $slug) {
                return $activity;
            }
        }
        return null;
    }

    public function allWorkloads(): array
    {
        if ($this->workloadsCache !== null) {
            return $this->workloadsCache;
        }
        $this->workloadsCache = Database::connection()
            ->query('SELECT * FROM workloads ORDER BY id ASC')
            ->fetchAll();
        return $this->workloadsCache;
    }

    public function workloadsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT * FROM workloads WHERE id IN ($placeholders) ORDER BY id ASC");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    public function workloadsForActivity(int $activityId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.*, aw.relevance
             FROM activity_workloads aw
             INNER JOIN workloads w ON w.id = aw.workload_id
             WHERE aw.activity_id = ?
             ORDER BY aw.relevance DESC, w.name ASC'
        );
        $stmt->execute([$activityId]);
        return $stmt->fetchAll();
    }

    public function workloadRelevanceMap(int $activityId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT workload_id, relevance FROM activity_workloads WHERE activity_id = ?'
        );
        $stmt->execute([$activityId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['workload_id']] = (int) $row['relevance'];
        }
        return $map;
    }

    public function workloadIdsForActivity(int $activityId): array
    {
        return array_keys($this->workloadRelevanceMap($activityId));
    }

    public function validWorkloadIds(): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $this->allWorkloads());
    }
}
