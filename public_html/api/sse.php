<?php
/**
 * Server-Sent Events (SSE) endpoint for real-time message streaming
 * 
 * This endpoint maintains a persistent connection and streams new messages
 * to the client in real-time, eliminating the need for continuous polling.
 */

require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\MessageService;
use App\Services\AuthService;
use App\Models\OnlineStatus;

CorsMiddleware::handle();

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Prevent output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Disable time limit for long-running connection
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

// Disable PHP execution time limit
ini_set('max_execution_time', 0);

// Release session lock to prevent blocking other requests
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$authMiddleware = new AuthMiddleware();
$authService = new AuthService();
$messageService = new MessageService();
$onlineStatus = new OnlineStatus();

$user = $authService->getCurrentUser();
if (!$user) {
    // Send error event and close connection
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Authentication required']) . "\n\n";
    flush();
    exit;
}

// Get last message ID from query parameter
$lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : null;

// Update user's online status
$onlineStatus->update($user['id'], 'online');

// Send initial connection event
echo "event: connected\n";
echo "data: " . json_encode([
    'user_id' => $user['id'],
    'timestamp' => time()
]) . "\n\n";
flush();

// Keep connection alive and stream messages
$checkInterval = 1; // Check every second
$heartbeatInterval = 15; // Send heartbeat every 15 seconds
$lastHeartbeat = time();
$lastMessageCheck = time();
$maxIdleTime = 300; // Close connection after 5 minutes of inactivity
$connectionStartTime = time();

// Function to send SSE message
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
    
    // Ensure output is sent immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

// Main event loop
while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    // Close connection after max idle time to prevent resource exhaustion
    $currentTime = time();
    if ($currentTime - $connectionStartTime > $maxIdleTime) {
        sendSSE('timeout', [
            'message' => 'Connection timeout, please reconnect'
        ]);
        break;
    }
    
    // Check for new messages
    try {
        $result = $messageService->pollNewMessages($user['id'], $lastMessageId);
        $messages = $result['messages'] ?? [];
        
        if (!empty($messages)) {
            // Send new messages
            sendSSE('message', [
                'messages' => $messages
            ]);
            
            // Update last message ID
            $lastMessageId = $messages[count($messages) - 1]['id'] ?? $lastMessageId;
            $lastMessageCheck = $currentTime;
        }
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
        sendSSE('error', [
            'error' => 'Failed to fetch messages',
            'message' => $e->getMessage()
        ]);
    }
    
    // Send heartbeat to keep connection alive
    if ($currentTime - $lastHeartbeat >= $heartbeatInterval) {
        sendSSE('heartbeat', [
            'timestamp' => $currentTime
        ]);
        $lastHeartbeat = $currentTime;
        
        // Update online status periodically
        try {
            $onlineStatus->update($user['id'], 'online');
        } catch (Exception $e) {
            error_log("SSE Status Update Error: " . $e->getMessage());
        }
    }
    
    // Small delay to prevent CPU spinning
    usleep($checkInterval * 1000000); // Convert seconds to microseconds
}

// Send disconnect event
sendSSE('disconnected', [
    'message' => 'Connection closed'
]);

