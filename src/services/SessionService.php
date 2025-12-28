<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use App\Config\Database;
use PDO;

class SessionService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', (string)Config::sessionTimeout());
            
            session_start();
        }
    }

    public function create(int $userId, ?string $ipAddress = null, ?string $userAgent = null): string {
        $sessionId = session_id();
        
        if (empty($sessionId)) {
            session_regenerate_id(true);
            $sessionId = session_id();
        }

        // Use VALUES() in ON DUPLICATE KEY UPDATE to avoid repeating named parameters which can
        // cause "Invalid parameter number" errors with some PDO drivers
        $sql = "INSERT INTO sessions (id, user_id, ip_address, user_agent, data, last_activity) 
            VALUES (:id, :user_id, :ip_address, :user_agent, :data, NOW())
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_activity = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $sessionId,
            ':user_id' => $userId,
            ':ip_address' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':data' => serialize($_SESSION)
        ]);

        $_SESSION['user_id'] = $userId;
        return $sessionId;
    }

    public function getUserId(): ?int {
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        return null;
    }

    public function isValid(): bool {
        // First check if user_id is already in session (faster check)
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
            // Verify the session still exists in database
            $sessionId = session_id();
            if (!empty($sessionId)) {
                $sql = "SELECT user_id FROM sessions 
                        WHERE id = :id AND user_id = :user_id AND last_activity > DATE_SUB(NOW(), INTERVAL :timeout SECOND)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':id' => $sessionId,
                    ':user_id' => $userId,
                    ':timeout' => Config::sessionTimeout()
                ]);
                
                if ($stmt->fetch()) {
                    $this->updateActivity();
                    return true;
                } else {
                    // Session expired in database, clear session
                    unset($_SESSION['user_id']);
                }
            }
        }

        // Fallback: check database if no session user_id
        $sessionId = session_id();
        if (empty($sessionId)) {
            return false;
        }

        $sql = "SELECT user_id, last_activity FROM sessions 
                WHERE id = :id AND last_activity > DATE_SUB(NOW(), INTERVAL :timeout SECOND)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $sessionId,
            ':timeout' => Config::sessionTimeout()
        ]);

        $result = $stmt->fetch();
        if ($result) {
            $_SESSION['user_id'] = $result['user_id'];
            $this->updateActivity();
            return true;
        }

        return false;
    }

    public function updateActivity(): void {
        $sessionId = session_id();
        if (empty($sessionId)) {
            return;
        }

        $sql = "UPDATE sessions SET last_activity = NOW(), data = :data WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $sessionId,
            ':data' => serialize($_SESSION)
        ]);
    }

    public function destroy(): void {
        $sessionId = session_id();
        
        if (!empty($sessionId)) {
            $sql = "DELETE FROM sessions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $sessionId]);
        }

        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    public function regenerateId(): void {
        $oldSessionId = session_id();
        session_regenerate_id(true);
        $newSessionId = session_id();
        
        // Update the session ID in database if it changed
        if ($oldSessionId !== $newSessionId && !empty($oldSessionId)) {
            $sql = "UPDATE sessions SET id = :new_id WHERE id = :old_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':new_id' => $newSessionId,
                ':old_id' => $oldSessionId
            ]);
        }
        
        $this->updateActivity();
    }
}

