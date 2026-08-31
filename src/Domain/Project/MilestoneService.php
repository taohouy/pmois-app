<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class MilestoneService
{
    public function __construct(
        private readonly MilestoneRepositoryInterface $milestoneRepository,
        private readonly int $workspaceId,
    ) {
    }

    public function create(string $code, string $title, int $projectId, int $workspaceId, ?string $plannedDate, int $createdBy): int
    {
        $milestone = new Milestone(
            id: 0,
            projectId: $projectId,
            workspaceId: $workspaceId,
            code: $code,
            title: $title,
            status: 'open',
            plannedDate: $plannedDate,
            closedBy: null,
            closedAt: null,
            createdBy: $createdBy,
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            updatedAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        );

        return $this->milestoneRepository->create($milestone);
    }

    public function getById(int $id): ?Milestone
    {
        return $this->milestoneRepository->findById($id);
    }

    public function getByProjectId(int $projectId): array
    {
        return $this->milestoneRepository->findByProjectId($projectId);
    }

    public function getByProjectIdAndCode(int $projectId, string $code): ?Milestone
    {
        return $this->milestoneRepository->findByProjectIdAndCode($projectId, $code);
    }

    public function updateTitle(int $id, string $title): bool
    {
        $milestone = $this->milestoneRepository->findById($id);
        if ($milestone === null) {
            throw new \InvalidArgumentException("Milestone not found");
        }

        $milestone = new Milestone(
            id: $milestone->id,
            projectId: $milestone->projectId,
            workspaceId: $milestone->workspaceId,
            code: $milestone->code,
            title: $title,
            status: $milestone->status,
            plannedDate: $milestone->plannedDate,
            closedBy: $milestone->closedBy,
            closedAt: $milestone->closedAt,
            createdBy: $milestone->createdBy,
            createdAt: $milestone->createdAt,
            updatedAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        );

        return $this->milestoneRepository->update($milestone);
    }

    public function close(int $id, int $closedBy): bool
    {
        return $this->milestoneRepository->close($id, $closedBy);
    }

    public function open(int $id): bool
    {
        return $this->milestoneRepository->open($id);
    }

    public function getByProjectIdAndStatus(int $projectId, string $status): array
    {
        // TODO: Add method to repository if needed
        return [];
    }
}