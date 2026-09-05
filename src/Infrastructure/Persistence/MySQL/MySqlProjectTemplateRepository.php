<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\ProjectTemplate;
use App\Domain\Registry\ProjectTemplateRepositoryInterface;
use PDO;
use RuntimeException;

final class MySqlProjectTemplateRepository extends BaseRepository implements ProjectTemplateRepositoryInterface
{
    public function listByWorkspace(): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_templates WHERE {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectTemplate => ProjectTemplate::fromRow($row), $rows);
    }

    public function findById(int $id): ?ProjectTemplate
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_templates WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectTemplate::fromRow($row);
    }

    public function findByWorkspaceAndCode(int $workspaceId, string $code): ?ProjectTemplate
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_templates WHERE code = :code AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['code' => $code, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectTemplate::fromRow($row);
    }

    public function findDefaultForWorkspace(int $workspaceId): ?ProjectTemplate
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_templates WHERE is_default = 1 AND status = "active" AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectTemplate::fromRow($row);
    }

    public function create(
        int $workspaceId,
        string $code,
        string $name,
        ?string $description,
        bool $isDefault,
        array $payload,
        int $createdBy
    ): ProjectTemplate {
        $stmt = $this->db->prepare(
            'INSERT INTO project_templates (workspace_id, code, name, description, is_default, payload, status, created_by)
             VALUES (:workspace_id, :code, :name, :description, :is_default, :payload, "active", :created_by)'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'is_default' => $isDefault ? 1 : 0,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_by' => $createdBy,
        ]);

        $template = $this->findById((int) $this->db->lastInsertId());
        if ($template === null) {
            throw new RuntimeException('created project_template but re-read failed');
        }
        return $template;
    }

    public function update(int $id, string $name, ?string $description, array $payload, string $status): bool
    {
        $sql = $this->applyWorkspaceScope(
            'UPDATE project_templates SET name = :name, description = :description, payload = :payload, status = :status
             WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'workspace_id' => $this->workspaceId,
            'name' => $name,
            'description' => $description,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => $status,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function setDefault(int $workspaceId, int $templateId): bool
    {
        $clear = $this->db->prepare(
            'UPDATE project_templates SET is_default = 0 WHERE workspace_id = :workspace_id AND is_default = 1'
        );
        $clear->execute(['workspace_id' => $workspaceId]);

        $sql = $this->applyWorkspaceScope(
            'UPDATE project_templates SET is_default = 1 WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $templateId, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }
}
