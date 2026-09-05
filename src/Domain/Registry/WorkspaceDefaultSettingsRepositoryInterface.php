<?php

declare(strict_types=1);

namespace App\Domain\Registry;

interface WorkspaceDefaultSettingsRepositoryInterface
{
    public function findByWorkspaceId(int $workspaceId): ?WorkspaceDefaultSettings;

    /**
     * Upsert 1:1 ตาม workspace_id
     *
     * @param array<string, mixed> $fields — key ตรงกับ column ของ workspace_default_settings
     */
    public function upsert(int $workspaceId, array $fields, int $updatedBy): WorkspaceDefaultSettings;
}
