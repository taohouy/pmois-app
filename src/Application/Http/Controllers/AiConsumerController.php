<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Ai\AiConsumerRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AiConsumerController
{
    public function __construct(private readonly AiConsumerRepositoryInterface $consumerRepo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $consumers = $this->consumerRepo->listByWorkspace();

        return ApiResponse::success($response, array_map(static fn ($c) => [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'status' => $c->status,
        ], $consumers));
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['name'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ code และ name', [], 422);
        }

        $consumer = $this->consumerRepo->create($body['code'], $body['name'], $body['description'] ?? null, $userId);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'ai_consumer', entityId: $consumer->id, afterValue: ['code' => $consumer->code]);

        return ApiResponse::success($response, ['id' => $consumer->id, 'code' => $consumer->code], [], 201);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $body = (array) $request->getParsedBody();

        if (empty($body['status']) || !in_array($body['status'], ['active', 'inactive'], true)) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'status ต้องเป็น active หรือ inactive', [], 422);
        }

        if (!$this->consumerRepo->updateStatus($id, $body['status'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ AI consumer', [], 404);
        }

        return ApiResponse::success($response, ['id' => $id, 'status' => $body['status']]);
    }
}
