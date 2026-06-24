<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Governance\GovernanceAdoptionItemRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class GovernanceAdoptionController
{
    public function __construct(
        private readonly GovernanceAdoptionRepositoryInterface $adoptionRepo,
        private readonly GovernanceAdoptionItemRepositoryInterface $adoptionItemRepo,
        private readonly GovernanceAdoptionService $adoptionService
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $adoptions = $this->adoptionRepo->listByProject((int) $args['project_id']);
        $data = array_map(static fn ($a) => [
            'id' => $a->id, 'governance_version_id' => $a->governanceVersionId,
            'adoption_status' => $a->adoptionStatus, 'status' => $a->status,
        ], $adoptions);

        return ApiResponse::success($response, $data);
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['governance_version_id']) || empty($body['governance_record_id'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ governance_version_id และ governance_record_id', [], 422);
        }

        try {
            $adoption = $this->adoptionService->adopt(
                (int) $args['project_id'],
                (int) $body['governance_version_id'],
                (int) $body['governance_record_id'],
                $userId
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'CONFLICT', $e->getMessage(), [], 409);
        }

        return ApiResponse::success($response, ['id' => $adoption->id, 'adoption_status' => $adoption->adoptionStatus], [], 201);
    }

    public function retire(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->adoptionRepo->retire($id)) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ adoption', [], 404);
        }

        return ApiResponse::success($response, ['id' => $id, 'status' => 'superseded']);
    }

    public function listItems(Request $request, Response $response, array $args): Response
    {
        $items = $this->adoptionItemRepo->listByAdoption((int) $args['id']);
        $data = array_map(static fn ($i) => [
            'id' => $i->id, 'governance_version_item_id' => $i->governanceVersionItemId,
            'compliance_status' => $i->complianceStatus,
        ], $items);

        return ApiResponse::success($response, $data);
    }

    public function updateItem(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['compliance_status'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ compliance_status', [], 422);
        }

        try {
            $adoption = $this->adoptionService->updateItemCompliance(
                (int) $args['item_id'],
                $body['compliance_status'],
                $body['evidence_note'] ?? null,
                $userId
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', $e->getMessage(), [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'governance_adoption_item',
            entityId: (int) $args['item_id'],
            afterValue: ['compliance_status' => $body['compliance_status']]
        );

        return ApiResponse::success($response, [
            'adoption_id' => $adoption->id,
            'adoption_status' => $adoption->adoptionStatus, // rollup ล่าสุดหลัง recalculate
        ]);
    }
}
