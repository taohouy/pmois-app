<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Registry\ProjectTemplateService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Project Template management — permission: project.template.manage (route level)
 */
final class ProjectTemplateController
{
    public function __construct(private readonly ProjectTemplateService $templateService)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $templates = $this->templateService->listByWorkspace();

        return ApiResponse::success($response, array_map(static fn ($t) => $t->toArray(), $templates));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $template = $this->templateService->findById((int) $args['id']);
        if ($template === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'template not found', [], 404);
        }

        return ApiResponse::success($response, $template->toArray());
    }

    public function create(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        if (empty($body['code']) || empty($body['name'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'code and name are required', [], 422);
        }

        try {
            $template = $this->templateService->create(
                (int) $request->getAttribute('workspace_id'),
                (string) $body['code'],
                (string) $body['name'],
                $body['description'] ?? null,
                (bool) ($body['is_default'] ?? false),
                (array) ($body['payload'] ?? []),
                (int) $request->getAttribute('user_id')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'TEMPLATE_PAYLOAD_INVALID', $e->getMessage(), [], 422);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'project_template', entityId: $template->id, afterValue: ['code' => $template->code]);

        return ApiResponse::success($response, $template->toArray(), [], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        try {
            $this->templateService->update(
                (int) $args['id'],
                (string) ($body['name'] ?? ''),
                $body['description'] ?? null,
                (array) ($body['payload'] ?? []),
                (string) ($body['status'] ?? 'active')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'TEMPLATE_PAYLOAD_INVALID', $e->getMessage(), [], 422);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', $e->getMessage(), [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'updated' => true]);
    }

    public function setDefault(Request $request, Response $response, array $args): Response
    {
        try {
            $this->templateService->setDefault((int) $request->getAttribute('workspace_id'), (int) $args['id']);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', $e->getMessage(), [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'is_default' => true]);
    }
}
