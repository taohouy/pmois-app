<?php

declare(strict_types=1);

namespace App\Domain\Registry;

final class WorkspaceDefaultSettings
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $defaultCtoUserId,
        public readonly ?int $defaultDevUserId,
        public readonly ?int $defaultGovernanceVersionId,
        public readonly ?int $defaultGitProviderId,
        public readonly ?int $defaultProjectTemplateId,
        public readonly string $defaultDevelopmentMode,
        public readonly ?string $defaultPermissionPreset,
        public readonly int $createdBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            defaultCtoUserId: $row['default_cto_user_id'] !== null ? (int) $row['default_cto_user_id'] : null,
            defaultDevUserId: $row['default_dev_user_id'] !== null ? (int) $row['default_dev_user_id'] : null,
            defaultGovernanceVersionId: $row['default_governance_version_id'] !== null ? (int) $row['default_governance_version_id'] : null,
            defaultGitProviderId: $row['default_git_provider_id'] !== null ? (int) $row['default_git_provider_id'] : null,
            defaultProjectTemplateId: $row['default_project_template_id'] !== null ? (int) $row['default_project_template_id'] : null,
            defaultDevelopmentMode: (string) $row['default_development_mode'],
            defaultPermissionPreset: $row['default_permission_preset'] !== null ? (string) $row['default_permission_preset'] : null,
            createdBy: (int) $row['created_by'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'default_cto_user_id' => $this->defaultCtoUserId,
            'default_dev_user_id' => $this->defaultDevUserId,
            'default_governance_version_id' => $this->defaultGovernanceVersionId,
            'default_git_provider_id' => $this->defaultGitProviderId,
            'default_project_template_id' => $this->defaultProjectTemplateId,
            'default_development_mode' => $this->defaultDevelopmentMode,
            'default_permission_preset' => $this->defaultPermissionPreset,
        ];
    }
}
