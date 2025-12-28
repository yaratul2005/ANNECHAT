<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Story {
    private PDO $db;
    
    // Story expires after 24 hours
    private const EXPIRY_HOURS = 24;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new story
     */
    public function create(int $userId, string $mediaType, string $mediaUrl, ?string $text = null): int {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_HOURS . ' hours'));
        
        $sql = "INSERT INTO stories (user_id, media_type, media_url, text, expires_at, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $mediaType, $mediaUrl, $text, $expiresAt]);
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get all active (non-expired) stories ordered by creation date
     */
    public function getActiveStories(int $limit = 50): array {
        $sql = "SELECT s.*, u.username, u.profile_picture,
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id AND sr.reaction_type = 'star') as star_count,
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id AND sr.reaction_type = 'reply') as reply_count
                FROM stories s
                INNER JOIN users u ON s.user_id = u.id
                WHERE s.expires_at > NOW()
                ORDER BY s.created_at DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get stories by a specific user
     */
    public function getUserStories(int $userId): array {
        $sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id AND sr.reaction_type = 'star') as star_count,
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id AND sr.reaction_type = 'reply') as reply_count
                FROM stories s
                WHERE s.user_id = ? AND s.expires_at > NOW()
                ORDER BY s.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single story by ID
     */
    public function getById(int $storyId): ?array {
        $sql = "SELECT s.*, u.username, u.profile_picture,
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id AND sr.reaction_type = 'star') as star_count,
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id AND sr.reaction_type = 'reply') as reply_count
                FROM stories s
                INNER JOIN users u ON s.user_id = u.id
                WHERE s.id = ? AND s.expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$storyId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Delete a story (soft delete by user)
     */
    public function delete(int $storyId, int $userId): bool {
        $sql = "DELETE FROM stories WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$storyId, $userId]);
    }

    /**
     * Check if story has expired
     */
    public function hasExpired(int $storyId): bool {
        $sql = "SELECT COUNT(*) FROM stories WHERE id = ? AND expires_at <= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$storyId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Clean up expired stories (should be called periodically)
     */
    public function cleanupExpired(): int {
        $sql = "DELETE FROM stories WHERE expires_at <= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Check if user has already posted a story recently (within last hour) - limit spam
     */
    public function canUserPost(int $userId): bool {
        $sql = "SELECT COUNT(*) FROM stories 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() < 5; // Allow max 5 stories per hour
    }
}

