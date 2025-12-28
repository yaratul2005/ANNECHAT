<?php
declare(strict_types=1);

// Error reporting
// Error reporting
// Suppress display of deprecation notices to keep API JSON responses clean during normal operation
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Timezone
date_default_timezone_set('UTC');

// Autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Fallback: Simple autoloader if composer hasn't run
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__) . '/src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Load configuration
use App\Config\Config;
try {
    Config::load();
} catch (Exception $e) {
    // If .env doesn't exist, that's okay for install
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        error_log('Config load error: ' . $e->getMessage());
    }
}

// Helper functions
require_once dirname(__DIR__) . '/src/utils/helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

