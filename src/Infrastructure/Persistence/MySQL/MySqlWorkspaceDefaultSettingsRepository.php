<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\WorkspaceDefaultSettings;
use App\Domain\Registry\WorkspaceDefaultSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MySqlWorkspaceDefaultSettingsRepository extends BaseRepository implements WorkspaceDefaultSettingsRepositoryInterface
{
    public function findByWorkspaceId(int $workspaceId): ?WorkspaceDefaultSettings
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM workspace_default_settings WHERE {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return WorkspaceDefaultSettings::fromRow($row);
    }

    public function upsert(int $workspaceId, array $fields, int $updatedBy): WorkspaceDefaultSettings
    {
        $allowed = [
            'default_cto_user_id', 'default_dev_user_id', 'default_governance_version_id',
            'default_git_provider_id', 'default_project_template_id', 'default_development_mode',
            'default_permission_preset',
        ];
        $fields = array_intersect_key($fields, array_flip($allowed));
        if ($fields === []) {
            throw new RuntimeException('no upsert fields supplied');
        }

        $existing = $this->findByWorkspaceId($workspaceId);

        if ($existing === null) {
            $columns = array_merge(array_keys($fields), ['workspace_id', 'created_by']);
            $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
            $sql = 'INSERT INTO workspace_default_settings (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $params = $fields;
            $params['workspace_id'] = $workspaceId;
            $params['created_by'] = $updatedBy;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $assignments = [];
            $params = ['workspace_id' => $workspaceId];
            foreach ($fields as $column => $value) {
                $assignments[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $sql = 'UPDATE workspace_default_settings SET ' . implode(', ', $assignments) . ' WHERE workspace_id = :workspace_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        $settings = $this->findByWorkspaceId($workspaceId);
        if ($settings === null) {
            throw new RuntimeException('workspace_default_settings upsert succeeded but re-read failed');
        }
        return $settings;
    }
}
