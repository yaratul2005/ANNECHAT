<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class StoryReaction {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Add a star reaction to a story
     */
    public function addStar(int $storyId, int $userId): bool {
        // Check if user already starred this story
        $existing = $this->getReaction($storyId, $userId, 'star');
        if ($existing) {
            return true; // Already starred
        }

        $sql = "INSERT INTO story_reactions (story_id, user_id, reaction_type, created_at) 
                VALUES (?, ?, 'star', NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$storyId, $userId]);
    }

    /**
     * Add a reply to a story
     */
    public function addReply(int $storyId, int $userId, string $content): int {
        $sql = "INSERT INTO story_reactions (story_id, user_id, reaction_type, content, created_at) 
                VALUES (?, ?, 'reply', ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$storyId, $userId, $content]);
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get reactions for a story
     */
    public function getReactions(int $storyId, ?string $type = null): array {
        $sql = "SELECT sr.*, u.username, u.profile_picture
                FROM story_reactions sr
                INNER JOIN users u ON sr.user_id = u.id
                WHERE sr.story_id = ?";
        $params = [$storyId];
        
        if ($type !== null) {
            $sql .= " AND sr.reaction_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY sr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific reaction
     */
    public function getReaction(int $storyId, int $userId, string $type): ?array {
        $sql = "SELECT * FROM story_reactions 
                WHERE story_id = ? AND user_id = ? AND reaction_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$storyId, $userId, $type]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Remove a star reaction
     */
    public function removeStar(int $storyId, int $userId): bool {
        $sql = "DELETE FROM story_reactions 
                WHERE story_id = ? AND user_id = ? AND reaction_type = 'star'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$storyId, $userId]);
    }

    /**
     * Check if user has starred a story
     */
    public function hasStarred(int $storyId, int $userId): bool {
        $sql = "SELECT COUNT(*) FROM story_reactions 
                WHERE story_id = ? AND user_id = ? AND reaction_type = 'star'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$storyId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

