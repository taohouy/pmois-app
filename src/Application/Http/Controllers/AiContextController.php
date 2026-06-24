<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Ai\AiContextAggregationService;
use App\Domain\Ai\MarkdownFormatterService;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AiContextController
 *
 * ทุก method รองรับ ?format=markdown ตาม CTO Decision:
 *   - JSON (default) -- ใช้ Standard Envelope ปกติ
 *   - Markdown -- ไม่ใช้ Envelope, Content-Type: text/markdown ตรงๆ
 * ทั้ง 2 format เขียน ai_context_exports เหมือนกันทุกประการ (logic เดียวกันใน Service)
 */
final class AiContextController
{
    public function __construct(
        private readonly AiContextAggregationService $aggregationService,
        private readonly MarkdownFormatterService $markdownService,
        private readonly WorkspaceRepositoryInterface $workspaceRepo
    ) {
    }

    public function pmoContext(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, fn ($tokenId, $consumerId) =>
            $this->aggregationService->getFullWorkspaceContext($tokenId, $consumerId));
    }

    public function projectsStatus(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, fn ($tokenId, $consumerId) =>
            $this->aggregationService->getProjectStatus($tokenId, $consumerId));
    }

    public function governanceSummary(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, fn ($tokenId, $consumerId) =>
            $this->aggregationService->getGovernanceSummary($tokenId, $consumerId));
    }

    public function decisionsRecent(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, fn ($tokenId, $consumerId) =>
            $this->aggregationService->getRecentDecisions($tokenId, $consumerId));
    }

    /**
     * @param callable $buildPayload fn(int $apiTokenId, ?int $aiConsumerId): array
     */
    private function respond(Request $request, Response $response, callable $buildPayload): Response
    {
        $apiTokenId = (int) $request->getAttribute('api_token_id');
        $aiConsumerIdAttr = $request->getAttribute('ai_consumer_id');
        $workspaceId = (int) $request->getAttribute('workspace_id');

        // Human token (ai_consumer_id เป็น NULL): ส่ง NULL ตรงเข้า Service/Repository ตาม CTO Decision
        // (Option B -- ai_context_exports.ai_consumer_id เป็น nullable แล้ว: NULL = Human Export)
        $aiConsumerId = $aiConsumerIdAttr !== null ? (int) $aiConsumerIdAttr : null;

        $payload = $buildPayload($apiTokenId, $aiConsumerId);

        $params = $request->getQueryParams();
        if (($params['format'] ?? null) === 'markdown') {
            $workspace = $this->workspaceRepo->findById($workspaceId);
            $markdown = $this->markdownService->format($payload, $workspace?->name ?? 'Workspace');

            $response->getBody()->write($markdown);
            return $response->withHeader('Content-Type', 'text/markdown; charset=utf-8');
        }

        return ApiResponse::success($response, $payload);
    }
}
