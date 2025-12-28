<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class FriendRequest {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $senderId, int $receiverId): int {
        $sql = "INSERT INTO friend_requests (sender_id, receiver_id, status) 
                VALUES (:sender_id, :receiver_id, 'pending')
                ON DUPLICATE KEY UPDATE status = 'pending', updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $sql = "SELECT fr.*, 
                u1.username as sender_username, u1.fullname as sender_fullname, u1.profile_picture as sender_avatar,
                u2.username as receiver_username, u2.fullname as receiver_fullname, u2.profile_picture as receiver_avatar
                FROM friend_requests fr
                JOIN users u1 ON fr.sender_id = u1.id
                JOIN users u2 ON fr.receiver_id = u2.id
                WHERE fr.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getPendingForUser(int $userId): array {
        $sql = "SELECT fr.*, 
                u.username, u.fullname, u.profile_picture, u.gender, u.is_guest
                FROM friend_requests fr
                JOIN users u ON (fr.sender_id = u.id AND fr.receiver_id = :user_id) 
                             OR (fr.receiver_id = u.id AND fr.sender_id = :user_id)
                WHERE (fr.sender_id = :user_id OR fr.receiver_id = :user_id)
                AND fr.status = 'pending'
                AND u.id != :user_id
                ORDER BY fr.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getIncomingRequests(int $userId): array {
        $sql = "SELECT fr.*, 
                u.username, u.fullname, u.profile_picture, u.gender, u.is_guest
                FROM friend_requests fr
                JOIN users u ON fr.sender_id = u.id
                WHERE fr.receiver_id = :user_id
                AND fr.status = 'pending'
                ORDER BY fr.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFriends(int $userId): array {
        $sql = "SELECT u.id, u.username, u.fullname, u.profile_picture, u.gender, u.is_guest, u.is_verified,
                fr.created_at as friendship_date
                FROM friend_requests fr
                JOIN users u ON (CASE 
                    WHEN fr.sender_id = :user_id THEN fr.receiver_id = u.id
                    WHEN fr.receiver_id = :user_id THEN fr.sender_id = u.id
                END)
                WHERE (fr.sender_id = :user_id OR fr.receiver_id = :user_id)
                AND fr.status = 'accepted'
                ORDER BY fr.updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $requestId, string $status): bool {
        $sql = "UPDATE friend_requests SET status = :status, updated_at = NOW() 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $requestId,
            ':status' => $status
        ]);
    }

    public function accept(int $requestId): bool {
        return $this->updateStatus($requestId, 'accepted');
    }

    public function reject(int $requestId): bool {
        return $this->updateStatus($requestId, 'rejected');
    }

    public function exists(int $senderId, int $receiverId): bool {
        $sql = "SELECT COUNT(*) FROM friend_requests 
                WHERE ((sender_id = :sender_id AND receiver_id = :receiver_id)
                    OR (sender_id = :receiver_id AND receiver_id = :sender_id))
                AND status IN ('pending', 'accepted')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function areFriends(int $userId1, int $userId2): bool {
        $sql = "SELECT COUNT(*) FROM friend_requests 
                WHERE ((sender_id = :user1 AND receiver_id = :user2)
                    OR (sender_id = :user2 AND receiver_id = :user1))
                AND status = 'accepted'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user1' => $userId1,
            ':user2' => $userId2
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function removeFriendship(int $userId1, int $userId2): bool {
        $sql = "DELETE FROM friend_requests 
                WHERE ((sender_id = :user1 AND receiver_id = :user2)
                    OR (sender_id = :user2 AND receiver_id = :user1))
                AND status = 'accepted'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user1' => $userId1,
            ':user2' => $userId2
        ]);
    }

    public function getRequestBetween(int $senderId, int $receiverId): ?array {
        $sql = "SELECT * FROM friend_requests 
                WHERE sender_id = :sender_id AND receiver_id = :receiver_id
                AND status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}

