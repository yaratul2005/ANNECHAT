<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\Comment;

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

$commentModel = new Comment();
$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $postId = (int)($input['post_id'] ?? 0);
        $content = trim($input['content'] ?? '');

        if ($postId <= 0) {
            errorResponse('Invalid post ID', 'VALIDATION_ERROR', 400);
        }

        if (empty($content)) {
            errorResponse('Comment content is required', 'VALIDATION_ERROR', 400);
        }

        $commentId = $commentModel->create($postId, $user['id'], $content);
        $comment = $commentModel->getByPostId($postId);
        $newComment = null;
        foreach ($comment as $c) {
            if ($c['id'] == $commentId) {
                $newComment = $c;
                break;
            }
        }
        
        if (!$newComment) {
            // Fallback: get comment directly
            $allComments = $commentModel->getByPostId($postId);
            foreach ($allComments as $c) {
                if ($c['id'] == $commentId) {
                    $newComment = $c;
                    break;
                }
            }
        }
        
        successResponse(['comment' => $newComment], 'Comment added successfully');
        break;

    case 'list':
        $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
        if ($postId <= 0) {
            errorResponse('Invalid post ID', 'VALIDATION_ERROR', 400);
        }

        $comments = $commentModel->getByPostId($postId);
        successResponse(['comments' => $comments]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $commentId = (int)($input['comment_id'] ?? 0);
        if ($commentId <= 0) {
            errorResponse('Invalid comment ID', 'VALIDATION_ERROR', 400);
        }

        if ($commentModel->delete($commentId, $user['id'])) {
            successResponse(['comment_id' => $commentId], 'Comment deleted successfully');
        } else {
            errorResponse('Failed to delete comment or comment not found', 'ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

