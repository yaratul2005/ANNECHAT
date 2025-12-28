<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO users (username, email, password_hash, age, is_verified, is_admin, is_guest, verification_token, verification_token_expires, created_at) 
                VALUES (:username, :email, :password_hash, :age, :is_verified, :is_admin, :is_guest, :verification_token, :verification_token_expires, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'] ?? null,
            ':password_hash' => $data['password_hash'] ?? null,
            ':age' => $data['age'] ?? null,
            // Cast boolean flags to integer to avoid strict SQL errors when using strict SQL modes
            ':is_verified' => isset($data['is_verified']) ? (int)$data['is_verified'] : 0,
            ':is_admin' => isset($data['is_admin']) ? (int)$data['is_admin'] : 0,
            ':is_guest' => isset($data['is_guest']) ? (int)$data['is_guest'] : 0,
            ':verification_token' => $data['verification_token'] ?? null,
            ':verification_token_expires' => $data['verification_token_expires'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $sql = "SELECT id, username, fullname, email, age, gender, profile_picture, bio, is_verified, is_admin, is_guest, is_banned, created_at, last_ip_address 
                FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function updateLastIp(int $userId, string $ipAddress): bool {
        $sql = "UPDATE users SET last_ip_address = :ip_address WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':ip_address' => $ipAddress
        ]);
    }

    public static function getClientIp(): string {
        // Check for IP from various sources (proxy, load balancer, etc.)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            // Nginx proxy
            return $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Proxy/load balancer
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    public function findByUsername(string $username): ?array {
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByVerificationToken(string $token): ?array {
        $sql = "SELECT * FROM users WHERE verification_token = :token AND verification_token_expires > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function verify(int $userId): bool {
        $sql = "UPDATE users SET is_verified = TRUE, verification_token = NULL, verification_token_expires = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $userId]);
    }

    public function update(int $userId, array $data): bool {
        $allowedFields = ['username', 'email', 'password_hash', 'age', 'profile_picture', 'bio', 'verification_token', 'verification_token_expires', 'password_reset_token', 'password_reset_expires', 'gender', 'is_banned', 'fullname'];
        $updates = [];
        $params = [':id' => $userId];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = :{$key}";
                // Handle boolean fields
                if ($key === 'is_banned' || $key === 'is_verified' || $key === 'is_admin' || $key === 'is_guest') {
                    $params[":{$key}"] = $value ? 1 : 0;
                } else {
                    $params[":{$key}"] = $value;
                }
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function getAllOnline(): array {
        // Include all users with their online status so all connected users can see each other.
        // Use LEFT JOIN to include users even if they don't have an online_status entry yet.
        try {
            $sql = "SELECT u.id, u.username, u.fullname, u.gender, u.profile_picture, u.is_guest, u.is_verified,
                    COALESCE(os.status, 'offline') as status, 
                    COALESCE(os.last_seen, u.created_at) as last_seen 
                FROM users u 
                LEFT JOIN online_status os ON u.id = os.user_id 
                ORDER BY 
                    CASE 
                        WHEN os.status = 'online' THEN 1 
                        WHEN os.status = 'away' THEN 2 
                        ELSE 3 
                    END,
                    os.last_seen DESC, u.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure all users have required fields
            foreach ($result as &$user) {
                if (!isset($user['status']) || empty($user['status'])) {
                    $user['status'] = 'offline';
                }
                // Ensure is_guest is a boolean
                $user['is_guest'] = isset($user['is_guest']) ? (bool)$user['is_guest'] : false;
            }
            unset($user);
            
            return $result;
        } catch (\PDOException $e) {
            error_log("Error in getAllOnline: " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A'));
            // Return empty array on error rather than throwing
            return [];
        } catch (\Exception $e) {
            error_log("Unexpected error in getAllOnline: " . $e->getMessage());
            return [];
        }
    }

    public function getAll(int $limit = 100, int $offset = 0, bool $includeGuests = false): array {
        $sql = "SELECT id, username, email, age, profile_picture, bio, is_verified, is_admin, is_guest, is_banned, created_at, last_ip_address 
                FROM users";
        if (!$includeGuests) {
            $sql .= " WHERE is_guest = FALSE";
        }
        $sql .= " ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function delete(int $userId): bool {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $userId]);
    }

    public function exists(string $username, ?string $email = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
        $params = [':username' => $username];
        
        if ($email) {
            $sql .= " OR email = :email";
            $params[':email'] = $email;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}

