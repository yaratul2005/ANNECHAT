<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Services\AuthService;

CorsMiddleware::handle();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$authService = new AuthService();
$authService->logout();

successResponse(null, 'Logged out successfully');

