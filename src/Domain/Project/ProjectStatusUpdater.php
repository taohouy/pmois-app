<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Project\ProjectStatusUpdateRepositoryInterface;

final class ProjectStatusUpdater
{
    public function __construct(
        private readonly ProjectStatusUpdateRepositoryInterface $statusUpdateRepository,
    ) {
    }

    public function updateOnRevisionCommitted(int $revisionId): void
    {
        // This would create a project_status_updates entry when a revision is committed
        // Implementation depends on the specific business logic
    }
}