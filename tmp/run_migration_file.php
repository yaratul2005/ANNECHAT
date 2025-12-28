<?php
// Usage: php run_migration_file.php path/to/migration.sql
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/config/Database.php';

use App\Config\Config;
use App\Config\Database;

$filename = $argv[1] ?? __DIR__ . '/../database/migration_add_user_ip_footer_smtp.sql';

if (!file_exists($filename)) {
    echo "Migration file not found: {$filename}\n";
    exit(1);
}

try {
    Config::load();
    $pdo = Database::getInstance();

    $sql = file_get_contents($filename);
    if ($sql === false) {
        throw new \RuntimeException('Could not read migration file');
    }

    // Remove lines that are SQL comments starting with --
    $lines = explode("\n", $sql);
    $filtered = array_filter($lines, function($line) {
        $trim = trim($line);
        return $trim !== '' && strpos($trim, '--') !== 0;
    });
    $cleanSql = implode("\n", $filtered);

    // Split statements by semicolon
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));

    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        if (preg_match('/^USE\s+/i', $stmt)) {
            // Skip USE statements
            continue;
        }

        // Handle ALTER TABLE with 'IF NOT EXISTS' clauses for columns/indexes which older MySQL versions
        // may not support. We will split ADD clauses and apply conditionally.
        if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+(.*)$/is', $stmt, $m)) {
            $table = $m[1];
            $rest = trim($m[2]);

            // Split on ', ADD ' while keeping the ADD keyword with fragments
            $parts = preg_split('/,\s*(?=ADD\s+)/i', $rest);
            foreach ($parts as $part) {
                $part = trim($part);
                // Normalize leading ADD
                if (preg_match('/^ADD\s+/i', $part)) {
                    $partBody = preg_replace('/^ADD\s+/i', '', $part);

                    // Column with IF NOT EXISTS
                    if (preg_match('/^COLUMN\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?\s+(.*)$/is', $partBody, $colm)) {
                        $colName = $colm[1];
                        $colDef = $colm[2];
                        // Check if column exists
                        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col");
                        $colStmt->execute([':table' => $table, ':col' => $colName]);
                        $colExists = (int)$colStmt->fetchColumn() > 0;
                        if ($colExists) {
                            echo "Column {$colName} on {$table} already exists, skipping...\n";
                        } else {
                            $alter = "ALTER TABLE `{$table}` ADD COLUMN {$colName} {$colDef}";
                            echo "Executing: " . preg_replace('/\s+/', ' ', substr($alter, 0, 120)) . "...\n";
                            $pdo->exec($alter);
                        }
                        continue;
                    }

                    // Index with IF NOT EXISTS
                    if (preg_match('/^INDEX\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?\s*\((.*)\)$/is', $partBody, $idxm)) {
                        $idxName = $idxm[1];
                        // Check if index exists
                        $idxStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :idx");
                        $idxStmt->execute([':table' => $table, ':idx' => $idxName]);
                        $idxExists = (int)$idxStmt->fetchColumn() > 0;
                        if ($idxExists) {
                            echo "Index {$idxName} on {$table} already exists, skipping...\n";
                        } else {
                            $alter = "ALTER TABLE `{$table}` ADD INDEX `{$idxName}` ({$idxm[2]})";
                            echo "Executing: " . preg_replace('/\s+/', ' ', substr($alter, 0, 120)) . "...\n";
                            $pdo->exec($alter);
                        }
                        continue;
                    }

                    // Fallback: execute the ADD fragment as-is appended to ALTER TABLE
                    $fallback = "ALTER TABLE `{$table}` ADD " . $partBody;
                    echo "Executing (fallback): " . preg_replace('/\s+/', ' ', substr($fallback, 0, 120)) . "...\n";
                    $pdo->exec($fallback);
                    continue;
                }
                // If no ADD prefix, execute statement as a whole
                echo "Executing: " . preg_replace('/\s+/', ' ', substr($part, 0, 120)) . "...\n";
                $pdo->exec($part);
            }

            continue;
        }

        echo "Executing: " . preg_replace('/\s+/', ' ', substr($stmt, 0, 120)) . "...\n";
        $pdo->exec($stmt);
    }

    echo "Migration applied successfully: {$filename}\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
