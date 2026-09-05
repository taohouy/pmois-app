<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * GovernanceAutoBindService (M0 Item 8 / M1 Plan §3.2 — implementation จริงแทน placeholder)
 *
 * Project ใหม่ต้องได้ Governance Baseline อัตโนมัติ โดย binding ผ่าน governance_adoptions
 * (reuses existing table — ไม่มีกลไกใหม่) ตามลำดับ resolution (R6-05 §2):
 *   1. explicit governance_version_id (จาก template payload)
 *   2. workspace default (workspace_default_settings.default_governance_version_id)
 *   3. latest published version ของ workspace
 */
final class GovernanceAutoBindService
{
    public function __construct(
        private readonly GovernanceAdoptionRepositoryInterface $adoptionRepository,
        private readonly \PDO $db,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return int|null governance_adoptions.id ที่ bind สำเร็จ (null = ไม่มี baseline ให้ bind)
     */
    public function bindDefaultGovernance(int $projectId, ?int $explicitGovernanceVersionId = null, ?int $workspaceDefaultGovernanceVersionId = null): ?int
    {
        $versionId = $explicitGovernanceVersionId ?? $workspaceDefaultGovernanceVersionId ?? $this->findLatestPublishedVersionId();

        if ($versionId === null) {
            return null;
        }

        // กัน double-bind ถ้า project นี้ adopt version เดียวกันอยู่แล้ว
        foreach ($this->adoptionRepository->listByProject($projectId) as $adoption) {
            if ((int) $adoption->governanceVersionId === $versionId) {
                return (int) $adoption->id;
            }
        }

        $adoption = $this->adoptionRepository->create($projectId, $versionId, $this->resolveActorId($projectId));

        return (int) $adoption->id;
    }

    private function findLatestPublishedVersionId(): ?int
    {
        $sql = 'SELECT gv.id
                FROM governance_versions gv
                JOIN governance_records gr ON gr.id = gv.governance_record_id
                WHERE gv.status = "published" AND gr.workspace_id = :workspace_id
                ORDER BY gv.published_at DESC, gv.id DESC
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? (int) $row['id'] : null;
    }

    private function resolveActorId(int $projectId): int
    {
        $stmt = $this->db->prepare('SELECT owner_user_id FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? (int) $row['owner_user_id'] : 0;
    }
}
