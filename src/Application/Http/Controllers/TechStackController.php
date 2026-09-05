<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectProfileCompletenessInterface;
use App\Domain\Project\TechStackService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TechStackController
{
    public function __construct(
        private readonly TechStackService $techStackService,
        private readonly ProjectProfileCompletenessInterface $completenessProvider,
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $entries = $this->techStackService->listByProject((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($e) => $e->toArray(), $entries));
    }

    public function add(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $entry = $this->techStackService->add((int) $args['project_id'], $body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $this->completenessProvider->refresh((int) $args['project_id']);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'project_technology_stack', entityId: $entry->id, afterValue: ['name' => $entry->name, 'layer' => $entry->layer]);

        return ApiResponse::success($response, $entry->toArray(), [], 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->techStackService->remove((int) $args['id'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'tech stack entry not found', [], 404);
        }

        $this->completenessProvider->refresh((int) $request->getAttribute('project_id') ?? 0);

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'deleted' => true]);
    }
}
