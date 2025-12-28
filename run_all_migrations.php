<?php
declare(strict_types=1);

// This script runs all database migrations in the correct order.

require_once 'src/config/Config.php';
require_once 'src/config/Database.php';

use App\Config\Database;
use App\Config\Config;

try {
    echo "Starting database migration process...\n";

    // Load configuration from .env file
    Config::load();
    echo "Configuration loaded.\n";

    // Get database connection
    $pdo = Database::getInstance();
    echo "Database connection established.\n";

    // List of migration files to run in order
    $migrations = [
        'database/schema.sql',
        'database/migration_add_attachments.sql',
        'database/migration_add_blocks_reports.sql',
        'database/migration_add_comments.sql',
        'database/migration_add_friends.sql',
        'database/migration_add_groups.sql',
        'database/migration_add_ip_blocks_cooldowns.sql',
        'database/migration_add_is_banned.sql',
        'database/migration_add_post_reactions.sql',
        'database/migration_add_posts.sql',
        'database/migration_add_profile_fields.sql',
        'database/migration_add_stories.sql',
        'database/migration_add_user_ip_footer_smtp.sql'
    ];

    echo "Found " . count($migrations) . " migration files to run.\n";

    foreach ($migrations as $migrationFile) {
        if (!file_exists($migrationFile)) {
            // It's better to throw an exception and stop the process if a file is missing.
            throw new \RuntimeException("Migration file not found: $migrationFile");
        }

        echo "Running migration: $migrationFile...\n";

        // Read SQL content
        $sql = file_get_contents($migrationFile);

        // PDO::exec can execute multiple queries separated by semicolons
        $pdo->exec($sql);

        echo "Migration completed: $migrationFile\n";
    }

    echo "\nAll migrations completed successfully!\n";

} catch (\Exception $e) {
    echo "\n\nAn error occurred during migration: " . $e->getMessage() . "\n";
    // It's good practice to provide more details if possible, like the file and line number.
    echo "In " . $e->getFile() . " on line " . $e->getLine() . "\n";
    exit(1); // Exit with a non-zero status code to indicate failure
}
