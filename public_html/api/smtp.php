<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Services\AuthService;
use App\Models\SmtpSettings;
use App\Services\EmailService;

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
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $smtpModel = new SmtpSettings();
        
        // Get existing settings to preserve password if not provided
        $existing = $smtpModel->get();
        if ($existing && (empty($input['password']) || !isset($input['password']))) {
            $input['password'] = $existing['password'];
        }

        if ($smtpModel->update($input)) {
            successResponse(null, 'SMTP settings updated successfully');
        } else {
            errorResponse('Failed to update SMTP settings', 'ERROR', 500);
        }
        break;

    case 'test':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $emailService = new EmailService();
        $result = $emailService->testConnection();
        
        if ($result['success']) {
            successResponse(null, $result['message']);
        } else {
            errorResponse($result['error'] ?? 'Test failed', 'ERROR', 500);
        }
        break;

    case 'send':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }

        $to = $input['to'] ?? '';
        $toName = $input['to_name'] ?? '';
        $subject = $input['subject'] ?? '';
        $body = $input['body'] ?? '';

        if (empty($to) || empty($subject) || empty($body)) {
            errorResponse('To, subject, and body are required', 'VALIDATION_ERROR', 400);
        }

        $emailService = new EmailService();
        $result = $emailService->sendEmail($to, $toName, $subject, $body);
        
        if ($result['success']) {
            successResponse(null, $result['message']);
        } else {
            errorResponse($result['error'] ?? 'Failed to send email', 'ERROR', 500);
        }
        break;

    default:
        errorResponse('Invalid action', 'INVALID_ACTION', 400);
}

