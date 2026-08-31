<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Identity\User;
use App\Domain\Identity\UserRepositoryInterface;
use PDO;
use RuntimeException;

/**
 * Pattern: Top-level (global) -- เหมือน MySqlWorkspaceRepository ไม่ extends BaseRepository
 */
final class MySqlUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function findByLineUserId(string $lineUserId): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE line_user_id = :line_user_id LIMIT 1');
        $stmt->execute(['line_user_id' => $lineUserId]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function isPlatformAdmin(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT is_platform_admin FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        return $row !== false && (bool) $row['is_platform_admin'];
    }

    public function create(string $name, string $email, string $passwordHash): User
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, status, is_platform_admin)
             VALUES (:name, :email, :password_hash, :status, :is_platform_admin)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => 'active',
            // ✅ แก้: cast bool -> int ก่อน bind เพราะ PDO แปลง PHP false เป็น string ว่าง ""
            // ไม่ใช่ 0 ทำให้ MySQL strict mode reject ("Incorrect integer value: ''")
            'is_platform_admin' => 0, // ต้องไปตั้งแยกผ่านช่องทาง admin เท่านั้น ไม่ใช่ตอนสมัครสมาชิกปกติ
        ]);

        $newId = (int) $this->db->lastInsertId();
        $user = $this->findById($newId);

        if ($user === null) {
            throw new RuntimeException('สร้าง user สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $user;
    }

    public function createWithLine(string $name, string $email, string $lineUserId, string $authProvider): User
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, line_user_id, auth_provider, status, is_platform_admin)
             VALUES (:name, :email, :line_user_id, :auth_provider, :status, :is_platform_admin)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'line_user_id' => $lineUserId,
            'auth_provider' => $authProvider,
            'status' => 'active',
            'is_platform_admin' => 0,
        ]);

        $newId = (int) $this->db->lastInsertId();
        $user = $this->findById($newId);

        if ($user === null) {
            throw new RuntimeException('สร้าง user สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $user;
    }

    public function findByLineUserId(string $lineUserId): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE line_user_id = :line_user_id LIMIT 1');
        $stmt->execute(['line_user_id' => $lineUserId]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function updateLineInfo(int $id, string $lineUserId, string $lineDisplayName, string $avatarUrl, string $authProvider): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET line_user_id = :line_user_id, line_display_name = :line_display_name, avatar_url = :avatar_url, auth_provider = :auth_provider WHERE id = :id'
        );
        return $stmt->execute([
            'line_user_id' => $lineUserId,
            'line_display_name' => $lineDisplayName,
            'avatar_url' => $avatarUrl,
            'auth_provider' => $authProvider,
            'id' => $id,
        ]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function setPlatformAdmin(int $id, bool $isPlatformAdmin): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_platform_admin = :flag WHERE id = :id'
        );
        // ✅ แก้: cast bool -> int ก่อน bind (เหตุผลเดียวกับ create())
        return $stmt->execute(['flag' => $isPlatformAdmin ? 1 : 0, 'id' => $id]);
    }
}