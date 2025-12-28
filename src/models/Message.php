<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Message {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $senderId, int $recipientId, ?string $messageText = null, ?string $attachmentType = null, ?string $attachmentUrl = null, ?string $attachmentName = null, ?int $attachmentSize = null): int {
        $sql = "INSERT INTO messages (sender_id, recipient_id, message_text, attachment_type, attachment_url, attachment_name, attachment_size, is_read, created_at) 
                VALUES (:sender_id, :recipient_id, :message_text, :attachment_type, :attachment_url, :attachment_name, :attachment_size, FALSE, NOW())";
        
        $stmt = $this->db->prepare($sql);
        // Ensure message_text is never NULL because the DB column is NOT NULL.
        $stmt->execute([
            ':sender_id' => $senderId,
            ':recipient_id' => $recipientId,
            ':message_text' => $messageText ?? '',
            ':attachment_type' => $attachmentType ?? 'none',
            ':attachment_url' => $attachmentUrl,
            ':attachment_name' => $attachmentName,
            ':attachment_size' => $attachmentSize
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Find a recent duplicate message (within N seconds) to avoid double-inserts
     */
    public function findRecentDuplicate(int $senderId, int $recipientId, ?string $messageText = null, ?string $attachmentUrl = null, int $seconds = 5): ?int {
        $sql = "SELECT id FROM messages WHERE sender_id = :sender_id AND recipient_id = :recipient_id AND message_text = :message_text AND COALESCE(attachment_url, '') = COALESCE(:attachment_url, '') AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND) ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $senderId,
            ':recipient_id' => $recipientId,
            ':message_text' => $messageText ?? '',
            ':attachment_url' => $attachmentUrl ?? '',
            ':seconds' => $seconds
        ]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public function findById(int $id): ?array {
        $sql = "SELECT m.*, 
                s.username as sender_username, s.profile_picture as sender_picture, s.is_guest as sender_is_guest,
                r.username as recipient_username, r.profile_picture as recipient_picture
                FROM messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE m.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getConversation(int $userId1, int $userId2, int $limit = 50, int $offset = 0): array {
        // Use unique named parameters to avoid drivers that do not allow repeated named placeholders
        $sql = "SELECT m.*, 
            s.username as sender_username, s.profile_picture as sender_picture, s.is_guest as sender_is_guest,
            r.username as recipient_username, r.profile_picture as recipient_picture
            FROM messages m
            LEFT JOIN users s ON m.sender_id = s.id
            LEFT JOIN users r ON m.recipient_id = r.id
            WHERE (m.sender_id = :user1a AND m.recipient_id = :user2a) 
               OR (m.sender_id = :user2b AND m.recipient_id = :user1b)
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        // Bind each placeholder separately
        $stmt->bindValue(':user1a', $userId1, PDO::PARAM_INT);
        $stmt->bindValue(':user2a', $userId2, PDO::PARAM_INT);
        $stmt->bindValue(':user2b', $userId2, PDO::PARAM_INT);
        $stmt->bindValue(':user1b', $userId1, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $messages = $stmt->fetchAll();
        return array_reverse($messages);
    }

    public function getNewMessages(int $userId, ?int $lastMessageId = null): array {
        $sql = "SELECT m.*, 
                s.username as sender_username, s.profile_picture as sender_picture, s.is_guest as sender_is_guest,
                r.username as recipient_username, r.profile_picture as recipient_picture
                FROM messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE m.recipient_id = :user_id";
        
        $params = [':user_id' => $userId];
        
        if ($lastMessageId !== null) {
            $sql .= " AND m.id > :last_id";
            $params[':last_id'] = $lastMessageId;
        }
        
        $sql .= " ORDER BY m.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function markAsRead(int $messageId, int $userId): bool {
        $sql = "UPDATE messages SET is_read = TRUE 
                WHERE id = :id AND recipient_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $messageId, ':user_id' => $userId]);
    }

    public function markConversationAsRead(int $userId1, int $userId2): bool {
        $sql = "UPDATE messages SET is_read = TRUE 
                WHERE recipient_id = :user_id AND sender_id = :other_user";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':user_id' => $userId1, ':other_user' => $userId2]);
    }

    public function delete(int $messageId, int $userId): bool {
        $sql = "DELETE FROM messages WHERE id = :id AND (sender_id = :user_id OR :is_admin = TRUE)";
        $stmt = $this->db->prepare($sql);
        // Note: is_admin check should be done in service layer
        return $stmt->execute([':id' => $messageId, ':user_id' => $userId]);
    }

    public function getUnreadCount(int $userId): int {
        $sql = "SELECT COUNT(*) FROM messages WHERE recipient_id = :user_id AND is_read = FALSE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get unread message count for a specific conversation
     */
    public function getUnreadCountForUser(int $recipientId, int $senderId): int {
        $sql = "SELECT COUNT(*) FROM messages 
                WHERE recipient_id = :recipient_id AND sender_id = :sender_id AND is_read = FALSE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':recipient_id' => $recipientId,
            ':sender_id' => $senderId
        ]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get unread counts for all conversations (returns array of sender_id => count)
     */
    public function getUnreadCountsBySender(int $recipientId): array {
        $sql = "SELECT sender_id, COUNT(*) as unread_count 
                FROM messages 
                WHERE recipient_id = :recipient_id AND is_read = FALSE 
                GROUP BY sender_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':recipient_id' => $recipientId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[(int)$row['sender_id']] = (int)$row['unread_count'];
        }
        return $counts;
    }

    /**
     * Get total number of unique users who have sent unread messages
     */
    public function getUnreadConversationsCount(int $recipientId): int {
        $sql = "SELECT COUNT(DISTINCT sender_id) as count 
                FROM messages 
                WHERE recipient_id = :recipient_id AND is_read = FALSE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':recipient_id' => $recipientId]);
        return (int)$stmt->fetchColumn();
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT m.*, 
                s.username as sender_username, s.profile_picture as sender_picture,
                r.username as recipient_username, r.profile_picture as recipient_picture
                FROM messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                ORDER BY m.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

