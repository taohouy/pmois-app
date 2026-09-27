<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectRepositoryInterface
{
    /**
     * @param int|null $workspaceIdOverride ระบุเมื่อต้องดึง project ที่เพิ่งย้าย/สร้างเข้า
     * workspace อื่นที่ไม่ใช่ workspace ของ session ปัจจุบัน (เช่นหลัง updateWorkspace() หรือ
     * create() เข้า workspace target จาก Workspace Tabs) — Controller ที่เรียกต้องเช็ค
     * PermissionResolver กับ workspace เป้าหมายเองก่อนเรียกเสมอ ค่า null (ค่าเดิม) ยังคง
     * พฤติกรรมเดิมทุกประการ
     */
    public function findById(int $id, ?int $workspaceIdOverride = null): ?Project;

    /**
     * M3 Completion Gate (CTO Decision Round 6): unscoped lookup ของ workspace_id เจ้าของ
     * project — ใช้เฉพาะเพื่อประกอบกับ findById($id, $workspaceIdOverride) ใน endpoint ที่รับ
     * แค่ project id จาก route (เช่น PUT /projects/{id}, PUT /projects/{id}/close) ซึ่งไม่รู้
     * workspace ล่วงหน้า — Controller ต้องผ่าน RequiresPermissionMiddleware(project_id) มา
     * ก่อนเสมอ (เช็คสิทธิ์ระดับ project แล้ว) การ lookup นี้จึงไม่ใช่ช่องโหว่ข้าม workspace
     * เพิ่มเติม เป็นเพียงการหา "workspace จริง" ของ project นั้นเพื่อดึงข้อมูลให้ถูกต้อง
     * (แก้บั๊กที่ project ในหลาย workspace ที่ผู้ใช้เห็นผ่าน Workspace Tabs แต่ไม่ใช่
     * workspace ของ session แก้ไข/ปิดไม่ได้ เพราะ findById() แบบเดิม scope กับ workspace
     * ของ session เท่านั้น)
     */
    public function findWorkspaceIdForProject(int $id): ?int;

    /**
     * @param int|null $workspaceIdOverride ระบุเพื่อ list โครงการของ workspace อื่นที่ไม่ใช่
     * workspace ของ session ปัจจุบัน (ใช้โดย Workspace Tabs ของหน้า Projects) — Controller
     * ที่เรียกต้องเช็ค PermissionResolver กับ workspace เป้าหมายเองก่อนเรียกเสมอ ค่า null (ค่า
     * เดิม) ยังคงพฤติกรรมเดิมทุกประการ (list ของ workspace ปัจจุบันของ session)
     * @return array<int, Project>
     */
    public function listByWorkspace(?int $workspaceIdOverride = null): array;

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
    ): Project;

    public function updateStatus(int $id, string $status): bool;

    /**
     * @param int|null $workspaceIdOverride ระบุเมื่อ project ปัจจุบันไม่ได้อยู่ workspace ของ
     * session (เช่นถูกเรียกจาก ProjectStructureService::moveWorkspace() ที่ต้อง resolve
     * workspace จริงของ project ก่อนเสมอ) ค่า null (ค่าเดิม) ยังคงพฤติกรรมเดิมทุกประการ
     */
    public function updateWorkspace(int $id, int $newWorkspaceId, ?int $workspaceIdOverride = null): bool;
    public function updateParent(int $id, ?int $parentProjectId): bool;

    /**
     * @param int|null $workspaceIdOverride ระบุเมื่อ project ปัจจุบันไม่ได้อยู่ workspace ของ
     * session (ดู doc-comment เดียวกันกับ updateWorkspace()) ค่า null (ค่าเดิม) ยังคง
     * พฤติกรรมเดิมทุกประการ
     */
    public function updateProgress(int $id, int $progressPercent, string $health, ?int $workspaceIdOverride = null): bool;
    public function updateCurrentMilestone(int $id, ?int $milestoneId): bool;
}
