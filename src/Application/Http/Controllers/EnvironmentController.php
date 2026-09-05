<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\EnvironmentService;
use App\Domain\Project\ProjectProfileCompletenessInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class EnvironmentController
{
    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly ProjectProfileCompletenessInterface $completenessProvider,
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $environments = $this->environmentService->listByProject((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($e) => $e->toArray(), $environments));
    }

    public function add(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $environment = $this->environmentService->add((int) $args['project_id'], $body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $this->completenessProvider->refresh((int) $args['project_id']);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'project_environment', entityId: $environment->id, afterValue: ['environment' => $environment->environment, 'name' => $environment->name]);

        return ApiResponse::success($response, $environment->toArray(), [], 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->environmentService->remove((int) $args['id'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'environment not found', [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'deleted' => true]);
    }
}
