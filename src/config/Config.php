<?php
declare(strict_types=1);

namespace App\Config;

class Config {
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $envPath = null): void {
        if (self::$loaded) {
            return;
        }

        $envPath = $envPath ?? dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($envPath)) {
            throw new \RuntimeException('.env file not found at: ' . $envPath);
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            self::$config[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config[$key] ?? $default;
    }

    public static function has(string $key): bool {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]);
    }

    // Database
    public static function dbHost(): string {
        return self::get('DB_HOST', 'localhost');
    }

    public static function dbPort(): int {
        return (int)self::get('DB_PORT', 3306);
    }

    public static function dbName(): string {
        return self::get('DB_NAME', 'anne_chat');
    }

    public static function dbUser(): string {
        return self::get('DB_USER', 'root');
    }

    public static function dbPassword(): string {
        return self::get('DB_PASSWORD', '');
    }

    // Application
    public static function appName(): string {
        return self::get('APP_NAME', 'Anne Chat');
    }

    public static function appUrl(): string {
        return rtrim(self::get('APP_URL', 'http://localhost'), '/');
    }

    public static function appDebug(): bool {
        return filter_var(self::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function appTimezone(): string {
        return self::get('APP_TIMEZONE', 'UTC');
    }

    public static function appKey(): string {
        return self::get('APP_KEY', '');
    }

    // SMTP
    public static function smtpHost(): string {
        return self::get('SMTP_HOST', 'smtp.gmail.com');
    }

    public static function smtpPort(): int {
        return (int)self::get('SMTP_PORT', 587);
    }

    public static function smtpUser(): string {
        return self::get('SMTP_USER', '');
    }

    public static function smtpPassword(): string {
        return self::get('SMTP_PASSWORD', '');
    }

    public static function smtpFromEmail(): string {
        return self::get('SMTP_FROM_EMAIL', 'noreply@example.com');
    }

    public static function smtpFromName(): string {
        return self::get('SMTP_FROM_NAME', 'Anne Chat');
    }

    // Security
    public static function bcryptCost(): int {
        return (int)self::get('BCRYPT_COST', 12);
    }

    public static function sessionTimeout(): int {
        return (int)self::get('SESSION_TIMEOUT', 1800);
    }

    // File Upload
    public static function maxFileSize(): int {
        return (int)self::get('MAX_FILE_SIZE', 5242880);
    }

    public static function allowedImageTypes(): array {
        $types = self::get('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif');
        return explode(',', $types);
    }
}

