<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Cooldown {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Check if an action is on cooldown
     */
    public function isOnCooldown(string $actionType, string $actionIdentifier, ?int $userId = null, ?string $ipAddress = null): bool {
        $this->cleanupExpired();
        
        $sql = "SELECT COUNT(*) FROM cooldowns 
                WHERE action_type = ? 
                AND action_identifier = ? 
                AND expires_at > NOW()";
        $params = [$actionType, $actionIdentifier];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        if ($ipAddress !== null) {
            $sql .= " AND ip_address = ?";
            $params[] = $ipAddress;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get remaining cooldown time in seconds
     */
    public function getRemainingTime(string $actionType, string $actionIdentifier, ?int $userId = null, ?string $ipAddress = null): int {
        $this->cleanupExpired();
        
        $sql = "SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining
                FROM cooldowns 
                WHERE action_type = ? 
                AND action_identifier = ? 
                AND expires_at > NOW()";
        $params = [$actionType, $actionIdentifier];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        if ($ipAddress !== null) {
            $sql .= " AND ip_address = ?";
            $params[] = $ipAddress;
        }
        
        $sql .= " ORDER BY expires_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? max(0, (int)$result['remaining']) : 0;
    }

    /**
     * Set a cooldown for an action
     */
    public function setCooldown(string $actionType, string $actionIdentifier, int $durationSeconds, ?int $userId = null, ?string $ipAddress = null, int $attemptCount = 1): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);
        
        $sql = "INSERT INTO cooldowns (user_id, ip_address, action_type, action_identifier, expires_at, attempt_count, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    expires_at = VALUES(expires_at),
                    attempt_count = attempt_count + 1,
                    updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $ipAddress, $actionType, $actionIdentifier, $expiresAt, $attemptCount]);
    }

    /**
     * Get cooldown info
     */
    public function getCooldown(string $actionType, string $actionIdentifier, ?int $userId = null, ?string $ipAddress = null): ?array {
        $sql = "SELECT * FROM cooldowns 
                WHERE action_type = ? 
                AND action_identifier = ?";
        $params = [$actionType, $actionIdentifier];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        if ($ipAddress !== null) {
            $sql .= " AND ip_address = ?";
            $params[] = $ipAddress;
        }
        
        $sql .= " ORDER BY expires_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Clear cooldown for an action
     */
    public function clearCooldown(string $actionType, string $actionIdentifier, ?int $userId = null, ?string $ipAddress = null): bool {
        $sql = "DELETE FROM cooldowns 
                WHERE action_type = ? 
                AND action_identifier = ?";
        $params = [$actionType, $actionIdentifier];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        if ($ipAddress !== null) {
            $sql .= " AND ip_address = ?";
            $params[] = $ipAddress;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get all active cooldowns
     */
    public function getAll(int $limit = 100, int $offset = 0): array {
        $this->cleanupExpired();
        
        $sql = "SELECT c.*, u.username as user_username
                FROM cooldowns c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.expires_at > NOW()
                ORDER BY c.expires_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clean up expired cooldowns
     */
    public function cleanupExpired(): int {
        $sql = "DELETE FROM cooldowns WHERE expires_at <= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}

