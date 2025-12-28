const chat = {
    currentRecipient: null,
      currentGroup: null, // Track current group for group chat
      lastMessageId: null,
      pollingInterval: null,
      sseConnection: null, // Server-Sent Events connection
      pollTimeout: 2000,
      pollingInProgress: false,
      seenMessageIds: new Set(),
      currentTab: 'online',
      conversations: [],
      unreadCounts: {}, // Store unread counts per user ID
      totalUnreadConversations: 0,
      sseReconnectAttempts: 0,
      maxSSEReconnectAttempts: 5,
      currentRecipientProfile: null, // Store current recipient profile data
      recipientProfileDropdownOpen: false,

    init() {
        this.setupSearch();
        this.loadUsers();
        this.loadInbox();
        this.setupMessageForm();
        this.startSSE(); // Use SSE instead of polling
        this.setupMobileView();
        // Load notifications on init
        this.updateNotifications();
        // Update notifications periodically
        setInterval(() => this.updateNotifications(), 10000); // Every 10 seconds
    },

    setupMobileView() {
        // Check if mobile view
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            document.getElementById('usersSidebar').classList.add('mobile-active');
            document.getElementById('chatMain').classList.add('mobile-hidden');
            document.getElementById('mobileUsersBtn').style.display = 'none';
        }
        
        // Handle window resize
        window.addEventListener('resize', () => {
            const isMobileNow = window.innerWidth <= 768;
            if (!isMobileNow) {
                document.getElementById('usersSidebar').classList.remove('mobile-active', 'mobile-hidden');
                document.getElementById('chatMain').classList.remove('mobile-hidden', 'mobile-active');
                document.getElementById('mobileUsersBtn').style.display = 'none';
                document.getElementById('mobileBackBtn').style.display = 'none';
            }
        });
    },

    showUsersList() {
        const sidebar = document.getElementById('usersSidebar');
        const chatMain = document.getElementById('chatMain');
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            sidebar.classList.remove('mobile-hidden');
            sidebar.classList.add('mobile-active');
            chatMain.classList.remove('mobile-active');
            chatMain.classList.add('mobile-hidden');
            document.getElementById('mobileUsersBtn').style.display = 'block';
            document.getElementById('mobileBackBtn').style.display = 'none';
        }
    },

    showChat() {
        const sidebar = document.getElementById('usersSidebar');
        const chatMain = document.getElementById('chatMain');
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            sidebar.classList.remove('mobile-active');
            sidebar.classList.add('mobile-hidden');
            chatMain.classList.remove('mobile-hidden');
            chatMain.classList.add('mobile-active');
            document.getElementById('mobileUsersBtn').style.display = 'none';
            document.getElementById('mobileBackBtn').style.display = 'block';
        }
    },

    switchTab(tab) {
        this.currentTab = tab;
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const tabBtn = document.querySelector(`[data-tab="${tab}"]`);
        if (tabBtn) tabBtn.classList.add('active');
        
        // Hide all lists
        const usersList = document.getElementById('usersList');
        const inboxList = document.getElementById('inboxList');
        const groupsList = document.getElementById('groupsList');
        const sidebarSearch = document.getElementById('sidebarSearch');
        
        if (usersList) usersList.style.display = 'none';
        if (inboxList) inboxList.style.display = 'none';
        if (groupsList) groupsList.style.display = 'none';
        
        // Show/hide search box based on tab
        if (sidebarSearch) {
            if (tab === 'online') {
                sidebarSearch.style.display = 'block';
            } else {
                sidebarSearch.style.display = 'none';
                this.clearSearch();
            }
        }
        
        if (tab === 'online') {
            if (usersList) usersList.style.display = 'block';
            this.loadUsers();
        } else if (tab === 'inbox') {
            if (inboxList) inboxList.style.display = 'block';
            this.loadInbox();
        } else if (tab === 'groups') {
            if (groupsList) groupsList.style.display = 'block';
            this.loadGroups();
        }
    },

    async loadUsers() {
        const container = document.getElementById('usersList');
        if (!container) {
            console.error('usersList container not found');
            return;
        }
        
        try {
            console.log('[loadUsers] Starting to load users...');
            
            // Add timeout to prevent hanging
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('Request timeout after 10 seconds')), 10000);
            });
            
            const response = await Promise.race([
                API.getOnlineUsers(),
                timeoutPromise
            ]);
            
            console.log('[loadUsers] API response received:', response);
            
            if (!response) {
                console.error('[loadUsers] No response from server');
                container.innerHTML = '<div class="error">No response from server</div>';
                return;
            }
            
            if (!response.success) {
                console.error('[loadUsers] API returned error:', response.error);
                container.innerHTML = '<div class="error">Failed to load users: ' + (response.error || 'Unknown error') + '</div>';
                return;
            }
            
            // Handle different response formats
            let users = [];
            if (response.data && Array.isArray(response.data.users)) {
                users = response.data.users;
            } else if (response.data && Array.isArray(response.data)) {
                users = response.data;
            } else if (Array.isArray(response)) {
                users = response;
            }
            
            console.log('[loadUsers] Users extracted:', users.length, users);
            
            if (!Array.isArray(users)) {
                console.error('[loadUsers] Users is not an array:', typeof users, users);
                container.innerHTML = '<div class="error">Invalid users data format</div>';
                return;
            }
            
            // Store unread counts
            users.forEach(user => {
                if (user.unread_count) {
                    this.unreadCounts[user.id] = user.unread_count;
                }
            });
            
            console.log('[loadUsers] Rendering users list...');
            this.renderUsersList(users);
            await this.updateNotifications();
            console.log('[loadUsers] Completed successfully');
        } catch (error) {
            console.error('[loadUsers] Exception caught:', error);
            console.error('[loadUsers] Error message:', error.message);
            console.error('[loadUsers] Error stack:', error.stack);
            container.innerHTML = '<div class="error">Failed to load users. Error: ' + (error.message || 'Unknown error') + '</div>';
        }
    },

    async loadInbox() {
        try {
            // Get all users (not just online) for inbox
            const response = await API.getOnlineUsers();
            const allUsers = response.data.users || [];
            
            // Get users with conversations
            const conversations = await Promise.all(
                allUsers
                    .filter(user => user.id !== currentUser.id)
                    .map(async (user) => {
                        try {
                            const convResponse = await API.getConversation(user.id, 50, 0);
                            const messages = convResponse.data.messages || [];
                            if (messages.length > 0) {
                                const unreadCount = messages.filter(m => !m.is_read && m.recipient_id === currentUser.id).length;
                                return {
                                    user: user,
                                    lastMessage: messages[messages.length - 1],
                                    unreadCount: unreadCount
                                };
                            }
                        } catch (e) {
                            console.error('Error loading conversation for user', user.id, e);
                            return null;
                        }
                        return null;
                    })
            );
            
            this.conversations = conversations.filter(c => c !== null);
            this.renderInboxList(this.conversations);
        } catch (error) {
            console.error('Error loading inbox:', error);
            document.getElementById('inboxList').innerHTML = '<div class="error">Failed to load conversations</div>';
        }
    },

    setupSearch() {
        const searchInput = document.getElementById('userSearchInput');
        if (!searchInput) return;
        
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim().toLowerCase();
            const clearBtn = document.getElementById('searchClearBtn');
            
            if (query) {
                if (clearBtn) clearBtn.style.display = 'block';
                searchTimeout = setTimeout(() => {
                    this.filterUsers(query);
                }, 300);
            } else {
                if (clearBtn) clearBtn.style.display = 'none';
                this.clearSearch();
            }
        });
    },

    filterUsers(query) {
        const container = document.getElementById('usersList');
        if (!container) return;
        
        const allUsers = Array.from(container.querySelectorAll('.user-item'));
        let visibleCount = 0;
        
        allUsers.forEach(item => {
            const username = item.dataset.username || '';
            const fullname = item.dataset.fullname || '';
            const searchText = (username + ' ' + fullname).toLowerCase();
            
            if (searchText.includes(query)) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // Show empty message if no results
        let emptyMsg = container.querySelector('.search-empty');
        if (visibleCount === 0 && !emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.className = 'search-empty';
            emptyMsg.textContent = 'No users found';
            container.appendChild(emptyMsg);
        } else if (visibleCount > 0 && emptyMsg) {
            emptyMsg.remove();
        }
    },

    clearSearch() {
        const searchInput = document.getElementById('userSearchInput');
        const clearBtn = document.getElementById('searchClearBtn');
        const container = document.getElementById('usersList');
        
        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.style.display = 'none';
        
        if (container) {
            const allUsers = container.querySelectorAll('.user-item');
            allUsers.forEach(item => {
                item.style.display = 'flex';
            });
            const emptyMsg = container.querySelector('.search-empty');
            if (emptyMsg) emptyMsg.remove();
        }
    },

    renderUsersList(users) {
        const container = document.getElementById('usersList');
        
        if (users.length === 0) {
            container.innerHTML = '<div class="empty">No users online</div>';
            return;
        }

        const html = users.map(user => {
            const isCurrentUser = user.id === currentUser.id;
            if (isCurrentUser) return '';

            // Ensure is_guest is defined (default to false if not set)
            if (typeof user.is_guest === 'undefined') {
                user.is_guest = false;
            }

            // Always show status indicator - green for online, red for offline
            const statusClass = (user.status === 'online') ? 'status-online' : 
                               (user.status === 'away') ? 'status-away' : 'status-offline';

            // Get unread count for this user
            const unreadCount = user.unread_count || this.unreadCounts[user.id] || 0;
            const notificationBadge = unreadCount > 0 
                ? `<span class="notification-badge" data-user-id="${user.id}">${unreadCount > 99 ? '99+' : unreadCount}</span>` 
                : '';

            // Add verified badge for non-guest users
            const verifiedBadge = !user.is_guest ? 
                `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </span>` : '';

            return `
                <div class="user-item ${unreadCount > 0 ? 'has-notification' : ''}" data-user-id="${user.id}" data-username="${this.escapeHtml(user.username)}" data-fullname="${this.escapeHtml(user.fullname || '')}" data-user-status="${user.status || 'offline'}" onclick="chat.selectUser(${user.id}, '${this.escapeHtml(user.username)}', '${user.status || 'offline'}')">
                    <div class="user-avatar">
                        ${user.profile_picture ? 
                            `<img src="${this.escapeHtml(user.profile_picture)}" alt="${this.escapeHtml(user.username)}">` : 
                            `<div class="avatar-placeholder">${user.username.charAt(0).toUpperCase()}</div>`
                        }
                        <span class="status-indicator ${statusClass}" data-status="${user.status || 'offline'}"></span>
                    </div>
                    <div class="user-info">
                        <div class="user-name-wrapper">
                            <div class="user-name">${this.escapeHtml(user.username)}${this.getGenderIcon(user.gender)}${verifiedBadge}</div>
                            ${notificationBadge}
                        </div>
                        <div class="user-status">${user.status || 'offline'}</div>
                    </div>
                </div>
            `;
        }).filter(Boolean).join('');

        console.log('[renderUsersList] HTML generated, length:', html.length);
        container.innerHTML = html;
        console.log('[renderUsersList] HTML inserted into container');
    },

    renderInboxList(conversations) {
        const container = document.getElementById('inboxList');
        
        if (conversations.length === 0) {
            container.innerHTML = '<div class="empty">No conversations yet</div>';
            return;
        }

        // Sort by last message time
        conversations.sort((a, b) => {
            return new Date(b.lastMessage.created_at) - new Date(a.lastMessage.created_at);
        });

        const html = conversations.map(conv => {
            const user = conv.user;
            const lastMsg = conv.lastMessage;
            const isOwn = lastMsg.sender_id === currentUser.id;
            const preview = lastMsg.message_text.length > 30 
                ? lastMsg.message_text.substring(0, 30) + '...' 
                : lastMsg.message_text;
            const time = new Date(lastMsg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const unreadBadge = conv.unreadCount > 0 
                ? `<span class="unread-badge">${conv.unreadCount}</span>` 
                : '';
            
            // Ensure is_guest is defined
            if (typeof user.is_guest === 'undefined') {
                user.is_guest = false;
            }

            return `
                <div class="inbox-item ${conv.unreadCount > 0 ? 'unread' : ''}" data-user-id="${user.id}" onclick="chat.selectUser(${user.id}, '${this.escapeHtml(user.username)}', '${user.status || 'offline'}')">
                    <div class="user-avatar">
                        ${user.profile_picture ? 
                            `<img src="${this.escapeHtml(user.profile_picture)}" alt="${this.escapeHtml(user.username)}">` : 
                            `<div class="avatar-placeholder">${user.username.charAt(0).toUpperCase()}</div>`
                        }
                        <span class="status-indicator ${(user.status === 'online') ? 'status-online' : ((user.status === 'away') ? 'status-away' : 'status-offline')}" data-status="${user.status || 'offline'}"></span>
                    </div>
                    <div class="inbox-info">
                        <div class="inbox-header">
                            <div class="user-name">
                                ${this.escapeHtml(user.username)}
                                ${!user.is_guest ? 
                                    `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                    </span>` : ''}
                            </div>
                            <div class="inbox-time">${time}</div>
                        </div>
                        <div class="inbox-preview">
                            <span class="preview-text">${isOwn ? 'You: ' : ''}${this.escapeHtml(preview)}</span>
                            ${unreadBadge}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;
    },

    async selectUser(userId, username, userStatus = null) {
        // Clear current group when selecting a user
        this.currentGroup = null;
        this.currentRecipient = userId;
        
        // Update recipient profile header
        await this.updateRecipientProfileHeader(userId, username, userStatus);
        
        // Hide old title, show profile header
        const chatTitle = document.getElementById('chatTitle');
        const recipientHeader = document.getElementById('recipientProfileHeader');
        chatTitle.style.display = 'none';
        recipientHeader.style.display = 'flex';
        
        // Update active user in list with smooth transition
        document.querySelectorAll('.user-item, .inbox-item').forEach(item => {
            item.classList.remove('active');
            if (parseInt(item.dataset.userId) === userId) {
                item.classList.add('active');
                // Add a subtle highlight animation
                item.style.animation = 'userSelectPulse 0.3s ease';
                setTimeout(() => {
                    item.style.animation = '';
                }, 300);
            }
        });

        // Clear notification badge for this user when conversation is opened
        this.clearUserNotification(userId);

        // Reset lastMessageId when switching conversations
        // Note: Don't reset if we want to continue receiving messages via SSE
        // this.lastMessageId = null;

        // Show loading state first
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInputContainer = document.getElementById('messageInputContainer');
        
        // Clear previous messages and show loading skeleton
        messagesContainer.innerHTML = this.renderLoadingSkeleton();
        messagesContainer.classList.remove('hidden');
        messagesContainer.classList.add('visible', 'loading');

        // Show input container with delay for smooth cascade effect
        messageInputContainer.classList.remove('hidden');
        setTimeout(() => {
            messageInputContainer.classList.add('open');
        }, 100);

        // Show chat on mobile
        this.showChat();

        // Load conversation with smooth transition
        this.loadConversation(userId);
    },

    renderLoadingSkeleton() {
        // Create skeleton loader for messages
        const skeletonCount = 5;
        let skeletonHtml = '';
        
        for (let i = 0; i < skeletonCount; i++) {
            const isOwn = i % 3 === 0; // Mix of own and other messages
            skeletonHtml += `
                <div class="message-skeleton ${isOwn ? 'message-own' : 'message-other'}" style="animation-delay: ${i * 0.1}s">
                    <div class="message-skeleton-avatar"></div>
                    <div class="message-skeleton-content">
                        <div class="message-skeleton-line ${i % 2 === 0 ? 'short' : 'medium'}"></div>
                        <div class="message-skeleton-line ${i % 2 === 0 ? 'long' : 'short'}"></div>
                    </div>
                </div>
            `;
        }
        
        return skeletonHtml;
    },

    async loadConversation(userId) {
        try {
            // Reset seen message IDs when loading a new conversation to avoid duplicates
            this.seenMessageIds.clear();
            
            const response = await API.getConversation(userId);
            const messages = response.data.messages || [];
            
            // Small delay to ensure smooth transition from skeleton to messages
            await new Promise(resolve => setTimeout(resolve, 300));
            
            // Remove loading state and render messages with smooth transition
            const messagesContainer = document.getElementById('messagesContainer');
            messagesContainer.classList.remove('loading');
            
            // Render messages with fade-in effect
            this.renderMessages(messages);
            
            if (messages.length > 0) {
                this.lastMessageId = messages[messages.length - 1].id;
                // Add all loaded messages to seen set
                messages.forEach(m => {
                    if (m.id !== undefined && m.id !== null) {
                        this.seenMessageIds.add(String(m.id));
                    }
                });
            }
            
            // Clear notification for this user after loading conversation
            this.clearUserNotification(userId);
        } catch (error) {
            console.error('Error loading conversation:', error);
            const messagesContainer = document.getElementById('messagesContainer');
            messagesContainer.classList.remove('loading');
            messagesContainer.innerHTML = '<div class="empty-state"><p>Failed to load conversation. Please try again.</p></div>';
            this.showError('Failed to load conversation');
        }
    },

    renderMessages(messages) {
        const container = document.getElementById('messagesContainer');
        
        if (messages.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>No messages yet. Start the conversation!</p></div>';
            return;
        }

            // Deduplicate identical messages (same sender, recipient, text and timestamp)
            const seenKeys = new Set();
            const uniqueMessages = [];
            messages.forEach(m => {
                const key = `${m.sender_id}:${m.recipient_id}:${m.message_text || ''}:${m.created_at}`;
                if (!seenKeys.has(key)) {
                    seenKeys.add(key);
                    uniqueMessages.push(m);
                }
            });

        const html = messages.map(message => {
            const isOwn = message.sender_id === currentUser.id;
            const senderName = message.sender_username || 'Unknown';
            const senderGender = message.sender_gender || null;
            const timestamp = new Date(message.created_at).toLocaleTimeString();
            const hasAttachment = message.attachment_type && message.attachment_type !== 'none';
            
            let attachmentHtml = '';
            if (hasAttachment) {
                if (message.attachment_type === 'image') {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <img src="${this.escapeHtml(message.attachment_url)}" alt="${this.escapeHtml(message.attachment_name || 'Image')}" class="message-image" onclick="chat.openImageModal('${this.escapeHtml(message.attachment_url)}')">
                        </div>
                    `;
                } else if (message.attachment_type === 'video') {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <video controls class="message-video">
                                <source src="${this.escapeHtml(message.attachment_url)}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    `;
                } else {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <a href="${this.escapeHtml(message.attachment_url)}" download="${this.escapeHtml(message.attachment_name || 'file')}" class="message-file">
                                <span class="file-icon">📎</span>
                                <span class="file-name">${this.escapeHtml(message.attachment_name || 'File')}</span>
                                ${message.attachment_size ? `<span class="file-size">${this.formatFileSize(message.attachment_size)}</span>` : ''}
                            </a>
                        </div>
                    `;
                }
            }

            // Add verified badge for non-guest senders
            const senderVerifiedBadge = !isOwn && message.sender_is_guest === false ? 
                `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </span>` : '';

            return `
                <div class="message ${isOwn ? 'message-own' : 'message-other'}">
                    <div class="message-header">
                        <span class="message-sender">${this.escapeHtml(senderName)}${this.getGenderIcon(senderGender)}${senderVerifiedBadge}</span>
                        <span class="message-time">${timestamp}</span>
                    </div>
                    ${attachmentHtml}
                    ${message.message_text ? `<div class="message-content">${this.escapeHtml(message.message_text)}</div>` : ''}
                </div>
            `;
        }).join('');

        container.innerHTML = html;
        // rebuild seen message id set from current messages
        this.seenMessageIds.clear();
        messages.forEach(m => {
            if (m.id !== undefined && m.id !== null) {
                this.seenMessageIds.add(String(m.id));
            }
        });
        container.scrollTop = container.scrollHeight;
    },

    openImageModal(imageUrl) {
        // Create modal for full-size image viewing
        const modal = document.createElement('div');
        modal.className = 'image-modal';
        modal.innerHTML = `
            <div class="image-modal-content">
                <button class="image-modal-close" onclick="this.closest('.image-modal').remove()">×</button>
                <img src="${this.escapeHtml(imageUrl)}" alt="Full size">
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    },

    setupMessageForm() {
        const form = document.getElementById('messageForm');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        
        // Handle file selection
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelection(file, filePreview);
                }
            });
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            
            if (!this.currentRecipient && !this.currentGroup) {
                this.showError('Please select a user or group to message');
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            if (!isVerified && !isGuest && !this.currentGroup) {
                // Group messages don't require verification
                this.showError('Please verify your email before sending messages');
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            const input = document.getElementById('messageInput');
            const messageText = input.value.trim();
            const file = fileInput?.files[0];

            if (!messageText && !file) {
                this.showError('Please enter a message or select a file');
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            try {
                let attachmentData = null;
                
                // Upload file if selected
                if (file) {
                    attachmentData = await this.uploadFile(file);
                }
                
                // Check if sending to group or individual
                if (this.currentGroup) {
                    // Send group message
                    const sendResult = await API.sendGroupMessage(
                        this.currentGroup,
                        messageText || null,
                        attachmentData?.type || null,
                        attachmentData?.url || null
                    );
                    
                    if (sendResult.success) {
                        // Clear input and reload group messages
                        input.value = '';
                        this.clearFileSelection(fileInput, filePreview);
                        await this.loadGroupMessages(this.currentGroup);
                    } else {
                        throw new Error(sendResult.error || 'Failed to send message');
                    }
                } else if (this.currentRecipient) {
                    // Send individual message
                    // Optimistic UI: append a temporary message immediately so user sees it appear
                    const tmpId = 'tmp-' + Date.now();
                    const tmpMessage = {
                        id: tmpId,
                        sender_id: currentUser.id,
                        recipient_id: this.currentRecipient,
                        message_text: messageText || '',
                        attachment_type: attachmentData?.type || null,
                        attachment_url: attachmentData?.url || null,
                        attachment_name: attachmentData?.name || null,
                        attachment_size: attachmentData?.size || null,
                        created_at: new Date().toISOString(),
                        sender_username: currentUser.username || 'You'
                    };

                    this.appendNewMessages([tmpMessage]);

                    // Send message with or without attachment
                    const sendResult = await API.sendMessage(
                        this.currentRecipient, 
                        messageText || null,
                        attachmentData?.type || null,
                        attachmentData?.url || null,
                        attachmentData?.name || null,
                        attachmentData?.size || null
                    );

                    // Clear input/preview and refresh conversation (replace temp message with server message)
                    input.value = '';
                    this.clearFileSelection(fileInput, filePreview);
                    this.loadConversation(this.currentRecipient);
                }
            } catch (error) {
                this.showError(error.message || 'Failed to send message');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    },

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        const response = await fetch('/api/upload.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        // Read the body as text once and parse JSON from it. This avoids
        // "Failed to execute 'text' on 'Response': body stream already read"
        // when response.json() throws after partially consuming the stream.
        const rawText = await response.text();
        let result = null;
        if (rawText) {
            try {
                result = JSON.parse(rawText);
            } catch (err) {
                throw new Error(rawText || 'Invalid JSON response from upload endpoint');
            }
        }

        if (!result || !result.success) {
            throw new Error((result && result.error) ? result.error : 'File upload failed');
        }

        return result.data;
    },

    handleFileSelection(file, filePreview) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            this.showError('File size must be less than 5MB');
            document.getElementById('fileInput').value = '';
            return;
        }
        
        const fileType = file.type.split('/')[0];
        const fileInput = document.getElementById('fileInput');
        
        if (fileType === 'image') {
            const reader = new FileReader();
            reader.onload = (e) => {
                const previewHtml = `
                    <div class="file-preview-item">
                        <img src="${e.target.result}" alt="Preview" class="file-preview-image">
                        <div class="file-preview-info">
                            <span class="file-name">${this.escapeHtml(file.name)}</span>
                            <span class="file-size">${this.formatFileSize(file.size)}</span>
                        </div>
                        <button type="button" class="remove-file-btn" onclick="chat.clearFileSelection(document.getElementById('fileInput'), document.getElementById('filePreview'))">×</button>
                    </div>
                `;
                filePreview.innerHTML = previewHtml;
                filePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            const previewHtml = `
                <div class="file-preview-item">
                    <div class="file-icon">📎</div>
                    <div class="file-preview-info">
                        <span class="file-name">${this.escapeHtml(file.name)}</span>
                        <span class="file-size">${this.formatFileSize(file.size)}</span>
                    </div>
                    <button type="button" class="remove-file-btn" onclick="chat.clearFileSelection(document.getElementById('fileInput'), document.getElementById('filePreview'))">×</button>
                </div>
            `;
            filePreview.innerHTML = previewHtml;
            filePreview.style.display = 'block';
        }
    },

    clearFileSelection(fileInput, filePreview) {
        if (fileInput) fileInput.value = '';
        if (filePreview) {
            filePreview.innerHTML = '';
            filePreview.style.display = 'none';
        }
    },

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    startPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }

        this.pollingInterval = setInterval(async () => {
            if (!this.currentRecipient) {
                return;
            }

            // Prevent overlapping polls
            if (this.pollingInProgress) return;
            this.pollingInProgress = true;

            try {
                const response = await API.pollMessages(this.lastMessageId);
                const messages = response.data.messages || [];

                if (messages.length > 0) {
                    // Only process messages if we have a current recipient
                    // This prevents showing messages in wrong conversation
                    if (this.currentRecipient) {
                        this.appendNewMessages(messages);
                        // Update lastMessageId only for messages in current conversation
                        const currentConversationMessages = messages.filter(m => 
                            (m.sender_id === this.currentRecipient && m.recipient_id === currentUser.id) ||
                            (m.sender_id === currentUser.id && m.recipient_id === this.currentRecipient)
                        );
                        if (currentConversationMessages.length > 0) {
                            this.lastMessageId = currentConversationMessages[currentConversationMessages.length - 1].id;
                        }
                    }
                    
                    // Update notifications when new messages arrive (regardless of current conversation)
                    await this.updateNotifications();
                    
                    // Refresh inbox if on inbox tab
                    if (this.currentTab === 'inbox') {
                        this.loadInbox();
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
            } finally {
                this.pollingInProgress = false;
            }
        }, this.pollTimeout);
        
        // Also refresh users list periodically to update status indicators
        setInterval(() => {
            if (this.currentTab === 'online') {
                this.loadUsers();
            }
            // Update status indicators in inbox too
            if (this.currentTab === 'inbox') {
                this.loadInbox();
            }
        }, 15000); // Update every 15 seconds for real-time status
        
        // Update status indicators in real-time
        this.updateStatusIndicators();
    },

    updateStatusIndicators() {
        // Update status indicators periodically - optimized to fetch once and update all
        setInterval(async () => {
            try {
                const response = await API.getOnlineUsers();
                const users = response.data.users || [];
                const userStatusMap = {};
                
                // Create a map of user IDs to their statuses for O(1) lookup
                users.forEach(user => {
                    userStatusMap[user.id] = user.status || 'offline';
                });
                
                // Update all status indicators on the page
                document.querySelectorAll('.user-item, .inbox-item').forEach(item => {
                    const userId = parseInt(item.dataset.userId);
                    if (!userId) return;
                    
                    const currentStatus = item.dataset.userStatus || 'offline';
                    const newStatus = userStatusMap[userId] || 'offline';
                    
                    if (newStatus !== currentStatus) {
                        item.dataset.userStatus = newStatus;
                        const indicator = item.querySelector('.status-indicator');
                        if (indicator) {
                            // Remove all status classes
                            indicator.classList.remove('status-online', 'status-away', 'status-offline');
                            
                            // Add the correct status class with smooth transition
                            if (newStatus === 'online') {
                                indicator.classList.add('status-online');
                            } else if (newStatus === 'away') {
                                indicator.classList.add('status-away');
                            } else {
                                indicator.classList.add('status-offline');
                            }
                            
                            indicator.setAttribute('data-status', newStatus);
                            
                            // Update status text if present
                            const statusText = item.querySelector('.user-status');
                            if (statusText) {
                                statusText.textContent = newStatus;
                            }
                        }
                    }
                });
            } catch (error) {
                // Silently fail - status update is not critical
                console.debug('Status update error:', error);
            }
        }, 10000); // Check every 10 seconds
    },

    appendNewMessages(messages) {
        const container = document.getElementById('messagesContainer');
        
        // Filter messages based on current conversation type (individual or group)
        const filteredMessages = messages.filter(message => {
            if (this.currentGroup) {
                // Group chat: check if message belongs to current group
                return message.group_id === this.currentGroup;
            } else if (this.currentRecipient) {
                // Individual chat: check if message belongs to current conversation
                const isFromCurrentRecipient = message.sender_id === this.currentRecipient;
                const isToCurrentRecipient = message.recipient_id === this.currentRecipient;
                const isFromCurrentUser = message.sender_id === currentUser.id;
                const isToCurrentUser = message.recipient_id === currentUser.id;
                
                // Message belongs to current conversation if:
                // - It's from current recipient to current user, OR
                // - It's from current user to current recipient
                return (isFromCurrentRecipient && isToCurrentUser) || (isFromCurrentUser && isToCurrentRecipient);
            }
            return false;
        });
        
        // Only append messages that are not already present to avoid duplicates
        filteredMessages.forEach(message => {
            const messageIdStr = String(message.id);
            if (this.seenMessageIds.has(messageIdStr)) {
                return; // skip duplicates
            }
            
            // Use group message rendering if in group chat, otherwise use individual chat rendering
            if (this.currentGroup) {
                this.appendGroupMessage(message, container);
            } else {
                this.appendIndividualMessage(message, container);
            }
        });

        container.scrollTop = container.scrollHeight;
    },

    appendGroupMessage(message, container) {
        const isOwnMessage = message.sender_id === currentUser.id;
        const senderName = message.sender_username || message.sender_fullname || 'Unknown';
        const senderProfilePicture = message.sender_profile_picture || null;
        const senderGender = message.sender_gender || null;
        const timestamp = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        // Build attachment HTML
        let attachmentHtml = '';
        if (message.attachment_url) {
            if (message.attachment_type === 'image') {
                attachmentHtml = `
                    <div class="message-attachment">
                        <img src="${this.escapeHtml(message.attachment_url)}" alt="${this.escapeHtml(message.attachment_name || 'Image')}" class="message-image" onclick="chat.openImageModal('${this.escapeHtml(message.attachment_url)}')">
                    </div>
                `;
            } else if (message.attachment_type === 'video') {
                attachmentHtml = `
                    <div class="message-attachment">
                        <video controls class="message-video">
                            <source src="${this.escapeHtml(message.attachment_url)}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                `;
            } else {
                attachmentHtml = `
                    <div class="message-attachment">
                        <a href="${this.escapeHtml(message.attachment_url)}" download="${this.escapeHtml(message.attachment_name || 'file')}" class="message-file">
                            <span class="file-icon">📎</span>
                            <span class="file-name">${this.escapeHtml(message.attachment_name || 'File')}</span>
                            ${message.attachment_size ? `<span class="file-size">${this.formatFileSize(message.attachment_size)}</span>` : ''}
                        </a>
                    </div>
                `;
            }
        }
        
        // Profile picture HTML
        const profilePictureHtml = senderProfilePicture 
            ? `<img src="${this.escapeHtml(senderProfilePicture)}" alt="${this.escapeHtml(senderName)}" onerror="this.parentElement.innerHTML='<div class=\\'message-avatar-placeholder\\'>${senderName.charAt(0).toUpperCase()}</div>'">`
            : `<div class="message-avatar-placeholder">${senderName.charAt(0).toUpperCase()}</div>`;
        
        // Verified badge for non-guest senders
        const senderVerifiedBadge = !isOwnMessage && message.sender_is_guest === false ? 
            `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </span>` : '';
        
        const messageWrapper = document.createElement('div');
        messageWrapper.className = `group-message-wrapper ${isOwnMessage ? 'group-message-own' : 'group-message-other'}`;
        messageWrapper.style.opacity = '0';
        messageWrapper.style.transform = 'translateY(10px)';
        
        if (isOwnMessage) {
            messageWrapper.innerHTML = `
                <div class="group-message-content">
                    <div class="message message-own">
                        <div class="message-header">
                            <span class="message-time">${timestamp}</span>
                        </div>
                        ${attachmentHtml}
                        ${message.message_text ? `<div class="message-content">${this.escapeHtml(message.message_text)}</div>` : ''}
                    </div>
                </div>
                <div class="group-message-avatar group-message-avatar-right">
                    ${profilePictureHtml}
                </div>
            `;
        } else {
            messageWrapper.innerHTML = `
                <div class="group-message-avatar group-message-avatar-left">
                    ${profilePictureHtml}
                </div>
                <div class="group-message-content">
                    <div class="message-header">
                        <span class="message-sender">${this.escapeHtml(senderName)}${this.getGenderIcon(senderGender)}${senderVerifiedBadge}</span>
                        <span class="message-time">${timestamp}</span>
                    </div>
                    <div class="message message-other">
                        ${attachmentHtml}
                        ${message.message_text ? `<div class="message-content">${this.escapeHtml(message.message_text)}</div>` : ''}
                    </div>
                </div>
            `;
        }
        
        container.appendChild(messageWrapper);
        
        // Trigger fade-in animation
        requestAnimationFrame(() => {
            messageWrapper.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            messageWrapper.style.opacity = '1';
            messageWrapper.style.transform = 'translateY(0)';
        });
        
        if (message.id !== undefined && message.id !== null) {
            this.seenMessageIds.add(String(message.id));
        }
    },

    appendIndividualMessage(message, container) {
        const isOwn = message.sender_id === currentUser.id;
        const senderName = message.sender_username || 'Unknown';
        const senderGender = message.sender_gender || null;
        const timestamp = new Date(message.created_at).toLocaleTimeString();
        const hasAttachment = message.attachment_type && message.attachment_type !== 'none';
        
        let attachmentHtml = '';
        if (hasAttachment) {
            if (message.attachment_type === 'image') {
                attachmentHtml = `
                    <div class="message-attachment">
                        <img src="${this.escapeHtml(message.attachment_url)}" alt="${this.escapeHtml(message.attachment_name || 'Image')}" class="message-image" onclick="chat.openImageModal('${this.escapeHtml(message.attachment_url)}')">
                    </div>
                `;
            } else if (message.attachment_type === 'video') {
                attachmentHtml = `
                    <div class="message-attachment">
                        <video controls class="message-video">
                            <source src="${this.escapeHtml(message.attachment_url)}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                `;
            } else {
                attachmentHtml = `
                    <div class="message-attachment">
                        <a href="${this.escapeHtml(message.attachment_url)}" download="${this.escapeHtml(message.attachment_name || 'file')}" class="message-file">
                            <span class="file-icon">📎</span>
                            <span class="file-name">${this.escapeHtml(message.attachment_name || 'File')}</span>
                            ${message.attachment_size ? `<span class="file-size">${this.formatFileSize(message.attachment_size)}</span>` : ''}
                        </a>
                    </div>
                `;
            }
        }

        // Add verified badge for non-guest senders
        const senderVerifiedBadge = !isOwn && message.sender_is_guest === false ? 
            `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </span>` : '';

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isOwn ? 'message-own' : 'message-other'}`;
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateY(10px)';
        messageDiv.innerHTML = `
            <div class="message-header">
                <span class="message-sender">${this.escapeHtml(senderName)}${this.getGenderIcon(senderGender)}${senderVerifiedBadge}</span>
                <span class="message-time">${timestamp}</span>
            </div>
            ${attachmentHtml}
            ${message.message_text ? `<div class="message-content">${this.escapeHtml(message.message_text)}</div>` : ''}
        `;

        container.appendChild(messageDiv);
        
        // Trigger fade-in animation
        requestAnimationFrame(() => {
            messageDiv.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            messageDiv.style.opacity = '1';
            messageDiv.style.transform = 'translateY(0)';
        });
        
        if (message.id !== undefined && message.id !== null) {
            this.seenMessageIds.add(String(message.id));
        }
    },

    showError(message) {
        // Simple error display - can be enhanced with toast notifications
        console.error(message);
        alert(message);
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    getGenderIcon(gender) {
        if (!gender) return '';
        const icons = {
            'male': '♂',
            'female': '♀',
            'other': '⚧',
            'prefer_not_to_say': ''
        };
        const icon = icons[gender] || '';
        return icon ? `<span class="gender-icon" title="${gender.charAt(0).toUpperCase() + gender.slice(1).replace(/_/g, ' ')}">${icon}</span>` : '';
    },

    showVerifiedPopup(event) {
        if (event) {
            event.stopPropagation();
        }
        
        // Create or show modal
        let modal = document.getElementById('verifiedInfoModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'verifiedInfoModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content verified-modal-content">
                    <div class="modal-header">
                        <h3>Get Verified</h3>
                        <button class="modal-close" onclick="chat.closeVerifiedPopup()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="verified-info">
                            <div class="verified-icon-large">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <p>This user is verified because they have registered an account.</p>
                            <p class="verified-action-text">Want to get verified? <a href="index.php">Register now</a> to create your account!</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="chat.closeVerifiedPopup()">Got it</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close on background click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeVerifiedPopup();
                }
            });
        }
        
        modal.style.display = 'flex';
    },

    closeVerifiedPopup() {
        const modal = document.getElementById('verifiedInfoModal');
        if (modal) {
            modal.style.display = 'none';
        }
    },


    async updateNotifications() {
        try {
            const response = await API.getNotifications();
            if (response.success && response.data) {
                // Update unread counts
                if (response.data.unread_counts) {
                    Object.assign(this.unreadCounts, response.data.unread_counts);
                }
                
                // Update total unread conversations
                if (typeof response.data.total_unread_conversations !== 'undefined') {
                    this.totalUnreadConversations = response.data.total_unread_conversations;
                }
                
                // Update UI
                this.updateInboxTabBadge();
                this.updateUserNotificationBadges();
            }
        } catch (error) {
            console.error('Error updating notifications:', error);
        }
    },

    updateInboxTabBadge() {
        const inboxTab = document.querySelector('[data-tab="inbox"]');
        if (!inboxTab) return;
        
        // Remove existing badge
        const existingBadge = inboxTab.querySelector('.inbox-notification-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        // Add badge if there are unread conversations
        if (this.totalUnreadConversations > 0) {
            const badge = document.createElement('span');
            badge.className = 'inbox-notification-badge';
            badge.textContent = this.totalUnreadConversations > 99 ? '99+' : this.totalUnreadConversations;
            inboxTab.appendChild(badge);
        }
    },

    updateUserNotificationBadges() {
        // Update notification badges for all users in the list
        Object.keys(this.unreadCounts).forEach(userId => {
            const count = this.unreadCounts[userId];
            const userItem = document.querySelector(`[data-user-id="${userId}"]`);
            if (!userItem) return;
            
            const nameWrapper = userItem.querySelector('.user-name-wrapper');
            if (!nameWrapper) return;
            
            // Remove existing badge
            const existingBadge = nameWrapper.querySelector('.notification-badge');
            if (existingBadge) {
                existingBadge.remove();
            }
            
            // Add or update badge
            if (count > 0) {
                const badge = document.createElement('span');
                badge.className = 'notification-badge';
                badge.setAttribute('data-user-id', userId);
                badge.textContent = count > 99 ? '99+' : count;
                nameWrapper.appendChild(badge);
                userItem.classList.add('has-notification');
            } else {
                userItem.classList.remove('has-notification');
            }
        });
    },

    clearUserNotification(userId) {
        // Clear unread count for this user
        this.unreadCounts[userId] = 0;
        
        // Update UI
        const userItem = document.querySelector(`[data-user-id="${userId}"]`);
        if (userItem) {
            const badge = userItem.querySelector('.notification-badge');
            if (badge) {
                badge.remove();
            }
            userItem.classList.remove('has-notification');
        }
        
        // Recalculate total unread conversations
        this.totalUnreadConversations = Object.values(this.unreadCounts).filter(count => count > 0).length;
        this.updateInboxTabBadge();
    },

    async updateRecipientProfileHeader(userId, username, userStatus = null) {
        const recipientHeader = document.getElementById('recipientProfileHeader');
        const recipientName = document.getElementById('recipientName');
        const recipientAvatar = document.getElementById('recipientAvatar');
        const recipientAvatarPlaceholder = document.getElementById('recipientAvatarPlaceholder');
        const recipientStatusDot = document.getElementById('recipientStatusDot');
        const recipientStatusText = document.getElementById('recipientStatusText');

        // Set name immediately (will be updated with badge after profile load)
        if (recipientName) {
            recipientName.innerHTML = `<span>${username}</span>`;
        }

        // Load full profile data
        try {
            // Use user-actions API to get profile with blocking info
            const response = await API.request(`/api/user-actions.php?action=get_profile&user_id=${userId}`, {
                method: 'GET'
            });
            const userData = response.data.user || response.data;
            
            // Store profile data with proper structure
            this.currentRecipientProfile = {
                user: userData,
                status: response.data.status || userStatus || 'offline',
                blocked: response.data.blocked || false,
                has_blocked_me: response.data.has_blocked_me || false
            };
            
            // Update name with verified badge if not guest
            if (recipientName) {
                const verifiedBadge = !userData.is_guest ? 
                    `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>` : '';
                recipientName.innerHTML = `<span>${userData.username || username}</span>${this.getGenderIcon(userData.gender)}${verifiedBadge}`;
            }
            
            // Update avatar
            if (userData.profile_picture) {
                if (recipientAvatar) {
                    recipientAvatar.src = userData.profile_picture;
                    recipientAvatar.style.display = 'block';
                }
                if (recipientAvatarPlaceholder) recipientAvatarPlaceholder.style.display = 'none';
            } else {
                if (recipientAvatar) recipientAvatar.style.display = 'none';
                if (recipientAvatarPlaceholder) {
                    recipientAvatarPlaceholder.textContent = (userData.username || username).charAt(0).toUpperCase();
                    recipientAvatarPlaceholder.style.display = 'flex';
                }
            }

            // Show profile visit button
            const profileVisitBtn = document.getElementById('profileVisitBtn');
            if (profileVisitBtn) {
                profileVisitBtn.style.display = 'flex';
                profileVisitBtn.setAttribute('data-user-id', userId);
            }
            
            // Update status
            const status = userStatus || 'offline';
            if (recipientStatusDot) {
                recipientStatusDot.className = `recipient-status-dot status-${status}`;
            }
            
            const statusTexts = {
                'online': 'Online',
                'away': 'Away',
                'offline': 'Offline'
            };
            if (recipientStatusText) {
                recipientStatusText.textContent = statusTexts[status] || 'Offline';
            }
        } catch (error) {
            console.error('Error loading recipient profile:', error);
            // Fallback: show placeholder avatar
            if (recipientAvatar) recipientAvatar.style.display = 'none';
            if (recipientAvatarPlaceholder) {
                recipientAvatarPlaceholder.textContent = username.charAt(0).toUpperCase();
                recipientAvatarPlaceholder.style.display = 'flex';
            }
            if (recipientStatusDot) recipientStatusDot.className = 'recipient-status-dot status-offline';
            if (recipientStatusText) recipientStatusText.textContent = 'Offline';
            
            // Hide profile visit button on error
            const profileVisitBtn = document.getElementById('profileVisitBtn');
            if (profileVisitBtn) {
                profileVisitBtn.style.display = 'none';
            }
        }
    },

    visitProfile() {
        if (this.currentRecipient) {
            window.location.href = `profile.php?user_id=${this.currentRecipient}`;
        }
    },

    async loadGroups() {
        const container = document.getElementById('groupsList');
        if (!container) return;
        
        try {
            container.innerHTML = '<div class="loading">Loading groups...</div>';
            const response = await API.getGroups();
            if (response.success && response.data) {
                this.renderGroupsList(response.data.groups || []);
            } else {
                container.innerHTML = '<div class="error">Failed to load groups</div>';
            }
        } catch (error) {
            console.error('Error loading groups:', error);
            container.innerHTML = '<div class="error">Failed to load groups</div>';
        }
    },

    renderGroupsList(groups) {
        const container = document.getElementById('groupsList');
        if (!container) return;
        
        if (groups.length === 0) {
            container.innerHTML = `
                <div class="empty">No groups yet</div>
                <button class="btn-create-group" onclick="chat.showCreateGroupModal()" style="margin-top: var(--space-md); width: 100%; padding: var(--space-md); background: var(--color-primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer;">
                    Create Group
                </button>
            `;
            return;
        }
        
        const html = groups.map(group => `
            <div class="group-item" data-group-id="${group.id}" onclick="chat.selectGroup(${group.id})">
                <div class="group-avatar">
                    ${group.avatar ? 
                        `<img src="${this.escapeHtml(group.avatar)}" alt="${this.escapeHtml(group.name)}">` : 
                        `<div class="avatar-placeholder">${group.name.charAt(0).toUpperCase()}</div>`
                    }
                </div>
                <div class="group-info">
                    <div class="group-name">${this.escapeHtml(group.name)}</div>
                    <div class="group-meta">${group.member_count || 0} members</div>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html + `
            <button class="btn-create-group" onclick="chat.showCreateGroupModal()" style="margin-top: var(--space-md); width: 100%; padding: var(--space-md); background: var(--color-primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer;">
                + Create Group
            </button>
        `;
    },

    async selectGroup(groupId) {
        // Clear current recipient when selecting a group
        this.currentRecipient = null;
        this.currentGroup = groupId;
        
        // Hide recipient profile header, show group header
        const chatTitle = document.getElementById('chatTitle');
        const recipientHeader = document.getElementById('recipientProfileHeader');
        const profileVisitBtn = document.getElementById('profileVisitBtn');
        
        if (chatTitle) chatTitle.style.display = 'block';
        if (recipientHeader) recipientHeader.style.display = 'none';
        if (profileVisitBtn) profileVisitBtn.style.display = 'none';
        
        // Update active group in list
        document.querySelectorAll('.group-item').forEach(item => {
            item.classList.remove('active');
            if (parseInt(item.dataset.groupId) === groupId) {
                item.classList.add('active');
            }
        });
        
        // Load group info and messages
        await this.loadGroupChat(groupId);
        
        // Show message input
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInputContainer = document.getElementById('messageInputContainer');
        const emptyState = document.getElementById('emptyState');
        
        if (messagesContainer) {
            messagesContainer.classList.remove('hidden');
        }
        if (emptyState) {
            emptyState.style.display = 'none';
        }
        if (messageInputContainer) {
            messageInputContainer.classList.remove('hidden');
        }
    },

    async loadGroupChat(groupId) {
        try {
            // Load group info
            const groupResponse = await API.getGroups();
            const groups = groupResponse.data?.groups || [];
            const group = groups.find(g => g.id === groupId);
            
            if (!group) {
                this.showError('Group not found');
                return;
            }
            
            // Update chat header with group info
            const chatTitle = document.getElementById('chatTitle');
            if (chatTitle) {
                chatTitle.innerHTML = `
                    <span>${this.escapeHtml(group.name)}</span>
                    <button class="group-settings-btn" onclick="chat.showGroupSettings(${groupId})" title="Group Settings">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                        </svg>
                    </button>
                `;
                chatTitle.style.display = 'flex';
                chatTitle.style.alignItems = 'center';
                chatTitle.style.gap = 'var(--space-sm)';
            }
            
            // Store current group info
            this.currentGroupInfo = group;
            
            // Load group messages
            await this.loadGroupMessages(groupId);
        } catch (error) {
            console.error('Error loading group chat:', error);
            this.showError('Failed to load group chat');
        }
    },

    async loadGroupMessages(groupId) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        try {
            container.innerHTML = '<div class="loading">Loading messages...</div>';
            
            const response = await API.getGroupMessages(groupId, 50, 0);
            
            if (response.success && response.data) {
                const messages = response.data.messages || [];
                this.renderGroupMessages(messages);
            } else {
                container.innerHTML = '<div class="error">Failed to load messages</div>';
            }
        } catch (error) {
            console.error('Error loading group messages:', error);
            container.innerHTML = '<div class="error">Failed to load messages</div>';
        }
    },

    renderGroupMessages(messages) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        if (messages.length === 0) {
            container.innerHTML = '<div class="empty">No messages yet. Start the conversation!</div>';
            return;
        }
        
        const html = messages.map(message => {
            const isOwnMessage = message.sender_id === currentUser.id;
            const senderName = message.sender_username || message.sender_fullname || 'Unknown';
            const senderProfilePicture = message.sender_profile_picture || null;
            const senderGender = message.sender_gender || null;
            const messageDate = new Date(message.created_at);
            const timeStr = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            // Build attachment HTML
            let attachmentHtml = '';
            if (message.attachment_url) {
                if (message.attachment_type === 'image') {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <img src="${this.escapeHtml(message.attachment_url)}" alt="${this.escapeHtml(message.attachment_name || 'Image')}" class="message-image" onclick="chat.openImageModal('${this.escapeHtml(message.attachment_url)}')">
                        </div>
                    `;
                } else if (message.attachment_type === 'video') {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <video controls class="message-video">
                                <source src="${this.escapeHtml(message.attachment_url)}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    `;
                } else {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <a href="${this.escapeHtml(message.attachment_url)}" download="${this.escapeHtml(message.attachment_name || 'file')}" class="message-file">
                                <span class="file-icon">📎</span>
                                <span class="file-name">${this.escapeHtml(message.attachment_name || 'File')}</span>
                                ${message.attachment_size ? `<span class="file-size">${this.formatFileSize(message.attachment_size)}</span>` : ''}
                            </a>
                        </div>
                    `;
                }
            }
            
            // Profile picture HTML
            const profilePictureHtml = senderProfilePicture 
                ? `<img src="${this.escapeHtml(senderProfilePicture)}" alt="${this.escapeHtml(senderName)}" onerror="this.parentElement.innerHTML='<div class=\\'message-avatar-placeholder\\'>${senderName.charAt(0).toUpperCase()}</div>'">`
                : `<div class="message-avatar-placeholder">${senderName.charAt(0).toUpperCase()}</div>`;
            
            // Verified badge for non-guest senders
            const senderVerifiedBadge = !isOwnMessage && message.sender_is_guest === false ? 
                `<span class="verified-badge" title="Verified" onclick="event.stopPropagation(); chat.showVerifiedPopup(event)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </span>` : '';
            
            if (isOwnMessage) {
                // Current user's messages on the right
                return `
                    <div class="group-message-wrapper group-message-own">
                        <div class="group-message-content">
                            <div class="message ${isOwnMessage ? 'message-own' : 'message-other'}">
                                <div class="message-header">
                                    <span class="message-time">${timeStr}</span>
                                </div>
                                ${attachmentHtml}
                                ${message.message_text ? `<div class="message-content">${this.escapeHtml(message.message_text)}</div>` : ''}
                            </div>
                        </div>
                        <div class="group-message-avatar group-message-avatar-right">
                            ${profilePictureHtml}
                        </div>
                    </div>
                `;
            } else {
                // Other members' messages on the left
                return `
                    <div class="group-message-wrapper group-message-other">
                        <div class="group-message-avatar group-message-avatar-left">
                            ${profilePictureHtml}
                        </div>
                        <div class="group-message-content">
                            <div class="message-header">
                                <span class="message-sender">${this.escapeHtml(senderName)}${this.getGenderIcon(senderGender)}${senderVerifiedBadge}</span>
                                <span class="message-time">${timeStr}</span>
                            </div>
                            <div class="message ${isOwnMessage ? 'message-own' : 'message-other'}">
                                ${attachmentHtml}
                                ${message.message_text ? `<div class="message-content">${this.escapeHtml(message.message_text)}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }
        }).join('');
        
        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    },

    showCreateGroupModal() {
        const name = prompt('Enter group name:');
        if (name && name.trim()) {
            const description = prompt('Enter group description (optional):') || '';
            this.createGroup(name.trim(), description.trim());
        }
    },

    async createGroup(name, description = '') {
        try {
            const response = await API.createGroup(name, description);
            if (response.success) {
                this.loadGroups();
            } else {
                alert('Failed to create group: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error creating group:', error);
            alert('Failed to create group');
        }
    },

    async toggleRecipientProfile(event) {
        if (event) {
            event.stopPropagation();
        }
        
        console.log('[toggleRecipientProfile] Toggling dropdown, current state:', this.recipientProfileDropdownOpen);
        
        const dropdown = document.getElementById('recipientProfileDropdown');
        if (!dropdown) {
            console.error('[toggleRecipientProfile] Dropdown element not found');
            return;
        }
        
        this.recipientProfileDropdownOpen = !this.recipientProfileDropdownOpen;
        
        if (this.recipientProfileDropdownOpen) {
            console.log('[toggleRecipientProfile] Opening dropdown, loading profile...');
            await this.loadRecipientProfile();
            dropdown.classList.add('active');
            console.log('[toggleRecipientProfile] Dropdown opened, active class added');
        } else {
            console.log('[toggleRecipientProfile] Closing dropdown');
            dropdown.classList.remove('active');
        }
    },

    async loadRecipientProfile() {
        if (!this.currentRecipient) {
            console.error('[loadRecipientProfile] No current recipient');
            return;
        }

        // If profile data doesn't exist, fetch it
        if (!this.currentRecipientProfile) {
            try {
                console.log('[loadRecipientProfile] Fetching profile for user:', this.currentRecipient);
                // Use user-actions API to get profile with blocking info
                const response = await API.request(`/api/user-actions.php?action=get_profile&user_id=${this.currentRecipient}`, {
                    method: 'GET'
                });
                const userData = response.data.user || response.data;
                this.currentRecipientProfile = {
                    user: userData,
                    status: response.data.status || 'offline',
                    blocked: response.data.blocked || false,
                    has_blocked_me: response.data.has_blocked_me || false
                };
            } catch (error) {
                console.error('[loadRecipientProfile] Error fetching profile:', error);
                return;
            }
        }

        const content = document.getElementById('recipientProfileContent');
        if (!content) {
            console.error('[loadRecipientProfile] Content element not found');
            return;
        }

        const profile = this.currentRecipientProfile;
        // Handle both structures: { user: {...} } and direct user object
        const user = profile.user || profile;
        const isBlocked = profile.blocked || profile.is_blocked_by_current_user || false;
        const hasBlockedMe = profile.has_blocked_me || profile.has_blocked_current_user || false;

        // Format join date
        const joinDate = new Date(user.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        content.innerHTML = `
            <div class="recipient-profile-header-section">
                <div class="recipient-profile-avatar-large">
                    ${user.profile_picture ? 
                        `<img src="${this.escapeHtml(user.profile_picture)}" alt="${this.escapeHtml(user.username)}">` : 
                        `<div class="avatar-placeholder-large">${user.username.charAt(0).toUpperCase()}</div>`
                    }
                    <span class="recipient-status-dot-large status-${(profile.status || 'offline')}"></span>
                </div>
                <div class="recipient-profile-info">
                    <h3 class="recipient-profile-name">${this.escapeHtml(user.username)}</h3>
                    <p class="recipient-profile-status">${this.getStatusText(profile.status || 'offline')}</p>
                    ${user.bio ? `<p class="recipient-profile-bio">${this.escapeHtml(user.bio)}</p>` : ''}
                    <p class="recipient-profile-join-date">Joined ${joinDate}</p>
                </div>
            </div>
            ${hasBlockedMe ? `
                <div class="recipient-profile-alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>This user has blocked you</span>
                </div>
            ` : ''}
            <div class="recipient-profile-actions">
                <button class="recipient-action-btn" onclick="chat.openReportModal(${user.id})" ${hasBlockedMe ? 'disabled' : ''}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                        <line x1="4" y1="22" x2="4" y2="15"></line>
                    </svg>
                    <span>Report User</span>
                </button>
                <button class="recipient-action-btn ${isBlocked ? 'blocked' : ''}" onclick="chat.toggleBlockUser(${user.id}, ${isBlocked})">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        ${isBlocked ? 
                            `<path d="M9 12l2 2 4-4"></path>
                             <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3z"></path>
                             <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3z"></path>` :
                            `<path d="M18 6L6 18M6 6l12 12"></path>`
                        }
                    </svg>
                    <span>${isBlocked ? 'Unblock User' : 'Block User'}</span>
                </button>
            </div>
        `;
    },

    getStatusText(status) {
        const statusTexts = {
            'online': 'Online',
            'away': 'Away',
            'offline': 'Offline'
        };
        return statusTexts[status] || 'Offline';
    },

    async toggleBlockUser(userId, currentlyBlocked) {
        try {
            if (currentlyBlocked) {
                await API.unblockUser(userId);
                alert('User unblocked successfully');
            } else {
                if (confirm('Are you sure you want to block this user? You won\'t be able to send or receive messages from them.')) {
                    await API.blockUser(userId);
                    alert('User blocked successfully');
                } else {
                    return;
                }
            }
            
            // Reload profile to update blocked status
            if (this.currentRecipient === userId) {
                const response = await API.getUserProfile(userId);
                this.currentRecipientProfile = response.data;
                await this.loadRecipientProfile();
            }
        } catch (error) {
            alert(error.message || 'Failed to update block status');
        }
    },

    openReportModal(userId) {
        const reason = prompt('Select reason:\n1. Spam\n2. Harassment\n3. Inappropriate Content\n4. Fake Account\n5. Other\n\nEnter number (1-5):');
        if (!reason) return;

        const reasons = {
            '1': 'spam',
            '2': 'harassment',
            '3': 'inappropriate',
            '4': 'fake_account',
            '5': 'other'
        };

        const selectedReason = reasons[reason];
        if (!selectedReason) {
            alert('Invalid reason selected');
            return;
        }

        const description = prompt('Additional details (optional):');
        this.reportUser(userId, selectedReason, description || null);
    },

    async reportUser(userId, reason, description = null) {
        try {
            await API.reportUser(userId, reason, description);
            alert('User reported successfully. Thank you for keeping our community safe.');
            // Close dropdown
            const dropdown = document.getElementById('recipientProfileDropdown');
            if (dropdown) dropdown.classList.remove('active');
            this.recipientProfileDropdownOpen = false;
        } catch (error) {
            alert(error.message || 'Failed to report user');
        }
    },

    async showGroupSettings(groupId) {
        if (!this.currentGroupInfo) {
            const groupResponse = await API.getGroups();
            const groups = groupResponse.data?.groups || [];
            this.currentGroupInfo = groups.find(g => g.id === groupId);
        }
        
        if (!this.currentGroupInfo) {
            alert('Group not found');
            return;
        }
        
        // Load group members
        const membersResponse = await API.getGroupMembers(groupId);
        const members = membersResponse.success ? membersResponse.data.members : [];
        
        // Check user's role
        const userRole = members.find(m => m.id === currentUser.id)?.role || 'member';
        const isGroupAdmin = userRole === 'admin' || this.currentGroupInfo.created_by === currentUser.id || currentUser.is_admin;
        
        // Create settings modal
        this.createGroupSettingsModal(groupId, this.currentGroupInfo, members, isGroupAdmin);
    },

    createGroupSettingsModal(groupId, group, members, isAdmin) {
        // Remove existing modal if any
        const existingModal = document.getElementById('groupSettingsModal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.id = 'groupSettingsModal';
        modal.className = 'modal';
        modal.style.display = 'flex';
        
        const membersHtml = members.map(member => {
            const isMemberAdmin = member.role === 'admin';
            const isCurrentUser = member.id === currentUser.id;
            const canManage = isAdmin && !isCurrentUser;
            
            return `
                <div class="group-member-item">
                    <div class="member-info">
                        <div class="member-avatar">
                            ${member.profile_picture ? 
                                `<img src="${this.escapeHtml(member.profile_picture)}" alt="${this.escapeHtml(member.fullname || member.username)}">` :
                                `<div class="avatar-placeholder">${(member.fullname || member.username).charAt(0).toUpperCase()}</div>`
                            }
                        </div>
                        <div class="member-details">
                            <div class="member-name">${this.escapeHtml(member.fullname || member.username)}${isMemberAdmin ? ' <span style="color: var(--color-primary);">(Admin)</span>' : ''}</div>
                            <div class="member-username">@${this.escapeHtml(member.username)}</div>
                        </div>
                    </div>
                    ${canManage ? `
                        <div class="member-actions">
                            ${!isMemberAdmin ? `<button class="btn btn-sm btn-primary" onclick="chat.setGroupAdmin(${groupId}, ${member.id}, true)">Make Admin</button>` : ''}
                            <button class="btn btn-sm btn-danger" onclick="chat.removeUserFromGroup(${groupId}, ${member.id})">Remove</button>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
        
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 600px; max-height: 80vh; overflow-y: auto;">
                <div class="modal-header">
                    <h3>Group Settings: ${this.escapeHtml(group.name)}</h3>
                    <button class="modal-close" onclick="chat.closeGroupSettingsModal()">×</button>
                </div>
                <div class="modal-body">
                    ${isAdmin ? `
                        <div class="group-settings-section">
                            <h4>Group Information</h4>
                            <div class="form-group">
                                <label>Group Name</label>
                                <input type="text" id="groupNameInput" value="${this.escapeHtml(group.name)}" class="form-input">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea id="groupDescriptionInput" class="form-input" rows="3">${this.escapeHtml(group.description || '')}</textarea>
                            </div>
                            <button class="btn btn-primary" onclick="chat.updateGroupSettings(${groupId})">Save Changes</button>
                        </div>
                        
                        <div class="group-settings-section">
                            <h4>Add Members</h4>
                            <div class="form-group" style="position: relative;">
                                <input type="text" id="addUserSearchInput" placeholder="Search users to add..." class="form-input" onkeyup="chat.searchUsersForGroup(event, ${groupId})">
                                <div id="userSearchResults" class="user-search-results" style="display: none;"></div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="group-settings-section">
                        <h4>Members (${members.length})</h4>
                        <div class="group-members-list">
                            ${membersHtml || '<div class="empty">No members</div>'}
                        </div>
                    </div>
                    
                    ${isAdmin ? `
                        <div class="group-settings-section">
                            <h4>Danger Zone</h4>
                            <button class="btn btn-danger" onclick="chat.deleteGroup(${groupId})">Delete Group</button>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
    },

    closeGroupSettingsModal() {
        const modal = document.getElementById('groupSettingsModal');
        if (modal) {
            modal.remove();
            document.body.style.overflow = '';
        }
    },

    async searchUsersForGroup(event, groupId) {
        if (event.key === 'Enter') return;
        
        const query = event.target.value.trim();
        const resultsContainer = document.getElementById('userSearchResults');
        
        if (!query || query.length < 2) {
            if (resultsContainer) resultsContainer.style.display = 'none';
            return;
        }
        
        try {
            const response = await API.getOnlineUsers();
            if (response.success && response.data) {
                const allUsers = response.data.users || [];
                // Get current group members to exclude them
                const membersResponse = await API.getGroupMembers(groupId);
                const memberIds = membersResponse.success ? (membersResponse.data.members || []).map(m => m.id) : [];
                
                const filtered = allUsers.filter(user => {
                    const searchText = (user.username + ' ' + (user.fullname || '')).toLowerCase();
                    return searchText.includes(query.toLowerCase()) && 
                           user.id !== currentUser.id && 
                           !memberIds.includes(user.id);
                });
                
                if (resultsContainer) {
                    if (filtered.length > 0) {
                        resultsContainer.innerHTML = filtered.slice(0, 5).map(user => `
                            <div class="user-search-result-item" onclick="chat.addUserToGroup(${groupId}, ${user.id})">
                                <div class="user-avatar-small">
                                    ${user.profile_picture ? 
                                        `<img src="${this.escapeHtml(user.profile_picture)}" alt="${this.escapeHtml(user.username)}">` :
                                        `<div class="avatar-placeholder">${user.username.charAt(0).toUpperCase()}</div>`
                                    }
                                </div>
                                <div class="user-info-small">
                                    <div class="user-name-small">${this.escapeHtml(user.fullname || user.username)}</div>
                                    <div class="user-username-small">@${this.escapeHtml(user.username)}</div>
                                </div>
                            </div>
                        `).join('');
                        resultsContainer.style.display = 'block';
                    } else {
                        resultsContainer.innerHTML = '<div class="empty">No users found</div>';
                        resultsContainer.style.display = 'block';
                    }
                }
            }
        } catch (error) {
            console.error('Error searching users:', error);
        }
    },

    async addUserToGroup(groupId, userId) {
        try {
            const response = await API.addUserToGroup(groupId, userId);
            if (response.success) {
                // Close search results
                const resultsContainer = document.getElementById('userSearchResults');
                const searchInput = document.getElementById('addUserSearchInput');
                if (resultsContainer) resultsContainer.style.display = 'none';
                if (searchInput) searchInput.value = '';
                
                // Reload group settings
                await this.showGroupSettings(groupId);
            } else {
                alert('Failed to add user: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error adding user to group:', error);
            alert('Failed to add user to group');
        }
    },

    async removeUserFromGroup(groupId, userId) {
        if (!confirm('Are you sure you want to remove this user from the group?')) {
            return;
        }
        
        try {
            const response = await API.removeUserFromGroup(groupId, userId);
            if (response.success) {
                // Reload group settings
                await this.showGroupSettings(groupId);
                // If removed self, reload groups list
                if (userId === currentUser.id) {
                    this.currentGroup = null;
                    this.loadGroups();
                }
            } else {
                alert('Failed to remove user: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error removing user from group:', error);
            alert('Failed to remove user from group');
        }
    },

    async setGroupAdmin(groupId, userId, isAdmin) {
        try {
            const response = await API.setGroupAdmin(groupId, userId, isAdmin);
            if (response.success) {
                await this.showGroupSettings(groupId);
            } else {
                alert('Failed to update admin status: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error setting group admin:', error);
            alert('Failed to update admin status');
        }
    },

    async updateGroupSettings(groupId) {
        const nameInput = document.getElementById('groupNameInput');
        const descriptionInput = document.getElementById('groupDescriptionInput');
        
        if (!nameInput || !nameInput.value.trim()) {
            alert('Group name is required');
            return;
        }
        
        try {
            const response = await API.updateGroup(
                groupId,
                nameInput.value.trim(),
                descriptionInput.value.trim()
            );
            
            if (response.success) {
                alert('Group settings updated successfully');
                this.currentGroupInfo = response.data.group;
                // Update header
                const chatTitle = document.getElementById('chatTitle');
                if (chatTitle) {
                    chatTitle.innerHTML = `
                        <span>${this.escapeHtml(response.data.group.name)}</span>
                        <button class="group-settings-btn" onclick="chat.showGroupSettings(${groupId})" title="Group Settings">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                            </svg>
                        </button>
                    `;
                }
                await this.showGroupSettings(groupId);
            } else {
                alert('Failed to update group: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating group:', error);
            alert('Failed to update group settings');
        }
    },

    async deleteGroup(groupId) {
        if (!confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await API.deleteGroup(groupId);
            if (response.success) {
                alert('Group deleted successfully');
                this.currentGroup = null;
                this.loadGroups();
                // Reset chat view
                const chatTitle = document.getElementById('chatTitle');
                const messagesContainer = document.getElementById('messagesContainer');
                const messageInputContainer = document.getElementById('messageInputContainer');
                if (chatTitle) chatTitle.textContent = 'Select a user to start chatting';
                if (messagesContainer) messagesContainer.innerHTML = '';
                if (messageInputContainer) messageInputContainer.classList.add('hidden');
            } else {
                alert('Failed to delete group: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting group:', error);
            alert('Failed to delete group');
        }
    }
};

