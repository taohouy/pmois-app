<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use PDO;
use RuntimeException;

/**
 * BaseRepository
 *
 * หัวใจของ Workspace-aware Repository Pattern (System Design v0.1 หมวด 4)
 *
 * หลักการ:
 *  - ทุก Repository ที่ scope ด้วย workspace_id ต้อง extends คลาสนี้
 *  - ทุก query READ/WRITE ต้องผ่าน applyWorkspaceScope() เสมอ ห้าม query workspace_id ตรงๆ ในคลาสลูก
 *  - หลัง fetch ข้อมูลแล้ว ต้องเรียก assertWorkspaceMatch() ซ้ำอีกชั้น (defense in depth)
 *    เพราะ Risk Assessment ประเมินว่า "workspace data leak" เป็นความเสี่ยงที่ผลกระทบสูงมาก
 *    แม้จะมี cost ด้าน performance เล็กน้อย ก็คุ้มที่จะเช็คซ้ำ
 *    (อ้างอิง: Phase 0 Planning v0.1 หมวด 4.2)
 */
abstract class BaseRepository
{
    public function __construct(
        protected readonly PDO $db,
        protected readonly ?int $workspaceId
    ) {
    }

    /**
     * เติม WHERE workspace_id = :workspace_id เข้าไปใน SQL อัตโนมัติ
     *
     * คาดหวังว่า $sql มีคำว่า "{{WORKSPACE_FILTER}}" เป็น placeholder ตรงจุดที่ควรใส่ filter
     * เพื่อให้คลาสลูกควบคุมตำแหน่งของ filter ได้ชัดเจน (เช่น ก่อน ORDER BY)
     */
    protected function applyWorkspaceScope(string $sql): string
    {
        $this->assertWorkspaceContextPresent();

        $filter = 'workspace_id = :workspace_id';

        if (!str_contains($sql, '{{WORKSPACE_FILTER}}')) {
            throw new RuntimeException(
                'Query นี้ไม่มี {{WORKSPACE_FILTER}} placeholder — ห้าม query โดยไม่ scope workspace'
            );
        }

        return str_replace('{{WORKSPACE_FILTER}}', $filter, $sql);
    }

    /**
     * เช็คซ้ำหลัง fetch ว่าแถวที่ได้มาตรงกับ workspace context จริง
     * ถ้าไม่ตรง = แสดงว่ามี query ที่หลุด scope ไปแล้ว ต้อง throw ทันที ไม่ปล่อยให้ข้อมูลรั่วออกไป
     *
     * @param array<string, mixed>|null $row
     */
    protected function assertWorkspaceMatch(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $this->assertWorkspaceContextPresent();

        if ((int) $row['workspace_id'] !== $this->workspaceId) {
            throw new RuntimeException(
                'Workspace scope mismatch detected — เป็นไปได้ว่ามี query ที่หลุด workspace filter. '
                . "Expected workspace_id={$this->workspaceId} แต่ได้ row workspace_id={$row['workspace_id']}"
            );
        }

        return $row;
    }

    /**
     * เวอร์ชันสำหรับ array ของหลายแถว (list endpoint)
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function assertWorkspaceMatchAll(array $rows): array
    {
        foreach ($rows as $row) {
            $this->assertWorkspaceMatch($row);
        }
        return $rows;
    }

    private function assertWorkspaceContextPresent(): void
    {
        if ($this->workspaceId === null) {
            throw new RuntimeException(
                'Repository นี้ต้องมี workspace context — เรียกผ่าน WorkspaceContextMiddleware ก่อนเสมอ'
            );
        }
    }
}
