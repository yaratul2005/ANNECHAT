<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/config/Database.php';

use App\Config\Config;
use App\Config\Database;

try {
    Config::load();
    $pdo = Database::getInstance();
    $stmt = $pdo->query('SELECT COUNT(*) as c FROM sessions');
    $row = $stmt->fetch();
    echo "sessions_count=" . ($row['c'] ?? '0') . PHP_EOL;

    $stmt2 = $pdo->query('SELECT id, user_id, last_activity FROM sessions ORDER BY last_activity DESC LIMIT 10');
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "session: id=" . ($r['id'] ?? '') . " user_id=" . ($r['user_id'] ?? 'NULL') . " last_activity=" . ($r['last_activity'] ?? '') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . PHP_EOL;
}
