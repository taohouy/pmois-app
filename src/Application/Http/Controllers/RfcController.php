<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Rfc\RfcCommentRepositoryInterface;
use App\Domain\Rfc\RfcRepositoryInterface;
use App\Domain\Rfc\RfcService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class RfcController
{
    public function __construct(
        private readonly RfcRepositoryInterface $rfcRepo,
        private readonly RfcCommentRepositoryInterface $commentRepo,
        private readonly RfcService $rfcService
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $rfcs = $this->rfcRepo->listByWorkspace();
        $data = array_map(static fn ($r) => [
            'id' => $r->id, 'code' => $r->code, 'title' => $r->title, 'status' => $r->status,
        ], $rfcs);

        return ApiResponse::success($response, $data);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['title']) || empty($body['description'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ code, title, description', [], 422);
        }

        $rfc = $this->rfcRepo->create(
            $body['code'], $body['title'], $body['description'],
            $body['project_id'] ?? null, $body['related_governance_record_id'] ?? null, $userId
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'rfc', entityId: $rfc->id, afterValue: ['code' => $rfc->code]);

        return ApiResponse::success($response, ['id' => $rfc->id, 'code' => $rfc->code, 'status' => $rfc->status], [], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $rfc = $this->rfcRepo->findById((int) $args['id']);
        if ($rfc === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ RFC', [], 404);
        }

        return ApiResponse::success($response, [
            'id' => $rfc->id, 'code' => $rfc->code, 'title' => $rfc->title,
            'description' => $rfc->description, 'status' => $rfc->status,
            'resulting_decision_id' => $rfc->resultingDecisionId,
        ]);
    }

    public function submit(Request $request, Response $response, array $args): Response
    {
        try {
            $rfc = $this->rfcService->submit((int) $args['id']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'rfc', entityId: $rfc->id, afterValue: ['status' => 'under_review'], action: 'rfc_submitted');

        return ApiResponse::success($response, ['id' => $rfc->id, 'status' => $rfc->status]);
    }

    public function review(Request $request, Response $response, array $args): Response
    {
        $reviewerId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['decision'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ decision (approved/rejected)', [], 422);
        }

        try {
            $rfc = $this->rfcService->review((int) $args['id'], $reviewerId, $body['decision'], $body['review_note'] ?? null);
        } catch (RuntimeException $e) {
            $isSegregationViolation = str_starts_with($e->getMessage(), 'FORBIDDEN');
            return ApiResponse::error(
                $response,
                $isSegregationViolation ? 'FORBIDDEN' : 'VALIDATION_ERROR',
                $e->getMessage(),
                [],
                $isSegregationViolation ? 403 : 422
            );
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'rfc', entityId: $rfc->id, afterValue: ['status' => $rfc->status], action: 'rfc_reviewed');

        return ApiResponse::success($response, ['id' => $rfc->id, 'status' => $rfc->status]);
    }

    public function convertToDecision(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        try {
            $rfc = $this->rfcService->convertToDecision((int) $args['id'], $userId, $body['category'] ?? null);
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'rfc', entityId: $rfc->id,
            afterValue: ['status' => $rfc->status, 'resulting_decision_id' => $rfc->resultingDecisionId],
            action: 'rfc_converted_to_decision'
        );

        return ApiResponse::success($response, ['id' => $rfc->id, 'status' => $rfc->status, 'resulting_decision_id' => $rfc->resultingDecisionId]);
    }

    public function listComments(Request $request, Response $response, array $args): Response
    {
        $comments = $this->commentRepo->listByRfc((int) $args['id']);
        $data = array_map(static fn ($c) => ['id' => $c->id, 'comment_text' => $c->commentText, 'commented_by' => $c->commentedBy], $comments);

        return ApiResponse::success($response, $data);
    }

    public function addComment(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['comment_text'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ comment_text', [], 422);
        }

        try {
            $comment = $this->commentRepo->create((int) $args['id'], $body['comment_text'], $userId);
        } catch (RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', $e->getMessage(), [], 404);
        }

        return ApiResponse::success($response, ['id' => $comment->id], [], 201);
    }
}
