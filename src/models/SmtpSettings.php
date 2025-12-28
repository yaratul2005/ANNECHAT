<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class SmtpSettings {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function get(): ?array {
        $sql = "SELECT * FROM smtp_settings ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function update(array $data): bool {
        $existing = $this->get();
        
        if ($existing) {
            $sql = "UPDATE smtp_settings SET 
                    host = ?, port = ?, encryption = ?, username = ?, password = ?, 
                    from_email = ?, from_name = ?, is_active = ?, 
                    test_status = 'pending', test_message = NULL, tested_at = NULL,
                    updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['host'],
                $data['port'],
                $data['encryption'] ?? 'tls',
                $data['username'],
                $data['password'],
                $data['from_email'],
                $data['from_name'] ?? null,
                $data['is_active'] ?? false ? 1 : 0,
                $existing['id']
            ]);
        } else {
            $sql = "INSERT INTO smtp_settings 
                    (host, port, encryption, username, password, from_email, from_name, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['host'],
                $data['port'],
                $data['encryption'] ?? 'tls',
                $data['username'],
                $data['password'],
                $data['from_email'],
                $data['from_name'] ?? null,
                $data['is_active'] ?? false ? 1 : 0
            ]);
        }
    }

    public function updateTestStatus(string $status, ?string $message = null): bool {
        $existing = $this->get();
        if (!$existing) {
            return false;
        }

        $sql = "UPDATE smtp_settings SET 
                test_status = ?, test_message = ?, tested_at = NOW() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $message, $existing['id']]);
    }

    public function isConfigured(): bool {
        $settings = $this->get();
        return $settings !== null && !empty($settings['host']) && !empty($settings['username']);
    }

    public function isActive(): bool {
        $settings = $this->get();
        return $settings !== null && $settings['is_active'] == 1;
    }
}

