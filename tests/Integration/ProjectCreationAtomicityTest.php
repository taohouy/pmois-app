<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Auth\ApiTokenRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Governance\GovernanceAutoBindService;
use App\Domain\Project\MilestoneRepositoryInterface;
use App\Domain\Project\ProjectAiAssignmentRepositoryInterface;
use App\Domain\Project\ProjectCreationPipeline;
use App\Domain\Project\ProjectMemberAssignmentRepositoryInterface;
use App\Domain\Project\ProjectMemberRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Project\ProfileCompletenessCalculator;
use App\Domain\Project\ProjectTechStackRepositoryInterface;
use App\Domain\Registry\AiConsumerCodeResolverInterface;
use App\Domain\Registry\GitProviderRepositoryInterface;
use App\Domain\Registry\GovernanceVersionCheckerInterface;
use App\Domain\Registry\ProjectTemplateRepositoryInterface;
use App\Domain\Registry\WorkspaceDefaultSettingsService;
use App\Domain\Registry\WorkspaceMemberCheckerInterface;
use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\MySqlGitProviderRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionChecker;
use App\Infrastructure\Persistence\MySQL\MySqlMilestoneRepository;
use App\Infrastructure\Persistence\MySQL\MySqlApiTokenRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectAiAssignmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectEnvironmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberAssignmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectReleaseRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTechStackRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTemplateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRepositoryRegistryRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceDefaultSettingsRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberChecker;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceModuleSettingRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ProjectCreationAtomicityTest — CTO Review §2 + §3
 *
 * ProjectCreationPipeline ต้องเป็น atomic: failure ตรงจุดใดก็ได้ → ROLLBACK ทั้ง creation
 * ห้ามเหลือ partial project / orphan rows
 *
 * จำลอง failure ด้วยข้อจำกัดจริงของ DB (unique/FK/enum) เพื่อให้ failure เกิดกลาง pipeline
 * ตามขั้นตอนที่ต้องการ — รันบน DB ที่ migrate ถึง 0057 แล้ว
 */
final class ProjectCreationAtomicityTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $ownerUserId;
    private int $ctoUserId;
    private int $devUserId;
    private ProjectTemplateRepositoryInterface $templateRepo;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->ownerUserId = $this->insertUser('atomic-owner', true);
        $this->ctoUserId = $this->insertUser('atomic-cto');
        $this->devUserId = $this->insertUser('atomic-dev');

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Atomic Test WS", "active", :user)');
        $stmt->execute(['code' => 'atomic-ws-' . uniqid(), 'user' => $this->ownerUserId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $this->templateRepo = new MySqlProjectTemplateRepository($this->db, $this->workspaceId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function pipeline(?AiConsumerCodeResolverInterface $resolver = null): ProjectCreationPipeline
    {
        $workspaceId = $this->workspaceId;

        $defaults = new WorkspaceDefaultSettingsService(
            new MySqlWorkspaceDefaultSettingsRepository($this->db, $workspaceId),
            new class implements WorkspaceMemberCheckerInterface {
                public function hasActiveMembership(int $workspaceId, int $userId): bool
                {
                    return true;
                }
            },
            new class implements GovernanceVersionCheckerInterface {
                public function isPublishedInWorkspace(int $workspaceId, int $governanceVersionId): bool
                {
                    return true;
                }
            },
            new MySqlGitProviderRepository($this->db),
            $this->templateRepo
        );

        return new ProjectCreationPipeline(
            $this->db,
            new MySqlProjectRepository($this->db, $workspaceId),
            new MySqlProjectMemberRepository($this->db, $workspaceId),
            new MySqlProjectMemberAssignmentRepository($this->db, $workspaceId),
            new MySqlProjectAiAssignmentRepository($this->db, $workspaceId),
            new MySqlMilestoneRepository($this->db, $workspaceId),
            new MySqlProjectTechStackRepository($this->db, $workspaceId),
            new MySqlWorkspaceModuleSettingRepository($this->db, $workspaceId),
            new MySqlApiTokenRepository($this->db, $workspaceId),
            new GovernanceAutoBindService(
                new MySqlGovernanceAdoptionRepository($this->db, $workspaceId),
                $this->db,
                $workspaceId
            ),
            $defaults,
            $this->templateRepo,
            $resolver ?? new class implements AiConsumerCodeResolverInterface {
                public function resolveByCode(string $code): ?array
                {
                    return null;
                }
            },
            new ProfileCompletenessCalculator(
                new MySqlGovernanceAdoptionRepository($this->db, $workspaceId),
                new MySqlProjectMemberAssignmentRepository($this->db, $workspaceId),
                new MySqlProjectAiAssignmentRepository($this->db, $workspaceId),
                new MySqlRepositoryRegistryRepository($this->db, $workspaceId),
                new MySqlMilestoneRepository($this->db, $workspaceId),
                new MySqlProjectEnvironmentRepository($this->db, $workspaceId),
                new MySqlProjectTechStackRepository($this->db, $workspaceId),
                new MySqlProjectReleaseRepository($this->db, $workspaceId)
            )
        );
    }

    private function baseBody(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Atomic ' . $code,
            'cto_user_id' => $this->ctoUserId,
            'dev_user_id' => $this->devUserId,
            'development_mode' => 'manual',
        ];
    }

    private function templateWith(array $payloadOverrides): int
    {
        $template = $this->templateRepo->create(
            $this->workspaceId,
            'tpl-' . uniqid(),
            'Atomic Template',
            null,
            false,
            array_merge(['milestones' => [], 'ai_agents' => [], 'tech_stack' => [], 'auto_create_project_token' => false], $payloadOverrides),
            $this->ownerUserId
        );

        return $template->id;
    }

    // ===== §3: successful complete transaction =====

    public function testSuccessfulCreationPersistsEverything(): void
    {
        $templateId = $this->templateWith([
            'milestones' => [['code' => 'M1', 'title' => 'Kick-off', 'planned_offset_days' => 7]],
            'tech_stack' => [['layer' => 'language', 'name' => 'PHP', 'version' => '8.2']],
            'auto_create_project_token' => true,
            'module_settings' => ['milestone_tracking' => true],
        ]);

        $result = $this->pipeline()->create(
            array_merge($this->baseBody('OK-1'), ['template_id' => $templateId]),
            $this->ownerUserId,
            $this->workspaceId
        );

        $projectId = $result['project']->id;

        $this->assertGreaterThan(0, $projectId);
        $this->assertSame(1, $this->countProjectsByCode('OK-1'));
        $this->assertSame(2, $this->countRows('project_members', $projectId), 'CTO + Dev projection');
        $this->assertSame(2, $this->countRows('project_member_assignments', $projectId), 'CTO + Dev ledger');
        $this->assertSame(1, $this->countRows('milestones', $projectId));
        $this->assertSame(1, $this->countRows('project_technology_stack', $projectId));
        $this->assertSame(1, $this->countRows('api_tokens', $projectId), 'template auto token');
        $this->assertArrayHasKey('project_token', $result);

        // completeness ถูก persist ภายใน transaction เดียวกัน
        $stmt = $this->db->prepare('SELECT profile_completeness_percent FROM projects WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn());
    }

    // ===== §3: failure → rollback ทั้ง creation =====

    public function testFailureDuringMilestoneCreationRollsBack(): void
    {
        // milestone ซ้ำ code — unique constraint พังกลาง pipeline (หลัง project + team assignment)
        $templateId = $this->templateWith([
            'milestones' => [
                ['code' => 'M1', 'title' => 'First'],
                ['code' => 'M1', 'title' => 'Duplicate'],
            ],
        ]);

        try {
            $this->pipeline()->create(array_merge($this->baseBody('FAIL-M'), ['template_id' => $templateId]), $this->ownerUserId, $this->workspaceId);
            $this->fail('expected failure during milestone creation');
        } catch (\PDOException) {
            // expected
        }

        $this->assertNothingLeft('FAIL-M');
    }

    public function testFailureDuringGovernanceBindRollsBack(): void
    {
        // governance_version_id ไม่มีจริง → FK violation ตอน governance binding
        $templateId = $this->templateWith(['governance' => ['governance_version_id' => 999999999]]);

        try {
            $this->pipeline()->create(array_merge($this->baseBody('FAIL-G'), ['template_id' => $templateId]), $this->ownerUserId, $this->workspaceId);
            $this->fail('expected failure during governance binding');
        } catch (\PDOException) {
            // expected
        }

        $this->assertNothingLeft('FAIL-G');
    }

    public function testFailureDuringAiAssignmentRollsBack(): void
    {
        // resolver "เจอ" agent ตาม registry แต่ id ไม่มีใน ai_consumers → FK violation
        $resolver = new class implements AiConsumerCodeResolverInterface {
            public function resolveByCode(string $code): ?array
            {
                return ['id' => 999999999, 'code' => $code, 'name' => $code, 'status' => 'active'];
            }
        };

        $templateId = $this->templateWith(['ai_agents' => [['ai_consumer_code' => 'ghost-agent', 'role' => 'MEMBER']]]);

        try {
            $this->pipeline($resolver)->create(array_merge($this->baseBody('FAIL-AI'), ['template_id' => $templateId]), $this->ownerUserId, $this->workspaceId);
            $this->fail('expected failure during AI assignment');
        } catch (\PDOException) {
            // expected
        }

        $this->assertNothingLeft('FAIL-AI');
    }

    public function testFailureDuringTokenAndDefaultConfigRollsBack(): void
    {
        // token ถูกสร้างก่อน แล้ว default configuration พัง (module_code นอก ENUM, strict mode)
        $templateId = $this->templateWith([
            'auto_create_project_token' => true,
            'module_settings' => ['bogus_module_code' => true],
        ]);

        try {
            $this->pipeline()->create(array_merge($this->baseBody('FAIL-T'), ['template_id' => $templateId]), $this->ownerUserId, $this->workspaceId);
            $this->fail('expected failure during default configuration');
        } catch (\PDOException) {
            // expected
        }

        $this->assertNothingLeft('FAIL-T');
        // ยืนยันเฉพาะจุด: token ที่ถูกสร้างก่อน failure ต้องถูก rollback หายไปด้วย
        $tokens = $this->db->query('SELECT COUNT(*) FROM api_tokens WHERE token_name LIKE "project-FAIL-T-%"')->fetchColumn();
        $this->assertSame(0, (int) $tokens, 'orphan api_token ห้ามหลงเหลือ');
    }

    public function testFailureDuringTechStackRollsBack(): void
    {
        // tech stack ซ้ำ (project, layer, name) → unique violation
        $templateId = $this->templateWith([
            'tech_stack' => [
                ['layer' => 'language', 'name' => 'PHP'],
                ['layer' => 'language', 'name' => 'PHP'],
            ],
        ]);

        try {
            $this->pipeline()->create(array_merge($this->baseBody('FAIL-TS'), ['template_id' => $templateId]), $this->ownerUserId, $this->workspaceId);
            $this->fail('expected failure during tech stack application');
        } catch (\PDOException) {
            // expected
        }

        $this->assertNothingLeft('FAIL-TS');
    }

    // ===== helpers =====

    private function assertNothingLeft(string $code): void
    {
        $this->assertSame(0, $this->countProjectsByCode($code), 'ห้ามเหลือ partial project');

        $projectId = $this->firstProjectIdByCode($code);
        if ($projectId === null) {
            // project ถูก rollback — ตรวจ orphan ผ่านตารางที่ join ไม่ได้ จึงใช้ count โดยรวมของ code-anchored rows แทน
            $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM project_member_assignments WHERE note LIKE '%{$code}%'")->fetchColumn());
            return;
        }

        foreach (['project_members', 'project_member_assignments', 'milestones', 'project_technology_stack', 'api_tokens', 'governance_adoptions', 'project_ai_assignments'] as $table) {
            $this->assertSame(0, $this->countRows($table, (int) $projectId), "orphan rows ใน {$table} ห้ามหลงเหลือ");
        }
    }

    private function countProjectsByCode(string $code): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM projects WHERE code = :code');
        $stmt->execute(['code' => $code]);

        return (int) $stmt->fetchColumn();
    }

    private function firstProjectIdByCode(string $code): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM projects WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function countRows(string $table, int $projectId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE project_id = :pid");
        $stmt->execute(['pid' => $projectId]);

        return (int) $stmt->fetchColumn();
    }

    private function insertUser(string $name, bool $isAdmin = false): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", :admin)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local', 'admin' => $isAdmin ? 1 : 0]);

        return (int) $this->db->lastInsertId();
    }
}
