<?php

declare(strict_types=1);

namespace App\Domain\Governance;

use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Governance\GovernanceVersionRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;

final class GovernanceAutoBindService
{
    public function __construct(
        private readonly GovernanceAdoptionRepositoryInterface $adoptionRepository,
        private readonly GovernanceVersionRepositoryInterface $versionRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function bindDefaultGovernance(int $projectId): void
    {
        // Find default governance records for the workspace
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            return;
        }

        // Find default governance versions (published, latest per record)
        // This would query governance_versions for published versions
        // For now, this is a placeholder
    }
}