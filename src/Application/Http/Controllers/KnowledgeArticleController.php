<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Knowledge\KnowledgeArticleRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class KnowledgeArticleController
{
    private const SUGGESTED_CATEGORIES = [
        'process', 'technical', 'onboarding', 'reference', 'faq',
        'best_practice', 'policy_summary', 'meeting_notes', 'other',
    ];

    public function __construct(private readonly KnowledgeArticleRepositoryInterface $articleRepo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $articles = $this->articleRepo->listByWorkspace(
            category: $params['category'] ?? null,
            projectId: isset($params['project_id']) ? (int) $params['project_id'] : null
        );

        return ApiResponse::success($response, array_map(static fn ($a) => [
            'id' => $a->id, 'title' => $a->title, 'category' => $a->category, 'status' => $a->status,
        ], $articles));
    }

    public function categorySuggestions(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, self::SUGGESTED_CATEGORIES);
    }

    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        if ($query === '') {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ query parameter "q"', [], 422);
        }

        $articles = $this->articleRepo->search($query, $params['category'] ?? null);

        return ApiResponse::success($response, array_map(static fn ($a) => [
            'id' => $a->id, 'title' => $a->title, 'category' => $a->category,
        ], $articles));
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['title']) || empty($body['content'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ title และ content', [], 422);
        }

        $article = $this->articleRepo->create(
            $body['title'], $body['category'] ?? null, $body['content'],
            $body['project_id'] ?? null, $userId, $userId
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'knowledge_article', entityId: $article->id, afterValue: ['title' => $article->title]);

        return ApiResponse::success($response, ['id' => $article->id, 'status' => $article->status], [], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $article = $this->articleRepo->findById((int) $args['id']);
        if ($article === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ knowledge article', [], 404);
        }

        return ApiResponse::success($response, [
            'id' => $article->id, 'title' => $article->title, 'category' => $article->category,
            'content' => $article->content, 'status' => $article->status,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $body = (array) $request->getParsedBody();

        if (empty($body['title']) || empty($body['content'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ title และ content', [], 422);
        }

        if (!$this->articleRepo->update($id, $body['title'], $body['category'] ?? null, $body['content'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ knowledge article', [], 404);
        }

        return ApiResponse::success($response, ['id' => $id]);
    }

    public function publish(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->articleRepo->updateStatus($id, 'published')) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ knowledge article', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'knowledge_article', entityId: $id, afterValue: ['status' => 'published'], action: 'publish_knowledge_article');

        return ApiResponse::success($response, ['id' => $id, 'status' => 'published']);
    }

    public function archive(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->articleRepo->updateStatus($id, 'archived')) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ knowledge article', [], 404);
        }

        return ApiResponse::success($response, ['id' => $id, 'status' => 'archived']);
    }
}
