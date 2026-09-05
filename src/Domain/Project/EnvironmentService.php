<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * EnvironmentService — Environment Registry (CTO Requirement #7)
 * Dev / UAT / Production + URL, Runtime, PHP Version, Database, Deploy Path
 * Hard rule: ไม่มี secret/password ใดๆ — credential_reference เป็น pointer เท่านั้น
 */
final class EnvironmentService
{
    private const TIERS = ['development', 'uat', 'production'];
    /** ชื่อ field ที่พยายามจะส่ง secret มา — reject ทันทีตาม R6-10 §2.2 */
    private const FORBIDDEN_KEYS = ['password', 'secret', 'token', 'api_key', 'apikey', 'connection_string'];

    public function __construct(
        private readonly ProjectEnvironmentRepositoryInterface $repository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectEnvironment>
     */
    public function listByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR (รวมถึง secret-like keys)
     */
    public function add(int $projectId, array $data, int $createdBy): ProjectEnvironment
    {
        foreach (array_keys($data) as $key) {
            foreach (self::FORBIDDEN_KEYS as $forbidden) {
                if (str_contains((string) $key, $forbidden)) {
                    throw new \InvalidArgumentException("field '{$key}' looks like a secret — environments never store secrets (use credential_reference)");
                }
            }
        }

        $errors = [];
        if (empty($data['name'])) {
            $errors[] = 'name is required';
        }
        if (isset($data['environment']) && !in_array($data['environment'], self::TIERS, true)) {
            $errors[] = 'environment must be one of: ' . implode(', ', self::TIERS);
        }
        if (isset($data['url']) && $data['url'] !== null && filter_var($data['url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'url must be a valid URL';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return $this->repository->create([
            'project_id' => $projectId,
            'workspace_id' => $this->workspaceId,
            'environment' => $data['environment'] ?? 'development',
            'name' => (string) $data['name'],
            'url' => $data['url'] ?? null,
            'runtime' => $data['runtime'] ?? null,
            'php_version' => $data['php_version'] ?? null,
            'database_engine' => $data['database_engine'] ?? null,
            'deploy_path' => $data['deploy_path'] ?? null,
            'credential_reference' => $data['credential_reference'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    public function remove(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
