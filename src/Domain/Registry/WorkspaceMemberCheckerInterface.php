<?php

declare(strict_types=1);

namespace App\Domain\Registry;

/** Port: เช็คว่า user เป็น active workspace member (ใช้ workspace_members.status='active') */
interface WorkspaceMemberCheckerInterface
{
    public function hasActiveMembership(int $workspaceId, int $userId): bool;
}
