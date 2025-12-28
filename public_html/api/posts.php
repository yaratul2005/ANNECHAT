<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\Post;

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

$postModel = new Post();
$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $content = isset($input['content']) ? trim($input['content']) : null;
        $mediaType = $input['media_type'] ?? 'text';
        $mediaUrl = isset($input['media_url']) ? trim($input['media_url']) : null;
        $mediaName = isset($input['media_name']) ? trim($input['media_name']) : null;

        // Validate media type
        $allowedMediaTypes = ['text', 'image', 'video', 'none'];
        if (!in_array($mediaType, $allowedMediaTypes)) {
            errorResponse('Invalid media type', 'VALIDATION_ERROR', 400);
        }

        // Content or media is required
        if (empty($content) && empty($mediaUrl)) {
            errorResponse('Post content or media is required', 'VALIDATION_ERROR', 400);
        }

        $postId = $postModel->create($user['id'], $content, $mediaType, $mediaUrl, $mediaName);
        $post = $postModel->getById($postId);
        
        successResponse(['post' => $post], 'Post created successfully');
        break;

    case 'list':
    case 'get':
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user['id'];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $posts = $postModel->getByUserId($userId, $limit, $offset);
        successResponse(['posts' => $posts, 'total' => count($posts)]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $postId = (int)($input['post_id'] ?? 0);
        if ($postId <= 0) {
            errorResponse('Invalid post ID', 'VALIDATION_ERROR', 400);
        }

        // Verify post belongs to user
        $post = $postModel->getById($postId);
        if (!$post) {
            errorResponse('Post not found', 'NOT_FOUND', 404);
        }

        if ($post['user_id'] != $user['id']) {
            errorResponse('You can only delete your own posts', 'PERMISSION_DENIED', 403);
        }

        if ($postModel->delete($postId, $user['id'])) {
            successResponse(['post_id' => $postId], 'Post deleted successfully');
        } else {
            errorResponse('Failed to delete post', 'ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

