const API = {
    baseUrl: '',

    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include'
        };

        const config = { ...defaultOptions, ...options };
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            console.log('[API.request] Making request to:', url);
            const response = await fetch(url, config);
            console.log('[API.request] Response status:', response.status, response.statusText);
            
            // Read body as text once (avoids "body stream already read" errors when
            // response.json() throws after partially consuming the stream).
            const rawText = await response.text();
            console.log('[API.request] Raw response text:', rawText.substring(0, 200));
            
            let data = null;
            if (rawText) {
                try {
                    data = JSON.parse(rawText);
                    console.log('[API.request] Parsed JSON:', data);
                } catch (err) {
                    console.error('[API.request] JSON parse error:', err);
                    throw new Error(rawText || 'Invalid JSON response from server');
                }
            }

            if (!response.ok) {
                console.error('[API.request] Response not OK:', response.status, data);
                throw new Error(data?.error || data?.message || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('[API.request] Exception:', error);
            console.error('[API.request] URL:', url);
            throw error;
        }
    },

    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    },

    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: data
        });
    },

    // Auth endpoints
    async register(username, email, password) {
        return this.post('/api/register.php', { username, email, password });
    },

    async login(email, password) {
        return this.post('/api/login.php', { email, password });
    },

    async guestLogin(username, age, gender) {
        return this.post('/api/guest-login.php', { username, age, gender });
    },

    async logout() {
        return this.post('/api/logout.php');
    },

    // Message endpoints
    async sendMessage(recipientId, messageText, attachmentType = null, attachmentUrl = null, attachmentName = null, attachmentSize = null) {
        const data = {
            action: 'send',
            recipient_id: recipientId
        };
        
        if (messageText) {
            data.message_text = messageText;
        }
        
        if (attachmentType && attachmentUrl) {
            data.attachment_type = attachmentType;
            data.attachment_url = attachmentUrl;
            data.attachment_name = attachmentName;
            data.attachment_size = attachmentSize;
        }
        
        return this.post('/api/messages.php', data);
    },

    async pollMessages(lastMessageId = null) {
        const params = { action: 'poll' };
        if (lastMessageId) {
            params.last_message_id = lastMessageId;
        }
        return this.get('/api/messages.php', params);
    },

    async getConversation(userId, limit = 50, offset = 0) {
        return this.get('/api/messages.php', {
            action: 'get_conversation',
            user_id: userId,
            limit,
            offset
        });
    },

    async deleteMessage(messageId) {
        return this.post('/api/messages.php', {
            action: 'delete',
            message_id: messageId
        });
    },

    // User endpoints
    async getOnlineUsers() {
        return this.get('/api/users.php', { action: 'online' });
    },

    async getUserProfile(userId) {
        return this.get('/api/users.php', { action: 'profile', id: userId });
    },

    async updateProfile(data) {
        return this.post('/api/users.php', {
            action: 'update',
            ...data
        });
    },

    // Settings endpoints (admin only)
    async getSettings() {
        return this.get('/api/settings.php');
    },

    async updateSettings(data) {
        return this.post('/api/settings.php', data);
    },

    // User actions endpoints
    async getUserProfile(userId) {
        const response = await this.request(`/api/user-actions.php?action=get_profile&user_id=${userId}`, {
            method: 'GET'
        });
        return response;
    },

    async blockUser(userId) {
        const response = await this.request('/api/user-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'block',
                user_id: userId
            })
        });
        return response;
    },

    async unblockUser(userId) {
        const response = await this.request('/api/user-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'unblock',
                user_id: userId
            })
        });
        return response;
    },

    async reportUser(userId, reason, description = null) {
        const response = await this.request('/api/user-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'report',
                user_id: userId,
                reason: reason,
                description: description
            })
        });
        return response;
    },

    async checkBlocked(userId) {
        const response = await this.request(`/api/user-actions.php?action=is_blocked&user_id=${userId}`, {
            method: 'GET'
        });
        return response;
    },

    // Admin endpoints
    async updateReportStatus(reportId, status) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_report_status',
                report_id: reportId,
                status: status
            })
        });
        return response;
    },

    async getReportDetails(reportId) {
        const response = await this.request(`/api/admin.php?action=get_report_details&report_id=${reportId}`, {
            method: 'GET'
        });
        return response;
    },

    async blockIP(ipAddress, duration, reason = null) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'block_ip',
                ip_address: ipAddress,
                duration: duration,
                reason: reason
            })
        });
        return response;
    },

    async unblockIP(ipAddress) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'unblock_ip',
                ip_address: ipAddress
            })
        });
        return response;
    },

    async clearCooldown(actionType, actionIdentifier) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'clear_cooldown',
                action_type: actionType,
                action_identifier: actionIdentifier
            })
        });
        return response;
    },

    // SMTP endpoints
    async updateSmtpSettings(data) {
        const response = await this.request('/api/smtp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update',
                ...data
            })
        });
        return response;
    },

    async testSmtp() {
        const response = await this.request('/api/smtp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'test'
            })
        });
        return response;
    },

    async sendEmail(data) {
        const response = await this.request('/api/smtp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'send',
                ...data
            })
        });
        return response;
    },

    // Admin user management endpoints
    async getUsers(limit = 100, offset = 0, includeGuests = false) {
        const params = {
            action: 'get_users',
            limit: limit,
            offset: offset
        };
        if (includeGuests) {
            params.include_guests = 'true';
        }
        const queryString = new URLSearchParams(params).toString();
        return this.request(`/api/admin.php?${queryString}`, { method: 'GET' });
    },

    async banUser(userId) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'ban_user',
                user_id: userId
            })
        });
        return response;
    },

    async unbanUser(userId) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'unban_user',
                user_id: userId
            })
        });
        return response;
    },

    async deleteUser(userId) {
        const response = await this.request('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete_user',
                user_id: userId
            })
        });
        return response;
    },

    // Stories endpoints
    async getStories() {
        return this.get('/api/stories.php', { action: 'get' });
    },

    async createStory(mediaType, mediaUrl, text = null) {
        return this.post('/api/stories.php', {
            action: 'create',
            media_type: mediaType,
            media_url: mediaUrl,
            text: text
        });
    },

    async starStory(storyId) {
        return this.post('/api/stories.php', {
            action: 'star',
            story_id: storyId
        });
    },

    async replyToStory(storyId, content) {
        return this.post('/api/stories.php', {
            action: 'reply',
            story_id: storyId,
            content: content
        });
    },

    async getStoryReactions(storyId, type = null) {
        const params = { action: 'get_reactions', story_id: storyId };
        if (type) params.type = type;
        return this.get('/api/stories.php', params);
    },

    async deleteStory(storyId) {
        return this.post('/api/stories.php', {
            action: 'delete',
            story_id: storyId
        });
    },

    // Email verification endpoints
    async resendVerificationEmail() {
        return this.post('/api/verify-email.php', {
            action: 'resend'
        });
    },

    async verifyEmail(token) {
        return this.post('/api/verify-email.php', {
            action: 'verify',
            token: token
        });
    },

    // Posts endpoints
    async createPost(content, mediaType = 'text', mediaUrl = null, mediaName = null) {
        return this.post('/api/posts.php', {
            action: 'create',
            content: content,
            media_type: mediaType,
            media_url: mediaUrl,
            media_name: mediaName
        });
    },

    async getPosts(userId = null, limit = 50, offset = 0) {
        const params = {
            action: 'list',
            limit: limit,
            offset: offset
        };
        if (userId) {
            params.user_id = userId;
        }
        return this.get('/api/posts.php', params);
    },

    async deletePost(postId) {
        return this.post('/api/posts.php', {
            action: 'delete',
            post_id: postId
        });
    },

    // Comments endpoints
    async createComment(postId, content) {
        return this.post('/api/comments.php', {
            action: 'create',
            post_id: postId,
            content: content
        });
    },

    async getComments(postId) {
        return this.get('/api/comments.php', {
            action: 'list',
            post_id: postId
        });
    },

    async deleteComment(commentId) {
        return this.post('/api/comments.php', {
            action: 'delete',
            comment_id: commentId
        });
    },

    // Reactions endpoints
    async toggleReaction(postId, reactionType = 'star') {
        return this.post('/api/reactions.php', {
            action: 'toggle',
            post_id: postId,
            reaction_type: reactionType
        });
    },

    async getReactions(postId, reactionType = 'star') {
        return this.get('/api/reactions.php', {
            action: 'get',
            post_id: postId,
            reaction_type: reactionType
        });
    },

    // Groups endpoints
    async getGroups() {
        return this.get('/api/groups.php', { action: 'list' });
    },

    async createGroup(name, description = '') {
        return this.post('/api/groups.php', {
            action: 'create',
            name: name,
            description: description
        });
    },

    async joinGroup(groupId) {
        return this.post('/api/groups.php', {
            action: 'join',
            group_id: groupId
        });
    },

    async leaveGroup(groupId) {
        return this.post('/api/groups.php', {
            action: 'leave',
            group_id: groupId
        });
    },

    async getGroupMessages(groupId, limit = 50, offset = 0) {
        return this.get('/api/groups.php', {
            action: 'messages',
            group_id: groupId,
            limit: limit,
            offset: offset
        });
    },

    async sendGroupMessage(groupId, messageText, attachmentType = null, attachmentUrl = null) {
        return this.post('/api/groups.php', {
            action: 'send_message',
            group_id: groupId,
            message_text: messageText,
            attachment_type: attachmentType,
            attachment_url: attachmentUrl
        });
    },

    async getGroupMembers(groupId) {
        return this.get('/api/groups.php', {
            action: 'members',
            group_id: groupId
        });
    },

    async addUserToGroup(groupId, userId) {
        return this.post('/api/groups.php', {
            action: 'add_user',
            group_id: groupId,
            user_id: userId
        });
    },

    async removeUserFromGroup(groupId, userId) {
        return this.post('/api/groups.php', {
            action: 'remove_user',
            group_id: groupId,
            user_id: userId
        });
    },

    async updateGroup(groupId, name, description, avatar = null) {
        return this.post('/api/groups.php', {
            action: 'update',
            group_id: groupId,
            name: name,
            description: description,
            avatar: avatar
        });
    },

    async deleteGroup(groupId) {
        return this.post('/api/groups.php', {
            action: 'delete',
            group_id: groupId
        });
    },

    async setGroupAdmin(groupId, userId, isAdmin) {
        return this.post('/api/groups.php', {
            action: 'set_admin',
            group_id: groupId,
            user_id: userId,
            is_admin: isAdmin
        });
    },

    // Friend requests endpoints
    async sendFriendRequest(userId) {
        return this.post('/api/friends.php', {
            action: 'send_request',
            user_id: userId
        });
    },

    async acceptFriendRequest(requestId) {
        return this.post('/api/friends.php', {
            action: 'accept',
            request_id: requestId
        });
    },

    async rejectFriendRequest(requestId) {
        return this.post('/api/friends.php', {
            action: 'reject',
            request_id: requestId
        });
    },

    async getFriendRequests() {
        return this.get('/api/friends.php', { action: 'requests' });
    },

    async getFriends(userId = null) {
        return this.get('/api/friends.php', {
            action: 'list',
            user_id: userId
        });
    },

    async removeFriend(userId) {
        return this.post('/api/friends.php', {
            action: 'remove',
            user_id: userId
        });
    }
};

