<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\Project;
use App\Domain\Project\ProjectRepositoryInterface;
use RuntimeException;

/**
 * Pattern: Scoped Repository ตรง — projects มี column workspace_id อยู่ในตัวเอง
 * ใช้ applyWorkspaceScope() + assertWorkspaceMatch() ตามมาตรฐานของ BaseRepository
 */
final class MySqlProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function findWorkspaceIdForProject(int $id): ?int
    {
        $stmt = $this->db->prepare('SELECT workspace_id FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? (int) $row['workspace_id'] : null;
    }

    public function findById(int $id, ?int $workspaceIdOverride = null): ?Project
    {
        if ($workspaceIdOverride === null) {
            $sql = $this->applyWorkspaceScope(
                'SELECT * FROM projects WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
            );
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            $this->assertWorkspaceMatch($row);

            return Project::fromRow($row);
        }

        // มี override — ใช้เมื่อต้องดึง project ที่เพิ่งย้าย/สร้างเข้า workspace อื่นที่ไม่ใช่
        // workspace ของ session ปัจจุบัน (ดู doc-comment บน interface)
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE id = :id AND workspace_id = :workspace_id LIMIT 1');
        $stmt->execute(['id' => $id, 'workspace_id' => $workspaceIdOverride]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        if ((int) $row['workspace_id'] !== $workspaceIdOverride) {
            throw new RuntimeException('Workspace scope mismatch detected ใน findById() override');
        }

        return Project::fromRow($row);
    }

    public function listByWorkspace(?int $workspaceIdOverride = null): array
    {
        // ค่า default (null) = พฤติกรรมเดิมทุกประการ: list ของ workspace ปัจจุบันของ session
        // ผ่าน applyWorkspaceScope()/assertWorkspaceMatchAll() ตามมาตรฐาน BaseRepository
        if ($workspaceIdOverride === null) {
            $sql = $this->applyWorkspaceScope(
                'SELECT * FROM projects WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC'
            );
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['workspace_id' => $this->workspaceId]);
            $rows = $stmt->fetchAll();

            $this->assertWorkspaceMatchAll($rows);

            return array_map(static fn (array $row): Project => Project::fromRow($row), $rows);
        }

        // มี override — ใช้สำหรับ Workspace Tabs ของหน้า Projects เพื่อดูโครงการของ workspace
        // อื่นที่ผู้ใช้เป็นสมาชิกด้วย (นอกเหนือจาก workspace เริ่มต้นของ session) — Controller
        // ผู้เรียกต้องเช็ค PermissionResolver กับ workspace นี้เองก่อนเรียกมาถึงจุดนี้เสมอ
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE workspace_id = :workspace_id ORDER BY created_at DESC');
        $stmt->execute(['workspace_id' => $workspaceIdOverride]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            if ((int) $row['workspace_id'] !== $workspaceIdOverride) {
                throw new RuntimeException('Workspace scope mismatch detected ใน listByWorkspace() override');
            }
        }

        return array_map(static fn (array $row): Project => Project::fromRow($row), $rows);
    }

    public function create(
        string $code,
        string $name,
        ?string $description,
        int $ownerUserId,
        int $workspaceId,
        ?int $parentProjectId = null,
        string $developmentMode = 'manual',
        ?string $abbreviation = null,
        ?string $startDate = null,
        ?int $sourceTemplateId = null,
    ): Project {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง project');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO projects (workspace_id, parent_project_id, source_template_id, code, abbreviation, name, description, start_date, status, development_mode, owner_user_id)
             VALUES (:workspace_id, :parent_project_id, :source_template_id, :code, :abbreviation, :name, :description, :start_date, :status, :development_mode, :owner_user_id)'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'parent_project_id' => $parentProjectId,
            'source_template_id' => $sourceTemplateId,
            'code' => $code,
            'abbreviation' => $abbreviation,
            'name' => $name,
            'description' => $description,
            'start_date' => $startDate,
            'status' => 'planning',
            'development_mode' => $developmentMode,
            'owner_user_id' => $ownerUserId,
        ]);

        $newId = (int) $this->db->lastInsertId();
        // แก้ไข (Consolidated Stabilization): ใช้ findById() ธรรมดา (ไม่ override) ไม่ได้ถ้า
        // $workspaceId ที่สร้างเข้าไปไม่ตรงกับ workspace ของ session ปัจจุบัน (เช่นสร้างเข้า
        // workspace อื่นจาก Workspace Tabs) — ก่อนหน้านี้ทำให้ create() รายงาน RuntimeException
        // "สร้างสำเร็จแต่ดึงข้อมูลกลับไม่ได้" ทั้งที่ insert สำเร็จจริง จึงต้องระบุ workspace
        // เป้าหมายตรงๆ ผ่าน override แทนที่จะพึ่ง $this->workspaceId ของ session
        $project = $this->findById($newId, $workspaceId);

        if ($project === null) {
            throw new RuntimeException('สร้าง project สำเร็จแต่ดึงข้อมูลกลับไม่ได้ — ตรวจสอบ DB');
        }

        return $project;
    }

    public function updateWorkspace(int $id, int $newWorkspaceId, ?int $workspaceIdOverride = null): bool
    {
        // แก้ไข (M3 Completion Gate — Round 6): เหตุผลเดียวกับ updateProgress() — เดิม scope
        // กับ workspace ของ session เท่านั้น ทำให้ ProjectStructureService::moveWorkspace()
        // ย้าย project ที่อยู่ Workspace Tab อื่น (ไม่ใช่ workspace หลักของ session) ไม่ได้เลย
        // (findById() คืน null ทั้งที่ project มีอยู่จริงและผู้ใช้มีสิทธิ์)
        $workspaceId = $workspaceIdOverride ?? $this->workspaceId;

        if ($this->findById($id, $workspaceIdOverride) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE projects SET workspace_id = :new_workspace_id WHERE id = :id AND workspace_id = :workspace_id'
        );

        return $stmt->execute([
            'new_workspace_id' => $newWorkspaceId,
            'id' => $id,
            'workspace_id' => $workspaceId,
        ]);
    }

    public function updateParent(int $id, ?int $parentProjectId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        // Prevent circular reference
        if ($parentProjectId !== null) {
            $current = $parentProjectId;
            while ($current !== null) {
                $parent = $this->findById($current);
                if ($parent !== null && $parent->parentProjectId !== null) {
                    $current = $parent->parentProjectId;
                } else {
                    $current = null;
                }
            }
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET parent_project_id = :parent_project_id WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'parent_project_id' => $parentProjectId,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    public function updateProgress(int $id, int $progressPercent, string $health, ?int $workspaceIdOverride = null): bool
    {
        // แก้ไข (M3 Completion Gate — Round 6): เดิม method นี้ scope กับ workspace ของ
        // session เท่านั้น ทำให้แก้ progress/health ของ project ที่อยู่ Workspace Tab อื่น
        // (ไม่ใช่ workspace หลักของ session) UPDATE ไม่โดนแถวไหนเลย (WHERE ไม่ match) แต่
        // execute() คืน true อยู่ดี (query รันสำเร็จ แค่ 0 rows affected) — Controller จึง
        // คิดว่าบันทึกสำเร็จทั้งที่ข้อมูลไม่ถูกบันทึกจริงเลย (fake success) เพิ่ม
        // $workspaceIdOverride ตาม pattern เดียวกับ findById() แก้ปัญหานี้
        $workspaceId = $workspaceIdOverride ?? $this->workspaceId;

        if ($this->findById($id, $workspaceIdOverride) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE projects SET progress_percent = :progress_percent, health = :health WHERE id = :id AND workspace_id = :workspace_id'
        );

        return $stmt->execute([
            'progress_percent' => $progressPercent,
            'health' => $health,
            'id' => $id,
            'workspace_id' => $workspaceId,
        ]);
    }

    public function updateCurrentMilestone(int $id, ?int $milestoneId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET current_milestone_id = :milestone_id WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'milestone_id' => $milestoneId,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        // ยืนยันก่อนว่า record นี้อยู่ใน workspace context จริง ก่อนแก้ไขใดๆ
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET status = :status WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }
}