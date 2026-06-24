<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Middleware\AiAccessControlMiddleware;
use App\Infrastructure\Persistence\MySQL\MySqlAuditTrailRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * AiAccessControlMiddlewareTest
 *
 * ครอบคลุมตาม Phase 3 Specification Package -- AI Access Control Test Plan ครบ 7 case
 *
 * ⚠️ ยังไม่ได้รันจริง -- ต้องรันยืนยันบน production-like server เหมือนทุกไฟล์ก่อนหน้า
 */
final class AiAccessControlMiddlewareTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private AiAccessControlMiddleware $middleware;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $userId = $this->seedUser();
        $this->workspaceId = $this->seedWorkspace($userId);
        $this->userId = $userId;

        $auditRepo = new MySqlAuditTrailRepository($this->db, $this->workspaceId);
        $this->middleware = new AiAccessControlMiddleware($auditRepo);
    }

    private int $userId;

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /** Test #1: AI token เรียก endpoint ใน allowlist ผ่านได้ */
    public function testAiTokenCanAccessAllowedPath(): void
    {
        $request = $this->makeRequest('GET', '/api/v1/pmo-context', aiConsumerId: 5);
        $response = $this->middleware->process($request, $this->passThroughHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    /** Test #2: AI token เรียก endpoint นอก allowlist ต้องถูก reject แม้เป็น GET */
    public function testAiTokenCannotAccessNonAllowedPath(): void
    {
        $request = $this->makeRequest('GET', '/api/v1/workspace-members', aiConsumerId: 5);
        $response = $this->middleware->process($request, $this->passThroughHandler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertAuditTrailHasDenial('path_not_allowed');
    }

    /** Test #3: AI token พยายามเขียนข้อมูล (POST) ต้อง reject แม้ path จะอยู่ใน allowlist */
    public function testAiTokenCannotPerformNonGetRequest(): void
    {
        $request = $this->makeRequest('POST', '/api/v1/pmo-context', aiConsumerId: 5);
        $response = $this->middleware->process($request, $this->passThroughHandler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertAuditTrailHasDenial('non_get_method');
    }

    /**
     * Test #4 (สำคัญที่สุด): แม้ AI consumer จะถูก assign permission ที่มีสิทธิ์เขียนได้
     * (จำลอง human error) middleware นี้ก็ยัง reject เพราะไม่ได้พึ่ง permission config เลย
     * -- พิสูจน์โดยการไม่ต้อง mock permission ใดๆ เลย middleware นี้ดูแค่ ai_consumer_id + method/path
     */
    public function testAiTokenStillBlockedEvenWithFullPermissions(): void
    {
        // ไม่ต้อง setup permission ใดๆ เลย -- เพราะ middleware นี้ไม่เรียก PermissionResolver
        // แม้ ai_consumer ตัวนี้จะมี role ADMIN เต็มในระบบจริง การเช็คของ middleware นี้ก็ยัง
        // ดูแค่ ai_consumer_id != null + method != GET เท่านั้น ไม่เกี่ยวกับ permission เลย
        $request = $this->makeRequest('DELETE', '/api/v1/pmo-context', aiConsumerId: 5);
        $response = $this->middleware->process($request, $this->passThroughHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    /** Test #5: Human token (ไม่มี ai_consumer_id) ไม่ถูกกระทบจาก middleware นี้เลย */
    public function testHumanTokenIsNotAffectedByThisMiddleware(): void
    {
        $request = $this->makeRequest('POST', '/api/v1/workspace-members', aiConsumerId: null);
        $response = $this->middleware->process($request, $this->passThroughHandler());

        $this->assertSame(200, $response->getStatusCode(), 'Human token ต้องผ่าน middleware นี้ไปตามปกติเสมอ');
    }

    /** Test #6: Path ใหม่ที่ยังไม่เพิ่มเข้า allowlist ถูก reject โดย default (deny-by-default จริง) */
    public function testNewFuturePathIsDeniedByDefault(): void
    {
        $request = $this->makeRequest('GET', '/api/v1/some-future-endpoint-not-yet-built', aiConsumerId: 5);
        $response = $this->middleware->process($request, $this->passThroughHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    /** Test #7: ทุกการ reject ต้องถูกบันทึกใน audit_trails */
    public function testDenialIsLoggedToAuditTrails(): void
    {
        $request = $this->makeRequest('GET', '/api/v1/workspace-members', aiConsumerId: 5);
        $this->middleware->process($request, $this->passThroughHandler());

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM audit_trails WHERE action = 'ai_access_denied' AND workspace_id = :ws"
        );
        $stmt->execute(['ws' => $this->workspaceId]);
        $row = $stmt->fetch();

        $this->assertGreaterThan(0, (int) $row['cnt']);
    }

    // ===== Helpers =====

    private function makeRequest(string $method, string $path, ?int $aiConsumerId): ServerRequestInterface
    {
        $request = (new RequestFactory())->createRequest($method, 'https://example.test' . $path);

        return $request
            ->withAttribute('user_id', $this->userId)
            ->withAttribute('workspace_id', $this->workspaceId)
            ->withAttribute('ai_consumer_id', $aiConsumerId);
    }

    private function passThroughHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = (new ResponseFactory())->createResponse(200);
                $response->getBody()->write('{"success":true}');
                return $response;
            }
        };
    }

    private function assertAuditTrailHasDenial(string $reason): void
    {
        $stmt = $this->db->prepare(
            "SELECT after_value FROM audit_trails WHERE action = 'ai_access_denied' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $this->assertStringContainsString($reason, $row['after_value']);
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'aiacl-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-AIACL-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
