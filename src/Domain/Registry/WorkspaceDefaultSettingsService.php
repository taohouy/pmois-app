<?php

declare(strict_types=1);

namespace App\Domain\Registry;

use RuntimeException;

/**
 * WorkspaceDefaultSettingsService
 *
 * กติกา resolution (M0-Design/Revision6/R6-05 §2):
 * explicit request value -> workspace default -> template payload -> system fallback
 */
final class WorkspaceDefaultSettingsService
{
    private const DEVELOPMENT_MODES = ['manual', 'ai_assisted', 'ai_dev_auto'];
    private const PRESETS = ['standard', 'restricted'];

    public function __construct(
        private readonly WorkspaceDefaultSettingsRepositoryInterface $settingsRepository,
        private readonly WorkspaceMemberCheckerInterface $memberChecker,
        private readonly GovernanceVersionCheckerInterface $governanceChecker,
        private readonly GitProviderRepositoryInterface $gitProviderRepository,
        private readonly ProjectTemplateRepositoryInterface $templateRepository,
    ) {
    }

    public function get(int $workspaceId): ?WorkspaceDefaultSettings
    {
        return $this->settingsRepository->findByWorkspaceId($workspaceId);
    }

    /**
     * @param array<string, mixed> $body — fields ที่จะตั้งค่า (key snake_case ตาม API)
     * @throws \InvalidArgumentException เมื่อค่า default ไม่ valid
     */
    public function upsert(int $workspaceId, array $body, int $updatedBy): WorkspaceDefaultSettings
    {
        $fields = [];

        foreach (['default_cto_user_id', 'default_dev_user_id'] as $userField) {
            if (array_key_exists($userField, $body)) {
                $userId = $body[$userField] !== null ? (int) $body[$userField] : null;
                if ($userId !== null && !$this->memberChecker->hasActiveMembership($workspaceId, $userId)) {
                    throw new \InvalidArgumentException("{$userField} must be an active member of this workspace");
                }
                $fields[$userField] = $userId;
            }
        }

        if (array_key_exists('default_governance_version_id', $body)) {
            $versionId = $body['default_governance_version_id'] !== null ? (int) $body['default_governance_version_id'] : null;
            if ($versionId !== null && !$this->governanceChecker->isPublishedInWorkspace($workspaceId, $versionId)) {
                throw new \InvalidArgumentException('default_governance_version_id must be a published governance version in this workspace');
            }
            $fields['default_governance_version_id'] = $versionId;
        }

        if (array_key_exists('default_git_provider_id', $body)) {
            $providerId = $body['default_git_provider_id'] !== null ? (int) $body['default_git_provider_id'] : null;
            if ($providerId !== null && $this->gitProviderRepository->findById($providerId) === null) {
                throw new \InvalidArgumentException('default_git_provider_id does not exist');
            }
            $fields['default_git_provider_id'] = $providerId;
        }

        if (array_key_exists('default_project_template_id', $body)) {
            $templateId = $body['default_project_template_id'] !== null ? (int) $body['default_project_template_id'] : null;
            if ($templateId !== null) {
                $template = $this->templateRepository->findById($templateId);
                if ($template === null || $template->workspaceId !== $workspaceId || $template->status !== 'active') {
                    throw new \InvalidArgumentException('default_project_template_id must be an active template in this workspace');
                }
            }
            $fields['default_project_template_id'] = $templateId;
        }

        if (array_key_exists('default_development_mode', $body)) {
            if (!in_array($body['default_development_mode'], self::DEVELOPMENT_MODES, true)) {
                throw new \InvalidArgumentException('default_development_mode must be one of: ' . implode(', ', self::DEVELOPMENT_MODES));
            }
            $fields['default_development_mode'] = $body['default_development_mode'];
        }

        if (array_key_exists('default_permission_preset', $body)) {
            $preset = $body['default_permission_preset'];
            if ($preset !== null && !in_array($preset, self::PRESETS, true)) {
                throw new \InvalidArgumentException('default_permission_preset must be one of: ' . implode(', ', self::PRESETS));
            }
            $fields['default_permission_preset'] = $preset;
        }

        if ($fields === []) {
            throw new \InvalidArgumentException('no default settings fields supplied');
        }

        return $this->settingsRepository->upsert($workspaceId, $fields, $updatedBy);
    }

    /**
     * Resolution helper สำหรับ creation pipeline: request -> workspace default -> fallback
     */
    public function resolveDevelopmentMode(int $workspaceId, ?string $requestValue): string
    {
        if ($requestValue !== null && in_array($requestValue, self::DEVELOPMENT_MODES, true)) {
            return $requestValue;
        }
        $settings = $this->get($workspaceId);
        return $settings?->defaultDevelopmentMode ?? 'manual';
    }

    public function resolveCtoUserId(int $workspaceId, ?int $requestValue): ?int
    {
        return $requestValue ?? $this->get($workspaceId)?->defaultCtoUserId;
    }

    public function resolveDevUserId(int $workspaceId, ?int $requestValue): ?int
    {
        return $requestValue ?? $this->get($workspaceId)?->defaultDevUserId;
    }

    public function resolveTemplateId(int $workspaceId, ?int $requestValue): ?int
    {
        return $requestValue ?? $this->get($workspaceId)?->defaultProjectTemplateId;
    }
}
