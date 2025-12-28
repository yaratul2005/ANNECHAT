<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\IpBlockMiddleware;
use App\Services\AuthService;
use App\Models\Story;
use App\Models\StoryReaction;

CorsMiddleware::handle();
IpBlockMiddleware::handle();

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

$storyModel = new Story();
$reactionModel = new StoryReaction();

switch ($action) {
    case 'get':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        $stories = $storyModel->getActiveStories();
        // Check which stories user has starred
        foreach ($stories as &$story) {
            $story['has_starred'] = $reactionModel->hasStarred((int)$story['id'], $user['id']);
        }
        unset($story);
        successResponse(['stories' => $stories], 'Stories fetched');
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        // Only non-guest users can create stories
        if ($user['is_guest']) {
            errorResponse('Guest users cannot create stories', 'PERMISSION_DENIED', 403);
        }

        $mediaType = $input['media_type'] ?? '';
        $mediaUrl = $input['media_url'] ?? '';
        $text = $input['text'] ?? null;

        if (empty($mediaType) || empty($mediaUrl)) {
            errorResponse('Media type and URL are required', 'VALIDATION_ERROR', 400);
        }

        if (!in_array($mediaType, ['image', 'video'])) {
            errorResponse('Invalid media type', 'VALIDATION_ERROR', 400);
        }

        // Check if user can post (rate limiting)
        if (!$storyModel->canUserPost($user['id'])) {
            errorResponse('You can only post 5 stories per hour. Please wait before posting another.', 'RATE_LIMIT', 429);
        }

        try {
            $storyId = $storyModel->create($user['id'], $mediaType, $mediaUrl, $text);
            $story = $storyModel->getById($storyId);
            $story['has_starred'] = false;
            successResponse(['story' => $story], 'Story created successfully');
        } catch (Exception $e) {
            errorResponse('Failed to create story: ' . $e->getMessage(), 'STORY_CREATE_ERROR', 500);
        }
        break;

    case 'star':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        $storyId = (int)($input['story_id'] ?? 0);
        if ($storyId <= 0) {
            errorResponse('Invalid story ID', 'VALIDATION_ERROR', 400);
        }

        // Check if story exists and is active
        $story = $storyModel->getById($storyId);
        if (!$story) {
            errorResponse('Story not found or expired', 'NOT_FOUND', 404);
        }

        try {
            $reactionModel->addStar($storyId, $user['id']);
            successResponse(null, 'Story starred');
        } catch (Exception $e) {
            errorResponse('Failed to star story: ' . $e->getMessage(), 'STAR_ERROR', 500);
        }
        break;

    case 'reply':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        $storyId = (int)($input['story_id'] ?? 0);
        $content = trim($input['content'] ?? '');
        
        if ($storyId <= 0 || empty($content)) {
            errorResponse('Invalid story ID or empty reply', 'VALIDATION_ERROR', 400);
        }

        if (strlen($content) > 500) {
            errorResponse('Reply is too long (max 500 characters)', 'VALIDATION_ERROR', 400);
        }

        // Check if story exists and is active
        $story = $storyModel->getById($storyId);
        if (!$story) {
            errorResponse('Story not found or expired', 'NOT_FOUND', 404);
        }

        try {
            $reactionId = $reactionModel->addReply($storyId, $user['id'], $content);
            successResponse(['reaction_id' => $reactionId], 'Reply added');
        } catch (Exception $e) {
            errorResponse('Failed to add reply: ' . $e->getMessage(), 'REPLY_ERROR', 500);
        }
        break;

    case 'get_reactions':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        $storyId = (int)($_GET['story_id'] ?? 0);
        $type = $_GET['type'] ?? null; // 'star' or 'reply'
        
        if ($storyId <= 0) {
            errorResponse('Invalid story ID', 'VALIDATION_ERROR', 400);
        }

        try {
            $reactions = $reactionModel->getReactions($storyId, $type);
            successResponse(['reactions' => $reactions], 'Reactions fetched');
        } catch (Exception $e) {
            errorResponse('Failed to fetch reactions: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        $storyId = (int)($input['story_id'] ?? 0);
        if ($storyId <= 0) {
            errorResponse('Invalid story ID', 'VALIDATION_ERROR', 400);
        }

        try {
            $storyModel->delete($storyId, $user['id']);
            successResponse(null, 'Story deleted');
        } catch (Exception $e) {
            errorResponse('Failed to delete story: ' . $e->getMessage(), 'DELETE_ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

