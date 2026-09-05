<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * Port สำหรับ controller — recomputes profile_completeness_percent ของ project
 * (เพื่อไม่ให้ controller ต้องรู้รายละเอียดของ calculator + repo update)
 */
interface ProjectProfileCompletenessInterface
{
    /** Recompute และ persist projects.profile_completeness_percent; คืนค่า percent ล่าสุด */
    public function refresh(int $projectId): int;
}
