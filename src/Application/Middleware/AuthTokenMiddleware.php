<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\AuditContext;
use App\Application\Http\Responders\ApiResponse;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * AuthTokenMiddleware
 *
 * แก้ไข (พบ bug จริงจากการทดสอบบน server): เพิ่มการ set ค่า workspace_id เข้า
 * DI Container โดยตรงผ่าน $container->set('current_workspace_id', ...)
 *
 * ก่อนหน้านี้ dependencies.php มี placeholder ที่รออ่านค่าจาก 'request_workspace_id'
 * แต่ไม่มีใครเคย set ค่านี้เข้า container เลย ทำให้ทุก Repository ที่ extends
 * BaseRepository ได้ workspaceId = null เสมอ (audit_trails.workspace_id ที่เห็นเป็น
 * NULL ในการทดสอบคือผลจาก bug นี้ -- และกระทบ ProjectRepository, WorkspaceMemberRepository,
 * ApiTokenRepository, WorkspaceModuleSettingRepository ทั้งหมดด้วย ไม่ใช่แค่ audit)
 *
 * จุดสำคัญ: ต้อง set() เข้า container "ก่อน" เรียก $handler->handle($request) เสมอ
 * เพราะ Controller/Repository จะถูกสร้างขึ้นตอน route handler ทำงาน (หลัง middleware
 * นี้รันเสร็จ) -- ถ้า set() หลังจาก handle() จะไม่มีผลอะไรเลย
 */
final class AuthTokenMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly \PDO $db,
        private readonly Container $container
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        $rawToken = trim(substr($header, 7));
        if ($rawToken === '') {
            return $this->unauthorized('Empty token');
        }

        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->db->prepare(
            'SELECT id, workspace_id, created_by_user_id, ai_consumer_id, status, expires_at
             FROM api_tokens WHERE token_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $row = $stmt->fetch();

        if ($row === false) {
            return $this->unauthorized('Token not found');
        }

        if ($row['status'] !== 'active') {
            return $this->unauthorized('Token revoked');
        }

        if ($row['expires_at'] !== null && new \DateTimeImmutable($row['expires_at']) < new \DateTimeImmutable()) {
            return $this->unauthorized('Token expired');
        }

        try {
            $update = $this->db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id');
            $update->execute(['id' => $row['id']]);
        } catch (\Throwable) {
            // เงียบไว้ -- ไม่ critical ต่อ request นี้
        }

        $workspaceId = (int) $row['workspace_id'];

        // ✅ จุดที่แก้: set เข้า container ตรงๆ ก่อนเรียก handler ถัดไป
        $this->container->set('current_workspace_id', $workspaceId);

        $request = $request
            ->withAttribute('api_token_id', (int) $row['id'])
            ->withAttribute('workspace_id', $workspaceId)
            ->withAttribute('user_id', (int) $row['created_by_user_id'])
            // 🆕 Phase 3: แนบ ai_consumer_id ด้วย (NULL = human token ปกติ)
            // ใช้โดย AiAccessControlMiddleware เพื่อบังคับ deny-by-default ของ AI token
            ->withAttribute('ai_consumer_id', $row['ai_consumer_id'] !== null ? (int) $row['ai_consumer_id'] : null)
            ->withAttribute('audit_context', new AuditContext());

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResponse::error($response, 'UNAUTHORIZED', $message, [], 401);
    }
}
