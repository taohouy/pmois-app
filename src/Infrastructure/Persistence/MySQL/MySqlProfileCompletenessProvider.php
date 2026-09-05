<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectProfileCompletenessInterface;
use PDO;

/**
 * Adapter ที่จับ calculator + project repo เข้าด้วยกัน:
 * recompute completeness แล้ว persist ลง projects.profile_completeness_percent
 */
final class MySqlProfileCompletenessProvider implements ProjectProfileCompletenessInterface
{
    public function __construct(
        private readonly \App\Domain\Project\ProfileCompletenessCalculator $calculator,
        private readonly \App\Domain\Project\ProjectRepositoryInterface $projectRepository,
        private readonly PDO $db,
    ) {
    }

    public function refresh(int $projectId): int
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            return 0;
        }

        $percent = $this->calculator->calculate($project)['percent'];

        // เขียนตรงเพราะ updateProgress ผูกกับ progress_percent (ตัวชี้วัดงาน — ต่างจาก completeness)
        $stmt = $this->db->prepare(
            'UPDATE projects SET profile_completeness_percent = :percent WHERE id = :id AND workspace_id = :workspace_id'
        );
        $stmt->execute(['percent' => $percent, 'id' => $projectId, 'workspace_id' => $project->workspaceId]);

        return $percent;
    }
}
