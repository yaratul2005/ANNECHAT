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
    <title>Login - <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></title>
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
            max-width: 450px;
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
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
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
                    <h2 class="auth-title">Welcome Back!</h2>
                    <p class="auth-subtitle">Login to continue to your account</p>
                </div>

                <form id="loginForm">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder="your.email@example.com">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>

                <div id="errorMessage" class="error-message"></div>
                <div id="successMessage" class="success-message"></div>

                <div class="auth-links">
                    <p style="margin-bottom: var(--space-sm); color: var(--color-text-secondary);">
                        Don't have an account? <a href="register.php">Sign up here</a>
                    </p>
                    <p style="margin: 0; color: var(--color-text-secondary);">
                        Or <a href="guest-login.php">continue as guest</a> to explore
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            const errorDiv = document.getElementById('errorMessage');
            const successDiv = document.getElementById('successMessage');
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            try {
                const result = await auth.login(email, password);
                if (result.success) {
                    successDiv.textContent = 'Login successful! Redirecting...';
                    successDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 500);
                } else {
                    errorDiv.textContent = result.error || 'Login failed. Please check your credentials.';
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Login error:', error);
            }
        });
    </script>
</body>
</html>
