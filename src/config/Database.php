<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;
    private static ?PDO $connection = null;

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    private static function connect(): PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        try {
            $host = Config::dbHost();
            $port = Config::dbPort();
            $dbname = Config::dbName();
            $user = Config::dbUser();
            $password = Config::dbPassword();

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            // Set init command after connection if needed
            self::$connection = new PDO($dsn, $user, $password, $options);
            self::$connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            return self::$connection;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \RuntimeException("Database connection failed. Please check your configuration.");
        }
    }

    public static function disconnect(): void {
        self::$connection = null;
        self::$instance = null;
    }

    public static function testConnection(): bool {
        try {
            $pdo = self::getInstance();
            $pdo->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

