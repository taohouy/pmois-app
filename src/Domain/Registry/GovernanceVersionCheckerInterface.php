<?php

declare(strict_types=1);

namespace App\Domain\Registry;

/** Port: เช็คว่า governance_version เป็น published และอยู่ใน workspace นี้ */
interface GovernanceVersionCheckerInterface
{
    public function isPublishedInWorkspace(int $workspaceId, int $governanceVersionId): bool;
}
