<?php
require_once 'bootstrap.php';

use App\Services\AuthService;
use App\Models\Settings;

$authService = new AuthService();
$user = $authService->getCurrentUser();

if ($user) {
    header('Location: dashboard.php');
    exit;
}

$settingsModel = new Settings();
$settings = $settingsModel->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></title>
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <?= $settings['custom_head_tags'] ?? '' ?>
    <style><?= $settings['custom_css'] ?? '' ?></style>
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--color-primary, #1a73e8) 0%, var(--color-secondary, #e91e8c) 100%);
            position: relative;
            overflow: hidden;
        }

        .auth-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
        }

        .auth-card {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-2xl);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .auth-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }

        .auth-logo {
            font-size: var(--font-size-4xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            margin-bottom: var(--space-sm);
        }

        .auth-title {
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-text-primary);
            margin-bottom: var(--space-xs);
        }

        .auth-subtitle {
            color: var(--color-text-secondary);
            font-size: var(--font-size-base);
        }

        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--space-xs);
            color: var(--color-text-secondary);
            font-weight: var(--font-weight-medium);
            font-size: var(--font-size-sm);
        }

        .form-group input {
            width: 100%;
            padding: var(--space-md);
            background: var(--color-bg-secondary);
            border: 2px solid var(--color-bg-tertiary);
            border-radius: var(--radius-md);
            color: var(--color-text-primary);
            font-size: var(--font-size-base);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .password-requirements {
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
            margin-top: var(--space-xs);
            padding: var(--space-sm);
            background: var(--color-bg-tertiary);
            border-radius: var(--radius-sm);
        }

        .password-requirements ul {
            margin: var(--space-xs) 0 0 0;
            padding-left: var(--space-lg);
        }

        .btn-primary {
            width: 100%;
            padding: var(--space-md);
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: var(--space-md);
        }

        .btn-primary:hover {
            background: var(--color-primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 115, 232, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .auth-links {
            text-align: center;
            margin-top: var(--space-lg);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--color-bg-tertiary);
        }

        .auth-links a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: var(--font-weight-medium);
            transition: color 0.2s ease;
        }

        .auth-links a:hover {
            color: var(--color-primary-hover);
            text-decoration: underline;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-top: var(--space-md);
            display: none;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-top: var(--space-md);
            display: none;
        }

        .back-home {
            position: absolute;
            top: var(--space-lg);
            left: var(--space-lg);
            z-index: 2;
        }

        .back-home a {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            color: white;
            text-decoration: none;
            padding: var(--space-sm) var(--space-md);
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .back-home a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-3px);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-md);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="back-home">
            <a href="index.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                <span>Back to Home</span>
            </a>
        </div>

        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="auth-logo"><?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></div>
                    <h2 class="auth-title">Create Your Account</h2>
                    <p class="auth-subtitle">Join our community and start connecting</p>
                </div>

                <form id="registerForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="username" placeholder="Choose a unique username" minlength="3" maxlength="50">
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">3-50 characters, letters, numbers, and underscores only</small>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder="your.email@example.com">
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">We'll send a verification email (optional)</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Create a strong password" minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" required autocomplete="new-password" placeholder="Confirm your password">
                        </div>
                    </div>
                    <div class="password-requirements" id="passwordRequirements" style="display: none;">
                        <strong>Password requirements:</strong>
                        <ul>
                            <li>At least 6 characters long</li>
                            <li>Mix of letters and numbers recommended</li>
                        </ul>
                    </div>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Create Account</button>
                </form>

                <div id="errorMessage" class="error-message"></div>
                <div id="successMessage" class="success-message"></div>

                <div class="auth-links">
                    <p style="margin-bottom: var(--space-sm); color: var(--color-text-secondary);">
                        Already have an account? <a href="login.php">Login here</a>
                    </p>
                    <p style="margin: 0; color: var(--color-text-secondary);">
                        Or <a href="guest-login.php">try as guest</a> first
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordRequirements = document.getElementById('passwordRequirements');
        const submitBtn = document.getElementById('submitBtn');

        passwordInput.addEventListener('focus', () => {
            passwordRequirements.style.display = 'block';
        });

        passwordInput.addEventListener('input', () => {
            const password = passwordInput.value;
            if (password.length >= 6) {
                passwordInput.style.borderColor = 'var(--color-success, #22c55e)';
            } else {
                passwordInput.style.borderColor = 'var(--color-bg-tertiary)';
            }
        });

        confirmPasswordInput.addEventListener('input', () => {
            if (confirmPasswordInput.value !== passwordInput.value) {
                confirmPasswordInput.style.borderColor = '#ef4444';
            } else {
                confirmPasswordInput.style.borderColor = 'var(--color-success, #22c55e)';
            }
        });

        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            const errorDiv = document.getElementById('errorMessage');
            const successDiv = document.getElementById('successMessage');
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            // Validation
            if (password !== confirmPassword) {
                errorDiv.textContent = 'Passwords do not match!';
                errorDiv.style.display = 'block';
                return;
            }

            if (password.length < 6) {
                errorDiv.textContent = 'Password must be at least 6 characters long!';
                errorDiv.style.display = 'block';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                const result = await auth.register(username, email, password);
                if (result.success) {
                    successDiv.textContent = result.message || 'Account created successfully! Redirecting to login...';
                    successDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    errorDiv.textContent = result.error || 'Registration failed. Please try again.';
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Account';
                }
            } catch (error) {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Registration error:', error);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        });
    </script>
</body>
</html>
