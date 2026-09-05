<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * RevisionService — Revision Management + Commit Tracking (M3)
 *
 * State machine (M0 R5 §3.6 / 07-CTO-Review-Commit-PMO-Update-Flow.md):
 *   submitted ──review(approved)──▶ cto_approved ──commit──▶ committed
 *   submitted ──review(rejected)──▶ cto_rejected (terminal)
 *
 * กฎ:
 *  - dev_user_id XOR dev_ai_consumer_id (คนเดียวเท่านั้น — human หรือ AI)
 *  - submit กับ milestone ที่ปิดแล้วห้าม (MILESTONE_NOT_OPEN)
 *  - commit ได้เฉพาะหลัง CTO approved (REVISION_NOT_APPROVED)
 *  - commit สำเร็จ → สร้าง project_status_updates (PMO Timeline update)
 */
final class RevisionService
{
    public function __construct(
        private readonly RevisionRepositoryInterface $revisionRepository,
        private readonly MilestoneRepositoryInterface $milestoneRepository,
        private readonly ProjectStatusUpdater $statusUpdater,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR
     * @throws \DomainException MILESTONE_NOT_OPEN
     */
    public function submit(array $data, int $submittedBy): Revision
    {
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        $summary = trim((string) ($data['summary'] ?? ''));

        $errors = [];
        if ($projectId === 0) {
            $errors[] = 'project_id is required';
        }
        if ($summary === '') {
            $errors[] = 'summary is required';
        }
        if (isset($data['test_result']) && !in_array($data['test_result'], ['passed', 'failed', 'skipped', 'pending'], true)) {
            $errors[] = 'test_result must be one of: passed, failed, skipped, pending';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        // dev_user_id XOR dev_ai_consumer_id — human หรือ AI อย่างใดอย่างหนึ่ง
        $devUserId = isset($data['dev_user_id']) && $data['dev_user_id'] !== null ? (int) $data['dev_user_id'] : null;
        $devAiConsumerId = isset($data['dev_ai_consumer_id']) && $data['dev_ai_consumer_id'] !== null ? (int) $data['dev_ai_consumer_id'] : null;
        if ($devUserId !== null && $devAiConsumerId !== null) {
            throw new \InvalidArgumentException('dev_user_id and dev_ai_consumer_id are mutually exclusive');
        }
        if ($devUserId === null && $devAiConsumerId === null) {
            $devUserId = $submittedBy; // default: ผู้ submit คือ dev
        }

        // MILESTONE_NOT_OPEN — submit กับ milestone ที่ปิดแล้วห้าม
        $milestoneId = isset($data['milestone_id']) && $data['milestone_id'] !== null ? (int) $data['milestone_id'] : null;
        if ($milestoneId !== null) {
            $milestone = $this->milestoneRepository->findById($milestoneId);
            if ($milestone === null || $milestone->status !== 'open') {
                throw new \DomainException('MILESTONE_NOT_OPEN');
            }
        }

        return $this->revisionRepository->create([
            'project_id' => $projectId,
            'workspace_id' => $this->workspaceId,
            'milestone_id' => $milestoneId,
            'repository_id' => $data['repository_id'] ?? null,
            'summary' => $summary,
            'test_result' => $data['test_result'] ?? 'pending',
            'branch' => $data['branch'] ?? null,
            'known_issue' => $data['known_issue'] ?? null,
            'next_action' => $data['next_action'] ?? null,
            'dev_user_id' => $devUserId,
            'dev_ai_consumer_id' => $devAiConsumerId,
            'submitted_by' => $submittedBy,
        ]);
    }

    /**
     * CTO review — approve/reject (permission: revision.review ตรวจที่ route)
     *
     * @throws \DomainException REVISION_NOT_SUBMITTED / REVISION_ALREADY_REVIEWED
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function review(int $revisionId, string $decision, ?string $note, int $reviewerId): Revision
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('decision must be approved or rejected');
        }

        $revision = $this->revisionRepository->findById($revisionId);
        if ($revision === null) {
            throw new \InvalidArgumentException('revision not found');
        }
        if ($revision->status !== 'submitted') {
            throw new \DomainException('REVISION_ALREADY_REVIEWED');
        }

        $newStatus = $decision === 'approved' ? 'cto_approved' : 'cto_rejected';

        $this->revisionRepository->createReview($revisionId, $decision, $note, $reviewerId);
        $this->revisionRepository->update($revisionId, ['status' => $newStatus]);

        $updated = $this->revisionRepository->findById($revisionId);
        if ($updated === null) {
            throw new \RuntimeException('review update succeeded but re-read failed');
        }

        return $updated;
    }

    /**
     * Commit Tracking — dev บันทึก commit หลัง CTO approved
     *
     * @throws \DomainException REVISION_NOT_APPROVED
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function commit(int $revisionId, string $commitHash, ?string $branch, string $pushStatus, int $actorId): Revision
    {
        if ($commitHash === '') {
            throw new \InvalidArgumentException('commit_hash is required');
        }
        if (!in_array($pushStatus, ['pending', 'success', 'failed'], true)) {
            throw new \InvalidArgumentException('push_status must be one of: pending, success, failed');
        }

        $revision = $this->revisionRepository->findById($revisionId);
        if ($revision === null) {
            throw new \InvalidArgumentException('revision not found');
        }
        if ($revision->status !== 'cto_approved') {
            throw new \DomainException('REVISION_NOT_APPROVED');
        }

        $this->revisionRepository->update($revisionId, [
            'status' => 'committed',
            'commit_hash' => $commitHash,
            'branch' => $branch ?? $revision->branch,
            'push_status' => $pushStatus,
            'committed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // PMO Timeline update — reuse project_status_updates (idempotent ต่อ revision)
        $this->statusUpdater->updateOnRevisionCommitted($revisionId);

        $updated = $this->revisionRepository->findById($revisionId);
        if ($updated === null) {
            throw new \RuntimeException('commit update succeeded but re-read failed');
        }

        return $updated;
    }

    /**
     * @return array<int, Revision>
     */
    public function listByProject(int $projectId, ?string $status = null): array
    {
        return $this->revisionRepository->findByProjectId($projectId, $status);
    }

    public function getById(int $revisionId): ?Revision
    {
        return $this->revisionRepository->findById($revisionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReviews(int $revisionId): array
    {
        return $this->revisionRepository->findReviews($revisionId);
    }
}
