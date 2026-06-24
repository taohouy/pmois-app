<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Project\ProjectStatusUpdateRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProjectStatusUpdateController
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepo,
        private readonly ProjectStatusUpdateRepositoryInterface $statusRepo,
    ) {
    }

    /**
     * POST /api/v1/projects/{project_id}/status
     * Permission: project_status_update.create
     */
    public function submit(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];
        $userId    = (int) $request->getAttribute('user_id');
        $body      = (array) $request->getParsedBody();

        if ($this->projectRepo->findById($projectId) === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project', [], 404);
        }

        if (empty($body['report_date']) || empty($body['overall_status']) || empty($body['summary'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ report_date, overall_status และ summary', [], 422);
        }

        $validStatuses = ['on_track', 'at_risk', 'off_track'];
        if (!in_array($body['overall_status'], $validStatuses, true)) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'overall_status ต้องเป็น on_track, at_risk หรือ off_track', [], 422);
        }

        $idempotencyKey = isset($body['idempotency_key']) && $body['idempotency_key'] !== ''
            ? (string) $body['idempotency_key']
            : null;

        if ($idempotencyKey !== null) {
            $existing = $this->statusRepo->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return ApiResponse::success($response, $this->format($existing));
            }
        }

        $update = $this->statusRepo->create(
            projectId: $projectId,
            reportDate: (string) $body['report_date'],
            overallStatus: (string) $body['overall_status'],
            summary: (string) $body['summary'],
            keyAchievements: $body['key_achievements'] ?? null,
            keyIssues: $body['key_issues'] ?? null,
            nextSteps: $body['next_steps'] ?? null,
            submittedByUserId: $userId,
            idempotencyKey: $idempotencyKey,
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_status_update',
            entityId: $update->id,
            afterValue: ['project_id' => $projectId, 'overall_status' => $update->overallStatus, 'report_date' => $update->reportDate]
        );

        return ApiResponse::success($response, $this->format($update), [], 201);
    }

    /**
     * GET /api/v1/projects/{project_id}/status
     * Permission: project_status_update.view
     * Returns latest record; data: null if none exists yet (approved behavior — Decision 2)
     */
    public function latest(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];

        if ($this->projectRepo->findById($projectId) === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project', [], 404);
        }

        $update = $this->statusRepo->findLatestByProject($projectId);

        return ApiResponse::success($response, $update !== null ? $this->format($update) : null);
    }

    /**
     * GET /api/v1/projects/{project_id}/status/history
     * Permission: project_status_update.view
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];

        if ($this->projectRepo->findById($projectId) === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project', [], 404);
        }

        $updates = $this->statusRepo->listByProject($projectId);
        $data    = array_map(fn ($u) => $this->format($u), $updates);

        return ApiResponse::success($response, $data, [
            'pagination' => ['page' => 1, 'per_page' => count($data), 'total' => count($data)],
        ]);
    }

    private function format(\App\Domain\Project\ProjectStatusUpdate $u): array
    {
        return [
            'id'               => $u->id,
            'project_id'       => $u->projectId,
            'report_date'      => $u->reportDate,
            'overall_status'   => $u->overallStatus,
            'summary'          => $u->summary,
            'key_achievements' => $u->keyAchievements,
            'key_issues'       => $u->keyIssues,
            'next_steps'       => $u->nextSteps,
            'submitted_by'     => $u->submittedBy,
            'submitted_at'     => $u->submittedAt,
        ];
    }
}
