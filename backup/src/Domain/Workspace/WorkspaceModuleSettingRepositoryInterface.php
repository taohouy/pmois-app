<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

interface WorkspaceModuleSettingRepositoryInterface
{
    public function isEnabled(int $workspaceId, string $moduleCode): bool;

    public function setEnabled(int $workspaceId, string $moduleCode, bool $isEnabled, int $changedByUserId): void;

    /**
     * @return array<int, array{module_code: string, is_enabled: bool}>
     */
    public function listForWorkspace(int $workspaceId): array;
}
