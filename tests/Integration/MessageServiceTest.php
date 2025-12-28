<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use App\Services\MessageService;

final class MessageServiceTest extends TestCase
{
    private \PDO $pdo;
    private int $senderId;
    private int $recipientId;

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance();
        $this->pdo->beginTransaction();

        // Create sender and recipient users for the test (verified)
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password_hash, is_verified, is_admin, is_guest, created_at) VALUES (?, ?, ?, ?, 0, 0, ?)');
        $passwordHash = password_hash('testpass', PASSWORD_BCRYPT, ['cost' => 10]);

        $stmt->execute(['test_sender_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->senderId = (int)$this->pdo->lastInsertId();

        $stmt->execute(['test_recipient_' . uniqid(), uniqid() . '@example.com', $passwordHash, 1, $now]);
        $this->recipientId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Roll back everything we did in this test
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        Database::disconnect();
    }

    public function testImageOnlySendCreatesMessageWithAttachment(): void
    {
        $service = new MessageService();

        $result = $service->send(
            $this->senderId,
            $this->recipientId,
            null, // no text
            'image',
            '/uploads/messages/test.png',
            'test.png',
            68
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);

        $message = $result['message'];
        $this->assertSame('', $message['message_text']); // empty string not null
        $this->assertSame('image', $message['attachment_type']);
        $this->assertSame('/uploads/messages/test.png', $message['attachment_url']);
        $this->assertSame('test.png', $message['attachment_name']);
        $this->assertSame(68, (int)$message['attachment_size']);

        // Also verify message exists in DB
        $stmt = $this->pdo->prepare('SELECT * FROM messages WHERE id = ?');
        $stmt->execute([(int)$message['id']]);
        $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($dbRow);
        $this->assertSame('image', $dbRow['attachment_type']);
    }
}
