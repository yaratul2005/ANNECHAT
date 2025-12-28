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
    <title>Guest Access - <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></title>
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
            line-height: 1.6;
        }

        .guest-info {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .guest-info-title {
            font-weight: var(--font-weight-bold);
            color: #fbbf24;
            margin-bottom: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .guest-info-text {
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
            line-height: 1.6;
        }

        .guest-info-text ul {
            margin: var(--space-xs) 0 0 var(--space-lg);
            padding: 0;
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
                    <h2 class="auth-title">Try as Guest</h2>
                    <p class="auth-subtitle">Explore without creating an account</p>
                </div>

                <div class="guest-info">
                    <div class="guest-info-title">
                        <span>ℹ️</span>
                        <span>Guest Access Info</span>
                    </div>
                    <div class="guest-info-text">
                        <p style="margin: 0 0 var(--space-xs) 0;">As a guest, you can:</p>
                        <ul>
                            <li>Chat with other users</li>
                            <li>View profiles and posts</li>
                            <li>React and comment on posts</li>
                        </ul>
                        <p style="margin: var(--space-xs) 0 0 0;"><strong>Note:</strong> Guest accounts have limited features. <a href="register.php" style="color: var(--color-primary);">Register</a> to unlock full access including profile editing and post creation.</p>
                    </div>
                </div>

                <form id="guestLoginForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="username" placeholder="Choose a username" minlength="3" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age" required min="13" max="150" placeholder="Your age">
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Must be 13 or older</small>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required style="width: 100%; padding: var(--space-md); background: var(--color-bg-secondary); border: 2px solid var(--color-bg-tertiary); border-radius: var(--radius-md); color: var(--color-text-primary); font-size: var(--font-size-base); transition: border-color 0.3s ease, box-shadow 0.3s ease; box-sizing: border-box;">
                            <option value="">Select gender</option>
                            <option value="male">Male ♂</option>
                            <option value="female">Female ♀</option>
                            <option value="other">Other ⚧</option>
                            <option value="prefer_not_to_say">Prefer not to say</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Continue as Guest</button>
                </form>

                <div id="errorMessage" class="error-message"></div>

                <div class="auth-links">
                    <p style="margin-bottom: var(--space-sm); color: var(--color-text-secondary);">
                        Want full access? <a href="register.php">Create an account</a>
                    </p>
                    <p style="margin: 0; color: var(--color-text-secondary);">
                        Already registered? <a href="login.php">Login here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script>
        document.getElementById('guestLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const age = parseInt(document.getElementById('age').value);
            const gender = document.getElementById('gender').value;
            
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.style.display = 'none';
            
            if (age < 13) {
                errorDiv.textContent = 'You must be at least 13 years old to use this service.';
                errorDiv.style.display = 'block';
                return;
            }

            if (!gender) {
                errorDiv.textContent = 'Please select your gender.';
                errorDiv.style.display = 'block';
                return;
            }

            try {
                const result = await auth.guestLogin(username, age, gender);
                if (result.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    errorDiv.textContent = result.error || 'Guest login failed. Please try again.';
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Guest login error:', error);
            }
        });
    </script>
</body>
</html>
