<?php
// Trigger execution
declare(strict_types=1);

require_once 'src/config/Config.php';
require_once 'src/config/Database.php';

use App\Config\Database;

try {
    // Load configuration
    \App\Config\Config::load();

    // Get database connection
    $pdo = Database::getInstance();

    // List of migration files to run
    $migrations = [
        'database/migration_add_groups.sql',
        'database/migration_add_friends.sql'
    ];

    foreach ($migrations as $migrationFile) {
        if (!file_exists($migrationFile)) {
            throw new \RuntimeException("Migration file not found: $migrationFile");
        }

        echo "Running migration: $migrationFile\n";

        // Read SQL content
        $sql = file_get_contents($migrationFile);

        // Execute the SQL
        $pdo->exec($sql);

        echo "Migration completed: $migrationFile\n";
    }

    echo "All migrations completed successfully!\n";

} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
