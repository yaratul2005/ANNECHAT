<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Group {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO `groups` (name, description, avatar, created_by) 
                VALUES (:name, :description, :avatar, :created_by)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':avatar' => $data['avatar'] ?? null,
            ':created_by' => $data['created_by']
        ]);

        $groupId = (int)$this->db->lastInsertId();
        
        // Add creator as admin member
        $this->addMember($groupId, $data['created_by'], 'admin');
        
        return $groupId;
    }

    public function findById(int $id): ?array {
        $sql = "SELECT g.*, 
                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                FROM `groups` g 
                WHERE g.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAllForUser(int $userId): array {
        $sql = "SELECT g.*, 
                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                gm.role as user_role
                FROM `groups` g
                INNER JOIN group_members gm ON g.id = gm.group_id
                WHERE gm.user_id = :user_id
                ORDER BY g.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(): array {
        $sql = "SELECT g.*, 
                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                FROM `groups` g
                ORDER BY g.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $groupId, array $data): bool {
        $allowedFields = ['name', 'description', 'avatar'];
        $updates = [];
        $params = [':id' => $groupId];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE `groups` SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $groupId): bool {
        $sql = "DELETE FROM `groups` WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $groupId]);
    }

    public function addMember(int $groupId, int $userId, string $role = 'member'): bool {
        // Check if member already exists
        if ($this->isMember($groupId, $userId)) {
            // Update role if exists
            $sql = "UPDATE group_members SET role = :role 
                    WHERE group_id = :group_id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':group_id' => $groupId,
                ':user_id' => $userId,
                ':role' => $role
            ]);
        } else {
            // Insert new member
            $sql = "INSERT INTO group_members (group_id, user_id, role) 
                    VALUES (:group_id, :user_id, :role)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':group_id' => $groupId,
                ':user_id' => $userId,
                ':role' => $role
            ]);
        }
    }

    public function removeMember(int $groupId, int $userId): bool {
        $sql = "DELETE FROM group_members WHERE group_id = :group_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':group_id' => $groupId,
            ':user_id' => $userId
        ]);
    }

    public function isMember(int $groupId, int $userId): bool {
        $sql = "SELECT COUNT(*) FROM group_members 
                WHERE group_id = :group_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':group_id' => $groupId,
            ':user_id' => $userId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getMembers(int $groupId): array {
        $sql = "SELECT u.id, u.username, u.fullname, u.profile_picture, u.gender, u.is_guest,
                gm.role, gm.joined_at
                FROM group_members gm
                JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = :group_id
                ORDER BY 
                    CASE WHEN gm.role = 'admin' THEN 1 ELSE 2 END,
                    gm.joined_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserRole(int $groupId, int $userId): ?string {
        $sql = "SELECT role FROM group_members 
                WHERE group_id = :group_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':group_id' => $groupId,
            ':user_id' => $userId
        ]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }
}

