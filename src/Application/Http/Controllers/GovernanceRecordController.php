<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Governance\GovernanceRecordRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class GovernanceRecordController
{
    public function __construct(private readonly GovernanceRecordRepositoryInterface $recordRepo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $records = $this->recordRepo->listByWorkspace();
        $data = array_map(static fn ($r) => [
            'id' => $r->id, 'code' => $r->code, 'title' => $r->title,
            'category' => $r->category, 'status' => $r->status,
        ], $records);

        return ApiResponse::success($response, $data);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['title']) || empty($body['category'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ code, title, category', [], 422);
        }

        $record = $this->recordRepo->create(
            code: $body['code'],
            title: $body['title'],
            category: $body['category'],
            description: $body['description'] ?? null,
            ownerUserId: $body['owner_user_id'] ?? $userId,
            createdByUserId: $userId
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'governance_record', entityId: $record->id, afterValue: ['code' => $record->code]);

        return ApiResponse::success($response, ['id' => $record->id, 'code' => $record->code, 'status' => $record->status], [], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $record = $this->recordRepo->findById((int) $args['id']);
        if ($record === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ governance record', [], 404);
        }

        return ApiResponse::success($response, [
            'id' => $record->id, 'code' => $record->code, 'title' => $record->title,
            'category' => $record->category, 'description' => $record->description, 'status' => $record->status,
        ]);
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
