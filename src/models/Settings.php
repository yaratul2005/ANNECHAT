<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Settings {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function get(): ?array {
        $sql = "SELECT * FROM site_settings ORDER BY id ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function update(array $data): bool {
        $allowedFields = [
            'site_name', 'site_description', 'meta_title', 'meta_description', 
            'meta_keywords', 'custom_head_tags', 'custom_css', 'primary_color', 
            'secondary_color', 'logo_url', 'favicon_url', 'footer_text', 
            'footer_enabled', 'footer_copyright'
        ];
        
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE site_settings SET " . implode(', ', $updates) . " WHERE id = (SELECT id FROM (SELECT id FROM site_settings LIMIT 1) AS sub)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function create(array $data): bool {
        $sql = "INSERT INTO site_settings (site_name, site_description, meta_title, meta_description, meta_keywords, custom_head_tags, custom_css, primary_color, secondary_color, logo_url, favicon_url) 
                VALUES (:site_name, :site_description, :meta_title, :meta_description, :meta_keywords, :custom_head_tags, :custom_css, :primary_color, :secondary_color, :logo_url, :favicon_url)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':site_name' => $data['site_name'] ?? 'Anne Chat',
            ':site_description' => $data['site_description'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':meta_keywords' => $data['meta_keywords'] ?? null,
            ':custom_head_tags' => $data['custom_head_tags'] ?? null,
            ':custom_css' => $data['custom_css'] ?? null,
            ':primary_color' => $data['primary_color'] ?? '#1a73e8',
            ':secondary_color' => $data['secondary_color'] ?? '#e91e8c',
            ':logo_url' => $data['logo_url'] ?? null,
            ':favicon_url' => $data['favicon_url'] ?? null
        ]);
    }
}

