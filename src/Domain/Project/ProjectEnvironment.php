<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectEnvironment
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly string $environment,
        public readonly string $name,
        public readonly ?string $url,
        public readonly ?string $runtime,
        public readonly ?string $phpVersion,
        public readonly ?string $databaseEngine,
        public readonly ?string $deployPath,
        public readonly ?string $credentialReference,
        public readonly string $status,
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
            projectId: (int) $row['project_id'],
            workspaceId: (int) $row['workspace_id'],
            environment: (string) $row['environment'],
            name: (string) $row['name'],
            url: $row['url'] !== null ? (string) $row['url'] : null,
            runtime: $row['runtime'] !== null ? (string) $row['runtime'] : null,
            phpVersion: $row['php_version'] !== null ? (string) $row['php_version'] : null,
            databaseEngine: $row['database_engine'] !== null ? (string) $row['database_engine'] : null,
            deployPath: $row['deploy_path'] !== null ? (string) $row['deploy_path'] : null,
            credentialReference: $row['credential_reference'] !== null ? (string) $row['credential_reference'] : null,
            status: (string) $row['status'],
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
        // ห้ามส่ง credential_reference ออก API — เป็น pointer ไม่ใช่ secret แต่ไม่จำเป็นต้อง expose
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'environment' => $this->environment,
            'name' => $this->name,
            'url' => $this->url,
            'runtime' => $this->runtime,
            'php_version' => $this->phpVersion,
            'database_engine' => $this->databaseEngine,
            'deploy_path' => $this->deployPath,
            'status' => $this->status,
        ];
    }
}
