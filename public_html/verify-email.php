<?php
require_once 'bootstrap.php';

use App\Services\AuthService;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: index.php');
    exit;
}

$authService = new AuthService();
$result = $authService->verifyEmail($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Anne Chat</title>
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <div class="container">
        <main class="auth-container">
            <div class="auth-card">
                <?php if ($result['success']): ?>
                    <h2>Email Verified!</h2>
                    <p class="success-message"><?= htmlspecialchars($result['message']) ?></p>
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                <?php else: ?>
                    <h2>Verification Failed</h2>
                    <p class="error-message"><?= htmlspecialchars(implode(', ', $result['errors'] ?? ['Invalid token'])) ?></p>
                    <a href="index.php" class="btn btn-secondary">Go to Home</a>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

