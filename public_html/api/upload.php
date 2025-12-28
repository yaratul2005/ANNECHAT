<?php
require_once '../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Services\AuthService;
use App\Services\ValidationService;
use App\Config\Config;

CorsMiddleware::handle();

header('Content-Type: application/json');

// Ensure uncaught exceptions and shutdown errors return JSON so the client can parse responses
set_exception_handler(function ($e) {
    errorResponse('Server error: ' . $e->getMessage(), 'SERVER_ERROR', 500);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        // If output buffering has started, clear it to avoid partial responses
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Log detailed error for debugging
        // Always log shutdown errors
        error_log('Shutdown error in upload.php: ' . print_r($err, true));
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Server error', 'code' => 'SERVER_ERROR']);
        exit;
    }
});

$authService = new AuthService();
$user = $authService->getCurrentUser();

if (!$user) {
    errorResponse('Authentication required', 'UNAUTHORIZED', 401);
}

// Email verification is now optional - users can upload files without verification

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    errorResponse('No file uploaded or upload error', 'UPLOAD_ERROR', 400);
}

$uploadType = $_POST['type'] ?? 'message'; // 'message', 'profile', or 'post'

$file = $_FILES['file'];

// Debug: log uploaded file info to help diagnose malformed uploads
    debugLog('Upload received: name=' . ($file['name'] ?? '') . ' tmp=' . ($file['tmp_name'] ?? '') . ' size=' . ($file['size'] ?? '') . ' type=' . ($file['type'] ?? ''));

// Extend validation for videos and other files
$allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
$allowedFileTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
$maxFileSize = Config::maxFileSize(); // 5MB default

// Use finfo object to avoid calling finfo_close (deprecated in PHP 8.5)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
    debugLog('Detected MIME type: ' . $mimeType);

// If finfo returns a generic type for small files, prefer client-provided type when available
if ($mimeType === 'application/octet-stream' && !empty($file['type'])) {
    debugLog('Falling back to client MIME type: ' . $file['type']);
    $mimeType = $file['type'];
}

// Check file type
$allAllowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes, $allowedFileTypes);
if (!in_array($mimeType, $allAllowedTypes)) {
    errorResponse('Invalid file type. Allowed: Images (JPEG, PNG, GIF, WebP), Videos (MP4, WebM, OGG), Documents (PDF, DOC, DOCX, TXT)', 'INVALID_FILE_TYPE', 400);
}

// Determine file type
$fileType = 'file';
if (in_array($mimeType, $allowedImageTypes)) {
    $fileType = 'image';
} elseif (in_array($mimeType, $allowedVideoTypes)) {
    $fileType = 'video';
}

// Check file size
if ($file['size'] > $maxFileSize) {
    errorResponse('File size exceeds maximum allowed size (5MB)', 'FILE_TOO_LARGE', 400);
}

// Validate upload
$errors = ValidationService::validateImageUpload($file);
if (!empty($errors)) {
    // Log validation errors for easier debugging
    debugLog('Upload validation failed: ' . implode('; ', $errors));
    errorResponse(implode(', ', $errors), 'VALIDATION_ERROR', 400);
}

// Generate unique filename and determine upload directory based on type
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$prefix = 'msg_';
$subDir = 'messages';

if ($uploadType === 'profile') {
    $prefix = 'profile_';
    $subDir = 'profiles';
    // Only allow images for profile pictures
    if ($fileType !== 'image') {
        errorResponse('Profile pictures must be images', 'INVALID_FILE_TYPE', 400);
    }
} elseif ($uploadType === 'post') {
    $prefix = 'post_';
    $subDir = 'posts';
    // Allow images and videos for posts
    if ($fileType !== 'image' && $fileType !== 'video') {
        errorResponse('Posts can only contain images or videos', 'INVALID_FILE_TYPE', 400);
    }
}

$filename = uniqid($prefix, true) . '_' . time() . '.' . $extension;
$uploadDir = __DIR__ . '/../uploads/' . $subDir . '/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    // Add .htaccess for security
    file_put_contents($uploadDir . '.htaccess', "php_flag engine off\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\nOptions -ExecCGI");
}

$filePath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    errorResponse('Failed to save file', 'UPLOAD_ERROR', 500);
}

// Generate URL path
$fileUrl = '/uploads/' . $subDir . '/' . $filename;

successResponse([
    'url' => $fileUrl,
    'type' => $fileType,
    'name' => $file['name'],
    'size' => $file['size']
], 'File uploaded successfully');

