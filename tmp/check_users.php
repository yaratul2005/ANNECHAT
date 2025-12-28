<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/config/Database.php';

use App\Config\Config;
use App\Config\Database;

try {
    Config::load();
    $pdo = Database::getInstance();
    $stmt = $pdo->query('SELECT COUNT(*) as c FROM users');
    $row = $stmt->fetch();
    echo "users_count=" . ($row['c'] ?? '0') . PHP_EOL;

    $stmt2 = $pdo->query('SELECT id, username, email FROM users ORDER BY id DESC LIMIT 5');
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "user: " . ($r['id'] ?? '') . " " . ($r['username'] ?? '') . " " . ($r['email'] ?? '') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . PHP_EOL;
}
