<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/config/Database.php';

use App\Config\Config;
use App\Config\Database;

try {
    Config::load();
    $pdo = Database::getInstance();

    $sql = file_get_contents(__DIR__ . '/../database/migration_add_ip_blocks_cooldowns.sql');
    if ($sql === false) {
        throw new \RuntimeException('Could not read migration file');
    }

    // Remove SQL comments that start with --
    $lines = explode("\n", $sql);
    $filtered = array_filter($lines, function($line) {
        $trim = trim($line);
        return $trim !== '' && strpos($trim, '--') !== 0;
    });
    $cleanSql = implode("\n", $filtered);

    // Split statements on semicolon
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));

    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        // Skip if only USE statement for safety, ensure using the configured DB
        if (preg_match('/^USE\s+/i', $stmt)) {
            continue;
        }
        echo "Executing: " . substr($stmt, 0, 120) . "...\n";
        $pdo->exec($stmt);
    }

    echo "Migration applied successfully.\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
