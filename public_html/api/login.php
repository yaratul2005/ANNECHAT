<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Services\AuthService;

CorsMiddleware::handle();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$raw = file_get_contents('php://input');
error_log('Login raw input: ' . $raw);
$input = getJsonInput();
$authService = new AuthService();

$result = $authService->login(
    $input['email'] ?? '',
    $input['password'] ?? ''
);

if ($result['success']) {
    successResponse($result['user'], 'Login successful');
} else {
    errorResponse(implode(', ', $result['errors'] ?? ['Login failed']), 'AUTH_ERROR', 401);
}

