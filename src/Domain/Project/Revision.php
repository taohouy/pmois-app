<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class Revision
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly ?int $milestoneId,
        public readonly ?int $repositoryId,
        public readonly string $status,
        public readonly string $summary,
        public readonly string $testResult,
        public readonly ?string $branch,
        public readonly ?string $commitHash,
        public readonly string $pushStatus,
        public readonly ?string $knownIssue,
        public readonly ?string $nextAction,
        public readonly ?int $devUserId,
        public readonly ?int $devAiConsumerId,
        public readonly int $submittedBy,
        public readonly string $submittedAt,
        public readonly ?string $committedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            projectId: (int) $row['project_id'],
            workspaceId: (int) $row['workspace_id'],
            milestoneId: $row['milestone_id'] !== null ? (int) $row['milestone_id'] : null,
            repositoryId: $row['repository_id'] !== null ? (int) $row['repository_id'] : null,
            status: (string) $row['status'],
            summary: (string) $row['summary'],
            testResult: (string) $row['test_result'],
            branch: $row['branch'] !== null ? (string) $row['branch'] : null,
            commitHash: $row['commit_hash'] !== null ? (string) $row['commit_hash'] : null,
            pushStatus: (string) $row['push_status'],
            knownIssue: $row['known_issue'] !== null ? (string) $row['known_issue'] : null,
            nextAction: $row['next_action'] !== null ? (string) $row['next_action'] : null,
            devUserId: $row['dev_user_id'] !== null ? (int) $row['dev_user_id'] : null,
            devAiConsumerId: $row['dev_ai_consumer_id'] !== null ? (int) $row['dev_ai_consumer_id'] : null,
            submittedBy: (int) $row['submitted_by'],
            submittedAt: (string) $row['submitted_at'],
            committedAt: $row['committed_at'] !== null ? (string) $row['committed_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'milestone_id' => $this->milestoneId,
            'repository_id' => $this->repositoryId,
            'status' => $this->status,
            'summary' => $this->summary,
            'test_result' => $this->testResult,
            'branch' => $this->branch,
            'commit_hash' => $this->commitHash,
            'push_status' => $this->pushStatus,
            'known_issue' => $this->knownIssue,
            'next_action' => $this->nextAction,
            'dev_user_id' => $this->devUserId,
            'dev_ai_consumer_id' => $this->devAiConsumerId,
            'submitted_by' => $this->submittedBy,
            'submitted_at' => $this->submittedAt,
            'committed_at' => $this->committedAt,
        ];
    }
}
