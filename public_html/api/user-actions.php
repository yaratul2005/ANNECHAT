<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\Block;
use App\Models\Report;
use App\Models\User;

CorsMiddleware::handle();

header('Content-Type: application/json');

$authMiddleware = new AuthMiddleware();
$authService = new AuthService();
$blockModel = new Block();
$reportModel = new Report();
$userModel = new User();

$user = $authService->getCurrentUser();
if (!$user) {
    errorResponse('Authentication required', 'UNAUTHORIZED', 401);
}

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'block':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        if ($userId === $user['id']) {
            errorResponse('Cannot block yourself', 'VALIDATION_ERROR', 400);
        }

        // Check if user exists
        $targetUser = $userModel->findById($userId);
        if (!$targetUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        if ($blockModel->block($user['id'], $userId)) {
            successResponse(['blocked' => true], 'User blocked successfully');
        } else {
            errorResponse('Failed to block user', 'ERROR', 500);
        }
        break;

    case 'unblock':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        if ($blockModel->unblock($user['id'], $userId)) {
            successResponse(['blocked' => false], 'User unblocked successfully');
        } else {
            errorResponse('Failed to unblock user', 'ERROR', 500);
        }
        break;

    case 'is_blocked':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        $isBlocked = $blockModel->isBlocked($user['id'], $userId);
        successResponse(['blocked' => $isBlocked]);
        break;

    case 'report':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = (int)($input['user_id'] ?? 0);
        $reason = $input['reason'] ?? 'other';
        $description = $input['description'] ?? null;

        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        if ($userId === $user['id']) {
            errorResponse('Cannot report yourself', 'VALIDATION_ERROR', 400);
        }

        $allowedReasons = ['spam', 'harassment', 'inappropriate', 'fake_account', 'other'];
        if (!in_array($reason, $allowedReasons)) {
            errorResponse('Invalid reason', 'VALIDATION_ERROR', 400);
        }

        // Check if user exists
        $targetUser = $userModel->findById($userId);
        if (!$targetUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        try {
            $reportId = $reportModel->create($user['id'], $userId, $reason, $description);
            successResponse(['report_id' => $reportId], 'User reported successfully');
        } catch (\Exception $e) {
            errorResponse($e->getMessage(), 'ERROR', 500);
        }
        break;

    case 'get_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        $targetUser = $userModel->findById($userId);
        if (!$targetUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        // Check if blocked
        $isBlocked = $blockModel->isBlocked($user['id'], $userId);
        $hasBlockedMe = $blockModel->isBlocked($userId, $user['id']);

        // Get online status
        $onlineStatusModel = new \App\Models\OnlineStatus();
        $status = $onlineStatusModel->get($userId);

        successResponse([
            'user' => $targetUser,
            'blocked' => $isBlocked,
            'has_blocked_me' => $hasBlockedMe,
            'status' => $status ? $status['status'] : 'offline',
            'last_seen' => $status ? $status['last_seen'] : null
        ]);
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

