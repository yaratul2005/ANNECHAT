<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\MessageService;
use App\Services\AuthService;

CorsMiddleware::handle();

header('Content-Type: application/json');

$authMiddleware = new AuthMiddleware();
$authService = new AuthService();
$messageService = new MessageService();

$user = $authService->getCurrentUser();
if (!$user) {
    errorResponse('Authentication required', 'UNAUTHORIZED', 401);
}

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'send':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        // Email verification is now optional - users can send messages without verification

        // Support both JSON body and form-encoded POSTs by falling back to $_POST
        $recipientId = (int)($input['recipient_id'] ?? $_POST['recipient_id'] ?? 0);
        $messageText = $input['message_text'] ?? $_POST['message_text'] ?? null;
        $attachmentType = $input['attachment_type'] ?? $_POST['attachment_type'] ?? null;
        $attachmentUrl = $input['attachment_url'] ?? $_POST['attachment_url'] ?? null;
        $attachmentName = $input['attachment_name'] ?? $_POST['attachment_name'] ?? null;
        $attachmentSize = isset($input['attachment_size']) ? (int)$input['attachment_size'] : (isset($_POST['attachment_size']) ? (int)$_POST['attachment_size'] : null);

        $result = $messageService->send(
            $user['id'],
            $recipientId,
            $messageText,
            $attachmentType,
            $attachmentUrl,
            $attachmentName,
            $attachmentSize
        );

        if ($result['success']) {
            successResponse($result['message'], 'Message sent');
        } else {
            errorResponse(implode(', ', $result['errors'] ?? ['Failed to send message']), 'VALIDATION_ERROR', 400);
        }
        break;

    case 'poll':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $lastMessageId = isset($input['last_message_id']) ? (int)$input['last_message_id'] : (isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : null);
        
        // Long polling: wait up to 30 seconds for new messages
        // Ensure PHP doesn't time out during long-polling
        if (function_exists('set_time_limit')) {
            @set_time_limit(35);
        }

        // If running under PHP built-in server (development), avoid long blocking polls because
        // the built-in server is single-threaded and long polls will block other requests.
        if (PHP_SAPI === 'cli-server') {
            $maxWait = 2; // short wait to keep dev server responsive
            $checkInterval = 1;
        } else {
            $maxWait = 30;
            $checkInterval = 2;
        }
        $waited = 0;

        // Release PHP session lock so long-polling does not block other requests
        // (session_start() acquires an exclusive lock until the script ends).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        while ($waited < $maxWait) {
            $result = $messageService->pollNewMessages($user['id'], $lastMessageId);
            
            if (!empty($result['messages'])) {
                // Return only the messages array in the response data to match frontend expectations
                successResponse(['messages' => $result['messages']]);
                break;
            }

            sleep($checkInterval);
            $waited += $checkInterval;
            
            // Check if connection is still alive
            if (connection_aborted()) {
                exit;
            }
        }

        // Timeout - return empty messages
        successResponse(['messages' => []]);
        break;

    case 'get_conversation':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $otherUserId = isset($input['user_id']) ? (int)$input['user_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);
        $limit = isset($input['limit']) ? (int)$input['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 50);
        $offset = isset($input['offset']) ? (int)$input['offset'] : (isset($_GET['offset']) ? (int)$_GET['offset'] : 0);

        if ($otherUserId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        $result = $messageService->getConversation($user['id'], $otherUserId, $limit, $offset);
        // Return messages directly so client can access response.data.messages
        successResponse(['messages' => $result['messages']]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $messageId = (int)($input['message_id'] ?? 0);
        if ($messageId <= 0) {
            errorResponse('Invalid message ID', 'VALIDATION_ERROR', 400);
        }

        $result = $messageService->delete($messageId, $user['id'], $user['is_admin'] ?? false);
        if ($result['success']) {
            successResponse(null, $result['message'] ?? 'Message deleted');
        } else {
            errorResponse(implode(', ', $result['errors'] ?? ['Failed to delete message']), 'ERROR', 400);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

