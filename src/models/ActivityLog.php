<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class ActivityLog {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(?int $userId, string $action, ?string $description = null, ?string $ipAddress = null, ?string $userAgent = null): int {
        $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) 
                VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':description' => $description,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getAll(int $limit = 100, int $offset = 0, ?string $action = null, ?int $userId = null): array {
        $sql = "SELECT al.*, u.username 
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if ($action) {
            $sql .= " AND al.action = :action";
            $params[':action'] = $action;
        }
        
        if ($userId) {
            $sql .= " AND al.user_id = :user_id";
            $params[':user_id'] = $userId;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getByUser(int $userId, int $limit = 50): array {
        return $this->getAll($limit, 0, null, $userId);
    }
}

