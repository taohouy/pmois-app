<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Governance\GovernancePolicyService;
use App\Domain\Governance\GovernanceRecordRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class GovernanceRecordController
{
    public function __construct(
        private readonly GovernanceRecordRepositoryInterface $recordRepo,
        private readonly GovernancePolicyService $policyService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        // M4: filters (audience / policy_type / category) — ไม่ส่ง = ทั้งหมด (backward compatible)
        $query = $request->getQueryParams();
        $records = $this->recordRepo->listByWorkspaceFiltered(
            isset($query['audience']) && $query['audience'] !== '' ? (string) $query['audience'] : null,
            isset($query['policy_type']) && $query['policy_type'] !== '' ? (string) $query['policy_type'] : null,
            isset($query['category']) && $query['category'] !== '' ? (string) $query['category'] : null
        );

        return ApiResponse::success($response, array_map(static fn ($r) => $r->toArray(), $records));
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['title']) || empty($body['category'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ code, title, category', [], 422);
        }

        try {
            $record = $this->policyService->createTemplate(
                code: (string) $body['code'],
                title: (string) $body['title'],
                category: (string) $body['category'],
                description: $body['description'] ?? null,
                ownerUserId: (int) ($body['owner_user_id'] ?? $userId),
                createdByUserId: $userId,
                audience: isset($body['audience']) && $body['audience'] !== '' ? (string) $body['audience'] : null,
                policyType: isset($body['policy_type']) && $body['policy_type'] !== '' ? (string) $body['policy_type'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'governance_record', entityId: $record->id, afterValue: ['code' => $record->code]);

        return ApiResponse::success($response, ['id' => $record->id, 'code' => $record->code, 'status' => $record->status], [], 201);
    }

    /** GET /api/v1/governance-policies?policy_type=review|delivery|approval — M4 */
    public function policies(Request $request, Response $response): Response
    {
        $policyType = $request->getQueryParams()['policy_type'] ?? null;

        try {
            $records = $this->policyService->listPolicies($policyType !== null && $policyType !== '' ? (string) $policyType : null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        return ApiResponse::success($response, array_map(static fn ($r) => $r->toArray(), $records));
    }

    /** GET /api/v1/governance/working-instructions?audience=cto|dev — M4 (CTO/Dev Working Instruction Management) */
    public function workingInstructions(Request $request, Response $response): Response
    {
        $audience = $request->getQueryParams()['audience'] ?? null;

        try {
            $records = $this->policyService->listWorkingInstructions($audience !== null && $audience !== '' ? (string) $audience : null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        return ApiResponse::success($response, array_map(static fn ($r) => $r->toArray(), $records));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $record = $this->recordRepo->findById((int) $args['id']);
        if ($record === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ governance record', [], 404);
        }

        return ApiResponse::success($response, $record->toArray());
    }

    public function archive(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->recordRepo->updateStatus($id, 'deprecated')) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ governance record', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'governance_record', entityId: $id, afterValue: ['status' => 'deprecated'], action: 'archive_governance_record');

        return ApiResponse::success($response, ['id' => $id, 'status' => 'deprecated']);
    }
}
