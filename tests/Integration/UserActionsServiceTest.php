<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use App\Services\SessionService;
use App\Services\AuthService;
use App\Models\Block;
use App\Models\Report;

final class UserActionsServiceTest extends TestCase
{
    private \PDO $pdo;
    private int $userA;
    private int $userB;

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance();
        $this->pdo->beginTransaction();

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password_hash, is_verified, is_admin, is_guest, created_at) VALUES (?, ?, ?, ?, 0, 0, ?)');
        $passwordHash = password_hash('testpass', PASSWORD_BCRYPT, ['cost' => 10]);

        $stmt->execute(['ua_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->userA = (int)$this->pdo->lastInsertId();

        $stmt->execute(['ub_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->userB = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        Database::disconnect();
    }

    public function testBlockAndUnblockFlowViaServices(): void
    {
        $session = new SessionService();
        $session->create($this->userA);

        $auth = new AuthService();
        $current = $auth->getCurrentUser();
        $this->assertNotNull($current);
        $this->assertSame((string)$this->userA, (string)$current['id']);

        $block = new Block();

        $this->assertFalse($block->isBlocked($this->userA, $this->userB));
        $this->assertTrue($block->block($this->userA, $this->userB));
        $this->assertTrue($block->isBlocked($this->userA, $this->userB));
        $this->assertFalse($block->canInteract($this->userA, $this->userB));

        $this->assertTrue($block->unblock($this->userA, $this->userB));
        $this->assertFalse($block->isBlocked($this->userA, $this->userB));
        $this->assertTrue($block->canInteract($this->userA, $this->userB));
    }

    public function testReportCreatesRecord(): void
    {
        $session = new SessionService();
        $session->create($this->userA);

        $report = new Report();
        $reportId = $report->create($this->userA, $this->userB, 'spam', 'automated test');
        $this->assertIsInt($reportId);
        $this->assertGreaterThan(0, $reportId);

        $this->assertTrue($report->hasReported($this->userA, $this->userB));
    }
}
