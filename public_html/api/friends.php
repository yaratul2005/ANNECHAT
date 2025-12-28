<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\FriendRequest;
use App\Models\User;

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

// Block guest users from friend requests
if ($user['is_guest']) {
    errorResponse('Guest users cannot send friend requests', 'FORBIDDEN', 403);
}

$input = getJsonInput();
$action = $_GET['action'] ?? $input['action'] ?? 'list';

$friendRequestModel = new FriendRequest();
$userModel = new User();

switch ($action) {
    case 'send_request':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $receiverId = (int)($input['user_id'] ?? 0);
        if (!$receiverId) {
            errorResponse('User ID is required', 'VALIDATION_ERROR', 400);
        }

        if ($receiverId === $user['id']) {
            errorResponse('Cannot send friend request to yourself', 'VALIDATION_ERROR', 400);
        }

        $receiver = $userModel->findById($receiverId);
        if (!$receiver) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        if ($friendRequestModel->exists($user['id'], $receiverId)) {
            errorResponse('Friend request already exists', 'VALIDATION_ERROR', 400);
        }

        try {
            $requestId = $friendRequestModel->create($user['id'], $receiverId);
            successResponse(['request_id' => $requestId], 'Friend request sent successfully');
        } catch (\Exception $e) {
            error_log("Error sending friend request: " . $e->getMessage());
            errorResponse('Failed to send friend request: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'accept':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $requestId = (int)($input['request_id'] ?? 0);
        if (!$requestId) {
            errorResponse('Request ID is required', 'VALIDATION_ERROR', 400);
        }

        $request = $friendRequestModel->findById($requestId);
        if (!$request) {
            errorResponse('Friend request not found', 'NOT_FOUND', 404);
        }

        if ($request['receiver_id'] != $user['id']) {
            errorResponse('Unauthorized', 'FORBIDDEN', 403);
        }

        if ($request['status'] !== 'pending') {
            errorResponse('Friend request is not pending', 'VALIDATION_ERROR', 400);
        }

        try {
            $friendRequestModel->accept($requestId);
            successResponse([], 'Friend request accepted');
        } catch (\Exception $e) {
            error_log("Error accepting friend request: " . $e->getMessage());
            errorResponse('Failed to accept friend request: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'reject':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $requestId = (int)($input['request_id'] ?? 0);
        if (!$requestId) {
            errorResponse('Request ID is required', 'VALIDATION_ERROR', 400);
        }

        $request = $friendRequestModel->findById($requestId);
        if (!$request) {
            errorResponse('Friend request not found', 'NOT_FOUND', 404);
        }

        if ($request['receiver_id'] != $user['id']) {
            errorResponse('Unauthorized', 'FORBIDDEN', 403);
        }

        try {
            $friendRequestModel->reject($requestId);
            successResponse([], 'Friend request rejected');
        } catch (\Exception $e) {
            error_log("Error rejecting friend request: " . $e->getMessage());
            errorResponse('Failed to reject friend request: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'requests':
        try {
            $incoming = $friendRequestModel->getIncomingRequests($user['id']);
            successResponse(['requests' => $incoming]);
        } catch (\Exception $e) {
            error_log("Error fetching friend requests: " . $e->getMessage());
            errorResponse('Failed to load friend requests: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'list':
        $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user['id'];
        
        // Only allow viewing own friends or friends of public profiles
        if ($targetUserId !== $user['id']) {
            // In future, can add privacy check here
        }

        try {
            $friends = $friendRequestModel->getFriends($targetUserId);
            successResponse(['friends' => $friends]);
        } catch (\Exception $e) {
            error_log("Error fetching friends: " . $e->getMessage());
            errorResponse('Failed to load friends: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'remove':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $friendId = (int)($input['user_id'] ?? 0);
        if (!$friendId) {
            errorResponse('User ID is required', 'VALIDATION_ERROR', 400);
        }

        if (!$friendRequestModel->areFriends($user['id'], $friendId)) {
            errorResponse('Not friends with this user', 'VALIDATION_ERROR', 400);
        }

        try {
            $friendRequestModel->removeFriendship($user['id'], $friendId);
            successResponse([], 'Friend removed successfully');
        } catch (\Exception $e) {
            error_log("Error removing friend: " . $e->getMessage());
            errorResponse('Failed to remove friend: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'status':
        $targetUserId = (int)($_GET['user_id'] ?? $input['user_id'] ?? 0);
        if (!$targetUserId) {
            errorResponse('User ID is required', 'VALIDATION_ERROR', 400);
        }

        try {
            $areFriends = $friendRequestModel->areFriends($user['id'], $targetUserId);
            $request = $friendRequestModel->getRequestBetween($user['id'], $targetUserId);
            if (!$request) {
                $request = $friendRequestModel->getRequestBetween($targetUserId, $user['id']);
            }

            $status = 'none';
            if ($areFriends) {
                $status = 'friends';
            } elseif ($request) {
                if ($request['sender_id'] == $user['id']) {
                    $status = 'sent';
                } else {
                    $status = 'received';
                }
            }

            successResponse([
                'status' => $status,
                'are_friends' => $areFriends,
                'request' => $request
            ]);
        } catch (\Exception $e) {
            error_log("Error checking friend status: " . $e->getMessage());
            errorResponse('Failed to check friend status: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

