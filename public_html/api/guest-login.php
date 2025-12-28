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

$result = $authService->guestLogin(
    $input['username'] ?? '',
    (int)($input['age'] ?? 0),
    $input['gender'] ?? null
);

if ($result['success']) {
    successResponse($result['user'], 'Guest login successful');
} else {
    errorResponse(implode(', ', $result['errors'] ?? ['Guest login failed']), 'VALIDATION_ERROR', 400);
}

