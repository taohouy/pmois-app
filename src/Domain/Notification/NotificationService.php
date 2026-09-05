<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Infrastructure\Http\HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * NotificationService — Telegram เป็น provider หลัก (CTO Constraint #3)
 *
 * Configuration over Hardcode:
 *  - เปิดใช้เมื่อมี TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID ใน env — ไม่ตั้ง = log status 'skipped' (ระบบยังทำงานปกติ)
 *  - event → message template เป็น config ภายใน (EVENT_TEMPLATES)
 *
 * การส่งเป็น best-effort: ล้มเหลวจะ log status='failed' แล้วไม่ throw — ธุรกรรมหลักไม่พังเพราะ notification
 */
final class NotificationService
{
    private const TELEGRAM_API = 'https://api.telegram.org';

    /** event_type => message template (config — เพิ่ม event ใหม่แก้ที่เดียว) */
    private const EVENT_TEMPLATES = [
        'revision_submitted' => '📝 Revision #%d submitted — %s',
        'revision_reviewed' => '✅/❌ Revision #%d %s — %s',
        'revision_committed' => '🚀 Revision #%d committed (branch: %s, push: %s)',
        'deployment_status_changed' => '📦 Deployment #%d → %s (project #%d)',
    ];

    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function isConfigured(): bool
    {
        return ($GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'] ?? '') !== ''
            && ($GLOBALS['app_env']['TELEGRAM_CHAT_ID'] ?? '') !== '';
    }

    /**
     * ส่ง notification ตาม event (best-effort — ไม่ throw)
     *
     * @param array<int, string|int|null> $args template arguments
     */
    public function notify(string $eventType, ?int $workspaceId, ?int $projectId, array $args = []): void
    {
        $template = self::EVENT_TEMPLATES[$eventType] ?? null;
        $message = $template !== null ? sprintf($template, ...array_map(static fn ($a) => (string) ($a ?? '-'), $args)) : $eventType;

        if (!$this->isConfigured()) {
            $this->repository->log($workspaceId, $projectId, 'telegram', $eventType, $message, 'skipped', 'TELEGRAM_BOT_TOKEN/TELEGRAM_CHAT_ID not configured');
            return;
        }

        try {
            $url = self::TELEGRAM_API . '/bot' . $GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'] . '/sendMessage';
            $request = $this->requestFactory->createRequest('POST', $url)
                ->withAddedHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream((string) json_encode([
                    'chat_id' => $GLOBALS['app_env']['TELEGRAM_CHAT_ID'],
                    'text' => $message,
                ], JSON_UNESCAPED_UNICODE)));

            $response = $this->httpClient->sendRequest($request);
            $ok = (int) $response->getStatusCode() === 200;

            $this->repository->log(
                $workspaceId, $projectId, 'telegram', $eventType, $message,
                $ok ? 'sent' : 'failed',
                $ok ? null : 'HTTP ' . $response->getStatusCode()
            );
        } catch (\Throwable $e) {
            $this->repository->log($workspaceId, $projectId, 'telegram', $eventType, $message, 'failed', $e->getMessage());
        }
    }

    /**
     * ส่งข้อความอิสระ (ใช้โดย Automation job handler — M8) — best-effort เหมือน notify()
     */
    public function sendMessage(string $message, ?int $workspaceId, ?int $projectId): void
    {
        if (!$this->isConfigured()) {
            $this->repository->log($workspaceId, $projectId, 'telegram', 'custom_message', $message, 'skipped', 'TELEGRAM_BOT_TOKEN/TELEGRAM_CHAT_ID not configured');
            return;
        }

        try {
            $url = self::TELEGRAM_API . '/bot' . $GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'] . '/sendMessage';
            $request = $this->requestFactory->createRequest('POST', $url)
                ->withAddedHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream((string) json_encode([
                    'chat_id' => $GLOBALS['app_env']['TELEGRAM_CHAT_ID'],
                    'text' => $message,
                ], JSON_UNESCAPED_UNICODE)));

            $response = $this->httpClient->sendRequest($request);
            $ok = (int) $response->getStatusCode() === 200;

            $this->repository->log(
                $workspaceId, $projectId, 'telegram', 'custom_message', $message,
                $ok ? 'sent' : 'failed',
                $ok ? null : 'HTTP ' . $response->getStatusCode()
            );
        } catch (\Throwable $e) {
            $this->repository->log($workspaceId, $projectId, 'telegram', 'custom_message', $message, 'failed', $e->getMessage());
        }
    }
}
