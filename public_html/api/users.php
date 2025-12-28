<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\User;
use App\Models\OnlineStatus;
use App\Models\Message;

CorsMiddleware::handle();

header('Content-Type: application/json');

$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->requireAuth()) {
    exit;
}

$authService = new AuthService();
$user = $authService->getCurrentUser();

if (!$user) {
    // Additional check: try to get user from session directly
    if (isset($_SESSION['user_id'])) {
        $userModel = new User();
        $user = $userModel->findById((int)$_SESSION['user_id']);
        if ($user) {
            unset($user['password_hash'], $user['verification_token'], $user['password_reset_token']);
        }
    }
    
    if (!$user) {
        errorResponse('Authentication required', 'UNAUTHORIZED', 401);
    }
}

// Get action from GET or POST body
// Only read JSON input for POST requests to avoid consuming php://input
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
}
$action = $_GET['action'] ?? $input['action'] ?? 'list';
$userModel = new User();
$onlineStatus = new OnlineStatus();
$messageModel = new Message();

// Update current user's online status
$onlineStatus->update($user['id'], 'online');

switch ($action) {
    case 'list':
    case 'online':
        try {
            $users = $userModel->getAllOnline();
            
            // Log for debugging
            debugLog("getAllOnline returned " . count($users) . " users");
            
            // Get unread counts for all conversations
            $unreadCounts = $messageModel->getUnreadCountsBySender($user['id']);
            
            // Ensure status is properly set for each user (fallback check)
            foreach ($users as &$userItem) {
                if (!isset($userItem['status']) || empty($userItem['status'])) {
                    $status = $onlineStatus->get($userItem['id']);
                    $userItem['status'] = $status ? $status['status'] : 'offline';
                }
                // Ensure status is always a valid value
                if (!in_array($userItem['status'], ['online', 'away', 'offline'])) {
                    $userItem['status'] = 'offline';
                }
                
                // Add unread count for this user
                $userItem['unread_count'] = $unreadCounts[$userItem['id']] ?? 0;
            }
            unset($userItem);
            
            successResponse(['users' => $users]);
        } catch (\Exception $e) {
            error_log("Error fetching users: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            errorResponse('Failed to load users: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'profile':
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : $user['id'];
        $profileUser = $userModel->findById($userId);
        
        if (!$profileUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        unset($profileUser['password_hash'], $profileUser['verification_token'], $profileUser['password_reset_token']);
        successResponse(['user' => $profileUser]);
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        // Guest users and unverified users cannot edit profile - they need to verify email
        if ($user['is_guest']) {
            errorResponse('Guest users cannot edit their profile. Please register and verify your email.', 'PERMISSION_DENIED', 403);
        }
        
        if (!$user['is_verified'] && !empty($user['email'])) {
            errorResponse('Please verify your email to edit your profile. Check your inbox for the verification link.', 'EMAIL_NOT_VERIFIED', 403);
        }

        // Get input if not already loaded
        if (empty($input)) {
            $input = getJsonInput();
        }
        $updateData = [];

        if (isset($input['username'])) {
            $username = trim($input['username']);
            if (!empty($username)) {
                // Check if username is already taken by another user
                $existingUser = $userModel->findByUsername($username);
                if ($existingUser && $existingUser['id'] != $user['id']) {
                    errorResponse('Username already taken', 'VALIDATION_ERROR', 400);
                    exit;
                }
                $updateData['username'] = $username;
            }
        }

        if (isset($input['fullname'])) {
            $updateData['fullname'] = sanitize(trim($input['fullname']));
        }

        if (isset($input['bio'])) {
            // Allow empty bio (user can clear it)
            $updateData['bio'] = sanitize($input['bio']);
        }

        if (isset($input['age'])) {
            $age = trim($input['age']);
            if ($age === '' || $age === null) {
                // Allow clearing age by setting to null
                $updateData['age'] = null;
            } else {
                $age = (int)$age;
                if ($age > 0 && $age <= 150) {
                    $updateData['age'] = $age;
                }
            }
        }

        if (isset($input['gender'])) {
            $allowedGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
            $gender = trim($input['gender']);
            if (in_array($gender, $allowedGenders)) {
                $updateData['gender'] = $gender;
            } elseif ($gender === '' || $gender === null) {
                $updateData['gender'] = null;
            }
        }

        if (isset($input['profile_picture'])) {
            // Allow empty profile picture (user can clear it)
            $profilePic = trim($input['profile_picture']);
            $updateData['profile_picture'] = $profilePic === '' ? null : sanitize($profilePic);
        }

        if (!empty($updateData)) {
            $userModel->update($user['id'], $updateData);
            $updatedUser = $userModel->findById($user['id']);
            unset($updatedUser['password_hash'], $updatedUser['verification_token'], $updatedUser['password_reset_token']);
            successResponse(['user' => $updatedUser], 'Profile updated');
        } else {
            errorResponse('No valid fields to update', 'VALIDATION_ERROR', 400);
        }
        break;

    case 'notifications':
        try {
            // Get unread counts by sender
            $unreadCounts = $messageModel->getUnreadCountsBySender($user['id']);
            
            // Get total unread conversations count
            $totalUnreadConversations = $messageModel->getUnreadConversationsCount($user['id']);
            
            successResponse([
                'unread_counts' => $unreadCounts,
                'total_unread_conversations' => $totalUnreadConversations
            ]);
        } catch (\Exception $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            errorResponse('Failed to load notifications: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

