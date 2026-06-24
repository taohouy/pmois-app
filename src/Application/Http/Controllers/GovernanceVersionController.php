<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Governance\GovernanceVersionItemRepositoryInterface;
use App\Domain\Governance\GovernanceVersionRepositoryInterface;
use App\Domain\Governance\GovernanceVersionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class GovernanceVersionController
{
    public function __construct(
        private readonly GovernanceVersionRepositoryInterface $versionRepo,
        private readonly GovernanceVersionItemRepositoryInterface $itemRepo,
        private readonly GovernanceVersionService $versionService
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $versions = $this->versionRepo->listByRecord((int) $args['record_id']);
        $data = array_map(static fn ($v) => [
            'id' => $v->id, 'version_label' => $v->versionLabel, 'status' => $v->status,
        ], $versions);

        return ApiResponse::success($response, $data);
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['version_label']) || empty($body['content'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ version_label และ content', [], 422);
        }

        try {
            $version = $this->versionRepo->create((int) $args['record_id'], $body['version_label'], $body['content'], $userId);
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', $e->getMessage(), [], 404);
        }

        return ApiResponse::success($response, ['id' => $version->id, 'version_label' => $version->versionLabel, 'status' => $version->status], [], 201);
    }

    /**
     * PUT /governance-versions/{id}/publish
     * Response คืนทั้ง published และ superseded (ถ้ามี) ตาม Phase 1 Specification หมวด 2.2
     */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $versionId = (int) $args['id'];

        try {
            $result = $this->versionService->publish($versionId, $userId);
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'governance_version',
            entityId: $versionId,
            afterValue: ['status' => 'published'],
            action: 'governance_version_published'
        );

        return ApiResponse::success($response, [
            'published' => ['id' => $result['published']->id, 'status' => $result['published']->status],
            'superseded' => $result['superseded'] !== null
                ? ['id' => $result['superseded']->id, 'status' => $result['superseded']->status]
                : null,
        ]);
    }

    public function listItems(Request $request, Response $response, array $args): Response
    {
        $items = $this->itemRepo->listByVersion((int) $args['id']);
        $data = array_map(static fn ($i) => [
            'id' => $i->id, 'item_code' => $i->itemCode, 'title' => $i->title, 'sequence_order' => $i->sequenceOrder,
        ], $items);

        return ApiResponse::success($response, $data);
    }

    public function createItem(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['item_code']) || empty($body['title'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ item_code และ title', [], 422);
        }

        try {
            $item = $this->itemRepo->create(
                (int) $args['id'],
                $body['item_code'],
                $body['title'],
                $body['description'] ?? null,
                (int) ($body['sequence_order'] ?? 0),
                $userId
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', $e->getMessage(), [], 404);
        }

        return ApiResponse::success($response, ['id' => $item->id, 'item_code' => $item->itemCode], [], 201);
    }
}
