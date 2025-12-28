<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Services\AuthService;

CorsMiddleware::handle();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$input = getJsonInput();
$authService = new AuthService();

$result = $authService->register([
    'username' => $input['username'] ?? '',
    'email' => $input['email'] ?? '',
    'password' => $input['password'] ?? ''
]);

if ($result['success']) {
    successResponse(['user_id' => $result['user_id']], $result['message'] ?? 'Registration successful');
} else {
    errorResponse(implode(', ', $result['errors'] ?? ['Registration failed']), 'VALIDATION_ERROR', 400);
}

