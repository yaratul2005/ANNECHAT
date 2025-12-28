<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Config\Database;

final class DBSchemaTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance();
    }

    public function testBlocksTableExistsAndHasExpectedColumns(): void
    {
        // DESCRIBE table to get column names
        $stmt = $this->pdo->query("DESCRIBE blocks");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        $this->assertContains('id', $cols);
        $this->assertContains('blocker_id', $cols);
        $this->assertContains('blocked_id', $cols);
        $this->assertContains('created_at', $cols);
    }

    public function testBlocksForeignKeysReferenceUsers(): void
    {
        $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blocks' AND REFERENCED_TABLE_NAME IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($rows, 'Expected foreign key constraints on blocks');

        $refs = [];
        foreach ($rows as $r) {
            $refs[$r['COLUMN_NAME']] = [$r['REFERENCED_TABLE_NAME'], $r['REFERENCED_COLUMN_NAME']];
        }

        $this->assertArrayHasKey('blocker_id', $refs);
        $this->assertSame('users', $refs['blocker_id'][0]);
        $this->assertSame('id', $refs['blocker_id'][1]);

        $this->assertArrayHasKey('blocked_id', $refs);
        $this->assertSame('users', $refs['blocked_id'][0]);
        $this->assertSame('id', $refs['blocked_id'][1]);
    }
}
