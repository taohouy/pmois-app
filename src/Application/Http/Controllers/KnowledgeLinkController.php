<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Knowledge\KnowledgeLinkService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * KnowledgeLinkController
 *
 * ⚠️ ไม่มี permission middleware ผูกกับ route นี้แบบ static เหมือน controller อื่น
 * เพราะ permission ที่ต้องเช็คขึ้นกับ entity_type ที่ส่งมาใน request (dynamic) --
 * KnowledgeLinkService เป็นคนเช็คสิทธิ์เองข้างใน (ตาม CTO Decision หมวด 2.5)
 */
final class KnowledgeLinkController
{
    public function __construct(private readonly KnowledgeLinkService $linkService)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $params = $request->getQueryParams();

        if (empty($params['entity_type']) || empty($params['entity_id'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ entity_type และ entity_id', [], 422);
        }

        try {
            $links = $this->linkService->listLinksForEntity(
                $params['entity_type'], (int) $params['entity_id'], $userId, $workspaceId
            );
        } catch (RuntimeException $e) {
            $isForbidden = str_starts_with($e->getMessage(), 'FORBIDDEN');
            return ApiResponse::error(
                $response, $isForbidden ? 'FORBIDDEN' : 'VALIDATION_ERROR', $e->getMessage(), [], $isForbidden ? 403 : 422
            );
        }

        return ApiResponse::success($response, array_map(static fn ($l) => [
            'id' => $l->id, 'linked_type' => $l->linkedType, 'linked_id' => $l->linkedId,
            'external_url' => $l->externalUrl, 'link_label' => $l->linkLabel,
        ], $links));
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['entity_type']) || empty($body['entity_id']) || empty($body['linked_type'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ entity_type, entity_id, linked_type', [], 422);
        }

        try {
            $link = $this->linkService->createLink(
                $body['entity_type'], (int) $body['entity_id'], $body['linked_type'],
                $body['linked_id'] ?? null, $body['external_url'] ?? null, $body['link_label'] ?? null,
                $userId, $workspaceId
            );
        } catch (RuntimeException $e) {
            $isForbidden = str_starts_with($e->getMessage(), 'FORBIDDEN');
            return ApiResponse::error(
                $response, $isForbidden ? 'FORBIDDEN' : 'VALIDATION_ERROR', $e->getMessage(), [], $isForbidden ? 403 : 422
            );
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'knowledge_link', entityId: $link->id, afterValue: ['entity_type' => $link->entityType]);

        return ApiResponse::success($response, ['id' => $link->id], [], 201);
    }
}
