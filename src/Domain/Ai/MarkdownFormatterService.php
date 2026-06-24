<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * MarkdownFormatterService
 *
 * แปลง context payload (array) เป็น Markdown string สำหรับ ?format=markdown
 * ตาม CTO Decision: ยกเว้น Standard Response Envelope, ต้องมี generated_at ปรากฏใน
 * เนื้อหาด้วย (ไม่ใช่แค่ field JSON)
 */
final class MarkdownFormatterService
{
    public function format(array $payload, string $workspaceName): string
    {
        $lines = [];
        $lines[] = "# PMO Context — {$workspaceName}";
        $lines[] = "Generated: {$payload['generated_at']}";
        $lines[] = '';

        if (isset($payload['governance_summary'])) {
            $lines[] = '## Governance Summary';
            $lines[] = '| Record | Adoption Status | Project ID |';
            $lines[] = '|---|---|---|';
            foreach ($payload['governance_summary'] as $g) {
                $lines[] = "| {$g['record_title']} | {$g['adoption_status']} | {$g['project_id']} |";
            }
            $lines[] = '';
        }

        if (isset($payload['project_status'])) {
            $lines[] = '## Project Status (Latest)';
            foreach ($payload['project_status'] as $p) {
                $lines[] = "### {$p['project_name']}";
                $lines[] = "- Status: {$p['overall_status']}";
                $lines[] = "- Summary: {$p['summary']}";
                $lines[] = '';
            }
        }

        if (isset($payload['recent_decisions'])) {
            $lines[] = '## Recent Decisions';
            foreach ($payload['recent_decisions'] as $d) {
                $lines[] = "- [{$d['status']}] {$d['title']} ({$d['category']})";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
