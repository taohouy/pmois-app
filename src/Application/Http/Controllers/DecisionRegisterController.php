<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use App\Domain\Decision\DecisionRegisterService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class DecisionRegisterController
{
    public function __construct(
        private readonly DecisionRegisterRepositoryInterface $decisionRepo,
        private readonly DecisionRegisterService $decisionService
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $decisions = $this->decisionRepo->listByWorkspace();
        $data = array_map(static fn ($d) => [
            'id' => $d->id, 'category' => $d->category, 'title' => $d->title, 'status' => $d->status,
        ], $decisions);

        return ApiResponse::success($response, $data);
    }

    /**
     * POST /decision-registers
     * category เป็น required เสมอ -- ถ้าไม่ส่งมา VALIDATION_ERROR (CTO Decision)
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['title']) || empty($body['decision_description']) || empty($body['decision_date'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ title, decision_description, decision_date', [], 422);
        }

        try {
            $decision = $this->decisionService->createDirect(
                category: $body['category'] ?? null, // ตั้งใจไม่ default -- ให้ Service เป็นคน throw ถ้าไม่มี
                title: $body['title'],
                context: $body['context'] ?? null,
                decisionDescription: $body['decision_description'],
                decisionDate: $body['decision_date'],
                projectId: $body['project_id'] ?? null,
                relatedGovernanceRecordId: $body['related_governance_record_id'] ?? null,
                decidedByUserId: $body['decided_by'] ?? $userId,
                createdByUserId: $userId
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'decision_register', entityId: $decision->id, afterValue: ['category' => $decision->category]);

        return ApiResponse::success($response, ['id' => $decision->id, 'category' => $decision->category, 'status' => $decision->status], [], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $decision = $this->decisionRepo->findById((int) $args['id']);
        if ($decision === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ decision', [], 404);
        }

        return ApiResponse::success($response, [
            'id' => $decision->id, 'category' => $decision->category, 'title' => $decision->title,
            'decision_description' => $decision->decisionDescription, 'status' => $decision->status,
            'related_governance_record_id' => $decision->relatedGovernanceRecordId,
        ]);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        try {
            $decision = $this->decisionService->approve((int) $args['id']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'decision_register', entityId: $decision->id, afterValue: ['status' => 'approved'], action: 'approve_decision');

        return ApiResponse::success($response, ['id' => $decision->id, 'status' => $decision->status]);
    }
}
