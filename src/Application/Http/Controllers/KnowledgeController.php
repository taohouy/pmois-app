<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Knowledge\KnowledgeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Knowledge Center API (M6) — read: project.view, write: knowledge.manage
 */
final class KnowledgeController
{
    private const MAX_LIMIT = 100;

    public function __construct(private readonly KnowledgeService $knowledgeService)
    {
    }

    /** GET /api/v1/knowledge-entries?entry_type=&project_id=&status= */
    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $entries = $this->knowledgeService->listEntries(
            isset($query['entry_type']) && $query['entry_type'] !== '' ? (string) $query['entry_type'] : null,
            isset($query['project_id']) && $query['project_id'] !== '' ? (int) $query['project_id'] : null,
            isset($query['status']) && $query['status'] !== '' ? (string) $query['status'] : null
        );

        return ApiResponse::success($response, array_map(static fn ($e) => $e->toArray(), $entries));
    }

    /** POST /api/v1/knowledge-entries */
    public function create(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $entry = $this->knowledgeService->create($body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'knowledge_entry', entityId: $entry->id, afterValue: ['entry_type' => $entry->entryType, 'title' => $entry->title]);

        return ApiResponse::success($response, $entry->toArray(), [], 201);
    }

    /** GET /api/v1/knowledge-entries/{id} */
    public function show(Request $request, Response $response, array $args): Response
    {
        $entry = $this->knowledgeService->getEntry((int) $args['id']);
        if ($entry === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'knowledge entry not found', [], 404);
        }

        return ApiResponse::success($response, $entry->toArray());
    }

    /** PATCH /api/v1/knowledge-entries/{id} */
    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $ok = $this->knowledgeService->updateEntry((int) $args['id'], $body);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        if (!$ok) {
            return ApiResponse::error($response, 'NOT_FOUND', 'knowledge entry not found', [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'updated' => true]);
    }

    /** DELETE /api/v1/knowledge-entries/{id} */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->knowledgeService->deleteEntry((int) $args['id'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'knowledge entry not found', [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'deleted' => true]);
    }

    /** GET /api/v1/knowledge/search?q=&entry_type= — unified cross-type search */
    public function search(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $q = (string) ($query['q'] ?? '');

        try {
            $result = $this->knowledgeService->search($q);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        return ApiResponse::success($response, $result);
    }

    /** GET /api/v1/architecture-decisions?project_id= — ADR (reuse decision_registers) */
    public function architectureDecisions(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $projectId = isset($query['project_id']) && $query['project_id'] !== '' ? (int) $query['project_id'] : null;

        return ApiResponse::success($response, $this->knowledgeService->architectureDecisionRecords($projectId));
    }

    /** GET /api/v1/projects/{project_id}/knowledge — Project Knowledge Base */
    public function projectKnowledge(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($response, $this->knowledgeService->projectKnowledge((int) $args['project_id']));
    }

    /** GET /api/v1/knowledge-timeline?project_id=&limit= — Knowledge Timeline */
    public function timeline(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $projectId = isset($query['project_id']) && $query['project_id'] !== '' ? (int) $query['project_id'] : null;
        $limit = max(1, min(self::MAX_LIMIT, (int) ($query['limit'] ?? 20)));

        return ApiResponse::success($response, $this->knowledgeService->knowledgeTimeline($projectId, $limit));
    }
}
