<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectProfileCompletenessInterface;
use App\Domain\Project\RepositoryRegistryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GitLab Repository Registry — manual metadata registration (write: repository.manage)
 */
final class RepositoryController
{
    public function __construct(
        private readonly RepositoryRegistryService $repositoryService,
        private readonly ProjectProfileCompletenessInterface $completenessProvider,
        private readonly ?int $workspaceDefaultGitProviderId = null,
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $repositories = $this->repositoryService->listByProject((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($r) => $r->toArray(), $repositories));
    }

    public function register(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $repository = $this->repositoryService->register(
                $body,
                (int) $request->getAttribute('user_id'),
                $this->workspaceDefaultGitProviderId
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $this->completenessProvider->refresh($repository->projectId);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'repository', entityId: $repository->id, afterValue: ['repository_url' => $repository->repositoryUrl]);

        return ApiResponse::success($response, $repository->toArray(), [], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $ok = $this->repositoryService->update((int) $args['id'], $body);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        if (!$ok) {
            return ApiResponse::error($response, 'NOT_FOUND', 'repository not found', [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'updated' => true]);
    }
}
