<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\IpBlock;

class IpBlockMiddleware {
    public static function check(): void {
        $ipAddress = self::getClientIp();
        $ipBlockModel = new IpBlock();
        
        if ($ipBlockModel->isBlocked($ipAddress)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Your IP address has been blocked',
                'code' => 'IP_BLOCKED'
            ]);
            exit;
        }
    }

    private static function getClientIp(): string {
        // Check for IP from various sources (proxy, load balancer, etc.)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            // Nginx proxy
            return $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Proxy/load balancer
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    // Backwards-compatible alias to match other middleware naming
    public static function handle(): void {
        self::check();
    }
}

