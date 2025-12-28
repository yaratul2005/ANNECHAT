<?php
declare(strict_types=1);

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('errorResponse')) {
    function errorResponse(string $message, string $code = 'ERROR', int $statusCode = 400): void {
        jsonResponse([
            'success' => false,
            'error' => $message,
            'code' => $code
        ], $statusCode);
    }
}

if (!function_exists('successResponse')) {
    function successResponse($data = null, string $message = null): void {
        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
        jsonResponse($response);
    }
}

if (!function_exists('getJsonInput')) {
    function getJsonInput(): array {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }
}

if (!function_exists('debugLog')) {
    function debugLog(string $message): void {
        // Only log debug messages when APP_DEBUG is enabled
        if (\App\Config\Config::appDebug()) {
            error_log($message);
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

