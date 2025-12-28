<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\OnlineStatus;

class AuthService {
    private User $userModel;
    private ActivityLog $activityLog;
    private OnlineStatus $onlineStatus;
    private SessionService $sessionService;
    private EmailService $emailService;

    public function __construct() {
        $this->userModel = new User();
        $this->activityLog = new ActivityLog();
        $this->onlineStatus = new OnlineStatus();
        $this->sessionService = new SessionService();
        $this->emailService = new EmailService();
    }

    public function register(array $data): array {
        $errors = [];

        // Validate input
        $errors = array_merge($errors, ValidationService::validateUsername($data['username'] ?? ''));
        $errors = array_merge($errors, ValidationService::validateEmail($data['email'] ?? ''));
        $errors = array_merge($errors, ValidationService::validatePassword($data['password'] ?? ''));

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $username = trim($data['username']);
        $email = strtolower(trim($data['email']));

        // Check if user exists
        if ($this->userModel->exists($username, $email)) {
            return ['success' => false, 'errors' => ['Username or email already exists']];
        }

        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => Config::bcryptCost()]);

        // Create user
        $userId = $this->userModel->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'is_verified' => false,
            'verification_token' => $token,
            'verification_token_expires' => $tokenExpires
        ]);

        // Send verification email
        $this->emailService->sendVerificationEmail($email, $username, $token);

        // Log activity
        $this->activityLog->create($userId, 'user_registered', "User registered: {$username}", $this->getIpAddress(), $this->getUserAgent());

        return ['success' => true, 'user_id' => $userId, 'message' => 'Registration successful! You can log in now. Check your email to verify your account for additional features.'];
    }

    public function login(string $email, string $password): array {
        $errors = [];

        if (empty($email) || empty($password)) {
            return ['success' => false, 'errors' => ['Email and password are required']];
        }

        $user = $this->userModel->findByEmail(strtolower(trim($email)));

        // Debugging: log whether user was found and a hash snippet (do not log password)
        if (!$user) {
            debugLog("Login: user not found for email={" . strtolower(trim($email)) . "}");
        } else {
            // For privacy, do not log password hashes. Log only id and email.
            debugLog("Login: user found id={$user['id']} email={$user['email']}");
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->activityLog->create(null, 'login_failed', "Failed login attempt: {$email}", $this->getIpAddress(), $this->getUserAgent());
            return ['success' => false, 'errors' => ['Invalid email or password']];
        }

        // Check if user is banned
        if (!empty($user['is_banned']) && $user['is_banned']) {
            $this->activityLog->create($user['id'], 'login_blocked', "Banned user attempted to login: {$email}", $this->getIpAddress(), $this->getUserAgent());
            return ['success' => false, 'errors' => ['Your account has been banned. Please contact an administrator.']];
        }

        // Email verification is now optional - users can login without verification
        // They can verify their email later from the dashboard

        // Update user's last IP address
        $ipAddress = $this->getIpAddress();
        $this->userModel->updateLastIp($user['id'], $ipAddress ?? '0.0.0.0');

        // Create session
        $this->sessionService->create($user['id'], $ipAddress, $this->getUserAgent());
        $this->sessionService->regenerateId();

        // Update online status
        $this->onlineStatus->update($user['id'], 'online');

        // Log activity
        $this->activityLog->create($user['id'], 'user_logged_in', "User logged in: {$user['username']}", $this->getIpAddress(), $this->getUserAgent());

        unset($user['password_hash'], $user['verification_token'], $user['password_reset_token']);
        return ['success' => true, 'user' => $user];
    }

    public function guestLogin(string $username, int $age, ?string $gender = null): array {
        $errors = [];

        $errors = array_merge($errors, ValidationService::validateUsername($username));
        $errors = array_merge($errors, ValidationService::validateAge($age));

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Validate gender if provided
        if ($gender !== null && !in_array($gender, ['male', 'female', 'other', 'prefer_not_to_say'])) {
            $errors[] = 'Invalid gender selection';
            return ['success' => false, 'errors' => $errors];
        }

        // Check if guest user exists
        $user = $this->userModel->findByUsername($username);
        
        $userData = [
            'username' => trim($username),
            'age' => $age,
            'is_guest' => true,
            'is_verified' => false
        ];
        
        if ($gender !== null) {
            $userData['gender'] = $gender;
        }
        
        if (!$user || !$user['is_guest']) {
            // Create guest user
            $userId = $this->userModel->create($userData);
            $user = $this->userModel->findById($userId);
        } else {
            // Update existing guest user with new data
            $this->userModel->update($user['id'], $userData);
            $user = $this->userModel->findById($user['id']);
        }

        // Update user's last IP address
        $ipAddress = $this->getIpAddress();
        $this->userModel->updateLastIp($user['id'], $ipAddress ?? '0.0.0.0');

        // Create session (24 hour expiration for guests)
        $this->sessionService->create($user['id'], $ipAddress, $this->getUserAgent());

        // Update online status
        $this->onlineStatus->update($user['id'], 'online');

        // Log activity
        $this->activityLog->create($user['id'], 'guest_logged_in', "Guest logged in: {$user['username']}", $this->getIpAddress(), $this->getUserAgent());

        unset($user['password_hash']);
        return ['success' => true, 'user' => $user];
    }

    public function verifyEmail(string $token): array {
        $user = $this->userModel->findByVerificationToken($token);

        if (!$user) {
            return ['success' => false, 'errors' => ['Invalid or expired verification token']];
        }

        $this->userModel->verify($user['id']);

        // Log activity
        $this->activityLog->create($user['id'], 'email_verified', "Email verified: {$user['username']}", $this->getIpAddress(), $this->getUserAgent());

        return ['success' => true, 'message' => 'Email verified successfully!'];
    }

    public function resendVerificationEmail(int $userId): array {
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            return ['success' => false, 'errors' => ['User not found']];
        }

        if ($user['is_verified']) {
            return ['success' => false, 'errors' => ['Email is already verified']];
        }

        if (empty($user['email'])) {
            return ['success' => false, 'errors' => ['No email address found']];
        }

        // Generate new verification token
        $token = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours

        // Update user with new token
        $this->userModel->update($userId, [
            'verification_token' => $token,
            'verification_token_expires' => $tokenExpires
        ]);

        // Send verification email
        $emailResult = $this->emailService->sendVerificationEmail($user['email'], $user['username'], $token);
        
        // Check if email was sent successfully
        if (!$emailResult['success']) {
            return [
                'success' => false, 
                'errors' => [$emailResult['error'] ?? 'Failed to send verification email. Please check SMTP settings.']
            ];
        }

        // Log activity
        $this->activityLog->create($userId, 'verification_email_resent', "Verification email resent: {$user['username']}", $this->getIpAddress(), $this->getUserAgent());

        return ['success' => true, 'message' => 'Verification email sent successfully. Please check your inbox.'];
    }

    public function logout(): void {
        $userId = $this->sessionService->getUserId();
        
        if ($userId) {
            $this->onlineStatus->setOffline($userId);
            $this->activityLog->create($userId, 'user_logged_out', "User logged out", $this->getIpAddress(), $this->getUserAgent());
        }

        $this->sessionService->destroy();
    }

    public function getCurrentUser(): ?array {
        $userId = $this->sessionService->getUserId();
        if (!$userId) {
            return null;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            return null;
        }

        // Check if user is banned - if so, destroy session and return null
        if (!empty($user['is_banned']) && $user['is_banned']) {
            $this->sessionService->destroy();
            return null;
        }

        unset($user['password_hash'], $user['verification_token'], $user['password_reset_token']);
        return $user;
    }

    private function getIpAddress(): ?string {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function getUserAgent(): ?string {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}

