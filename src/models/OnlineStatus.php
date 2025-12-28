<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class OnlineStatus {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function update(int $userId, string $status = 'online'): bool {
        // Use VALUES() in ON DUPLICATE KEY UPDATE to avoid repeating named parameters
        $sql = "INSERT INTO online_status (user_id, status, last_seen) 
            VALUES (:user_id, :status, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status), last_seen = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':status' => $status
        ]);
    }

    public function get(int $userId): ?array {
        $sql = "SELECT * FROM online_status WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAllOnline(): array {
        $sql = "SELECT os.*, u.username, u.profile_picture 
                FROM online_status os
                INNER JOIN users u ON os.user_id = u.id
                WHERE os.status = 'online' AND u.is_guest = FALSE
                ORDER BY os.last_seen DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setOffline(int $userId): bool {
        return $this->update($userId, 'offline');
    }

    public function cleanup(int $minutes = 5): int {
        $sql = "UPDATE online_status SET status = 'offline' 
                WHERE status = 'online' AND last_seen < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}

