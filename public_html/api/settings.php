<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Models\Settings;

CorsMiddleware::handle();

header('Content-Type: application/json');

$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->requireAdmin()) {
    exit;
}

$settingsModel = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = $settingsModel->get();
    successResponse(['settings' => $settings]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    
    $updateData = [];
    $allowedFields = [
        'site_name', 'site_description', 'meta_title', 'meta_description',
        'meta_keywords', 'custom_head_tags', 'custom_css', 'primary_color',
        'secondary_color', 'logo_url', 'favicon_url', 'footer_text',
        'footer_enabled', 'footer_copyright'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = $input[$field];
        }
    }

    if (empty($updateData)) {
        errorResponse('No valid fields to update', 'VALIDATION_ERROR', 400);
    }

    $settingsModel->update($updateData);
    $updatedSettings = $settingsModel->get();
    successResponse(['settings' => $updatedSettings], 'Settings updated');
} else {
    errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

