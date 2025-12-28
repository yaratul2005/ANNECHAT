<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\SessionService;

class AuthMiddleware {
    private SessionService $sessionService;

    public function __construct() {
        $this->sessionService = new SessionService();
    }

    public function requireAuth(): bool {
        if (!$this->sessionService->isValid()) {
            $this->sendUnauthorizedResponse();
            return false;
        }
        return true;
    }

    public function requireGuest(): bool {
        if ($this->sessionService->isValid()) {
            $this->sendForbiddenResponse('Already logged in');
            return false;
        }
        return true;
    }

    public function requireVerified(): bool {
        if (!$this->sessionService->isValid()) {
            $this->sendUnauthorizedResponse();
            return false;
        }

        $userId = $this->sessionService->getUserId();
        if (!$userId) {
            $this->sendUnauthorizedResponse();
            return false;
        }

        $userModel = new \App\Models\User();
        $user = $userModel->findById($userId);

        if (!$user || !$user['is_verified']) {
            $this->sendForbiddenResponse('Email verification required');
            return false;
        }

        return true;
    }

    public function requireAdmin(): bool {
        if (!$this->sessionService->isValid()) {
            $this->sendUnauthorizedResponse();
            return false;
        }

        $userId = $this->sessionService->getUserId();
        if (!$userId) {
            $this->sendUnauthorizedResponse();
            return false;
        }

        $userModel = new \App\Models\User();
        $user = $userModel->findById($userId);

        if (!$user || !$user['is_admin']) {
            $this->sendForbiddenResponse('Admin access required');
            return false;
        }

        return true;
    }

    private function sendUnauthorizedResponse(): void {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }

    private function sendForbiddenResponse(string $message): void {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => 'FORBIDDEN'
        ]);
        exit;
    }
}

