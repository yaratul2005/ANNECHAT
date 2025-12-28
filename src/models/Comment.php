<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Comment {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $postId, int $userId, string $content): int {
        $sql = "INSERT INTO post_comments (post_id, user_id, content, created_at) 
                VALUES (:post_id, :user_id, :content, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':content' => $content
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByPostId(int $postId): array {
        $sql = "SELECT c.*, u.username, u.fullname, u.profile_picture, u.id as user_id
                FROM post_comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.post_id = :post_id 
                ORDER BY c.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':post_id' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(int $commentId, int $userId): bool {
        $sql = "DELETE FROM post_comments WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $commentId,
            ':user_id' => $userId
        ]);
    }

    public function countByPostId(int $postId): int {
        $sql = "SELECT COUNT(*) FROM post_comments WHERE post_id = :post_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':post_id' => $postId]);
        return (int)$stmt->fetchColumn();
    }
}

