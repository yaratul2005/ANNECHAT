<?php
require_once 'bootstrap.php';

use App\Services\AuthService;
use App\Models\Settings;
use App\Models\User;
use App\Models\Message;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\IpBlock;
use App\Models\Cooldown;
use App\Models\SmtpSettings;

$authService = new AuthService();
$user = $authService->getCurrentUser();

if (!$user || !$user['is_admin']) {
    header('Location: index.php');
    exit;
}

$settingsModel = new Settings();
$settings = $settingsModel->get();

$userModel = new User();
$messageModel = new Message();
$activityLog = new ActivityLog();
$reportModel = new Report();
$ipBlockModel = new IpBlock();
$cooldownModel = new Cooldown();

// Get statistics
$totalUsers = count($userModel->getAll(1000, 0));
$totalMessages = count($messageModel->getAll(1000, 0));
$recentLogs = $activityLog->getAll(20, 0);
$pendingReports = $reportModel->getAll(50, 0, 'pending');
$allReports = $reportModel->getAll(100, 0);
$blockedIPs = $ipBlockModel->getAll(100, 0);
$activeCooldowns = $cooldownModel->getAll(100, 0);
$smtpSettingsModel = new SmtpSettings();
$smtpSettings = $smtpSettingsModel->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= htmlspecialchars($settings['site_name'] ?? 'Anne Chat') ?></title>
    <link rel="stylesheet" href="css/fallback.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <ul class="admin-nav">
                    <li><a href="#dashboard" class="active">Dashboard</a></li>
                    <li><a href="#settings">Site Settings</a></li>
                    <li><a href="#users">Users</a></li>
                    <li><a href="#messages">Messages</a></li>
                    <li><a href="#reports">Reports <?= count($pendingReports) > 0 ? '<span class="badge">' . count($pendingReports) . '</span>' : '' ?></a></li>
                    <li><a href="#ip-blocks">IP Blocks</a></li>
                    <li><a href="#cooldowns">Cooldowns</a></li>
                    <li><a href="#smtp">SMTP Settings</a></li>
                    <li><a href="#send-email">Send Email</a></li>
                    <li><a href="#logs">Activity Logs</a></li>
                    <li><a href="dashboard.php">Back to Chat</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1>Admin Dashboard</h1>
                <p>Welcome, <?= htmlspecialchars($user['username']) ?></p>
            </div>

            <div id="dashboard" class="admin-section">
                <h3>Statistics</h3>
                <div class="admin-stats">
                    <div class="stat-card">
                        <h4>Total Users</h4>
                        <div class="stat-value"><?= $totalUsers ?></div>
                    </div>
                    <div class="stat-card">
                        <h4>Total Messages</h4>
                        <div class="stat-value"><?= $totalMessages ?></div>
                    </div>
                </div>
            </div>

            <div id="users" class="admin-section">
                <h3>Users Management</h3>
                <div class="admin-form-card" style="margin-bottom: 2rem;">
                    <label>
                        <input type="checkbox" id="includeGuests" onchange="loadUsers()">
                        Include Guest Users
                    </label>
                </div>
                <div id="usersTableContainer">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Age</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>IP Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">Loading users...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="settings" class="admin-section">
                <h3>Site Settings</h3>
                <form id="settingsForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="primary_color">Primary Color</label>
                            <input type="color" id="primary_color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#1a73e8') ?>">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="site_description">Site Description</label>
                        <textarea id="site_description" name="site_description"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="meta_title">Meta Title</label>
                        <input type="text" id="meta_title" name="meta_title" value="<?= htmlspecialchars($settings['meta_title'] ?? '') ?>" maxlength="60">
                    </div>
                    <div class="form-group full-width">
                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" name="meta_description" maxlength="160"><?= htmlspecialchars($settings['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="custom_css">Custom CSS</label>
                        <textarea id="custom_css" name="custom_css" rows="10"><?= htmlspecialchars($settings['custom_css'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="custom_head_tags">Custom Head Tags</label>
                        <textarea id="custom_head_tags" name="custom_head_tags" rows="5"><?= htmlspecialchars($settings['custom_head_tags'] ?? '') ?></textarea>
                    </div>
                    <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--color-bg-tertiary);">
                    <h4 style="margin-bottom: 1rem;">Footer Settings</h4>
                    <div class="form-group full-width">
                        <label>
                            <input type="checkbox" id="footer_enabled" name="footer_enabled" <?= ($settings['footer_enabled'] ?? true) ? 'checked' : '' ?>>
                            Enable Footer
                        </label>
                    </div>
                    <div class="form-group full-width">
                        <label for="footer_text">Footer Text (HTML allowed)</label>
                        <textarea id="footer_text" name="footer_text" rows="4"><?= htmlspecialchars($settings['footer_text'] ?? '') ?></textarea>
                        <small style="color: var(--color-text-muted);">You can use HTML tags for formatting</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="footer_copyright">Copyright Text</label>
                        <input type="text" id="footer_copyright" name="footer_copyright" value="<?= htmlspecialchars($settings['footer_copyright'] ?? '') ?>" placeholder="© 2024 Your Site Name">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>

            <div id="reports" class="admin-section">
                <h3>User Reports</h3>
                <div class="admin-tabs">
                    <button class="tab-btn active" data-tab="pending-reports">Pending (<?= count($pendingReports) ?>)</button>
                    <button class="tab-btn" data-tab="all-reports">All Reports</button>
                </div>
                
                <div id="pending-reports" class="tab-content active">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reporter</th>
                                <th>Reported User</th>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingReports as $report): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i', strtotime($report['created_at'])) ?></td>
                                <td><?= htmlspecialchars($report['reporter_username']) ?></td>
                                <td><?= htmlspecialchars($report['reported_username']) ?></td>
                                <td><span class="badge badge-<?= $report['reason'] ?>"><?= ucfirst(str_replace('_', ' ', $report['reason'])) ?></span></td>
                                <td><?= htmlspecialchars($report['description'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="updateReportStatus(<?= $report['id'] ?>, 'reviewed')">Review</button>
                                    <button class="btn btn-sm btn-success" onclick="updateReportStatus(<?= $report['id'] ?>, 'resolved')">Resolve</button>
                                    <button class="btn btn-sm btn-secondary" onclick="updateReportStatus(<?= $report['id'] ?>, 'dismissed')">Dismiss</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pendingReports)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">No pending reports</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="all-reports" class="tab-content">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reporter</th>
                                <th>Reported User</th>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allReports as $report): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i', strtotime($report['created_at'])) ?></td>
                                <td><?= htmlspecialchars($report['reporter_username']) ?></td>
                                <td><?= htmlspecialchars($report['reported_username']) ?></td>
                                <td><code><?= htmlspecialchars($report['reported_ip_address'] ?? 'N/A') ?></code></td>
                                <td><span class="badge badge-<?= $report['reason'] ?>"><?= ucfirst(str_replace('_', ' ', $report['reason'])) ?></span></td>
                                <td><span class="badge badge-<?= $report['status'] ?>"><?= ucfirst($report['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewReportDetails(<?= $report['id'] ?>)">View</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="ip-blocks" class="admin-section">
                <h3>IP Blocking</h3>
                <div class="admin-form-card">
                    <h4>Block IP Address</h4>
                    <form id="blockIpForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ip_address">IP Address</label>
                                <input type="text" id="ip_address" name="ip_address" placeholder="192.168.1.1" required>
                            </div>
                            <div class="form-group">
                                <label for="block_duration">Duration</label>
                                <select id="block_duration" name="block_duration">
                                    <option value="1h">1 Hour</option>
                                    <option value="24h">24 Hours</option>
                                    <option value="7d">7 Days</option>
                                    <option value="30d">30 Days</option>
                                    <option value="permanent">Permanent</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="block_reason">Reason</label>
                            <textarea id="block_reason" name="block_reason" rows="3" placeholder="Reason for blocking this IP"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Block IP</button>
                    </form>
                </div>
                
                <h4 style="margin-top: 2rem;">Blocked IPs</h4>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Reason</th>
                            <th>Blocked By</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blockedIPs as $block): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($block['ip_address']) ?></code></td>
                            <td><?= htmlspecialchars($block['reason'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($block['blocked_by_username'] ?? 'System') ?></td>
                            <td>
                                <?php if ($block['is_permanent']): ?>
                                    <span class="badge badge-error">Permanent</span>
                                <?php elseif ($block['expires_at']): ?>
                                    <?= date('Y-m-d H:i', strtotime($block['expires_at'])) ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="unblockIP('<?= htmlspecialchars($block['ip_address']) ?>')">Unblock</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($blockedIPs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">No blocked IPs</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="cooldowns" class="admin-section">
                <h3>Active Cooldowns / Rate Limits</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Action Type</th>
                            <th>Identifier</th>
                            <th>Attempts</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeCooldowns as $cooldown): ?>
                        <tr>
                            <td><?= htmlspecialchars($cooldown['user_username'] ?? 'Guest') ?></td>
                            <td><code><?= htmlspecialchars($cooldown['ip_address'] ?? 'N/A') ?></code></td>
                            <td><span class="badge"><?= htmlspecialchars($cooldown['action_type']) ?></span></td>
                            <td><?= htmlspecialchars($cooldown['action_identifier']) ?></td>
                            <td><?= $cooldown['attempt_count'] ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($cooldown['expires_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="clearCooldown('<?= htmlspecialchars($cooldown['action_type']) ?>', '<?= htmlspecialchars($cooldown['action_identifier']) ?>')">Clear</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($activeCooldowns)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">No active cooldowns</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="smtp" class="admin-section">
                <h3>SMTP Settings</h3>
                <p style="color: var(--color-text-muted); margin-bottom: var(--space-lg);">Configure SMTP settings for sending emails from the system.</p>
                
                <form id="smtpForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($smtpSettings['host'] ?? 'smtp.gmail.com') ?>" placeholder="smtp.gmail.com" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($smtpSettings['port'] ?? '587') ?>" placeholder="587" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_encryption">Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption">
                                <option value="tls" <?= ($smtpSettings['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($smtpSettings['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= ($smtpSettings['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="smtp_is_active" name="smtp_is_active" <?= ($smtpSettings['is_active'] ?? false) ? 'checked' : '' ?>>
                                Enable SMTP
                            </label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_username">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" value="<?= htmlspecialchars($smtpSettings['username'] ?? '') ?>" placeholder="your-email@gmail.com" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep current password">
                            <small style="color: var(--color-text-muted);">Leave blank to keep the current password</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_from_email">From Email</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= htmlspecialchars($smtpSettings['from_email'] ?? '') ?>" placeholder="noreply@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_from_name">From Name</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= htmlspecialchars($smtpSettings['from_name'] ?? '') ?>" placeholder="Your Site Name" required>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
                        <button type="button" class="btn btn-secondary" onclick="testSMTP()" style="margin-left: var(--space-md);">Test SMTP Connection</button>
                    </div>
                </form>
            </div>

            <div id="send-email" class="admin-section">
                <h3>Send Email</h3>
                <p style="color: var(--color-text-muted); margin-bottom: var(--space-lg);">Send an email to a user. SMTP must be configured and enabled to use this feature.</p>
                
                <form id="sendEmailForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_to">To Email</label>
                            <input type="email" id="email_to" name="email_to" placeholder="user@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="email_to_name">To Name (Optional)</label>
                            <input type="text" id="email_to_name" name="email_to_name" placeholder="User Name">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="email_subject">Subject</label>
                        <input type="text" id="email_subject" name="email_subject" placeholder="Email subject" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="email_body">Message Body (HTML allowed)</label>
                        <textarea id="email_body" name="email_body" rows="10" placeholder="Enter your email message here..." required></textarea>
                        <small style="color: var(--color-text-muted);">You can use HTML tags for formatting</small>
                    </div>
                    <div class="form-group full-width">
                        <button type="submit" class="btn btn-primary">Send Email</button>
                    </div>
                </form>
            </div>

            <div id="logs" class="admin-section">
                <h3>Recent Activity Logs</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="js/api.js"></script>
    <script>
        // Section navigation - show/hide sections based on hash
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.admin-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.admin-nav a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = 'block';
                
                // Add active class to corresponding nav link
                const navLink = document.querySelector(`.admin-nav a[href="#${sectionId}"]`);
                if (navLink) {
                    navLink.classList.add('active');
                }
                
                // Scroll to top of section
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        // Handle hash changes (e.g., browser back/forward)
        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.substring(1) || 'dashboard';
            showSection(hash);
        });
        
        // Load users when users section is shown
        let usersLoaded = false;
        const originalShowSection = showSection;
        showSection = function(sectionId) {
            originalShowSection(sectionId);
            if (sectionId === 'users') {
                loadUsers();
            }
        };
        
        // Handle initial load
        const initialHash = window.location.hash.substring(1) || 'dashboard';
        showSection(initialHash);
        
        // Handle nav link clicks
        document.querySelectorAll('.admin-nav a[href^="#"]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = link.getAttribute('href').substring(1);
                window.location.hash = sectionId;
                showSection(sectionId);
            });
        });
        
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(tabName).classList.add('active');
            });
        });

        // Settings form
        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                site_name: document.getElementById('site_name').value,
                site_description: document.getElementById('site_description').value,
                meta_title: document.getElementById('meta_title').value,
                meta_description: document.getElementById('meta_description').value,
                primary_color: document.getElementById('primary_color').value,
                custom_css: document.getElementById('custom_css').value,
                custom_head_tags: document.getElementById('custom_head_tags').value,
                footer_text: document.getElementById('footer_text').value,
                footer_enabled: document.getElementById('footer_enabled').checked,
                footer_copyright: document.getElementById('footer_copyright').value
            };
            
            try {
                const response = await API.updateSettings(formData);
                alert('Settings saved successfully!');
                location.reload();
            } catch (error) {
                alert('Error saving settings: ' + error.message);
            }
        });

        // Block IP form
        document.getElementById('blockIpForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const ipAddress = document.getElementById('ip_address').value;
            const duration = document.getElementById('block_duration').value;
            const reason = document.getElementById('block_reason').value;
            
            try {
                await API.blockIP(ipAddress, duration, reason);
                alert('IP blocked successfully!');
                location.reload();
            } catch (error) {
                alert('Error blocking IP: ' + error.message);
            }
        });

        // Update report status
        async function updateReportStatus(reportId, status) {
            if (!confirm(`Are you sure you want to mark this report as "${status}"?`)) {
                return;
            }
            
            try {
                await API.updateReportStatus(reportId, status);
                alert('Report status updated!');
                location.reload();
            } catch (error) {
                alert('Error updating report: ' + error.message);
            }
        }

        // View report details
        async function viewReportDetails(reportId) {
            try {
                const response = await API.getReportDetails(reportId);
                const report = response.data;
                
                const details = `
Report ID: ${report.id}
Reporter: ${report.reporter_username} (${report.reporter_email})
Reported User: ${report.reported_username} (${report.reported_email})
Reason: ${report.reason}
Status: ${report.status}
Description: ${report.description || 'N/A'}
Created: ${report.created_at}
`;
                alert(details);
            } catch (error) {
                alert('Error fetching report details: ' + error.message);
            }
        }

        // Unblock IP
        async function unblockIP(ipAddress) {
            if (!confirm(`Are you sure you want to unblock ${ipAddress}?`)) {
                return;
            }
            
            try {
                await API.unblockIP(ipAddress);
                alert('IP unblocked successfully!');
                location.reload();
            } catch (error) {
                alert('Error unblocking IP: ' + error.message);
            }
        }

        // Clear cooldown
        async function clearCooldown(actionType, actionIdentifier) {
            if (!confirm(`Clear cooldown for ${actionType}: ${actionIdentifier}?`)) {
                return;
            }
            
            try {
                await API.clearCooldown(actionType, actionIdentifier);
                alert('Cooldown cleared!');
                location.reload();
            } catch (error) {
                alert('Error clearing cooldown: ' + error.message);
            }
        }

        // SMTP form
        document.getElementById('smtpForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                host: document.getElementById('smtp_host').value,
                port: parseInt(document.getElementById('smtp_port').value),
                encryption: document.getElementById('smtp_encryption').value,
                username: document.getElementById('smtp_username').value,
                password: document.getElementById('smtp_password').value,
                from_email: document.getElementById('smtp_from_email').value,
                from_name: document.getElementById('smtp_from_name').value,
                is_active: document.getElementById('smtp_is_active').checked
            };
            
            try {
                await API.updateSmtpSettings(formData);
                alert('SMTP settings saved successfully!');
                location.reload();
            } catch (error) {
                alert('Error saving SMTP settings: ' + error.message);
            }
        });

        // Test SMTP
        async function testSMTP() {
            if (!confirm('This will send a test email to the configured "From Email" address. Continue?')) {
                return;
            }
            
            try {
                const response = await API.testSmtp();
                alert(response.message || 'Test email sent! Check your inbox.');
                location.reload();
            } catch (error) {
                alert('Error testing SMTP: ' + error.message);
            }
        }

        // Send email form
        document.getElementById('sendEmailForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                to: document.getElementById('email_to').value,
                to_name: document.getElementById('email_to_name').value || null,
                subject: document.getElementById('email_subject').value,
                body: document.getElementById('email_body').value
            };
            
            try {
                await API.sendEmail(formData);
                alert('Email sent successfully!');
                document.getElementById('sendEmailForm').reset();
            } catch (error) {
                alert('Error sending email: ' + error.message);
            }
        });

        // Load users
        async function loadUsers() {
            const includeGuests = document.getElementById('includeGuests')?.checked || false;
            const tbody = document.getElementById('usersTableBody');
            
            if (!tbody) return;
            
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">Loading users...</td></tr>';
            
            try {
                const response = await API.getUsers(100, 0, includeGuests);
                const users = response.data?.users || [];
                
                if (users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No users found</td></tr>';
                    return;
                }
                
                tbody.innerHTML = users.map(user => {
                    const statusBadges = [];
                    if (user.is_admin) statusBadges.push('<span class="badge badge-error">Admin</span>');
                    if (user.is_guest) statusBadges.push('<span class="badge badge-guest-glow">Guest</span>');
                    if (user.is_banned) statusBadges.push('<span class="badge badge-error">Banned</span>');
                    if (user.is_verified && !user.is_guest) statusBadges.push('<span class="badge badge-verified-glow">Verified</span>');
                    if (statusBadges.length === 0) statusBadges.push('<span class="badge">User</span>');
                    
                    const createdDate = new Date(user.created_at).toLocaleDateString();
                    const ipAddress = user.last_ip_address || 'N/A';
                    
                    let actions = '';
                    if (!user.is_admin) {
                        if (user.is_banned) {
                            actions = `<button class="btn btn-sm btn-success" onclick="unbanUser(${user.id})">Unban</button> `;
                        } else {
                            actions = `<button class="btn btn-sm btn-warning" onclick="banUser(${user.id})">Ban</button> `;
                        }
                        actions += `<button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id}, '${user.username.replace(/'/g, "\\'")}')">Delete</button>`;
                    } else {
                        actions = '<span style="color: var(--color-text-muted);">Protected</span>';
                    }
                    
                    return `
                        <tr>
                            <td>${user.id}</td>
                            <td>${escapeHtml(user.username)}</td>
                            <td>${escapeHtml(user.email || 'N/A')}</td>
                            <td>${user.age || 'N/A'}</td>
                            <td>${statusBadges.join(' ')}</td>
                            <td>${createdDate}</td>
                            <td><code>${escapeHtml(ipAddress)}</code></td>
                            <td>${actions}</td>
                        </tr>
                    `;
                }).join('');
                
                usersLoaded = true;
            } catch (error) {
                console.error('Error loading users:', error);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--color-error);">Error loading users: ' + escapeHtml(error.message) + '</td></tr>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function banUser(userId) {
            if (!confirm('Are you sure you want to ban this user?')) {
                return;
            }
            
            try {
                await API.banUser(userId);
                alert('User banned successfully!');
                loadUsers();
            } catch (error) {
                alert('Error banning user: ' + error.message);
            }
        }

        async function unbanUser(userId) {
            if (!confirm('Are you sure you want to unban this user?')) {
                return;
            }
            
            try {
                await API.unbanUser(userId);
                alert('User unbanned successfully!');
                loadUsers();
            } catch (error) {
                alert('Error unbanning user: ' + error.message);
            }
        }

        async function deleteUser(userId, username) {
            if (!confirm(`Are you sure you want to DELETE user "${username}"? This action cannot be undone!`)) {
                return;
            }
            
            if (!confirm('This will permanently delete the user and all their messages. Are you absolutely sure?')) {
                return;
            }
            
            try {
                await API.deleteUser(userId);
                alert('User deleted successfully!');
                loadUsers();
            } catch (error) {
                alert('Error deleting user: ' + error.message);
            }
        }
    </script>
</body>
</html>

