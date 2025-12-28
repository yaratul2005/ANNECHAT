<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class EmailLog {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(string $recipientEmail, ?string $recipientName, string $subject, string $body): int {
        $sql = "INSERT INTO email_logs (recipient_email, recipient_name, subject, body, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$recipientEmail, $recipientName, $subject, $body]);
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $errorMessage = null): bool {
        $sql = "UPDATE email_logs SET 
                status = ?, 
                error_message = ?,
                sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $errorMessage, $status, $id]);
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT * FROM email_logs 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

