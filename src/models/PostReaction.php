<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class PostReaction {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function toggle(int $postId, int $userId, string $reactionType = 'star'): bool {
        // Check if reaction exists
        $sql = "SELECT id FROM post_reactions WHERE post_id = :post_id AND user_id = :user_id AND reaction_type = :reaction_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':reaction_type' => $reactionType
        ]);
        
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Remove reaction
            $sql = "DELETE FROM post_reactions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $existing['id']]);
        } else {
            // Add reaction
            $sql = "INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at) 
                    VALUES (:post_id, :user_id, :reaction_type, NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId,
                ':reaction_type' => $reactionType
            ]);
        }
    }

    public function hasReacted(int $postId, int $userId, string $reactionType = 'star'): bool {
        $sql = "SELECT COUNT(*) FROM post_reactions WHERE post_id = :post_id AND user_id = :user_id AND reaction_type = :reaction_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':reaction_type' => $reactionType
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getCount(int $postId, string $reactionType = 'star'): int {
        $sql = "SELECT COUNT(*) FROM post_reactions WHERE post_id = :post_id AND reaction_type = :reaction_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':post_id' => $postId,
            ':reaction_type' => $reactionType
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function getReactionsByPostId(int $postId): array {
        $sql = "SELECT pr.*, u.username, u.fullname 
                FROM post_reactions pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.post_id = :post_id 
                ORDER BY pr.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':post_id' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

