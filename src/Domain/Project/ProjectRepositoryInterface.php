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
    public function updateWorkspace(int $id, int $newWorkspaceId): bool;
    public function updateParent(int $id, ?int $parentProjectId): bool;
    public function updateProgress(int $id, int $progressPercent, string $health): bool;
    public function updateCurrentMilestone(int $id, ?int $milestoneId): bool;
}
