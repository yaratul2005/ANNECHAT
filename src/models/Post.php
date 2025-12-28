<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Post {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $userId, ?string $content, string $mediaType = 'text', ?string $mediaUrl = null, ?string $mediaName = null): int {
        $sql = "INSERT INTO posts (user_id, content, media_type, media_url, media_name, created_at) 
                VALUES (:user_id, :content, :media_type, :media_url, :media_name, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':content' => $content,
            ':media_type' => $mediaType,
            ':media_url' => $mediaUrl,
            ':media_name' => $mediaName
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByUserId(int $userId, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT p.*, u.username, u.fullname, u.profile_picture 
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = :user_id 
                ORDER BY p.created_at DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $postId): ?array {
        $sql = "SELECT p.*, u.username, u.fullname, u.profile_picture 
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $postId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function delete(int $postId, int $userId): bool {
        $sql = "DELETE FROM posts WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $postId,
            ':user_id' => $userId
        ]);
    }

    public function countByUserId(int $userId): int {
        $sql = "SELECT COUNT(*) FROM posts WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}

