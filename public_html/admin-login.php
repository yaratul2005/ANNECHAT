<?php
require_once 'bootstrap.php';

use App\Services\AuthService;

$authService = new AuthService();
$user = $authService->getCurrentUser();

if ($user && $user['is_admin']) {
    header('Location: admin-dashboard.php');
    exit;
} elseif ($user) {
    header('Location: dashboard.php');
    exit;
}

// Only allow access if coming from /admin path or direct access
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/admin') === false && strpos($referer, '/admin') === false && !isset($_GET['admin'])) {
    // Allow direct access but log it
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Anne Chat</title>
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <div class="container">
        <main class="auth-container">
            <div class="auth-card">
                <h2>Admin Login</h2>
                <p class="auth-note">Administrative access only</p>
                <form id="adminLoginForm">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
                <p class="auth-link">
                    <a href="index.php">Back to Home</a>
                </p>
                <div id="errorMessage" class="error-message" style="display: none;"></div>
            </div>
        </main>
    </div>
    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script>
        document.getElementById('adminLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            const result = await auth.login(email, password);
            if (result.success) {
                if (result.user && result.user.is_admin) {
                    window.location.href = 'admin-dashboard.php';
                } else {
                    showError('Access denied. Admin privileges required.');
                }
            } else {
                showError(result.error || 'Login failed');
            }
        });
        
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>

