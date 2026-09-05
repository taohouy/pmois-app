<?php

declare(strict_types=1);

namespace App\Domain\Registry;

interface ProjectTemplateRepositoryInterface
{
    /**
     * @return array<int, ProjectTemplate>
     */
    public function listByWorkspace(): array;

    public function findById(int $id): ?ProjectTemplate;

    public function findByWorkspaceAndCode(int $workspaceId, string $code): ?ProjectTemplate;

    public function findDefaultForWorkspace(int $workspaceId): ?ProjectTemplate;

    /**
     * @param array<string, mixed> $payload
     */
    public function create(
        int $workspaceId,
        string $code,
        string $name,
        ?string $description,
        bool $isDefault,
        array $payload,
        int $createdBy
    ): ProjectTemplate;

    public function update(
        int $id,
        string $name,
        ?string $description,
        array $payload,
        string $status
    ): bool;

    /** ตั้ง template เป็น default ของ workspace (และปัด is_default ของตัวอื่นลง) */
    public function setDefault(int $workspaceId, int $templateId): bool;
}
