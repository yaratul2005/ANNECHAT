<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use App\Models\Block;

final class BlockTest extends TestCase
{
    private \PDO $pdo;
    private Block $blockModel;
    private int $userA;
    private int $userB;

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance();
        $this->pdo->beginTransaction();

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password_hash, is_verified, is_admin, is_guest, created_at) VALUES (?, ?, ?, ?, 0, 0, ?)');
        $passwordHash = password_hash('testpass', PASSWORD_BCRYPT, ['cost' => 10]);

        $stmt->execute(['block_a_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->userA = (int)$this->pdo->lastInsertId();

        $stmt->execute(['block_b_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->userB = (int)$this->pdo->lastInsertId();

        $this->blockModel = new Block();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        Database::disconnect();
    }

    public function testBlockAndUnblock(): void
    {
        $this->assertFalse($this->blockModel->isBlocked($this->userA, $this->userB));

        $this->assertTrue($this->blockModel->block($this->userA, $this->userB));
        $this->assertTrue($this->blockModel->isBlocked($this->userA, $this->userB));

        // cannot interact after block
        $this->assertFalse($this->blockModel->canInteract($this->userA, $this->userB));

        // unblock
        $this->assertTrue($this->blockModel->unblock($this->userA, $this->userB));
        $this->assertFalse($this->blockModel->isBlocked($this->userA, $this->userB));
        $this->assertTrue($this->blockModel->canInteract($this->userA, $this->userB));
    }

    public function testGetBlockedUsersAndBlockers(): void
    {
        $this->blockModel->block($this->userA, $this->userB);

        $blocked = $this->blockModel->getBlockedUsers($this->userA);
        $this->assertIsArray($blocked);
        $this->assertNotEmpty($blocked);
        $this->assertSame((string)$this->userB, (string)$blocked[0]['blocked_id']);

        $blockers = $this->blockModel->getBlockers($this->userB);
        $this->assertIsArray($blockers);
        $this->assertNotEmpty($blockers);
        $this->assertSame((string)$this->userA, (string)$blockers[0]['blocker_id']);
    }

    public function testPositionalPrepareWorks(): void
    {
        // Ensure the PDO in the test environment can prepare and execute positional parameters
        $stmt = $this->pdo->prepare('SELECT ? + ? AS s');
        $stmt->execute([1, 2]);
        $row = $stmt->fetch();
        $this->assertSame(3, (int)$row['s']);
    }
}
