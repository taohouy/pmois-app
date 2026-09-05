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

        // ===== LINE Login session cookie (M1 R2 — CTO Review 1.3) =====
        // Web UI ที่ login ผ่าน LINE จะถือ HttpOnly session cookie แทน Bearer token
        if (!str_starts_with($header, 'Bearer ')) {
            $sessionToken = $request->getCookieParams()['pmois_session'] ?? null;
            if (is_string($sessionToken) && $sessionToken !== '') {
                return $this->withSession($request, $handler, $sessionToken);
            }

            return $this->unauthorized('Missing or invalid Authorization header');
        }

        $rawToken = trim(substr($header, 7));
        if ($rawToken === '') {
            return $this->unauthorized('Empty token');
        }

        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->db->prepare(
            'SELECT id, workspace_id, project_id, created_by_user_id, ai_consumer_id, status, expires_at, scopes
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
            // Phase 3: แนบ ai_consumer_id ด้วย (NULL = human token ปกติ)
            ->withAttribute('ai_consumer_id', $row['ai_consumer_id'] !== null ? (int) $row['ai_consumer_id'] : null)
            // M5: token scopes (แยกจาก comma) — NULL/ว่าง = legacy token (ผ่านทุก endpoint)
            ->withAttribute('token_scopes', $this->parseScopes($row['scopes'] ?? null))
            // Phase 4: project-scoped token (NULL = workspace-level / ADMIN token)
            ->withAttribute('token_project_id', $row['project_id'] !== null ? (int) $row['project_id'] : null)
            ->withAttribute('audit_context', new AuditContext());

        return $handler->handle($request);
    }

    /**
     * @return array<int, string>|null null = legacy token (ไม่ระบุ scopes)
     */
    private function parseScopes(?string $scopes): ?array
    {
        if ($scopes === null || trim($scopes) === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $scopes))));
    }

    /**
     * Session-cookie authentication path (PMOIS session ที่สร้างหลัง LINE Login)
     * Fail-closed: session ต้อง active (ยังไม่หมดอายุ/ไม่ถูก revoke) และ user
     * ต้องมี active workspace membership — ไม่งั้น 401
     */
    private function withSession(Request $request, RequestHandler $handler, string $sessionToken): Response
    {
        $sessionHash = hash('sha256', $sessionToken);
        $stmt = $this->db->prepare(
            "SELECT s.id, s.user_id, s.expires_at, u.status AS user_status
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.session_token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['hash' => $sessionHash]);
        $session = $stmt->fetch();

        if ($session === false || $session['user_status'] !== 'active') {
            return $this->unauthorized('Invalid or expired session');
        }

        $memberStmt = $this->db->prepare(
            "SELECT workspace_id FROM workspace_members
             WHERE user_id = :user_id AND status = 'active'
             ORDER BY workspace_id ASC LIMIT 1"
        );
        $memberStmt->execute(['user_id' => (int) $session['user_id']]);
        $membership = $memberStmt->fetch();

        if ($membership === false) {
            return $this->unauthorized('No active workspace membership');
        }

        $workspaceId = (int) $membership['workspace_id'];
        $this->container->set('current_workspace_id', $workspaceId);

        try {
            $update = $this->db->prepare('UPDATE user_sessions SET last_used_at = NOW() WHERE id = :id');
            $update->execute(['id' => (int) $session['id']]);
        } catch (\Throwable) {
            // non-critical
        }

        $request = $request
            ->withAttribute('workspace_id', $workspaceId)
            ->withAttribute('user_id', (int) $session['user_id'])
            ->withAttribute('session_id', (int) $session['id'])
            ->withAttribute('ai_consumer_id', null)
            ->withAttribute('token_project_id', null)
            ->withAttribute('audit_context', new AuditContext());

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResponse::error($response, 'UNAUTHORIZED', $message, [], 401);
    }
}
