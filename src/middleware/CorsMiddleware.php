<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Config\Config;

class CorsMiddleware {
    public static function handle(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowedOrigins = [Config::appUrl()];

        if (in_array($origin, $allowedOrigins) || Config::appDebug()) {
            header("Access-Control-Allow-Origin: {$origin}");
        } else {
            header("Access-Control-Allow-Origin: " . Config::appUrl());
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

