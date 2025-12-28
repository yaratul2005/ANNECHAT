<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Services\AuthService;
use App\Models\Report;
use App\Models\IpBlock;
use App\Models\Cooldown;
use App\Models\User;

CorsMiddleware::handle();

header('Content-Type: application/json');

$authService = new AuthService();
$user = $authService->getCurrentUser();

if (!$user || !$user['is_admin']) {
    errorResponse('Admin access required', 'UNAUTHORIZED', 401);
}

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_report_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $reportId = (int)($input['report_id'] ?? 0);
        $status = $input['status'] ?? '';

        if ($reportId <= 0) {
            errorResponse('Invalid report ID', 'VALIDATION_ERROR', 400);
        }

        $allowedStatuses = ['pending', 'reviewed', 'resolved', 'dismissed'];
        if (!in_array($status, $allowedStatuses)) {
            errorResponse('Invalid status', 'VALIDATION_ERROR', 400);
        }

        $reportModel = new Report();
        if ($reportModel->updateStatus($reportId, $status)) {
            successResponse(['report_id' => $reportId, 'status' => $status], 'Report status updated');
        } else {
            errorResponse('Failed to update report status', 'ERROR', 500);
        }
        break;

    case 'get_report_details':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $reportId = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
        if ($reportId <= 0) {
            errorResponse('Invalid report ID', 'VALIDATION_ERROR', 400);
        }

        $reportModel = new Report();
        $report = $reportModel->getById($reportId);
        if ($report) {
            successResponse($report);
        } else {
            errorResponse('Report not found', 'NOT_FOUND', 404);
        }
        break;

    case 'block_ip':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $ipAddress = $input['ip_address'] ?? '';
        $duration = $input['duration'] ?? '24h';
        $reason = $input['reason'] ?? null;

        if (empty($ipAddress) || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            errorResponse('Invalid IP address', 'VALIDATION_ERROR', 400);
        }

        // Calculate expiry time
        $expiresAt = null;
        $isPermanent = false;
        
        if ($duration === 'permanent') {
            $isPermanent = true;
        } else {
            $durationMap = [
                '1h' => 3600,
                '24h' => 86400,
                '7d' => 604800,
                '30d' => 2592000
            ];
            $seconds = $durationMap[$duration] ?? 86400;
            $expiresAt = new \DateTime();
            $expiresAt->modify("+{$seconds} seconds");
        }

        $ipBlockModel = new IpBlock();
        $blockId = $ipBlockModel->block($ipAddress, $reason, $user['id'], $expiresAt, $isPermanent);
        
        if ($blockId) {
            successResponse(['block_id' => $blockId, 'ip_address' => $ipAddress], 'IP blocked successfully');
        } else {
            errorResponse('Failed to block IP', 'ERROR', 500);
        }
        break;

    case 'unblock_ip':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $ipAddress = $input['ip_address'] ?? '';
        
        if (empty($ipAddress)) {
            errorResponse('Invalid IP address', 'VALIDATION_ERROR', 400);
        }

        $ipBlockModel = new IpBlock();
        if ($ipBlockModel->unblock($ipAddress)) {
            successResponse(['ip_address' => $ipAddress], 'IP unblocked successfully');
        } else {
            errorResponse('Failed to unblock IP', 'ERROR', 500);
        }
        break;

    case 'clear_cooldown':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $actionType = $input['action_type'] ?? '';
        $actionIdentifier = $input['action_identifier'] ?? '';
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : null;
        $ipAddress = $input['ip_address'] ?? null;

        if (empty($actionType) || empty($actionIdentifier)) {
            errorResponse('Invalid parameters', 'VALIDATION_ERROR', 400);
        }

        $cooldownModel = new Cooldown();
        if ($cooldownModel->clearCooldown($actionType, $actionIdentifier, $userId, $ipAddress)) {
            successResponse(null, 'Cooldown cleared successfully');
        } else {
            errorResponse('Failed to clear cooldown', 'ERROR', 500);
        }
        break;

    case 'get_users':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $includeGuests = isset($_GET['include_guests']) && $_GET['include_guests'] === 'true';

        $userModel = new User();
        $users = $userModel->getAll($limit, $offset, $includeGuests);
        successResponse(['users' => $users, 'total' => count($users)]);
        break;

    case 'ban_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        if ($userId === $user['id']) {
            errorResponse('Cannot ban yourself', 'VALIDATION_ERROR', 400);
        }

        $userModel = new User();
        $targetUser = $userModel->findById($userId);
        if (!$targetUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        if ($targetUser['is_admin']) {
            errorResponse('Cannot ban an admin user', 'VALIDATION_ERROR', 400);
        }

        if ($userModel->update($userId, ['is_banned' => true])) {
            successResponse(['user_id' => $userId], 'User banned successfully');
        } else {
            errorResponse('Failed to ban user', 'ERROR', 500);
        }
        break;

    case 'unban_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        $userModel = new User();
        $targetUser = $userModel->findById($userId);
        if (!$targetUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        if ($userModel->update($userId, ['is_banned' => false])) {
            successResponse(['user_id' => $userId], 'User unbanned successfully');
        } else {
            errorResponse('Failed to unban user', 'ERROR', 500);
        }
        break;

    case 'delete_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            errorResponse('Invalid user ID', 'VALIDATION_ERROR', 400);
        }

        if ($userId === $user['id']) {
            errorResponse('Cannot delete yourself', 'VALIDATION_ERROR', 400);
        }

        $userModel = new User();
        $targetUser = $userModel->findById($userId);
        if (!$targetUser) {
            errorResponse('User not found', 'NOT_FOUND', 404);
        }

        if ($targetUser['is_admin']) {
            errorResponse('Cannot delete an admin user', 'VALIDATION_ERROR', 400);
        }

        if ($userModel->delete($userId)) {
            successResponse(['user_id' => $userId], 'User deleted successfully');
        } else {
            errorResponse('Failed to delete user', 'ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

