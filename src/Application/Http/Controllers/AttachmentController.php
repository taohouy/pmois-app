<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Knowledge\AttachmentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class AttachmentController
{
    public function __construct(private readonly AttachmentService $attachmentService)
    {
    }

    /**
     * POST /attachments (multipart/form-data, field name: "file")
     */
    public function upload(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $workspaceId = (int) $request->getAttribute('workspace_id');

        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['file'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องแนบไฟล์ใน field ชื่อ "file"', [], 422);
        }

        $file = $uploadedFiles['file'];

        try {
            $attachment = $this->attachmentService->upload(
                originalName: $file->getClientFilename() ?? 'unnamed',
                tmpFilePath: $file->getStream()->getMetadata('uri'), // 🔴 ขึ้นกับ implementation ของ Slim PSR-7 stream จริง
                sizeBytes: $file->getSize() ?? 0,
                uploadedByUserId: $userId,
                workspaceId: $workspaceId
            );
        } catch (RuntimeException $e) {
            $isValidationError = str_starts_with($e->getMessage(), 'VALIDATION_ERROR');
            return ApiResponse::error(
                $response,
                $isValidationError ? 'VALIDATION_ERROR' : 'SERVER_ERROR',
                $e->getMessage(),
                [],
                $isValidationError ? 422 : 500
            );
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'attachment', entityId: $attachment->id, afterValue: ['original_name' => $attachment->originalName]);

        return ApiResponse::success($response, [
            'id' => $attachment->id,
            'original_name' => $attachment->originalName,
            'mime_type' => $attachment->mimeType,
            'size_bytes' => $attachment->sizeBytes,
        ], [], 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->attachmentService->delete($id)) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ attachment', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'attachment', entityId: $id, action: 'soft_delete_attachment');

        return ApiResponse::success($response, ['id' => $id, 'status' => 'deleted']);
    }
}
