<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\Group;
use App\Models\GroupMessage;
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

// Block guest users from groups
if ($user['is_guest']) {
    errorResponse('Guest users cannot access groups', 'FORBIDDEN', 403);
}

$input = getJsonInput();
$action = $_GET['action'] ?? $input['action'] ?? 'list';

$groupModel = new Group();
$groupMessageModel = new GroupMessage();

switch ($action) {
    case 'list':
        try {
            $groups = $groupModel->getAllForUser($user['id']);
            successResponse(['groups' => $groups]);
        } catch (\Exception $e) {
            error_log("Error fetching groups: " . $e->getMessage());
            errorResponse('Failed to load groups: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            errorResponse('Group name is required', 'VALIDATION_ERROR', 400);
        }

        if (strlen($name) > 100) {
            errorResponse('Group name must be 100 characters or less', 'VALIDATION_ERROR', 400);
        }

        try {
            $description = trim($input['description'] ?? '');
            $groupId = $groupModel->create([
                'name' => $name,
                'description' => $description ?: null,
                'created_by' => $user['id']
            ]);

            $group = $groupModel->findById($groupId);
            successResponse(['group' => $group], 'Group created successfully');
        } catch (\Exception $e) {
            error_log("Error creating group: " . $e->getMessage());
            errorResponse('Failed to create group: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'join':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        $group = $groupModel->findById($groupId);
        if (!$group) {
            errorResponse('Group not found', 'NOT_FOUND', 404);
        }

        if ($groupModel->isMember($groupId, $user['id'])) {
            errorResponse('Already a member of this group', 'VALIDATION_ERROR', 400);
        }

        try {
            $groupModel->addMember($groupId, $user['id']);
            successResponse(['group' => $groupModel->findById($groupId)], 'Joined group successfully');
        } catch (\Exception $e) {
            error_log("Error joining group: " . $e->getMessage());
            errorResponse('Failed to join group: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'leave':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        if (!$groupModel->isMember($groupId, $user['id'])) {
            errorResponse('Not a member of this group', 'VALIDATION_ERROR', 400);
        }

        $role = $groupModel->getUserRole($groupId, $user['id']);
        if ($role === 'admin') {
            $members = $groupModel->getMembers($groupId);
            $adminCount = count(array_filter($members, fn($m) => $m['role'] === 'admin'));
            if ($adminCount <= 1) {
                errorResponse('Cannot leave group: You are the only admin', 'VALIDATION_ERROR', 400);
            }
        }

        try {
            $groupModel->removeMember($groupId, $user['id']);
            successResponse([], 'Left group successfully');
        } catch (\Exception $e) {
            error_log("Error leaving group: " . $e->getMessage());
            errorResponse('Failed to leave group: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'messages':
        $groupId = (int)($_GET['group_id'] ?? $input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        if (!$groupModel->isMember($groupId, $user['id'])) {
            errorResponse('Not a member of this group', 'FORBIDDEN', 403);
        }

        try {
            $limit = (int)($_GET['limit'] ?? $input['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? $input['offset'] ?? 0);
            $messages = $groupMessageModel->getByGroupId($groupId, $limit, $offset);
            successResponse(['messages' => $messages]);
        } catch (\Exception $e) {
            error_log("Error fetching group messages: " . $e->getMessage());
            errorResponse('Failed to load messages: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'send_message':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        if (!$groupModel->isMember($groupId, $user['id'])) {
            errorResponse('Not a member of this group', 'FORBIDDEN', 403);
        }

        $messageText = trim($input['message_text'] ?? '');
        $attachmentType = $input['attachment_type'] ?? 'none';
        $attachmentUrl = $input['attachment_url'] ?? null;

        if (empty($messageText) && $attachmentType === 'none') {
            errorResponse('Message text or attachment is required', 'VALIDATION_ERROR', 400);
        }

        try {
            $messageId = $groupMessageModel->create([
                'group_id' => $groupId,
                'sender_id' => $user['id'],
                'message_text' => $messageText ?: null,
                'attachment_type' => $attachmentType,
                'attachment_url' => $attachmentUrl,
                'attachment_name' => $input['attachment_name'] ?? null,
                'attachment_size' => isset($input['attachment_size']) ? (int)$input['attachment_size'] : null
            ]);

            $message = $groupMessageModel->findById($messageId);
            successResponse(['message' => $message], 'Message sent successfully');
        } catch (\Exception $e) {
            error_log("Error sending group message: " . $e->getMessage());
            errorResponse('Failed to send message: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'members':
        $groupId = (int)($_GET['group_id'] ?? $input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        if (!$groupModel->isMember($groupId, $user['id'])) {
            errorResponse('Not a member of this group', 'FORBIDDEN', 403);
        }

        try {
            $members = $groupModel->getMembers($groupId);
            successResponse(['members' => $members]);
        } catch (\Exception $e) {
            error_log("Error fetching group members: " . $e->getMessage());
            errorResponse('Failed to load members: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'add_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        $userId = (int)($input['user_id'] ?? 0);
        
        if (!$groupId || !$userId) {
            errorResponse('Group ID and User ID are required', 'VALIDATION_ERROR', 400);
        }

        // Check if user is admin or group creator
        $group = $groupModel->findById($groupId);
        if (!$group) {
            errorResponse('Group not found', 'NOT_FOUND', 404);
        }

        $userRole = $groupModel->getUserRole($groupId, $user['id']);
        $isAdmin = $user['is_admin'] || $userRole === 'admin' || $group['created_by'] == $user['id'];
        
        if (!$isAdmin) {
            errorResponse('Only group admins can add members', 'FORBIDDEN', 403);
        }

        if ($groupModel->isMember($groupId, $userId)) {
            errorResponse('User is already a member', 'VALIDATION_ERROR', 400);
        }

        try {
            $groupModel->addMember($groupId, $userId, 'member');
            successResponse([], 'User added to group successfully');
        } catch (\Exception $e) {
            error_log("Error adding user to group: " . $e->getMessage());
            errorResponse('Failed to add user: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'remove_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        $userId = (int)($input['user_id'] ?? 0);
        
        if (!$groupId || !$userId) {
            errorResponse('Group ID and User ID are required', 'VALIDATION_ERROR', 400);
        }

        // Check if user is admin or group creator, or removing themselves
        $group = $groupModel->findById($groupId);
        if (!$group) {
            errorResponse('Group not found', 'NOT_FOUND', 404);
        }

        $userRole = $groupModel->getUserRole($groupId, $user['id']);
        $isAdmin = $user['is_admin'] || $userRole === 'admin' || $group['created_by'] == $user['id'];
        $isRemovingSelf = $userId == $user['id'];
        
        if (!$isAdmin && !$isRemovingSelf) {
            errorResponse('Only group admins can remove other members', 'FORBIDDEN', 403);
        }

        if (!$groupModel->isMember($groupId, $userId)) {
            errorResponse('User is not a member of this group', 'VALIDATION_ERROR', 400);
        }

        // Prevent removing the last admin
        if ($isAdmin && $userId != $user['id']) {
            $targetRole = $groupModel->getUserRole($groupId, $userId);
            if ($targetRole === 'admin') {
                $members = $groupModel->getMembers($groupId);
                $adminCount = count(array_filter($members, fn($m) => $m['role'] === 'admin'));
                if ($adminCount <= 1) {
                    errorResponse('Cannot remove the last admin', 'VALIDATION_ERROR', 400);
                }
            }
        }

        try {
            $groupModel->removeMember($groupId, $userId);
            successResponse([], 'User removed from group successfully');
        } catch (\Exception $e) {
            error_log("Error removing user from group: " . $e->getMessage());
            errorResponse('Failed to remove user: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        $group = $groupModel->findById($groupId);
        if (!$group) {
            errorResponse('Group not found', 'NOT_FOUND', 404);
        }

        // Check if user is admin or group creator
        $userRole = $groupModel->getUserRole($groupId, $user['id']);
        $isAdmin = $user['is_admin'] || $userRole === 'admin' || $group['created_by'] == $user['id'];
        
        if (!$isAdmin) {
            errorResponse('Only group admins can update group settings', 'FORBIDDEN', 403);
        }

        $updateData = [];
        if (isset($input['name'])) {
            $name = trim($input['name']);
            if (empty($name)) {
                errorResponse('Group name cannot be empty', 'VALIDATION_ERROR', 400);
            }
            if (strlen($name) > 100) {
                errorResponse('Group name must be 100 characters or less', 'VALIDATION_ERROR', 400);
            }
            $updateData['name'] = $name;
        }

        if (isset($input['description'])) {
            $updateData['description'] = trim($input['description']) ?: null;
        }

        if (isset($input['avatar'])) {
            $updateData['avatar'] = trim($input['avatar']) ?: null;
        }

        if (empty($updateData)) {
            errorResponse('No fields to update', 'VALIDATION_ERROR', 400);
        }

        try {
            $groupModel->update($groupId, $updateData);
            $updatedGroup = $groupModel->findById($groupId);
            successResponse(['group' => $updatedGroup], 'Group updated successfully');
        } catch (\Exception $e) {
            error_log("Error updating group: " . $e->getMessage());
            errorResponse('Failed to update group: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        if (!$groupId) {
            errorResponse('Group ID is required', 'VALIDATION_ERROR', 400);
        }

        $group = $groupModel->findById($groupId);
        if (!$group) {
            errorResponse('Group not found', 'NOT_FOUND', 404);
        }

        // Only group creator or site admin can delete
        $isAdmin = $user['is_admin'] || $group['created_by'] == $user['id'];
        
        if (!$isAdmin) {
            errorResponse('Only group creator or site admin can delete groups', 'FORBIDDEN', 403);
        }

        try {
            $groupModel->delete($groupId);
            successResponse([], 'Group deleted successfully');
        } catch (\Exception $e) {
            error_log("Error deleting group: " . $e->getMessage());
            errorResponse('Failed to delete group: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    case 'set_admin':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $groupId = (int)($input['group_id'] ?? 0);
        $userId = (int)($input['user_id'] ?? 0);
        $isAdmin = (bool)($input['is_admin'] ?? false);
        
        if (!$groupId || !$userId) {
            errorResponse('Group ID and User ID are required', 'VALIDATION_ERROR', 400);
        }

        $group = $groupModel->findById($groupId);
        if (!$group) {
            errorResponse('Group not found', 'NOT_FOUND', 404);
        }

        // Only site admin or group creator can set admins
        $canManage = $user['is_admin'] || $group['created_by'] == $user['id'];
        
        if (!$canManage) {
            errorResponse('Only site admin or group creator can manage admins', 'FORBIDDEN', 403);
        }

        if (!$groupModel->isMember($groupId, $userId)) {
            errorResponse('User is not a member of this group', 'VALIDATION_ERROR', 400);
        }

        try {
            $groupModel->addMember($groupId, $userId, $isAdmin ? 'admin' : 'member');
            successResponse([], 'User role updated successfully');
        } catch (\Exception $e) {
            error_log("Error updating user role: " . $e->getMessage());
            errorResponse('Failed to update user role: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

