<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/config/Database.php';

use App\Config\Config;
use App\Config\Database;

Config::load();
$pdo = Database::getInstance();

$tables = ['ip_blocks', 'cooldowns'];
// Add verification for new migration
$tables = array_merge($tables, ['smtp_settings', 'email_logs']);
// Add verification for stories tables
$tables = array_merge($tables, ['stories', 'story_reactions']);
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW TABLES LIKE '" . $t . "'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo $t . ': ' . (count($rows) ? 'FOUND' : 'MISSING') . PHP_EOL;
}

// Show columns
$stmt = $pdo->query("DESCRIBE ip_blocks");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "ip_blocks columns:\n";
foreach ($cols as $c) {
    echo " - " . $c['Field'] . "\n";
}

// Check users and site_settings columns
$stmt = $pdo->query("DESCRIBE users");
$userCols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
echo "\nusers columns include last_ip_address: " . (in_array('last_ip_address', $userCols) ? 'YES' : 'NO') . PHP_EOL;

$stmt = $pdo->query("DESCRIBE site_settings");
$siteCols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
echo "site_settings columns include footer_text/footer_enabled/footer_copyright: " . (
    (in_array('footer_text', $siteCols) && in_array('footer_enabled', $siteCols) && in_array('footer_copyright', $siteCols)) ? 'YES' : 'NO'
) . PHP_EOL;
