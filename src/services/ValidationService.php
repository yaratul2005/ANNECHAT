<?php
declare(strict_types=1);

namespace App\Services;

class ValidationService {
    public static function validateUsername(string $username): array {
        $errors = [];

        if (empty(trim($username))) {
            $errors[] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters";
        } elseif (strlen($username) > 50) {
            $errors[] = "Username must not exceed 50 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, underscores, and hyphens";
        }

        return $errors;
    }

    public static function validateEmail(string $email): array {
        $errors = [];

        if (empty(trim($email))) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } elseif (strlen($email) > 100) {
            $errors[] = "Email must not exceed 100 characters";
        }

        return $errors;
    }

    public static function validatePassword(string $password): array {
        $errors = [];

        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return $errors;
    }

    public static function validateAge(int $age): array {
        $errors = [];

        if ($age < 13) {
            $errors[] = "You must be at least 13 years old";
        } elseif ($age > 150) {
            $errors[] = "Invalid age";
        }

        return $errors;
    }

    public static function validateMessage(?string $message): array {
        $errors = [];

        if ($message === null) {
            return $errors; // Allow null messages (for attachments only)
        }

        $trimmed = trim($message);
        if (empty($trimmed)) {
            // Empty is allowed if there's an attachment
            return $errors;
        } elseif (strlen($trimmed) > 1000) {
            $errors[] = "Message must not exceed 1000 characters";
        }

        return $errors;
    }

    public static function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateImageUpload(array $file): array {
        $errors = [];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error";
            return $errors;
        }

        // Allow images, videos, and common file types
        $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
        $allowedFileTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
        
        $allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes, $allowedFileTypes);
        
        // Use finfo object; avoid finfo_close() which is deprecated in PHP 8.5
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        // Some servers may detect small images as application/octet-stream.
        // Fall back to the client-provided MIME type if it's more specific.
        if ($mimeType === 'application/octet-stream' && !empty($file['type'])) {
            $mimeType = $file['type'];
        }

        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = "Invalid file type. Allowed: Images (JPEG, PNG, GIF, WebP), Videos (MP4, WebM, OGG), Documents (PDF, DOC, DOCX, TXT)";
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            $errors[] = "File size must not exceed 5MB";
        }

        return $errors;
    }
}

