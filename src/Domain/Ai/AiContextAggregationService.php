<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Project\ProjectStatusUpdateRepositoryInterface;
use DateTimeImmutable;

/**
 * AiContextAggregationService
 *
 * รวบรวมข้อมูลข้าม Governance/Project/Decision เป็น context เดียวสำหรับ AI Consumer
 * ทุก method:
 *   1. ใส่ "generated_at" ใน data โดยตรง (CTO Decision -- ต้องอยู่ใน payload จริง
 *      ไม่ใช่แค่ meta.timestamp ของ Envelope เพราะ payload_snapshot เก็บแค่ data)
 *   2. เขียน ai_context_exports ทุกครั้งที่เรียกสำเร็จ (ไม่มีข้อยกเว้น)
 */
final class AiContextAggregationService
{
    private const RECENT_DECISIONS_LIMIT = 20; // CTO Decision -- default ยืนยันแล้ว

    public function __construct(
        private readonly GovernanceAdoptionRepositoryInterface $adoptionRepo,
        private readonly ProjectRepositoryInterface $projectRepo,
        private readonly ProjectStatusUpdateRepositoryInterface $statusUpdateRepo,
        private readonly DecisionRegisterRepositoryInterface $decisionRepo,
        private readonly AiContextExportRepositoryInterface $exportRepo
    ) {
    }

    public function getGovernanceSummary(int $apiTokenId, ?int $aiConsumerId): array
    {
        $payload = [
            'generated_at' => $this->now(),
            'governance_summary' => $this->buildGovernanceSummary(),
        ];

        $this->exportRepo->record($apiTokenId, $aiConsumerId, 'governance_summary', null, null, $payload);

        return $payload;
    }

    public function getProjectStatus(int $apiTokenId, ?int $aiConsumerId): array
    {
        $payload = [
            'generated_at' => $this->now(),
            'project_status' => $this->buildProjectStatus(),
        ];

        $this->exportRepo->record($apiTokenId, $aiConsumerId, 'project_status', null, null, $payload);

        return $payload;
    }

    public function getRecentDecisions(int $apiTokenId, ?int $aiConsumerId): array
    {
        $decisions = array_slice($this->decisionRepo->listByWorkspace(), 0, self::RECENT_DECISIONS_LIMIT);

        $payload = [
            'generated_at' => $this->now(),
            'recent_decisions' => array_map(static fn ($d) => [
                'title' => $d->title, 'category' => $d->category, 'status' => $d->status,
            ], $decisions),
        ];

        $this->exportRepo->record($apiTokenId, $aiConsumerId, 'decision_snapshot', null, null, $payload);

        return $payload;
    }

    public function getFullWorkspaceContext(int $apiTokenId, ?int $aiConsumerId): array
    {
        $decisions = array_slice($this->decisionRepo->listByWorkspace(), 0, 5); // ย่อกว่าตอนเรียกตรง เพราะรวมกับข้อมูลอื่นในก้อนเดียว

        $payload = [
            'generated_at' => $this->now(),
            'governance_summary' => $this->buildGovernanceSummary(),
            'project_status' => $this->buildProjectStatus(),
            'recent_decisions' => array_map(static fn ($d) => [
                'title' => $d->title, 'category' => $d->category, 'status' => $d->status,
            ], $decisions),
        ];

        $this->exportRepo->record($apiTokenId, $aiConsumerId, 'full_workspace_context', null, null, $payload);

        return $payload;
    }

    private function buildGovernanceSummary(): array
    {
        $rows = $this->adoptionRepo->listWorkspaceSummary();

        return array_map(static fn ($r) => [
            'project_id' => (int) $r['project_id'],
            'record_title' => $r['record_title'],
            'adoption_status' => $r['adoption_status'],
        ], $rows);
    }

    private function buildProjectStatus(): array
    {
        $projects = $this->projectRepo->listByWorkspace();
        $projectNameById = [];
        foreach ($projects as $p) {
            $projectNameById[$p->id] = $p->name;
        }

        $updates = $this->statusUpdateRepo->latestPerProject();

        return array_map(static fn ($u) => [
            'project_id' => $u->projectId,
            'project_name' => $projectNameById[$u->projectId] ?? 'Unknown',
            'overall_status' => $u->overallStatus,
            'summary' => $u->summary,
            'report_date' => $u->reportDate,
        ], $updates);
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format(DateTimeImmutable::ATOM);
    }
}
