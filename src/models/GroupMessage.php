<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class GroupMessage {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO group_messages (group_id, sender_id, message_text, attachment_type, attachment_url, attachment_name, attachment_size) 
                VALUES (:group_id, :sender_id, :message_text, :attachment_type, :attachment_url, :attachment_name, :attachment_size)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':group_id' => $data['group_id'],
            ':sender_id' => $data['sender_id'],
            ':message_text' => $data['message_text'] ?? null,
            ':attachment_type' => $data['attachment_type'] ?? 'none',
            ':attachment_url' => $data['attachment_url'] ?? null,
            ':attachment_name' => $data['attachment_name'] ?? null,
            ':attachment_size' => $data['attachment_size'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByGroupId(int $groupId, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT gm.*, 
                u.username as sender_username, u.fullname as sender_fullname, 
                u.profile_picture as sender_avatar, u.gender as sender_gender, u.is_guest as sender_is_guest
                FROM group_messages gm
                JOIN users u ON gm.sender_id = u.id
                WHERE gm.group_id = :group_id
                ORDER BY gm.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_reverse($messages); // Reverse to show oldest first
    }

    public function delete(int $messageId): bool {
        $sql = "DELETE FROM group_messages WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $messageId]);
    }

    public function findById(int $id): ?array {
        $sql = "SELECT * FROM group_messages WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}

