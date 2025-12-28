<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Report {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $reporterId, int $reportedId, string $reason, ?string $description = null): int {
        // Prevent self-reporting
        if ($reporterId === $reportedId) {
            throw new \InvalidArgumentException('Cannot report yourself');
        }

        $sql = "INSERT INTO reports (reporter_id, reported_id, reason, description, status, created_at) 
                VALUES (:reporter_id, :reported_id, :reason, :description, 'pending', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':reporter_id' => $reporterId,
            ':reported_id' => $reportedId,
            ':reason' => $reason,
            ':description' => $description
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getReportsByReporter(int $reporterId): array {
        $sql = "SELECT r.*, u.username as reported_username, u.profile_picture as reported_profile_picture
                FROM reports r
                INNER JOIN users u ON r.reported_id = u.id
                WHERE r.reporter_id = :reporter_id
                ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':reporter_id' => $reporterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasReported(int $reporterId, int $reportedId): bool {
        $sql = "SELECT COUNT(*) FROM reports 
                WHERE reporter_id = :reporter_id AND reported_id = :reported_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':reporter_id' => $reporterId,
            ':reported_id' => $reportedId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getAll(int $limit = 100, int $offset = 0, ?string $status = null): array {
        $sql = "SELECT r.*, 
                reporter.username as reporter_username, 
                reported.username as reported_username,
                reported.profile_picture as reported_profile_picture
                FROM reports r
                INNER JOIN users reporter ON r.reporter_id = reporter.id
                INNER JOIN users reported ON r.reported_id = reported.id";
        
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE r.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $paramIndex = 1;
        
        if ($status !== null) {
            $stmt->bindValue($paramIndex++, $status);
        }
        $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $reportId, string $status): bool {
        $allowedStatuses = ['pending', 'reviewed', 'resolved', 'dismissed'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $sql = "UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $reportId]);
    }

    public function getById(int $reportId): ?array {
        $sql = "SELECT r.*, 
                reporter.username as reporter_username,
                reporter.email as reporter_email,
                reporter.last_ip_address as reporter_ip_address,
                reported.username as reported_username,
                reported.email as reported_email,
                reported.profile_picture as reported_profile_picture,
                reported.last_ip_address as reported_ip_address
                FROM reports r
                INNER JOIN users reporter ON r.reporter_id = reporter.id
                INNER JOIN users reported ON r.reported_id = reported.id
                WHERE r.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$reportId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}

