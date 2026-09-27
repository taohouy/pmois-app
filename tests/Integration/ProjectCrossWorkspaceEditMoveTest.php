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
 * ProjectCrossWorkspaceEditMoveTest (M3 Completion Gate — CTO Decision Round 6, full workflow
 * re-verification)
 *
 * Round 6 made it normal for a project to live in a Workspace Tab other than the session's own
 * default workspace (via the new cross-workspace Create feature). Full end-to-end workflow
 * testing (create → edit → move, all across tabs) surfaced two real, previously-latent defects
 * in code that predates this session, invisible until a project could actually exist outside the
 * session's own default workspace:
 *
 *  1. `ProjectController::update()` (PUT /projects/{id}, the "แก้ไข" button's progress/health
 *     save) looked up the project via a session-workspace-scoped findById() — for a project in
 *     another workspace this returned NOT_FOUND (404) even though the permission check had
 *     already authorized the request via the project-level role.
 *  2. `MySqlProjectRepository::updateProgress()`/`updateWorkspace()` scoped their UPDATE ... WHERE
 *     workspace_id = :session_workspace clause to the SESSION's workspace — for a cross-workspace
 *     project this silently matched zero rows. `execute()` still returns true (the query itself
 *     ran without SQL error), so the controller believed the save succeeded and returned a fake
 *     "success" response with the client's intended values, while nothing was actually persisted.
 *
 * Both are fixed via the same override-parameter pattern already established for findById() in
 * Round 4 — this test proves the fix with real persistence checks, not just HTTP status codes,
 * since the exact failure mode here was a *misleading 200 OK with no actual write*.
 */
final class ProjectCrossWorkspaceEditMoveTest extends TestCase
{
    private PDO $db;
    private Container $container;
    private App $app;

    private int $workspaceA;
    private int $workspaceB;
    private int $adminBoth;

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
        $adminRole = $this->roleId('ADMIN');

        $this->adminBoth = $this->insertUser("xwem-both-$suffix");
        $ctoUser = $this->insertUser("xwem-cto-$suffix");
        $devUser = $this->insertUser("xwem-dev-$suffix");

        // workspaceA มี id เล็กกว่า -> session ของ adminBoth จะ bind กับ A เสมอ (AuthTokenMiddleware
        // เลือก workspace_id ต่ำสุดที่เป็นสมาชิก) — project ทุกตัวในเทสนี้อยู่ B (นอก session)
        $this->workspaceA = $this->insertWorkspace("XWEM-A-$suffix", $this->adminBoth);
        $this->workspaceB = $this->insertWorkspace("XWEM-B-$suffix", $this->adminBoth);

        $this->addMember($this->workspaceA, $this->adminBoth, $adminRole);
        $this->addMember($this->workspaceB, $this->adminBoth, $adminRole);
        // CTO/Dev แยกคนกัน (ไม่ใช่ adminBoth) กัน project-level MEMBER role ทับ workspace ADMIN
        // โดยไม่ตั้งใจ (ดู Round 5 out-of-scope observation เรื่อง PermissionResolver precedence)
        $this->addMember($this->workspaceB, $ctoUser, $this->roleId('CTO'));
        $this->addMember($this->workspaceB, $devUser, $this->roleId('MEMBER'));

        $this->insertWorkspaceDefaults($this->workspaceB, $ctoUser, $devUser);

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
        $ws = $this->workspaceA . ',' . $this->workspaceB;

        $this->db->exec("DELETE FROM audit_trails WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM project_structure_history WHERE project_id IN (SELECT id FROM projects WHERE workspace_id IN ($ws))");
        $this->db->exec("DELETE FROM project_members WHERE project_id IN (SELECT id FROM projects WHERE workspace_id IN ($ws))");
        $this->db->exec("DELETE FROM project_member_assignments WHERE project_id IN (SELECT id FROM projects WHERE workspace_id IN ($ws))");
        $this->db->exec("DELETE FROM projects WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM workspace_default_settings WHERE workspace_id IN ($ws)");
        $stmt = $this->db->prepare("SELECT DISTINCT user_id FROM workspace_members WHERE workspace_id IN ($ws)");
        $stmt->execute();
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->db->exec("DELETE FROM workspace_members WHERE workspace_id IN ($ws)");
        $this->db->exec("DELETE FROM workspaces WHERE id IN ($ws)");
        if ($userIds !== []) {
            $idList = implode(',', array_map('intval', $userIds));
            $this->db->exec("DELETE FROM user_sessions WHERE user_id IN ($idList)");
            $this->db->exec("DELETE FROM users WHERE id IN ($idList)");
        }
    }

    private function roleId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (int) $stmt->fetchColumn();
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

    private function insertWorkspaceDefaults(int $workspaceId, int $ctoUserId, int $devUserId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspace_default_settings (workspace_id, default_cto_user_id, default_dev_user_id, default_development_mode, created_by)
             VALUES (:ws, :cto, :dev, "ai_assisted", :cto)'
        );
        $stmt->execute(['ws' => $workspaceId, 'cto' => $ctoUserId, 'dev' => $devUserId]);
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

    private function request(string $method, string $uri, array $cookies, array $body): ResponseInterface
    {
        $streamFactory = new StreamFactory();
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'https://pmois.local' . $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withCookieParams($cookies)
            ->withBody($streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        return $this->app->handle($request);
    }

    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function createProjectInWorkspaceB(): int
    {
        $cookies = $this->sessionCookieFor($this->adminBoth);
        $response = $this->request('POST', '/api/v1/projects', $cookies, [
            'code' => 'XWEM-' . uniqid(),
            'name' => 'Cross-workspace edit/move fixture',
            'workspace_id' => $this->workspaceB,
        ]);
        $body = $this->decode($response);
        $this->assertTrue($body['success'], 'fixture setup: create must succeed — ' . json_encode($body));

        return (int) $body['data']['id'];
    }

    public function testEditProgressOnProjectOutsideSessionWorkspacePersistsForReal(): void
    {
        $projectId = $this->createProjectInWorkspaceB();

        // session ของ adminBoth ผูกกับ workspace A (id เล็กกว่า) — project นี้อยู่ B
        $response = $this->request('PUT', "/api/v1/projects/$projectId", $this->sessionCookieFor($this->adminBoth), [
            'progress' => 55,
            'health' => 'yellow',
        ]);

        $body = $this->decode($response);
        $this->assertSame(200, $response->getStatusCode(), json_encode($body));
        $this->assertTrue($body['success']);
        $this->assertSame(55, $body['data']['progress']);

        // ยืนยันด้วยการอ่านตรงจาก DB ไม่ใช่แค่เชื่อ response — นี่คือจุดที่เคยเป็น fake success
        $stmt = $this->db->prepare('SELECT progress_percent, health FROM projects WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch();
        $this->assertSame(55, (int) $row['progress_percent'], 'progress_percent must actually be persisted, not just echoed back');
        $this->assertSame('yellow', $row['health']);
    }

    public function testMoveProjectFromNonSessionWorkspaceSucceedsWithCorrectAudit(): void
    {
        $projectId = $this->createProjectInWorkspaceB();
        $cookies = $this->sessionCookieFor($this->adminBoth);

        $response = $this->request('PATCH', "/api/v1/projects/$projectId/structure", $cookies, [
            'action' => 'move_workspace',
            'new_workspace_id' => $this->workspaceA,
        ]);

        $body = $this->decode($response);
        $this->assertSame(200, $response->getStatusCode(), json_encode($body));
        $this->assertTrue($body['success']);

        $stmt = $this->db->prepare('SELECT workspace_id FROM projects WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
        $this->assertSame($this->workspaceA, (int) $stmt->fetchColumn());

        $stmt = $this->db->prepare(
            'SELECT from_workspace_id, to_workspace_id, changed_by FROM project_structure_history WHERE project_id = :id'
        );
        $stmt->execute(['id' => $projectId]);
        $history = $stmt->fetch();
        $this->assertSame($this->workspaceB, (int) $history['from_workspace_id']);
        $this->assertSame($this->workspaceA, (int) $history['to_workspace_id']);
        $this->assertSame($this->adminBoth, (int) $history['changed_by']);
    }
}
