<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class IpBlock {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function block(string $ipAddress, ?string $reason = null, ?int $blockedBy = null, ?\DateTime $expiresAt = null, bool $isPermanent = false): int {
        $sql = "INSERT INTO ip_blocks (ip_address, reason, blocked_by, expires_at, is_permanent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason),
                    blocked_by = VALUES(blocked_by),
                    expires_at = VALUES(expires_at),
                    is_permanent = VALUES(is_permanent),
                    created_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $expiresAtStr = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;
        $stmt->execute([$ipAddress, $reason, $blockedBy, $expiresAtStr, $isPermanent ? 1 : 0]);
        
        if ($stmt->rowCount() > 0) {
            return (int)$this->db->lastInsertId();
        }
        
        // If duplicate, get existing ID
        $existing = $this->getByIp($ipAddress);
        return $existing ? $existing['id'] : 0;
    }

    public function unblock(string $ipAddress): bool {
        $sql = "DELETE FROM ip_blocks WHERE ip_address = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$ipAddress]);
    }

    public function isBlocked(string $ipAddress): bool {
        // Clean up expired blocks first
        $this->cleanupExpired();
        
        $sql = "SELECT COUNT(*) FROM ip_blocks 
                WHERE ip_address = ? 
                AND (is_permanent = 1 OR expires_at IS NULL OR expires_at > NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ipAddress]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getByIp(string $ipAddress): ?array {
        $sql = "SELECT ib.*, u.username as blocked_by_username
                FROM ip_blocks ib
                LEFT JOIN users u ON ib.blocked_by = u.id
                WHERE ib.ip_address = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $this->cleanupExpired();
        
        $sql = "SELECT ib.*, u.username as blocked_by_username
                FROM ip_blocks ib
                LEFT JOIN users u ON ib.blocked_by = u.id
                ORDER BY ib.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanupExpired(): int {
        $sql = "DELETE FROM ip_blocks 
                WHERE is_permanent = 0 
                AND expires_at IS NOT NULL 
                AND expires_at <= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function delete(int $id): bool {
        $sql = "DELETE FROM ip_blocks WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}

