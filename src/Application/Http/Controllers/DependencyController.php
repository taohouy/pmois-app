<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectDependencyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DependencyController
{
    public function __construct(private readonly ProjectDependencyService $dependencyService)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $dependencies = $this->dependencyService->listByProject((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($d) => $d->toArray(), $dependencies));
    }

    public function graph(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->dependencyService->graph());
    }

    public function add(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $body['project_id'] = $body['project_id'] ?? (int) $args['project_id'];

        try {
            $dependency = $this->dependencyService->add($body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (\DomainException $e) {
            $status = $e->getMessage() === 'DEPENDENCY_WORKSPACE_MISMATCH' ? 409 : 409;
            return ApiResponse::error($response, $e->getMessage(), 'dependency rule violated', [], $status);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_dependency',
            entityId: $dependency->id,
            afterValue: ['project_id' => $dependency->projectId, 'related_project_id' => $dependency->relatedProjectId, 'type' => $dependency->dependencyType],
            action: 'dependency_added'
        );

        return ApiResponse::success($response, $dependency->toArray(), [], 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->dependencyService->remove((int) $args['id'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'dependency edge not found', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_dependency',
            entityId: (int) $args['id'],
            afterValue: ['deleted' => true],
            action: 'dependency_removed'
        );

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'deleted' => true]);
    }
}
