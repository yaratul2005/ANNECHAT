<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use App\Models\Report;

final class ReportTest extends TestCase
{
    private \PDO $pdo;
    private Report $reportModel;
    private int $reporter;
    private int $reported;

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance();
        $this->pdo->beginTransaction();

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password_hash, is_verified, is_admin, is_guest, created_at) VALUES (?, ?, ?, ?, 0, 0, ?)');
        $passwordHash = password_hash('testpass', PASSWORD_BCRYPT, ['cost' => 10]);

        $stmt->execute(['reporter_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->reporter = (int)$this->pdo->lastInsertId();

        $stmt->execute(['reported_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->reported = (int)$this->pdo->lastInsertId();

        $this->reportModel = new Report();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        Database::disconnect();
    }

    public function testCreateReportAndHasReported(): void
    {
        $reportId = $this->reportModel->create($this->reporter, $this->reported, 'spam', 'spamming test');
        $this->assertIsInt($reportId);
        $this->assertTrue($this->reportModel->hasReported($this->reporter, $this->reported));

        $reports = $this->reportModel->getReportsByReporter($this->reporter);
        $this->assertIsArray($reports);
        $this->assertNotEmpty($reports);
        $this->assertSame((string)$this->reported, (string)$reports[0]['reported_id']);
    }
}
