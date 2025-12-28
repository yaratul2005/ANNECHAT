<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Block {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function block(int $blockerId, int $blockedId): bool {
        // Prevent self-blocking
        if ($blockerId === $blockedId) {
            return false;
        }

        // Use INSERT IGNORE to avoid duplicate key errors and driver-specific issues with
        // ON DUPLICATE KEY in some PDO configurations during tests.
            // Use positional parameters to avoid named-parameter edge cases in some PDO drivers
            $sql = "INSERT IGNORE INTO blocks (blocker_id, blocked_id, created_at) VALUES (?, ?, NOW())";
            $params = [$blockerId, $blockedId];
            $stmt = $this->db->prepare($sql);
            try {
                return $stmt->execute($params);
            } catch (\PDOException $e) {
                error_log("Block::block SQL error: " . $e->getMessage());
                error_log("SQL: " . $sql);
                $err = $stmt->errorInfo();
                error_log("Stmt errorInfo: " . json_encode($err));
                throw $e;
            }
    }

    public function unblock(int $blockerId, int $blockedId): bool {
        $sql = "DELETE FROM blocks WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':blocker_id' => $blockerId,
            ':blocked_id' => $blockedId
        ]);
    }

    public function isBlocked(int $blockerId, int $blockedId): bool {
        $sql = "SELECT COUNT(*) FROM blocks 
                WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':blocker_id' => $blockerId,
            ':blocked_id' => $blockedId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getBlockedUsers(int $blockerId): array {
        $sql = "SELECT b.blocked_id, u.username, u.profile_picture, b.created_at
                FROM blocks b
                INNER JOIN users u ON b.blocked_id = u.id
                WHERE b.blocker_id = :blocker_id
                ORDER BY b.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':blocker_id' => $blockerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBlockers(int $blockedId): array {
        $sql = "SELECT b.blocker_id, u.username, u.profile_picture, b.created_at
                FROM blocks b
                INNER JOIN users u ON b.blocker_id = u.id
                WHERE b.blocked_id = :blocked_id
                ORDER BY b.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':blocked_id' => $blockedId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function canInteract(int $userId1, int $userId2): bool {
        // Check if either user has blocked the other
        $sql = "SELECT COUNT(*) FROM blocks 
                WHERE (blocker_id = ? AND blocked_id = ?) 
                   OR (blocker_id = ? AND blocked_id = ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
        return (int)$stmt->fetchColumn() === 0;
    }
}

