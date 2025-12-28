<?php
require_once 'bootstrap.php';

use App\Services\AuthService;
use App\Models\Settings;
use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use App\Models\PostReaction;
use App\Models\FriendRequest;

$authService = new AuthService();
$currentUser = $authService->getCurrentUser();

if (!$currentUser) {
    header('Location: index.php');
    exit;
}

// Block guest users from accessing profile pages
if ($currentUser['is_guest']) {
    // Show modal instead of redirecting
    // We'll handle this in the page itself
}

$settingsModel = new Settings();
$settings = $settingsModel->get();

// Get profile user (can be current user or another user)
$userModel = new \App\Models\User();
$profileUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $currentUser['id'];
$user = $userModel->findById($profileUserId);

if (!$user) {
    header('Location: profile.php');
    exit;
}

$isOwnProfile = $user['id'] === $currentUser['id'];
$canEdit = $isOwnProfile && !$currentUser['is_guest'] && ($currentUser['is_verified'] || empty($currentUser['email']));
$isGuest = $currentUser['is_guest'];

// Get user's posts
$postModel = new Post();
$posts = $postModel->getByUserId($user['id'], 50, 0);
$postCount = $postModel->countByUserId($user['id']);

// Get friends list (only for registered users, not guests)
$friends = [];
$friendRequestStatus = null;
if (!$currentUser['is_guest'] && !$user['is_guest']) {
    $friendRequestModel = new FriendRequest();
    $friends = $friendRequestModel->getFriends($user['id']);
    
    if (!$isOwnProfile) {
        // Check friend request status
        $areFriends = $friendRequestModel->areFriends($currentUser['id'], $user['id']);
        $sentRequest = $friendRequestModel->getRequestBetween($currentUser['id'], $user['id']);
        $receivedRequest = $friendRequestModel->getRequestBetween($user['id'], $currentUser['id']);
        
        if ($areFriends) {
            $friendRequestStatus = 'friends';
        } elseif ($sentRequest) {
            $friendRequestStatus = 'sent';
        } elseif ($receivedRequest) {
            $friendRequestStatus = 'received';
            $friendRequestStatusData = $receivedRequest;
        } else {
            $friendRequestStatus = 'none';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['fullname'] ?? $user['username']) ?> - Profile - <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></title>
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <?= $settings['custom_head_tags'] ?? '' ?>
    <style><?= $settings['custom_css'] ?? '' ?></style>
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-lg);
        }

        .profile-header {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-lg);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-cover {
            height: 300px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            position: relative;
        }

        .profile-info {
            padding: var(--space-xl);
            position: relative;
            margin-top: -80px;
        }

        .profile-avatar-section {
            display: flex;
            align-items: flex-end;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .profile-avatar-large {
            width: 160px;
            height: 160px;
            border-radius: var(--radius-full);
            border: 4px solid var(--color-bg-card);
            background: var(--color-bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--color-text-primary);
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            border-radius: var(--radius-full);
            object-fit: cover;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
        }

        .profile-name-section {
            flex: 1;
        }

        .profile-fullname {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-text-primary);
            margin-bottom: var(--space-xs);
        }

        .profile-username {
            font-size: var(--font-size-base);
            color: var(--color-text-secondary);
            margin-bottom: var(--space-sm);
        }

        .profile-stats {
            display: flex;
            gap: var(--space-xl);
            margin-top: var(--space-md);
        }

        .profile-stat {
            display: flex;
            flex-direction: column;
        }

        .profile-stat-value {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-text-primary);
        }

        .profile-stat-label {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .profile-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: var(--space-lg);
        }

        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }

        .profile-card {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-card h3 {
            margin: 0 0 var(--space-md) 0;
            font-size: var(--font-size-xl);
            color: var(--color-text-primary);
        }

        .profile-info-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--color-bg-tertiary);
        }

        .profile-info-item:last-child {
            border-bottom: none;
        }

        .profile-info-label {
            font-weight: var(--font-weight-medium);
            color: var(--color-text-secondary);
            min-width: 100px;
        }

        .profile-info-value {
            color: var(--color-text-primary);
        }

        .posts-feed {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }

        .create-post-card {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .create-post-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .create-post-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            object-fit: cover;
        }

        .create-post-input {
            flex: 1;
            padding: var(--space-md);
            background: var(--color-bg-secondary);
            border: 1px solid var(--color-bg-tertiary);
            border-radius: var(--radius-md);
            color: var(--color-text-primary);
            font-size: var(--font-size-base);
            resize: none;
            min-height: 100px;
        }

        .create-post-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-md);
        }

        .post-item {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .post-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .post-avatar {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-full);
            object-fit: cover;
        }

        .post-author {
            flex: 1;
        }

        .post-author-name {
            font-weight: var(--font-weight-bold);
            color: var(--color-text-primary);
        }

        .post-time {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .post-content {
            margin: var(--space-md) 0;
            color: var(--color-text-primary);
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .post-media {
            margin: var(--space-md) 0;
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .post-media img,
        .post-media video {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
        }

        .post-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: var(--space-md);
            padding-top: var(--space-md);
            border-top: 1px solid var(--color-bg-tertiary);
        }

        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }

            .profile-avatar-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .profile-cover {
                height: 200px;
            }
        }
    </style>
    <?php if ($isGuest): ?>
    <style>
        .profile-container {
            display: none;
        }
        .guest-limit-modal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
        }
        .guest-limit-modal-content {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-2xl);
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .guest-limit-icon {
            font-size: 4rem;
            margin-bottom: var(--space-lg);
        }
        .guest-limit-title {
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-text-primary);
            margin-bottom: var(--space-md);
        }
        .guest-limit-text {
            color: var(--color-text-secondary);
            margin-bottom: var(--space-lg);
            line-height: 1.6;
        }
        .guest-limit-features {
            text-align: left;
            background: var(--color-bg-secondary);
            padding: var(--space-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
        }
        .guest-limit-features h4 {
            color: var(--color-text-primary);
            margin-bottom: var(--space-md);
            text-align: center;
        }
        .guest-limit-features ul {
            list-style: none;
            padding: 0;
        }
        .guest-limit-features li {
            padding: var(--space-sm) 0;
            color: var(--color-text-secondary);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        .guest-limit-features li:before {
            content: '❌';
            font-size: var(--font-size-lg);
        }
        .guest-limit-actions {
            display: flex;
            gap: var(--space-md);
            justify-content: center;
        }
        .btn-register {
            padding: var(--space-md) var(--space-xl);
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            background: var(--color-primary-hover);
            transform: translateY(-2px);
        }
        .btn-back {
            padding: var(--space-md) var(--space-xl);
            background: var(--color-bg-tertiary);
            color: var(--color-text-primary);
            border: 2px solid var(--color-bg-tertiary);
            border-radius: var(--radius-md);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-medium);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: var(--color-bg-secondary);
            border-color: var(--color-primary);
        }
        
        @media (max-width: 768px) {
            .guest-limit-modal {
                padding: var(--space-md);
            }
            .guest-limit-modal-content {
                padding: var(--space-lg);
                max-width: 100%;
                margin: var(--space-md);
            }
            .guest-limit-icon {
                font-size: 3rem;
                margin-bottom: var(--space-md);
            }
            .guest-limit-title {
                font-size: var(--font-size-xl);
                margin-bottom: var(--space-sm);
            }
            .guest-limit-text {
                font-size: var(--font-size-sm);
                margin-bottom: var(--space-md);
            }
            .guest-limit-features {
                padding: var(--space-md);
                margin-bottom: var(--space-md);
            }
            .guest-limit-features h4 {
                font-size: var(--font-size-base);
                margin-bottom: var(--space-sm);
            }
            .guest-limit-features li {
                font-size: var(--font-size-sm);
                padding: var(--space-xs) 0;
            }
            .guest-limit-actions {
                flex-direction: column;
                gap: var(--space-sm);
            }
            .btn-register,
            .btn-back {
                width: 100%;
                padding: var(--space-sm) var(--space-md);
                font-size: var(--font-size-sm);
            }
        }
        
        @media (max-width: 480px) {
            .guest-limit-modal {
                padding: var(--space-sm);
            }
            .guest-limit-modal-content {
                padding: var(--space-md);
                margin: var(--space-sm);
            }
            .guest-limit-icon {
                font-size: 2.5rem;
            }
            .guest-limit-title {
                font-size: var(--font-size-lg);
            }
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if ($isGuest): ?>
    <!-- Guest User Limitation Modal -->
    <div class="guest-limit-modal" id="guestLimitModal">
        <div class="guest-limit-modal-content">
            <div class="guest-limit-icon">🔒</div>
            <h2 class="guest-limit-title">Profile Access Restricted</h2>
            <p class="guest-limit-text">
                As a guest user, you have limited access to certain features. To view and edit profiles, you need to create a registered account.
            </p>
            <div class="guest-limit-features">
                <h4>Guest Account Limitations:</h4>
                <ul>
                    <li>Cannot view profile pages</li>
                    <li>Cannot edit your profile</li>
                    <li>Cannot create posts</li>
                    <li>Cannot upload profile pictures</li>
                    <li>Limited access to advanced features</li>
                </ul>
            </div>
            <div class="guest-limit-features" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
                <h4 style="color: #22c55e;">Unlock Full Access:</h4>
                <ul>
                    <li style="color: var(--color-text-primary);">✅ View and edit your profile</li>
                    <li style="color: var(--color-text-primary);">✅ Create and share posts</li>
                    <li style="color: var(--color-text-primary);">✅ Upload profile pictures</li>
                    <li style="color: var(--color-text-primary);">✅ Access all features</li>
                </ul>
            </div>
            <div class="guest-limit-actions">
                <a href="register.php" class="btn-register">Create Free Account</a>
                <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="profile-container">
        <!-- Navigation -->
        <div style="margin-bottom: var(--space-lg);">
            <a href="dashboard.php" class="btn" style="display: inline-flex; align-items: center; gap: var(--space-sm);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Chat
            </a>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-cover"></div>
            <div class="profile-info">
                <div class="profile-avatar-section">
                    <div class="profile-avatar-large" id="profileAvatarDisplay">
                        <?php if (!empty($user['profile_picture']) && $user['profile_picture'] !== 'undefined'): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user['fullname'] ?? $user['username']) ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block; position: absolute; top: 0; left: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <span style="display: none; align-items: center; justify-content: center; width: 100%; height: 100%;"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                        <?php else: ?>
                            <span style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name-section">
                        <h1 class="profile-fullname" id="profileFullnameDisplay"><?= htmlspecialchars($user['fullname'] ?? $user['username']) ?></h1>
                        <div class="profile-username">@<?= htmlspecialchars($user['username']) ?></div>
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <span class="profile-stat-value"><?= $postCount ?></span>
                                <span class="profile-stat-label">Posts</span>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <?php if ($isOwnProfile): ?>
                                <?php if ($canEdit): ?>
                                    <button class="btn btn-primary" onclick="openEditProfileModal()">Edit Profile</button>
                                <?php else: ?>
                                    <?php if ($currentUser['is_guest']): ?>
                                        <div style="padding: var(--space-md); background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: var(--radius-md); color: #fbbf24;">
                                            <p style="margin: 0 0 var(--space-sm) 0;">Guest users cannot edit their profile.</p>
                                            <a href="register.php" class="btn btn-primary btn-sm">Register to Edit Profile</a>
                                        </div>
                                    <?php elseif (!$currentUser['is_verified']): ?>
                                        <div style="padding: var(--space-md); background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: var(--radius-md); color: #fbbf24;">
                                            <p style="margin: 0 0 var(--space-sm) 0;">Please verify your email to edit your profile.</p>
                                            <button class="btn btn-primary btn-sm" onclick="resendVerificationEmail()">Resend Verification Email</button>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="display: flex; gap: var(--space-md); flex-wrap: wrap;">
                                    <button class="btn btn-secondary" onclick="window.location.href='dashboard.php?user_id=<?= $user['id'] ?>'">Send Message</button>
                                    <?php if (!$currentUser['is_guest'] && !$user['is_guest']): ?>
                                        <div id="friendRequestContainer">
                                            <?php if ($friendRequestStatus === 'none'): ?>
                                                <button class="btn btn-primary" id="sendFriendRequestBtn" onclick="sendFriendRequest(<?= $user['id'] ?>)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="8.5" cy="7" r="4"></circle>
                                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                                    </svg>
                                                    Add Friend
                                                </button>
                                            <?php elseif ($friendRequestStatus === 'sent'): ?>
                                                <button class="btn btn-secondary" disabled>
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                                        <polyline points="20 6 9 17 4 12"></polyline>
                                                    </svg>
                                                    Request Sent
                                                </button>
                                            <?php elseif ($friendRequestStatus === 'received'): ?>
                                                <?php 
                                                $receivedRequest = $friendRequestModel->getRequestBetween($user['id'], $currentUser['id']);
                                                $requestId = $receivedRequest ? $receivedRequest['id'] : 0;
                                                ?>
                                                <div style="display: flex; gap: var(--space-sm);">
                                                    <button class="btn btn-primary" onclick="acceptFriendRequest(<?= $requestId ?>)">Accept</button>
                                                    <button class="btn btn-secondary" onclick="rejectFriendRequest(<?= $requestId ?>)">Decline</button>
                                                </div>
                                            <?php elseif ($friendRequestStatus === 'friends'): ?>
                                                <button class="btn btn-secondary" id="removeFriendBtn" onclick="removeFriend(<?= $user['id'] ?>)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="8.5" cy="7" r="4"></circle>
                                                        <line x1="18" y1="8" x2="23" y2="13"></line>
                                                        <line x1="23" y1="8" x2="18" y2="13"></line>
                                                    </svg>
                                                    Friends
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-card">
                    <h3>About</h3>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Email:</span>
                        <span class="profile-info-value"><?= htmlspecialchars($user['email'] ?? 'Not provided') ?></span>
                    </div>
                    <?php if ($user['age']): ?>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Age:</span>
                        <span class="profile-info-value"><?= htmlspecialchars($user['age']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($user['gender']): ?>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Gender:</span>
                        <span class="profile-info-value"><?= ucfirst(htmlspecialchars($user['gender'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($user['bio']): ?>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Bio:</span>
                        <span class="profile-info-value"><?= nl2br(htmlspecialchars($user['bio'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$user['is_guest'] && count($friends) > 0): ?>
                <div class="profile-card" style="margin-top: var(--space-lg);">
                    <h3>Friends (<?= count($friends) ?>)</h3>
                    <div class="friends-grid" id="friendsGrid">
                        <?php foreach (array_slice($friends, 0, 9) as $friend): ?>
                        <div class="friend-item" onclick="window.location.href='profile.php?user_id=<?= $friend['id'] ?>'">
                            <div class="friend-avatar">
                                <?php if ($friend['profile_picture']): ?>
                                    <img src="<?= htmlspecialchars($friend['profile_picture']) ?>" alt="<?= htmlspecialchars($friend['fullname'] ?? $friend['username']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="friend-avatar-placeholder" style="display: none;"><?= strtoupper(substr($friend['username'], 0, 1)) ?></div>
                                <?php else: ?>
                                    <div class="friend-avatar-placeholder"><?= strtoupper(substr($friend['username'], 0, 1)) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="friend-name"><?= htmlspecialchars($friend['fullname'] ?? $friend['username']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($friends) > 9): ?>
                    <button class="btn btn-secondary" style="width: 100%; margin-top: var(--space-md);" onclick="showAllFriends()">See All Friends</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Posts Feed -->
            <div class="posts-feed">
                <!-- Create Post (only for own profile) -->
                <?php if ($isOwnProfile): ?>
                <div class="create-post-card">
                    <div class="create-post-header">
                        <img src="<?= htmlspecialchars($currentUser['profile_picture'] ?? '') ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="create-post-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-bg-tertiary); display: none; align-items: center; justify-content: center; font-weight: bold;">
                            <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: bold;"><?= htmlspecialchars($currentUser['fullname'] ?? $currentUser['username']) ?></div>
                        </div>
                    </div>
                    <textarea id="postContent" class="create-post-input" placeholder="What's on your mind?"></textarea>
                    <div id="postMediaPreview" style="margin-top: var(--space-md); display: none;">
                        <img id="postMediaPreviewImg" style="max-width: 100%; max-height: 300px; border-radius: var(--radius-md);" />
                        <video id="postMediaPreviewVideo" style="max-width: 100%; max-height: 300px; border-radius: var(--radius-md); display: none;" controls></video>
                        <button onclick="clearPostMedia()" style="margin-top: var(--space-sm);" class="btn btn-secondary btn-sm">Remove Media</button>
                    </div>
                    <div class="create-post-actions">
                        <label class="btn btn-secondary" style="cursor: pointer;">
                            <input type="file" id="postMediaInput" accept="image/*,video/*" style="display: none;" onchange="handlePostMediaSelect(event)">
                            📷 Photo/Video
                        </label>
                        <button class="btn btn-primary" onclick="createPost()">Post</button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Posts List -->
                <div id="postsList">
                    <?php if (empty($posts)): ?>
                        <div class="profile-card" style="text-align: center; padding: var(--space-xl);">
                            <p style="color: var(--color-text-secondary);">No posts yet. Share something!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $commentModel = new \App\Models\Comment();
                        $reactionModel = new \App\Models\PostReaction();
                        foreach ($posts as $post): 
                            $comments = $commentModel->getByPostId($post['id']);
                            $starCount = $reactionModel->getCount($post['id'], 'star');
                            $hasStarred = $reactionModel->hasReacted($post['id'], $currentUser['id'], 'star');
                        ?>
                            <div class="post-item" data-post-id="<?= $post['id'] ?>">
                                <div class="post-header">
                                    <img src="<?= htmlspecialchars($post['profile_picture'] ?? '') ?>" alt="<?= htmlspecialchars($post['fullname'] ?? $post['username']) ?>" class="post-avatar" onerror="this.style.display='none';">
                                    <div class="post-author">
                                        <div class="post-author-name">
                                            <a href="profile.php?user_id=<?= $post['user_id'] ?>" style="color: inherit; text-decoration: none;">
                                                <?= htmlspecialchars($post['fullname'] ?? $post['username']) ?>
                                            </a>
                                        </div>
                                        <div class="post-time"><?= date('F j, Y g:i A', strtotime($post['created_at'])) ?></div>
                                    </div>
                                    <?php if ($post['user_id'] == $currentUser['id']): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deletePost(<?= $post['id'] ?>)">Delete</button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($post['content']): ?>
                                <div class="post-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                                <?php endif; ?>
                                <?php if ($post['media_url']): ?>
                                <div class="post-media">
                                    <?php if ($post['media_type'] === 'image'): ?>
                                        <img src="<?= htmlspecialchars($post['media_url']) ?>" alt="Post image" onclick="openImageModal(this.src)" style="cursor: pointer;">
                                    <?php elseif ($post['media_type'] === 'video'): ?>
                                        <video src="<?= htmlspecialchars($post['media_url']) ?>" controls></video>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Post Actions -->
                                <div class="post-actions-bar" style="display: flex; align-items: center; gap: var(--space-lg); padding: var(--space-md) 0; border-top: 1px solid var(--color-bg-tertiary); margin-top: var(--space-md);">
                                    <button class="btn-reaction <?= $hasStarred ? 'active' : '' ?>" onclick="toggleStar(<?= $post['id'] ?>)" style="display: flex; align-items: center; gap: var(--space-xs); background: none; border: none; color: var(--color-text-secondary); cursor: pointer; padding: var(--space-xs) var(--space-sm); border-radius: var(--radius-md); transition: all 0.2s;">
                                        <span style="font-size: 1.2rem;"><?= $hasStarred ? '⭐' : '☆' ?></span>
                                        <span id="starCount-<?= $post['id'] ?>"><?= $starCount ?></span>
                                    </button>
                                    <button class="btn-comment" onclick="toggleComments(<?= $post['id'] ?>)" style="display: flex; align-items: center; gap: var(--space-xs); background: none; border: none; color: var(--color-text-secondary); cursor: pointer; padding: var(--space-xs) var(--space-sm); border-radius: var(--radius-md);">
                                        💬 <span id="commentCount-<?= $post['id'] ?>"><?= count($comments) ?></span>
                                    </button>
                                </div>
                                
                                <!-- Comments Section -->
                                <div id="comments-<?= $post['id'] ?>" class="comments-section" style="display: none; margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--color-bg-tertiary);">
                                    <div class="comments-list" id="commentsList-<?= $post['id'] ?>">
                                        <?php foreach ($comments as $comment): ?>
                                            <div class="comment-item" style="display: flex; gap: var(--space-sm); margin-bottom: var(--space-md);" data-comment-id="<?= $comment['id'] ?>">
                                                <img src="<?= htmlspecialchars($comment['profile_picture'] ?? '') ?>" alt="<?= htmlspecialchars($comment['fullname'] ?? $comment['username']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none';">
                                                <div style="flex: 1;">
                                                    <div style="font-weight: bold; color: var(--color-text-primary); margin-bottom: var(--space-xs);">
                                                        <?= htmlspecialchars($comment['fullname'] ?? $comment['username']) ?>
                                                    </div>
                                                    <div style="color: var(--color-text-primary);"><?= nl2br(htmlspecialchars($comment['content'])) ?></div>
                                                    <div style="font-size: var(--font-size-sm); color: var(--color-text-secondary); margin-top: var(--space-xs);">
                                                        <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                                    </div>
                                                </div>
                                                <?php if ($comment['user_id'] == $currentUser['id']): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteComment(<?= $comment['id'] ?>, <?= $post['id'] ?>)">Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="comment-input" style="display: flex; gap: var(--space-sm); margin-top: var(--space-md);">
                                        <input type="text" id="commentInput-<?= $post['id'] ?>" placeholder="Write a comment..." style="flex: 1; padding: var(--space-sm) var(--space-md); background: var(--color-bg-secondary); border: 1px solid var(--color-bg-tertiary); border-radius: var(--radius-md); color: var(--color-text-primary);">
                                        <button class="btn btn-primary btn-sm" onclick="addComment(<?= $post['id'] ?>)">Comment</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="modal-close" onclick="closeEditProfileModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm">
                    <div class="form-group">
                        <label for="edit-fullname">Full Name</label>
                        <input type="text" id="edit-fullname" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" placeholder="Enter your full name">
                    </div>
                    <div class="form-group">
                        <label for="edit-username">Username</label>
                        <input type="text" id="edit-username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                        <div class="form-group">
                            <label for="edit-age">Age</label>
                            <input type="number" id="edit-age" name="age" value="<?= htmlspecialchars($user['age'] ?? '') ?>" min="1" max="150">
                        </div>
                        <div class="form-group">
                            <label for="edit-gender">Gender</label>
                            <select id="edit-gender" name="gender">
                                <option value="">Select...</option>
                                <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                <option value="prefer_not_to_say" <?= ($user['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-bio">Bio</label>
                        <textarea id="edit-bio" name="bio" rows="4" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit-profile-picture">Profile Picture</label>
                        <input type="file" id="edit-profile-picture-file" accept="image/*" onchange="handleProfilePictureSelect(event)">
                        <input type="hidden" id="edit-profile-picture" name="profile_picture" value="<?= htmlspecialchars($user['profile_picture'] ?? '') ?>">
                        <div id="profilePicturePreview" style="margin-top: var(--space-sm);">
                            <?php if ($user['profile_picture']): ?>
                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile" style="max-width: 200px; max-height: 200px; border-radius: var(--radius-md);">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled style="background: var(--color-bg-tertiary); opacity: 0.6;">
                        <small style="color: var(--color-text-secondary);">Email cannot be changed</small>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditProfileModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" style="display: none;" onclick="closeImageModal()">
        <div class="modal-content" style="max-width: 90%; max-height: 90vh; background: transparent; box-shadow: none;">
            <img id="imageModalImg" src="" style="max-width: 100%; max-height: 90vh; border-radius: var(--radius-md);">
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        // Profile editing
        function openEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Profile picture upload
        async function handleProfilePictureSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Image size must be less than 5MB');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'profile');

            try {
                const response = await fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                const data = await response.json();
                console.log('Upload response:', data);
                
                if (data.success) {
                    // The API wraps response in 'data' object: {success: true, data: {url: '...'}}
                    const imageUrl = data.data?.url || '';
                    
                    if (!imageUrl) {
                        console.error('No URL in response:', data);
                        alert('Upload successful but no URL returned. Please check console and try again.');
                        return;
                    }
                    
                    console.log('Profile picture URL:', imageUrl);
                    document.getElementById('edit-profile-picture').value = imageUrl;
                    
                    // Update preview in modal
                    document.getElementById('profilePicturePreview').innerHTML = `<img src="${imageUrl}" alt="Profile" style="max-width: 200px; max-height: 200px; border-radius: var(--radius-md);">`;
                    
                    // Update main profile avatar immediately
                    const profileAvatarDisplay = document.getElementById('profileAvatarDisplay');
                    if (profileAvatarDisplay) {
                        profileAvatarDisplay.innerHTML = `<img src="${imageUrl}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block; position: absolute; top: 0; left: 0;">`;
                    }
                    
                    // Update create post avatar
                    const createPostAvatar = document.querySelector('.create-post-avatar');
                    if (createPostAvatar) {
                        createPostAvatar.src = imageUrl;
                        createPostAvatar.style.display = 'block';
                    }
                } else {
                    alert('Failed to upload image: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Failed to upload image');
            }
        }

        // Profile form submission
        document.getElementById('editProfileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                fullname: document.getElementById('edit-fullname').value.trim(),
                username: document.getElementById('edit-username').value.trim(),
                age: document.getElementById('edit-age').value ? parseInt(document.getElementById('edit-age').value) : null,
                gender: document.getElementById('edit-gender').value || null,
                bio: document.getElementById('edit-bio').value.trim(),
                profile_picture: document.getElementById('edit-profile-picture').value.trim()
            };

            try {
                const response = await API.updateProfile(formData);
                if (response && response.success) {
                    // Update profile display immediately before reload
                    const updatedUser = response.data?.user;
                    if (updatedUser) {
                        // Update fullname
                        if (updatedUser.fullname) {
                            const fullnameDisplay = document.getElementById('profileFullnameDisplay');
                            if (fullnameDisplay) {
                                fullnameDisplay.textContent = updatedUser.fullname;
                            }
                        }
                        
                        // Update profile picture
                        if (updatedUser.profile_picture) {
                            const profileAvatarDisplay = document.getElementById('profileAvatarDisplay');
                            if (profileAvatarDisplay) {
                                profileAvatarDisplay.innerHTML = `<img src="${updatedUser.profile_picture}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
                            }
                            
                            // Update create post avatar
                            const createPostAvatar = document.querySelector('.create-post-avatar');
                            if (createPostAvatar) {
                                createPostAvatar.src = updatedUser.profile_picture;
                                createPostAvatar.style.display = 'block';
                            }
                        }
                    }
                    
                    alert('Profile updated successfully!');
                    // Small delay before reload to show the update
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert('Failed to update profile: ' + (response?.error || response?.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                alert('Failed to update profile: ' + (error.message || 'Unknown error'));
            }
        });

        // Post creation
        let selectedPostMedia = null;
        let selectedPostMediaType = null;

        function handlePostMediaSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            selectedPostMedia = file;
            selectedPostMediaType = file.type.startsWith('image/') ? 'image' : 'video';

            const preview = document.getElementById('postMediaPreview');
            const previewImg = document.getElementById('postMediaPreviewImg');
            const previewVideo = document.getElementById('postMediaPreviewVideo');

            if (selectedPostMediaType === 'image') {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                    previewVideo.style.display = 'none';
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewVideo.src = URL.createObjectURL(file);
                previewVideo.style.display = 'block';
                previewImg.style.display = 'none';
                preview.style.display = 'block';
            }
        }

        function clearPostMedia() {
            selectedPostMedia = null;
            selectedPostMediaType = null;
            document.getElementById('postMediaInput').value = '';
            document.getElementById('postMediaPreview').style.display = 'none';
        }

        async function createPost() {
            const content = document.getElementById('postContent').value.trim();
            
            if (!content && !selectedPostMedia) {
                alert('Please enter some content or select a media file');
                return;
            }

            let mediaUrl = null;
            let mediaName = null;
            let mediaType = 'text';

            // Upload media if selected
            if (selectedPostMedia) {
                const formData = new FormData();
                formData.append('file', selectedPostMedia);
                formData.append('type', 'post');

                try {
                    const uploadResponse = await fetch('/api/upload.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    });

                    const uploadData = await uploadResponse.json();
                    if (uploadData.success) {
                        mediaUrl = uploadData.data?.url || uploadData.url || '';
                        mediaName = selectedPostMedia.name;
                        mediaType = selectedPostMediaType;
                    } else {
                        alert('Failed to upload media: ' + (uploadData.error || 'Unknown error'));
                        return;
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    alert('Failed to upload media');
                    return;
                }
            }

            try {
                const response = await API.createPost(content || null, mediaType, mediaUrl, mediaName);
                if (response && response.success) {
                    document.getElementById('postContent').value = '';
                    clearPostMedia();
                    location.reload();
                } else {
                    alert('Failed to create post: ' + (response?.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error creating post:', error);
                alert('Failed to create post: ' + (error.message || 'Unknown error'));
            }
        }

        async function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post?')) {
                return;
            }

            try {
                const response = await API.deletePost(postId);
                if (response && response.success) {
                    document.querySelector(`[data-post-id="${postId}"]`).remove();
                } else {
                    alert('Failed to delete post: ' + (response?.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error deleting post:', error);
                alert('Failed to delete post: ' + (error.message || 'Unknown error'));
            }
        }

        function openImageModal(src) {
            document.getElementById('imageModalImg').src = src;
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Comments and Reactions
        async function toggleStar(postId) {
            try {
                const response = await API.toggleReaction(postId, 'star');
                if (response && response.success) {
                    const btn = document.querySelector(`[onclick="toggleStar(${postId})"]`);
                    const countSpan = document.getElementById(`starCount-${postId}`);
                    
                    if (response.data.has_reacted) {
                        btn.querySelector('span').textContent = '⭐';
                        btn.classList.add('active');
                    } else {
                        btn.querySelector('span').textContent = '☆';
                        btn.classList.remove('active');
                    }
                    
                    if (countSpan) {
                        countSpan.textContent = response.data.count;
                    }
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        }

        function toggleComments(postId) {
            const commentsSection = document.getElementById(`comments-${postId}`);
            if (commentsSection) {
                commentsSection.style.display = commentsSection.style.display === 'none' ? 'block' : 'none';
            }
        }

        async function addComment(postId) {
            const input = document.getElementById(`commentInput-${postId}`);
            const content = input.value.trim();
            
            if (!content) {
                alert('Please enter a comment');
                return;
            }

            try {
                const response = await API.createComment(postId, content);
                if (response && response.success) {
                    input.value = '';
                    // Reload comments
                    loadComments(postId);
                    // Update comment count
                    const commentCount = document.getElementById(`commentCount-${postId}`);
                    if (commentCount) {
                        const currentCount = parseInt(commentCount.textContent) || 0;
                        commentCount.textContent = currentCount + 1;
                    }
                } else {
                    alert('Failed to add comment: ' + (response?.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error adding comment:', error);
                alert('Failed to add comment: ' + (error.message || 'Unknown error'));
            }
        }

        async function loadComments(postId) {
            try {
                const response = await API.getComments(postId);
                if (response && response.success) {
                    const commentsList = document.getElementById(`commentsList-${postId}`);
                    if (commentsList) {
                        const comments = response.data.comments || [];
                        commentsList.innerHTML = comments.map(comment => {
                            const isOwnComment = comment.user_id == <?= $currentUser['id'] ?>;
                            return `
                                <div class="comment-item" style="display: flex; gap: var(--space-sm); margin-bottom: var(--space-md);" data-comment-id="${comment.id}">
                                    <img src="${comment.profile_picture || ''}" alt="${comment.fullname || comment.username}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none';">
                                    <div style="flex: 1;">
                                        <div style="font-weight: bold; color: var(--color-text-primary); margin-bottom: var(--space-xs);">
                                            ${comment.fullname || comment.username}
                                        </div>
                                        <div style="color: var(--color-text-primary);">${comment.content.replace(/\n/g, '<br>')}</div>
                                        <div style="font-size: var(--font-size-sm); color: var(--color-text-secondary); margin-top: var(--space-xs);">
                                            ${new Date(comment.created_at).toLocaleString()}
                                        </div>
                                    </div>
                                    ${isOwnComment ? `<button class="btn btn-sm btn-danger" onclick="deleteComment(${comment.id}, ${postId})">Delete</button>` : ''}
                                </div>
                            `;
                        }).join('');
                    }
                }
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }

        async function deleteComment(commentId, postId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }

            try {
                const response = await API.deleteComment(commentId);
                if (response && response.success) {
                    // Remove comment from DOM
                    const commentItem = document.querySelector(`[data-comment-id="${commentId}"]`);
                    if (commentItem) {
                        commentItem.remove();
                    }
                    // Update comment count
                    const commentCount = document.getElementById(`commentCount-${postId}`);
                    if (commentCount) {
                        const currentCount = parseInt(commentCount.textContent) || 0;
                        commentCount.textContent = Math.max(0, currentCount - 1);
                    }
                } else {
                    alert('Failed to delete comment: ' + (response?.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error deleting comment:', error);
                alert('Failed to delete comment: ' + (error.message || 'Unknown error'));
            }
        }

        async function resendVerificationEmail() {
            try {
                const response = await API.resendVerificationEmail();
                if (response && response.success) {
                    alert('Verification email sent! Please check your inbox.');
                } else {
                    alert('Failed to send verification email: ' + (response?.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending verification email:', error);
                alert('Failed to send verification email');
            }
        }

        // Allow Enter key to submit comments
        document.addEventListener('keypress', (e) => {
            if (e.target.id && e.target.id.startsWith('commentInput-') && e.key === 'Enter') {
                const postId = parseInt(e.target.id.replace('commentInput-', ''));
                addComment(postId);
            }
        });

        // Friend Request Functions
        async function sendFriendRequest(userId) {
            try {
                const response = await API.sendFriendRequest(userId);
                if (response.success) {
                    const container = document.getElementById('friendRequestContainer');
                    if (container) {
                        container.innerHTML = `
                            <button class="btn btn-secondary" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                Request Sent
                            </button>
                        `;
                    }
                } else {
                    alert('Failed to send friend request: ' + (response.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending friend request:', error);
                alert('Failed to send friend request');
            }
        }

        async function acceptFriendRequest(requestId) {
            try {
                const response = await API.acceptFriendRequest(requestId);
                if (response.success) {
                    const container = document.getElementById('friendRequestContainer');
                    if (container) {
                        container.innerHTML = `
                            <button class="btn btn-secondary" id="removeFriendBtn" onclick="removeFriend(<?= $user['id'] ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="18" y1="8" x2="23" y2="13"></line>
                                    <line x1="23" y1="8" x2="18" y2="13"></line>
                                </svg>
                                Friends
                            </button>
                        `;
                    }
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    alert('Failed to accept friend request: ' + (response.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error accepting friend request:', error);
                alert('Failed to accept friend request');
            }
        }

        async function rejectFriendRequest(requestId) {
            try {
                const response = await API.rejectFriendRequest(requestId);
                if (response.success) {
                    const container = document.getElementById('friendRequestContainer');
                    if (container) {
                        container.innerHTML = `
                            <button class="btn btn-primary" id="sendFriendRequestBtn" onclick="sendFriendRequest(<?= $user['id'] ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                Add Friend
                            </button>
                        `;
                    }
                } else {
                    alert('Failed to reject friend request: ' + (response.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error rejecting friend request:', error);
                alert('Failed to reject friend request');
            }
        }

        async function removeFriend(userId) {
            if (!confirm('Are you sure you want to remove this friend?')) {
                return;
            }
            try {
                const response = await API.removeFriend(userId);
                if (response.success) {
                    const container = document.getElementById('friendRequestContainer');
                    if (container) {
                        container.innerHTML = `
                            <button class="btn btn-primary" id="sendFriendRequestBtn" onclick="sendFriendRequest(${userId})">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                Add Friend
                            </button>
                        `;
                    }
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    alert('Failed to remove friend: ' + (response.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error removing friend:', error);
                alert('Failed to remove friend');
            }
        }

        function showAllFriends() {
            alert('All friends view coming soon!');
        }
    </script>
    <?php endif; ?>
</body>
</html>

