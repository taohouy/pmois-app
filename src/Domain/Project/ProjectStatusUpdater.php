<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * ProjectStatusUpdater — PMO Timeline update เมื่อ revision committed
 * (reuses project_status_updates — idempotent ต่อ revision ด้วย idempotency_key)
 */
final class ProjectStatusUpdater
{
    public function __construct(
        private readonly ProjectStatusUpdateRepositoryInterface $statusUpdateRepository,
        private readonly RevisionRepositoryInterface $revisionRepository,
    ) {
    }

    public function updateOnRevisionCommitted(int $revisionId): void
    {
        $revision = $this->revisionRepository->findById($revisionId);
        if ($revision === null) {
            return;
        }

        // idempotency: หนึ่ง revision commit = หนึ่ง timeline row
        $idempotencyKey = 'revision-commit-' . $revisionId;
        if ($this->statusUpdateRepository->findByIdempotencyKey($idempotencyKey) !== null) {
            return;
        }

        // revision ที่ test ไม่ผ่าน → at_risk, ปกติ → on_track
        $overallStatus = $revision->testResult === 'failed' ? 'at_risk' : 'on_track';

        $summary = sprintf(
            'Revision #%d committed — %s',
            $revision->id,
            mb_substr($revision->summary, 0, 180)
        );

        $this->statusUpdateRepository->create(
            projectId: $revision->projectId,
            reportDate: (new \DateTimeImmutable())->format('Y-m-d'),
            overallStatus: $overallStatus,
            summary: $summary,
            keyAchievements: null,
            keyIssues: $revision->knownIssue,
            nextSteps: $revision->nextAction,
            submittedByUserId: $revision->submittedBy,
            idempotencyKey: $idempotencyKey,
        );
    }
}
