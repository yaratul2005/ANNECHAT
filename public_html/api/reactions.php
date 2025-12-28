<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\PostReaction;

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

$reactionModel = new PostReaction();
$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'toggle';

switch ($action) {
    case 'toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $postId = (int)($input['post_id'] ?? 0);
        $reactionType = $input['reaction_type'] ?? 'star';

        if ($postId <= 0) {
            errorResponse('Invalid post ID', 'VALIDATION_ERROR', 400);
        }

        $allowedTypes = ['star', 'like', 'love'];
        if (!in_array($reactionType, $allowedTypes)) {
            errorResponse('Invalid reaction type', 'VALIDATION_ERROR', 400);
        }

        $reactionModel->toggle($postId, $user['id'], $reactionType);
        $hasReacted = $reactionModel->hasReacted($postId, $user['id'], $reactionType);
        $count = $reactionModel->getCount($postId, $reactionType);
        
        successResponse([
            'has_reacted' => $hasReacted,
            'count' => $count
        ]);
        break;

    case 'get':
        $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
        $reactionType = $_GET['reaction_type'] ?? 'star';

        if ($postId <= 0) {
            errorResponse('Invalid post ID', 'VALIDATION_ERROR', 400);
        }

        $hasReacted = $reactionModel->hasReacted($postId, $user['id'], $reactionType);
        $count = $reactionModel->getCount($postId, $reactionType);
        
        successResponse([
            'has_reacted' => $hasReacted,
            'count' => $count
        ]);
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

