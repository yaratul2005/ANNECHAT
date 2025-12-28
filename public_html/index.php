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
    <title><?= htmlspecialchars($settings['meta_title'] ?? $settings['site_name'] ?? 'Anne Chat - Connect, Share, Chat') ?></title>
    <meta name="description" content="<?= htmlspecialchars($settings['meta_description'] ?? 'A modern social chat platform where you can connect with friends, share posts, and chat in real-time.') ?>">
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <?= $settings['custom_head_tags'] ?? '' ?>
    <style><?= $settings['custom_css'] ?? '' ?></style>
    <style>
        .landing-hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: var(--space-xl);
            background: linear-gradient(135deg, var(--color-primary, #1a73e8) 0%, var(--color-secondary, #e91e8c) 100%);
            position: relative;
            overflow: hidden;
        }

        .landing-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: var(--font-weight-bold, 700);
            color: white;
            margin-bottom: var(--space-lg);
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.8s ease;
        }

        .hero-subtitle {
            font-size: clamp(1.1rem, 2vw, 1.5rem);
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: var(--space-xl);
            line-height: 1.6;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .hero-description {
            font-size: clamp(1rem, 1.5vw, 1.2rem);
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: var(--space-2xl);
            line-height: 1.8;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin: var(--space-2xl) 0;
            animation: fadeInUp 0.8s ease 0.6s both;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }

        .feature-title {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: white;
            margin-bottom: var(--space-sm);
        }

        .feature-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: var(--font-size-base);
            line-height: 1.6;
        }

        .cta-section {
            margin-top: var(--space-2xl);
            animation: fadeInUp 0.8s ease 0.8s both;
        }

        .cta-buttons {
            display: flex;
            gap: var(--space-md);
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: var(--space-lg);
        }

        .btn-hero {
            padding: var(--space-md) var(--space-xl);
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
            border-radius: var(--radius-lg);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-hero-primary {
            background: white;
            color: var(--color-primary);
        }

        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-hero-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .btn-hero-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            transform: translateY(-2px);
        }

        .how-it-works {
            background: var(--color-bg-primary);
            padding: var(--space-2xl) var(--space-lg);
            margin-top: var(--space-2xl);
        }

        .how-it-works-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: var(--font-weight-bold);
            text-align: center;
            margin-bottom: var(--space-xl);
            color: var(--color-text-primary);
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-xl);
            margin-top: var(--space-xl);
        }

        .step-card {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .step-card:hover {
            transform: translateY(-5px);
        }

        .step-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
            margin: 0 auto var(--space-md);
        }

        .step-title {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            margin-bottom: var(--space-sm);
            color: var(--color-text-primary);
        }

        .step-text {
            color: var(--color-text-secondary);
            line-height: 1.6;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }

        .shape:nth-child(1) {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 150px;
            height: 150px;
            top: 60%;
            right: 10%;
            animation-delay: 5s;
        }

        .shape:nth-child(3) {
            width: 100px;
            height: 100px;
            bottom: 20%;
            left: 20%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
            }
            33% {
                transform: translate(30px, -30px) rotate(120deg);
            }
            66% {
                transform: translate(-20px, 20px) rotate(240deg);
            }
        }

        @media (max-width: 768px) {
            .cta-buttons {
                flex-direction: column;
            }

            .btn-hero {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="landing-hero">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        
        <div class="hero-content">
            <h1 class="hero-title"><?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></h1>
            <p class="hero-subtitle">Connect, Share, and Chat with Friends in Real-Time</p>
            <p class="hero-description">
                A modern social platform where you can create your profile, share moments with posts and media, 
                chat with friends instantly, and build meaningful connections. Join our community today!
            </p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3 class="feature-title">Real-Time Chat</h3>
                    <p class="feature-text">Chat with friends instantly. Send messages, images, videos, and files in real-time conversations.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👤</div>
                    <h3 class="feature-title">Personal Profiles</h3>
                    <p class="feature-text">Create your unique profile, share your story, and let others know more about you.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📸</div>
                    <h3 class="feature-title">Share Posts</h3>
                    <p class="feature-text">Share your thoughts, photos, and videos. Get reactions and comments from your network.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⭐</div>
                    <h3 class="feature-title">Interact & Engage</h3>
                    <p class="feature-text">React to posts, leave comments, and engage with your community in meaningful ways.</p>
                </div>
            </div>

            <div class="cta-section">
                <div class="cta-buttons">
                    <a href="register.php" class="btn-hero btn-hero-primary">
                        <span>🚀</span>
                        <span>Get Started Free</span>
                    </a>
                    <a href="login.php" class="btn-hero btn-hero-secondary">
                        <span>🔐</span>
                        <span>Login</span>
                    </a>
                    <a href="guest-login.php" class="btn-hero btn-hero-outline">
                        <span>👋</span>
                        <span>Try as Guest</span>
                    </a>
                </div>
                <p style="color: rgba(255, 255, 255, 0.8); font-size: var(--font-size-sm); margin-top: var(--space-md);">
                    No credit card required • Free forever • Join thousands of users
                </p>
            </div>
        </div>
    </div>

    <div class="how-it-works">
        <div class="how-it-works-content">
            <h2 class="section-title">How It Works</h2>
            <div class="steps">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Sign Up</h3>
                    <p class="step-text">Create your account in seconds. You can register with email or try as a guest to explore.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Build Your Profile</h3>
                    <p class="step-text">Add your photo, bio, and personal information. Make your profile stand out!</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Start Sharing</h3>
                    <p class="step-text">Share posts, photos, and videos. Express yourself and connect with others.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3 class="step-title">Chat & Connect</h3>
                    <p class="step-text">Send messages, react to posts, leave comments, and build your social network.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($settings['footer_enabled'] ?? true): ?>
    <footer class="site-footer" style="background: var(--color-bg-secondary); padding: var(--space-xl); text-align: center;">
        <div class="footer-content">
            <?php if (!empty($settings['footer_text'])): ?>
                <div class="footer-text" style="color: var(--color-text-secondary); margin-bottom: var(--space-md);">
                    <?= $settings['footer_text'] ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($settings['footer_copyright'])): ?>
                <div class="footer-copyright" style="color: var(--color-text-muted); font-size: var(--font-size-sm);">
                    <?= htmlspecialchars($settings['footer_copyright']) ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>
    <?php endif; ?>
</body>
</html>
