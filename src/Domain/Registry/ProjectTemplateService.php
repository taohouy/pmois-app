<?php

declare(strict_types=1);

namespace App\Domain\Registry;

use RuntimeException;

/**
 * ProjectTemplateService
 *
 * ตรวจ payload contract ตาม M0-Design/Revision6/R6-05 §3 และจัดการ template CRUD
 * Permission: project.template.manage (ADMIN/is_platform_admin เท่านั้น — enforce ที่ route/service)
 */
final class ProjectTemplateService
{
    private const ALLOWED_LAYERS = ['language', 'framework', 'database', 'runtime', 'frontend', 'infrastructure', 'tooling', 'other'];

    public function __construct(
        private readonly ProjectTemplateRepositoryInterface $templateRepository,
        private readonly AiConsumerCodeResolverInterface $aiConsumerResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws \InvalidArgumentException เมื่อ payload ผิด contract (TEMPLATE_PAYLOAD_INVALID)
     */
    public function validatePayload(array $payload): void
    {
        $errors = [];

        $milestones = $payload['milestones'] ?? [];
        if (!is_array($milestones)) {
            $errors[] = 'milestones must be an array';
        } else {
            foreach ($milestones as $i => $m) {
                if (!is_array($m) || empty($m['code']) || empty($m['title'])) {
                    $errors[] = "milestones[$i] requires code and title";
                }
                if (isset($m['planned_offset_days']) && (!is_numeric($m['planned_offset_days']) || (int) $m['planned_offset_days'] < 0)) {
                    $errors[] = "milestones[$i].planned_offset_days must be a non-negative number";
                }
            }
        }

        $governance = $payload['governance'] ?? [];
        if (!is_array($governance)) {
            $errors[] = 'governance must be an object';
        } elseif (isset($governance['governance_version_id']) && $governance['governance_version_id'] !== null && !is_numeric($governance['governance_version_id'])) {
            $errors[] = 'governance.governance_version_id must be numeric or null';
        }

        $aiAgents = $payload['ai_agents'] ?? [];
        if (!is_array($aiAgents)) {
            $errors[] = 'ai_agents must be an array';
        } else {
            foreach ($aiAgents as $i => $a) {
                if (!is_array($a) || empty($a['ai_consumer_code'])) {
                    $errors[] = "ai_agents[$i] requires ai_consumer_code";
                    continue;
                }
                // Agent ต้อง resolve ได้จาก Registry เท่านั้น — ห้าม hardcode ชื่อนอก registry
                if ($this->aiConsumerResolver->resolveByCode((string) $a['ai_consumer_code']) === null) {
                    $errors[] = "ai_agents[$i].ai_consumer_code '{$a['ai_consumer_code']}' not found in the workspace AI registry";
                }
            }
        }

        $techStack = $payload['tech_stack'] ?? [];
        if (!is_array($techStack)) {
            $errors[] = 'tech_stack must be an array';
        } else {
            foreach ($techStack as $i => $t) {
                if (!is_array($t) || empty($t['name'])) {
                    $errors[] = "tech_stack[$i] requires name";
                }
                if (isset($t['layer']) && !in_array($t['layer'], self::ALLOWED_LAYERS, true)) {
                    $errors[] = "tech_stack[$i].layer '{$t['layer']}' is not an allowed layer";
                }
            }
        }

        if (isset($payload['environments']) && !is_array($payload['environments'])) {
            $errors[] = 'environments must be an array';
        }

        if (isset($payload['auto_create_project_token']) && !is_bool($payload['auto_create_project_token'])) {
            $errors[] = 'auto_create_project_token must be boolean';
        }

        if (isset($payload['module_settings']) && !is_array($payload['module_settings'])) {
            $errors[] = 'module_settings must be an object';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(int $workspaceId, string $code, string $name, ?string $description, bool $isDefault, array $payload, int $createdBy): ProjectTemplate
    {
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('template code and name are required');
        }
        $this->validatePayload($payload);

        if ($this->templateRepository->findByWorkspaceAndCode($workspaceId, $code) !== null) {
            throw new RuntimeException("template code '{$code}' already exists in this workspace");
        }

        return $this->templateRepository->create($workspaceId, $code, $name, $description, $isDefault, $payload, $createdBy);
    }

    public function update(int $id, string $name, ?string $description, array $payload, string $status): void
    {
        $this->validatePayload($payload);
        if (!$this->templateRepository->update($id, $name, $description, $payload, $status)) {
            throw new RuntimeException('template not found');
        }
    }

    public function setDefault(int $workspaceId, int $templateId): void
    {
        if ($this->templateRepository->findById($templateId) === null) {
            throw new RuntimeException('template not found');
        }
        $this->templateRepository->setDefault($workspaceId, $templateId);
    }

    /**
     * @return array<int, ProjectTemplate>
     */
    public function listByWorkspace(): array
    {
        return $this->templateRepository->listByWorkspace();
    }

    public function findById(int $id): ?ProjectTemplate
    {
        return $this->templateRepository->findById($id);
    }
}
