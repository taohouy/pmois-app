<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectProfileCompletenessInterface;
use App\Domain\Project\ProjectReleaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReleaseController
{
    public function __construct(
        private readonly ProjectReleaseService $releaseService,
        private readonly ProjectProfileCompletenessInterface $completenessProvider,
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $releases = $this->releaseService->listByProject((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($r) => $r->toArray(), $releases));
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $body['project_id'] = $body['project_id'] ?? (int) $args['project_id'];

        try {
            $release = $this->releaseService->create($body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $this->completenessProvider->refresh((int) $args['project_id']);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'project_release', entityId: $release->id, afterValue: ['version_label' => $release->versionLabel, 'release_type' => $release->releaseType]);

        return ApiResponse::success($response, $release->toArray(), [], 201);
    }

    public function transition(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $toStatus = (string) ($body['status'] ?? '');
        if ($toStatus === '') {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'status is required', [], 422);
        }

        try {
            $release = $this->releaseService->transition((int) $args['id'], $toStatus, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $this->completenessProvider->refresh($release->projectId);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_release',
            entityId: $release->id,
            afterValue: ['status' => $release->status],
            action: 'release_status_changed'
        );

        return ApiResponse::success($response, $release->toArray());
    }
}
