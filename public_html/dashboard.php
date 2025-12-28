<?php
require_once 'bootstrap.php';

use App\Services\AuthService;
use App\Models\Settings;

$authService = new AuthService();
$user = $authService->getCurrentUser();

if (!$user) {
    header('Location: index.php');
    exit;
}

$settingsModel = new Settings();
$settings = $settingsModel->get();

// Admin users go to admin dashboard (but don't redirect automatically - let them use /admin)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></title>
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <?= $settings['custom_head_tags'] ?? '' ?>
    <style><?= $settings['custom_css'] ?? '' ?></style>
</head>
<body>
    <div class="chat-container" style="position: relative;">
        <header class="chat-header">
            <h1><?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></h1>
            <div class="stories-carousel-container" id="storiesCarouselContainer">
                <?php if (!$user['is_guest']): ?>
                <button class="add-story-btn" id="addStoryBtn" onclick="stories.showAddStoryModal()" title="Add Story">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    <span class="add-story-text">Add Story</span>
                </button>
                <?php endif; ?>
                <div class="stories-carousel" id="storiesCarousel">
                    <!-- Stories will be loaded here -->
                </div>
            </div>
            <div class="user-info">
                <button class="user-icon-btn" id="userIconBtn" onclick="toggleUserProfile()" aria-label="User profile">
                    <div class="user-icon-avatar">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="user-icon-img">
                        <?php else: ?>
                            <div class="user-icon-placeholder"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                </button>
                
                <div class="user-profile-dropdown" id="userProfileDropdown">
                    <div class="user-profile-card">
                        <div class="user-profile-content">
                            <div class="user-avatar-header">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="user-avatar-img">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder-header"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name-header"><?= htmlspecialchars($user['username']) ?></div>
                                <?php if ($user['is_guest']): ?>
                                    <span class="badge badge-guest-glow">Guest</span>
                                <?php elseif (!$user['is_verified']): ?>
                                    <span class="badge badge-unverified-glow">Unverified</span>
                                <?php else: ?>
                                    <span class="badge badge-verified-glow">Verified</span>
                                <?php endif; ?>
                                <?php if (!$user['is_guest'] && !$user['is_verified'] && !empty($user['email'])): ?>
                                    <div class="email-verification-notice">
                                        <p>Please verify your email to unlock all features.</p>
                                        <button class="btn-verify-email" onclick="resendVerificationEmail()">Resend Verification Email</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="profile.php" class="btn-edit-profile" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>View Profile</span>
                        </a>
                        <a href="logout.php" class="btn-logout">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="chat-layout">
            <aside class="users-sidebar" id="usersSidebar">
                <div class="sidebar-header">
                    <h2>Users</h2>
                    <button class="mobile-back-btn" id="mobileBackBtn" style="display: none;" onclick="chat.showUsersList()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                    </button>
                </div>
                <div class="sidebar-search" id="sidebarSearch" style="display: none;">
                    <input type="text" id="userSearchInput" placeholder="Search users..." class="search-input">
                    <button class="search-clear" id="searchClearBtn" onclick="chat.clearSearch()" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="sidebar-tabs">
                    <button class="tab-btn active" data-tab="online" onclick="chat.switchTab('online')">Online</button>
                    <button class="tab-btn" data-tab="inbox" onclick="chat.switchTab('inbox')">Inbox</button>
                    <?php if (!$user['is_guest']): ?>
                    <button class="tab-btn" data-tab="groups" onclick="chat.switchTab('groups')">Groups</button>
                    <?php endif; ?>
                </div>
                <div id="usersList" class="users-list">
                    <div class="loading">Loading users...</div>
                </div>
                <div id="inboxList" class="users-list" style="display: none;">
                    <div class="loading">Loading conversations...</div>
                </div>
                <?php if (!$user['is_guest']): ?>
                <div id="groupsList" class="users-list" style="display: none;">
                    <div class="loading">Loading groups...</div>
                </div>
                <?php endif; ?>
            </aside>
            
            <main class="chat-main" id="chatMain">
                <div class="chat-header-bar">
                    <button class="mobile-users-btn" id="mobileUsersBtn" onclick="chat.showUsersList()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </button>
                    <div class="recipient-profile-header" id="recipientProfileHeader" style="display: none;">
                        <button class="recipient-profile-btn" id="recipientProfileBtn" onclick="chat.toggleRecipientProfile()">
                            <div class="recipient-avatar-wrapper">
                                <img id="recipientAvatar" src="" alt="" class="recipient-avatar" style="display: none;">
                                <div id="recipientAvatarPlaceholder" class="recipient-avatar-placeholder"></div>
                                <span class="recipient-status-dot" id="recipientStatusDot"></span>
                            </div>
                            <div class="recipient-info">
                                <span class="recipient-name" id="recipientName"></span>
                                <span class="recipient-status-text" id="recipientStatusText"></span>
                            </div>
                            <svg class="recipient-dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="recipient-profile-dropdown" id="recipientProfileDropdown">
                            <div class="recipient-profile-content" id="recipientProfileContent">
                                <!-- Profile content will be loaded here -->
                            </div>
                        </div>
                        <button class="profile-visit-btn" id="profileVisitBtn" onclick="chat.visitProfile()" title="Visit Profile" style="display: none;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </button>
                    </div>
                    <h2 id="chatTitle">Select a user to start chatting</h2>
                </div>
                
                <div id="messagesContainer" class="messages-container hidden">
                    <div class="empty-state" id="emptyState">
                        <div class="empty-state-icon">💬</div>
                        <h3>Welcome to <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?>!</h3>
                        <p>Select a user from the sidebar to start a conversation</p>
                        <div class="empty-state-tips">
                            <div class="tip-item">
                                <span class="tip-icon">👤</span>
                                <span>Click on any user to start chatting</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">📸</span>
                                <span>Share photos, videos, and files in your messages</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">⭐</span>
                                <span>Visit profiles to see posts and interact with others</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="messageInputContainer" class="message-input-container hidden">
                    <div id="filePreview" class="file-preview" style="display: none;"></div>
                    <form id="messageForm">
                        <div class="message-input-wrapper">
                            <label for="fileInput" class="file-input-label" title="Attach file">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                </svg>
                            </label>
                            <input type="file" id="fileInput" accept="image/*,video/*,.pdf,.doc,.docx,.txt" style="display: none;">
                            <input type="text" id="messageInput" placeholder="Type a message or attach a file..." maxlength="1000">
                            <button type="submit" class="btn btn-primary">Send</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
        
        <?php if ($settings['footer_enabled'] ?? true): ?>
        <footer class="site-footer">
            <div class="footer-content">
                <?php if (!empty($settings['footer_text'])): ?>
                    <div class="footer-text"><?= $settings['footer_text'] ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['footer_copyright'])): ?>
                    <div class="footer-copyright"><?= htmlspecialchars($settings['footer_copyright']) ?></div>
                <?php endif; ?>
            </div>
        </footer>
        <?php endif; ?>
    </div>
    
    <!-- Profile Edit Modal -->
    <div id="profileEditModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="modal-close" id="closeProfileModalBtn" onclick="if(typeof window.closeProfileEditModal==='function'){window.closeProfileEditModal();}">&times;</button>
            </div>
            <div class="modal-body">
                <form id="profileEditForm">
                    <div class="form-group">
                        <label for="edit-username">Username</label>
                        <input type="text" id="edit-username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-age">Age</label>
                        <input type="number" id="edit-age" name="age" value="<?= htmlspecialchars($user['age'] ?? '') ?>" min="1" max="150">
                    </div>
                    <div class="form-group">
                        <label for="edit-bio">Bio</label>
                        <textarea id="edit-bio" name="bio" rows="4" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit-profile-picture">Profile Picture URL</label>
                        <input type="url" id="edit-profile-picture" name="profile_picture" value="<?= htmlspecialchars($user['profile_picture'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                        <small>Enter a direct image URL</small>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelProfileEditBtn" onclick="if(typeof window.closeProfileEditModal==='function'){window.closeProfileEditModal();}">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Story Viewer Modal -->
    <div class="story-viewer-modal" id="storyViewerModal">
        <div class="story-viewer-container">
            <div class="story-progress-container">
                <div class="story-progress-bar">
                    <div class="story-progress-bar-fill"></div>
                </div>
            </div>
            <button class="story-viewer-close" onclick="stories.closeStoryViewer()" aria-label="Close story">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="story-viewer-header">
                <div class="story-viewer-user"></div>
            </div>
            <div class="story-viewer-content">
                <button class="story-viewer-nav prev" onclick="stories.prevStory()" aria-label="Previous story">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <div class="story-viewer-media"></div>
                <div class="story-viewer-text" style="display: none;"></div>
                <button class="story-viewer-nav next" onclick="stories.nextStory()" aria-label="Next story">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        const currentUser = <?= json_encode($user) ?>;
        const isVerified = <?= json_encode($user['is_verified'] ?? false) ?>;
        const isGuest = <?= json_encode($user['is_guest'] ?? false) ?>;
    </script>
    
    <?php
    // Cache-bust assets by appending file modification time to script URLs
    $assetVersion = function($relativePath) {
        $full = __DIR__ . '/' . $relativePath;
        return file_exists($full) ? filemtime($full) : time();
    };
    ?>
    <script src="js/api.js?v=<?= $assetVersion('js/api.js') ?>"></script>
    <script src="js/stories.js?v=<?= $assetVersion('js/stories.js') ?>"></script>
    <script src="js/chat.js?v=<?= $assetVersion('js/chat.js') ?>"></script>
    <script src="js/app.js?v=<?= $assetVersion('js/app.js') ?>"></script>
    <script>
        // Initialize stories on page load
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof stories !== 'undefined') {
                stories.init();
            }
        });
    </script>
</body>
</html>

