<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;

/**
 * ProfileCompletenessCalculator
 *
 * Project Progress (projects.progress_percent — ติดตามการดำเนินงาน) กับ
 * Project Profile Completeness (ตัวนี้ — วัดความครบถ้วนของข้อมูลโครงการ)
 * แยกกันชัดเจนตาม CTO Requirement #5 (M0-Design/Revision6/R6-01 §4)
 *
 * ค่าที่ได้คือ computed value — ห้าม set เอง
 */
final class ProfileCompletenessCalculator
{
    public function __construct(
        private readonly GovernanceAdoptionRepositoryInterface $adoptionRepository,
        private readonly ProjectMemberAssignmentRepositoryInterface $teamAssignmentRepository,
        private readonly ProjectAiAssignmentRepositoryInterface $aiAssignmentRepository,
        private readonly RepositoryRegistryRepositoryInterface $repositoryRegistryRepository,
        private readonly MilestoneRepositoryInterface $milestoneRepository,
        private readonly ProjectEnvironmentRepositoryInterface $environmentRepository,
        private readonly ProjectTechStackRepositoryInterface $techStackRepository,
        private readonly ProjectReleaseRepositoryInterface $releaseRepository,
    ) {
    }

    /**
     * @return array{percent: int, checklist: array<string, array{satisfied: bool, weight: int}>}
     */
    public function calculate(Project $project): array
    {
        $checklist = [
            'governance_binding' => [
                'weight' => 15,
                'satisfied' => count($this->adoptionRepository->listByProject($project->id)) > 0,
            ],
            'team_assigned' => [
                'weight' => 15,
                'satisfied' => $this->hasCtoAndDev($project),
            ],
            'repository_registered' => [
                'weight' => 15,
                'satisfied' => count($this->repositoryRegistryRepository->findByProjectId($project->id)) > 0,
            ],
            'milestone_defined' => [
                'weight' => 10,
                'satisfied' => count($this->milestoneRepository->findByProjectId($project->id)) > 0,
            ],
            'environment_registered' => [
                'weight' => 15,
                'satisfied' => $this->environmentRepository->countByProjectId($project->id) > 0,
            ],
            'tech_stack_filled' => [
                'weight' => 15,
                'satisfied' => $this->techStackRepository->countByProjectId($project->id) >= 3,
            ],
            'release_recorded' => [
                'weight' => 10,
                'satisfied' => count($this->releaseRepository->findByProjectId($project->id)) > 0,
            ],
            'description_and_start_date' => [
                'weight' => 5,
                'satisfied' => $project->description !== null && $project->startDate !== null,
            ],
        ];

        $percent = 0;
        foreach ($checklist as $item) {
            if ($item['satisfied']) {
                $percent += $item['weight'];
            }
        }

        return ['percent' => $percent, 'checklist' => $checklist];
    }

    private function hasCtoAndDev(Project $project): bool
    {
        $assignments = $this->teamAssignmentRepository->findByProjectId($project->id);
        $humanActive = [];
        foreach ($assignments as $a) {
            if ($a->isActive()) {
                $humanActive[$a->userId] = true;
            }
        }

        // Dev ฝั่ง AI (ai_dev_auto mode) นับรวมด้วย
        $aiActive = [];
        foreach ($this->aiAssignmentRepository->findByProjectId($project->id) as $a) {
            if ($a->isActive()) {
                $aiActive[$a->aiConsumerId] = true;
            }
        }

        // มีมนุษย์ ≥1 คน (CTO+Dev หรือ team ใดๆ) หรือ AI dev assignment — ครอบคลุม ai_dev_auto
        return count($humanActive) >= 1 || ($project->developmentMode === 'ai_dev_auto' && count($aiActive) >= 1);
    }
}
