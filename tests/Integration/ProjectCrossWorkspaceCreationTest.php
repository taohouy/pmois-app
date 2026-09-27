<?php

declare(strict_types=1);

namespace Tests\Integration;

use DI\Container;
use DI\ContainerBuilder;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * ProjectCrossWorkspaceCreationTest (M3 Completion Gate — CTO Decision Round 6)
 *
 * ทดสอบ business rule ที่ CTO อนุมัติ: ผู้ใช้อยู่ Workspace Tab ไหน กด "+ เพิ่มโครงการ"
 * โครงการต้องถูกสร้างเข้า Workspace นั้นจริง (POST /api/v1/projects รับ body.workspace_id
 * เป็น target workspace ที่ validate+authorize แล้ว)
 *
 * บูต Slim App จริงจาก dependencies.php + routes.php ตัวเดียวกับ production
 * (ไม่ใช่ hand-built app แบบ HttpRuntimeTest) เพราะบั๊กจริงที่พบระหว่างพัฒนา feature นี้
 * (ProjectCreateWorkspaceMiddleware ต้องอยู่ตำแหน่งที่ถูกต้องเป๊ะใน middleware chain
 * ไม่งั้น audit_trails จะได้ workspace ผิด เพราะ PHP-DI cache
 * AuditTrailRepositoryInterface ไว้ใช้ซ้ำทั้ง request ตั้งแต่ AiAccessControlMiddleware
 * resolve มันไปก่อนแล้ว) เกิดจาก "ลำดับ middleware จริง" เท่านั้น — unit test ที่ mock
 * middleware ทีละตัวจะจับบั๊กนี้ไม่ได้เลย ต้อง dispatch ผ่าน pipeline จริงเท่านั้น
 */
final class ProjectCrossWorkspaceCreationTest extends TestCase
{
    private PDO $db;
    private Container $container;
    private App $app;

    private int $workspaceA;
    private int $workspaceB;
    private int $workspaceC;
    private int $adminAOnly;
    private int $adminBOnly;
    private int $adminBoth;
    private int $outsider;

    protected function setUp(): void
    {
        $GLOBALS['app_env'] = [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => getenv('TEST_DB_PORT') ?: '3307',
            'DB_DATABASE' => 'pmois_test',
            'DB_USERNAME' => getenv('TEST_DB_USER') ?: 'root',
            'DB_PASSWORD' => getenv('TEST_DB_PASS') ?: '',
            'APP_DEBUG' => true,
        ];

        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3307;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $suffix = uniqid();
        $adminRole = (int) $this->db->query("SELECT id FROM roles WHERE code = 'ADMIN' LIMIT 1")->fetchColumn();
        $viewerRole = (int) $this->db->query("SELECT id FROM roles WHERE code = 'VIEWER' LIMIT 1")->fetchColumn();

        $this->adminAOnly = $this->insertUser("xw-a-only-$suffix");
        $this->adminBOnly = $this->insertUser("xw-b-only-$suffix");
        $this->adminBoth = $this->insertUser("xw-both-$suffix");
        $this->outsider = $this->insertUser("xw-outsider-$suffix");

        $this->workspaceA = $this->insertWorkspace("XW-A-$suffix", $this->adminAOnly);
        $this->workspaceB = $this->insertWorkspace("XW-B-$suffix", $this->adminBOnly);
        $this->workspaceC = $this->insertWorkspace("XW-C-$suffix", $this->outsider);

        $this->addMember($this->workspaceA, $this->adminAOnly, $adminRole);
        $this->addMember($this->workspaceB, $this->adminBOnly, $adminRole);
        $this->addMember($this->workspaceA, $this->adminBoth, $adminRole);
        $this->addMember($this->workspaceB, $this->adminBoth, $adminRole);
        $this->addMember($this->workspaceC, $this->outsider, $viewerRole);

        $this->insertWorkspaceDefaults($this->workspaceA, $this->adminAOnly);
        $this->insertWorkspaceDefaults($this->workspaceB, $this->adminBOnly);

        // container จริงจาก dependencies.php ตัวเดียวกับ production (ไม่ mock)
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../src/Config/dependencies.php');
        $this->container = $containerBuilder->build();

        AppFactory::setContainer($this->container);
        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        (require __DIR__ . '/../../src/Config/routes.php')($this->app, $this->container);
    }

    protected function tearDown(): void
    {
        // ลบเฉพาะข้อมูลของ test นี้ (uniqid สุ่มไม่ชนกับข้อมูลอื่น) — ไม่แตะข้อมูลอื่นในฐาน
        $ws = $this->workspaceA . ',' . $this->workspaceB . ',' . $this->workspaceC;
        $users = $this->adminAOnly . ',' . $this->adminBOnly . ',' . $this->adminBoth . ',' . $this->outsider;

        $this->db->exec("DELETE FROM audit_trails WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM user_sessions WHERE user_id IN ($users)");
        $this->db->exec("DELETE FROM project_members WHERE project_id IN (SELECT id FROM projects WHERE workspace_id IN ($ws))");
        $this->db->exec("DELETE FROM project_member_assignments WHERE project_id IN (SELECT id FROM projects WHERE workspace_id IN ($ws))");
        $this->db->exec("DELETE FROM projects WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM workspace_default_settings WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM workspace_members WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM workspaces WHERE id IN ($ws)");
        $this->db->exec("DELETE FROM users WHERE id IN ($users)");
    }

    private function insertUser(string $tag): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, auth_provider, status, is_platform_admin) VALUES (:name, :email, "local", "active", 0)');
        $stmt->execute(['name' => $tag, 'email' => $tag . '@test.local']);
        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(string $code, int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, "active", :user)');
        $stmt->execute(['code' => $code, 'user' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function addMember(int $workspaceId, int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare("INSERT INTO workspace_members (workspace_id, user_id, role_id, status) VALUES (:ws, :user, :role, 'active')");
        $stmt->execute(['ws' => $workspaceId, 'user' => $userId, 'role' => $roleId]);
    }

    private function insertWorkspaceDefaults(int $workspaceId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspace_default_settings (workspace_id, default_cto_user_id, default_dev_user_id, default_development_mode, created_by)
             VALUES (:ws, :user, :user, "ai_assisted", :user)'
        );
        $stmt->execute(['ws' => $workspaceId, 'user' => $userId]);
    }

    private function sessionCookieFor(int $userId): array
    {
        $raw = 'test-session-' . $userId . '-' . bin2hex(random_bytes(8));
        $stmt = $this->db->prepare(
            "INSERT INTO user_sessions (user_id, session_token_hash, purpose, expires_at) VALUES (:user, :hash, 'login', DATE_ADD(NOW(), INTERVAL 1 DAY))"
        );
        $stmt->execute(['user' => $userId, 'hash' => hash('sha256', $raw)]);

        return ['pmois_session' => $raw];
    }

    private function postProjects(array $cookies, array $body): ResponseInterface
    {
        $streamFactory = new StreamFactory();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://pmois.local/api/v1/projects')
            ->withHeader('Content-Type', 'application/json')
            ->withCookieParams($cookies)
            ->withBody($streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        return $this->app->handle($request);
    }

    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testBaselineCreateWithoutWorkspaceIdUsesSessionWorkspace(): void
    {
        $response = $this->postProjects(
            $this->sessionCookieFor($this->adminAOnly),
            ['code' => 'XW-T1-' . uniqid(), 'name' => 'Baseline']
        );

        $body = $this->decode($response);
        $this->assertTrue($body['success']);
        $this->assertSame($this->workspaceA, $body['data']['workspaceId']);
    }

    public function testCreateIntoUnauthorizedTargetWorkspaceIsRejected(): void
    {
        // 9001-equivalent: ADMIN ของ A เท่านั้น พยายามสร้างเข้า B ที่ไม่ได้เป็นสมาชิก
        $response = $this->postProjects(
            $this->sessionCookieFor($this->adminAOnly),
            ['code' => 'XW-T2-' . uniqid(), 'name' => 'Cross fail', 'workspace_id' => $this->workspaceB]
        );

        $this->assertSame(403, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertFalse($body['success']);
        $this->assertSame('FORBIDDEN', $body['error']['code']);
    }

    public function testCreateIntoNonexistentWorkspaceReturns404(): void
    {
        $response = $this->postProjects(
            $this->sessionCookieFor($this->adminAOnly),
            ['code' => 'XW-T3-' . uniqid(), 'name' => 'Not found', 'workspace_id' => 999999999]
        );

        $this->assertSame(404, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testOutsiderCannotCreateIntoWorkspaceTheyAreNotMemberOf(): void
    {
        $response = $this->postProjects(
            $this->sessionCookieFor($this->outsider),
            ['code' => 'XW-T4-' . uniqid(), 'name' => 'Outsider', 'workspace_id' => $this->workspaceA]
        );

        $this->assertSame(403, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame('FORBIDDEN', $body['error']['code']);
    }

    public function testCreateIntoAuthorizedNonSessionWorkspaceSucceedsWithCorrectPersistenceAndAudit(): void
    {
        // adminBoth: session ผูกกับ A (workspace_id เล็กสุดที่เป็นสมาชิก) แต่มี ADMIN ใน B ด้วย
        $code = 'XW-T5-' . uniqid();
        $response = $this->postProjects(
            $this->sessionCookieFor($this->adminBoth),
            ['code' => $code, 'name' => 'Cross success', 'workspace_id' => $this->workspaceB]
        );

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertTrue($body['success']);
        $this->assertSame($this->workspaceB, $body['data']['workspaceId']);

        $projectId = (int) $body['data']['id'];

        // Persistence: project จริงต้องอยู่ workspace B ไม่ใช่ A (session เดิม)
        $stmt = $this->db->prepare('SELECT workspace_id FROM projects WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
        $this->assertSame($this->workspaceB, (int) $stmt->fetchColumn());

        // Audit: ต้องบันทึก workspace ปลายทาง (B) ไม่ใช่ workspace เดิมของ session (A) —
        // นี่คือจุดที่เคยบั๊กจริง (PHP-DI cache AuditTrailRepositoryInterface ก่อน override)
        $stmt = $this->db->prepare(
            "SELECT workspace_id FROM audit_trails WHERE entity_type = 'project' AND entity_id = :id AND action = 'project_created'"
        );
        $stmt->execute(['id' => $projectId]);
        $this->assertSame($this->workspaceB, (int) $stmt->fetchColumn());

        // project_members ต้อง resolve default CTO/Dev ของ workspace B ไม่ใช่ A
        $stmt = $this->db->prepare('SELECT user_id FROM project_members WHERE project_id = :id');
        $stmt->execute(['id' => $projectId]);
        $memberIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $this->assertContains($this->adminBOnly, $memberIds);
    }

    public function testWorkspaceIdMatchingSessionIsNoOp(): void
    {
        $response = $this->postProjects(
            $this->sessionCookieFor($this->adminAOnly),
            ['code' => 'XW-T6-' . uniqid(), 'name' => 'Same as session', 'workspace_id' => $this->workspaceA]
        );

        $body = $this->decode($response);
        $this->assertTrue($body['success']);
        $this->assertSame($this->workspaceA, $body['data']['workspaceId']);
    }
}
