<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

CorsMiddleware::handle();

header('Content-Type: application/json');

$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->requireAuth()) {
    exit;
}

$authService = new AuthService();
$user = $authService->getCurrentUser();

if (!$user) {
    errorResponse('Authentication required', 'UNAUTHORIZED', 401);
}

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'resend':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $result = $authService->resendVerificationEmail($user['id']);
        
        if ($result['success']) {
            successResponse(null, $result['message']);
        } else {
            errorResponse(implode(', ', $result['errors'] ?? ['Failed to send verification email']), 'VERIFY_ERROR', 400);
        }
        break;

    case 'verify':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $token = $input['token'] ?? '';
        if (empty($token)) {
            errorResponse('Verification token is required', 'VALIDATION_ERROR', 400);
        }

        $result = $authService->verifyEmail($token);
        
        if ($result['success']) {
            // Refresh user data
            $user = $authService->getCurrentUser();
            successResponse(['user' => $user], $result['message']);
        } else {
            errorResponse(implode(', ', $result['errors'] ?? ['Verification failed']), 'VERIFY_ERROR', 400);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

