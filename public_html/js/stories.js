/**
 * Stories Carousel Module
 * Handles stories display, auto-scrolling, interactions
 */
const stories = {
    stories: [],
    currentIndex: 0,
    autoScrollInterval: null,
    autoScrollDelay: 5000, // 5 seconds per story
    isPaused: false,
    isUserInteracting: false,

    init() {
        this.loadStories();
        this.setupEventListeners();
    },

    async loadStories() {
        try {
            const response = await API.getStories();
            if (response.success && response.data.stories) {
                // Group stories by user_id
                const storiesByUser = {};
                response.data.stories.forEach(story => {
                    // Stories from API should have user_id field from the database
                    const userId = story.user_id;
                    if (!userId) {
                        console.warn('Story missing user_id:', story);
                        return; // Skip stories without user_id
                    }
                    if (!storiesByUser[userId]) {
                        storiesByUser[userId] = [];
                    }
                    storiesByUser[userId].push(story);
                });
                
                // Sort stories within each user group by created_at (newest first)
                Object.keys(storiesByUser).forEach(userId => {
                    storiesByUser[userId].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                });
                
                // Store grouped stories
                this.storiesByUser = storiesByUser;
                
                // Create array of user story groups for rendering (one entry per user)
                this.storyGroups = Object.keys(storiesByUser).map(userId => ({
                    userId: userId,
                    userInfo: {
                        username: storiesByUser[userId][0].username,
                        profile_picture: storiesByUser[userId][0].profile_picture,
                        user_id: userId
                    },
                    stories: storiesByUser[userId] // All stories from this user
                }));
                
                this.render();
                if (this.storyGroups.length > 0) {
                    this.startAutoScroll();
                }
            }
        } catch (error) {
            console.error('Error loading stories:', error);
        }
    },

    render() {
        const container = document.getElementById('storiesCarousel');
        if (!container) return;

        if (!this.storyGroups || this.storyGroups.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = this.storyGroups.map((group, index) => this.renderStoryGroup(group, index)).join('');
        
        // Add click handlers
        this.storyGroups.forEach((group, index) => {
            const storyElement = container.querySelector(`[data-user-id="${group.userId}"]`);
            if (storyElement) {
                storyElement.addEventListener('click', () => this.openStoryViewer(index));
            }
        });
    },

    renderStoryGroup(group, index) {
        const isActive = index === this.currentIndex;
        const userInfo = group.userInfo;
        
        // For circular story cards, show only the avatar (use the first/latest story's profile picture)
        const avatar = userInfo.profile_picture 
            ? `<img src="${this.escapeHtml(userInfo.profile_picture)}" alt="${this.escapeHtml(userInfo.username)}" class="story-circle-avatar">`
            : `<div class="story-circle-avatar-placeholder">${userInfo.username.charAt(0).toUpperCase()}</div>`;

        // Show badge if user has multiple stories
        const badge = group.stories.length > 1 ? `<span class="story-count-badge">${group.stories.length}</span>` : '';

        return `
            <div class="story-circle-item ${isActive ? 'active' : ''}" data-user-id="${group.userId}" data-index="${index}" title="${this.escapeHtml(userInfo.username)} (${group.stories.length} ${group.stories.length === 1 ? 'story' : 'stories'})">
                ${avatar}
                ${badge}
            </div>
        `;
    },

    startAutoScroll() {
        this.stopAutoScroll();
        if (!this.storyGroups || this.storyGroups.length <= 1) return;

        this.autoScrollInterval = setInterval(() => {
            if (!this.isPaused && !this.isUserInteracting) {
                this.scrollToNext();
            }
        }, this.autoScrollDelay);
    },

    stopAutoScroll() {
        if (this.autoScrollInterval) {
            clearInterval(this.autoScrollInterval);
            this.autoScrollInterval = null;
        }
    },

    next() {
        if (!this.storyGroups || this.storyGroups.length === 0) return;
        this.currentIndex = (this.currentIndex + 1) % this.storyGroups.length;
        this.render();
    },

    prev() {
        if (!this.storyGroups || this.storyGroups.length === 0) return;
        this.currentIndex = (this.currentIndex - 1 + this.storyGroups.length) % this.storyGroups.length;
        this.render();
    },

    goTo(index) {
        if (this.storyGroups && index >= 0 && index < this.storyGroups.length) {
            this.currentIndex = index;
            this.render();
        }
    },

    setupEventListeners() {
        const container = document.getElementById('storiesCarousel');
        if (!container) return;

        // Pause auto-scroll on hover
        container.addEventListener('mouseenter', () => {
            this.isPaused = true;
        });

        container.addEventListener('mouseleave', () => {
            this.isPaused = false;
        });

        // Manual scroll detection
        let scrollTimeout;
        container.addEventListener('scroll', () => {
            this.isUserInteracting = true;
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.isUserInteracting = false;
            }, 2000);
        });
    },

    async toggleStar(storyId, event) {
        if (event) {
            event.stopPropagation();
        }

        try {
            const story = this.stories.find(s => s.id === storyId);
            if (!story) return;

            await API.starStory(storyId);
            
            // Update local state
            story.has_starred = !story.has_starred;
            story.star_count = story.has_starred ? (story.star_count || 0) + 1 : Math.max(0, (story.star_count || 1) - 1);
            
            this.render();
        } catch (error) {
            console.error('Error toggling star:', error);
            alert('Failed to star story: ' + (error.message || 'Unknown error'));
        }
    },

    async showReplyModal(storyId, event) {
        if (event) {
            event.stopPropagation();
        }

        const content = prompt('Reply to this story:');
        if (!content || !content.trim()) return;

        try {
            await API.replyToStory(storyId, content.trim());
            
            // Update local state
            const story = this.stories.find(s => s.id === storyId);
            if (story) {
                story.reply_count = (story.reply_count || 0) + 1;
                this.render();
            }
            
            alert('Reply sent!');
        } catch (error) {
            console.error('Error replying to story:', error);
            alert('Failed to send reply: ' + (error.message || 'Unknown error'));
        }
    },

    scrollToNext() {
        const container = document.getElementById('storiesCarousel');
        if (!this.storyGroups || this.storyGroups.length === 0) return;

        // Move to next story group (user)
        this.currentIndex = (this.currentIndex + 1) % this.storyGroups.length;
        
        // Scroll to show the active story
        const storyElement = container.querySelector(`[data-index="${this.currentIndex}"]`);
        if (storyElement) {
            storyElement.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
        
        // Update active state
        this.render();
    },

    openStoryViewer(index) {
        if (!this.storyGroups || index < 0 || index >= this.storyGroups.length) return;
        
        const group = this.storyGroups[index];
        
        // Set current user group and start with first story from that user
        this.currentUserGroupIndex = index;
        this.currentStoryIndexInGroup = 0;
        this.currentUserStories = group.stories;
        this.currentUserInfo = group.userInfo;
        this.isViewerOpen = true;
        
        const modal = document.getElementById('storyViewerModal');
        if (!modal) {
            console.error('Story viewer modal not found');
            return;
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        this.renderStoryViewer();
        
        // Auto-advance story after 5 seconds
        this.startStoryAutoAdvance();
    },
    
    closeStoryViewer() {
        this.isViewerOpen = false;
        this.stopStoryAutoAdvance();
        
        const modal = document.getElementById('storyViewerModal');
        if (modal) {
            modal.style.display = 'none';
        }
        document.body.style.overflow = '';
    },
    
    renderStoryViewer() {
        if (!this.currentUserStories || this.currentStoryIndexInGroup < 0 || 
            this.currentStoryIndexInGroup >= this.currentUserStories.length) return;
        
        const story = this.currentUserStories[this.currentStoryIndexInGroup];
        const modal = document.getElementById('storyViewerModal');
        if (!modal) return;
        
        const viewerContent = modal.querySelector('.story-viewer-content');
        const viewerMedia = modal.querySelector('.story-viewer-media');
        const viewerText = modal.querySelector('.story-viewer-text');
        const viewerUser = modal.querySelector('.story-viewer-user');
        const progressBar = modal.querySelector('.story-progress-bar-fill');
        
        if (!viewerContent || !viewerMedia || !viewerUser || !progressBar) return;
        
        // Update user info (use current user info)
        const userAvatar = this.currentUserInfo.profile_picture 
            ? `<img src="${this.escapeHtml(this.currentUserInfo.profile_picture)}" alt="${this.escapeHtml(this.currentUserInfo.username)}" class="story-viewer-avatar-img">`
            : `<div class="story-viewer-avatar-placeholder">${this.currentUserInfo.username.charAt(0).toUpperCase()}</div>`;
        
        // Show story count if user has multiple stories
        const storyCount = this.currentUserStories.length > 1 
            ? ` (${this.currentStoryIndexInGroup + 1}/${this.currentUserStories.length})`
            : '';
        
        viewerUser.innerHTML = `
            <div class="story-viewer-avatar">${userAvatar}</div>
            <div class="story-viewer-username">${this.escapeHtml(this.currentUserInfo.username)}${storyCount}</div>
            <div class="story-viewer-time">${this.formatStoryTime(story.created_at)}</div>
        `;
        
        // Update media
        if (story.media_type === 'video') {
            viewerMedia.innerHTML = `<video class="story-viewer-media-element" src="${this.escapeHtml(story.media_url)}" autoplay muted loop></video>`;
            const video = viewerMedia.querySelector('video');
            if (video) {
                video.addEventListener('loadeddata', () => {
                    video.play().catch(e => console.error('Video play error:', e));
                });
            }
        } else {
            viewerMedia.innerHTML = `<img class="story-viewer-media-element" src="${this.escapeHtml(story.media_url)}" alt="${this.escapeHtml(this.currentUserInfo.username)}'s story">`;
        }
        
        // Update text overlay
        if (viewerText) {
            if (story.text) {
                viewerText.textContent = story.text;
                viewerText.style.display = 'block';
            } else {
                viewerText.style.display = 'none';
            }
        }
        
        // Reset and animate progress bar
        progressBar.style.width = '0%';
        setTimeout(() => {
            progressBar.style.width = '100%';
        }, 100);
    },
    
    nextStory() {
        if (!this.currentUserStories || this.currentUserStories.length === 0) return;
        
        this.stopStoryAutoAdvance();
        
        // If there are more stories from the current user, move to next story
        if (this.currentStoryIndexInGroup < this.currentUserStories.length - 1) {
            this.currentStoryIndexInGroup++;
        } else {
            // Move to next user's first story
            if (!this.storyGroups || this.storyGroups.length <= 1) return;
            this.currentUserGroupIndex = (this.currentUserGroupIndex + 1) % this.storyGroups.length;
            const nextGroup = this.storyGroups[this.currentUserGroupIndex];
            this.currentUserStories = nextGroup.stories;
            this.currentUserInfo = nextGroup.userInfo;
            this.currentStoryIndexInGroup = 0;
        }
        
        this.renderStoryViewer();
        this.startStoryAutoAdvance();
    },
    
    prevStory() {
        if (!this.currentUserStories || this.currentUserStories.length === 0) return;
        
        this.stopStoryAutoAdvance();
        
        // If there are previous stories from the current user, move to previous story
        if (this.currentStoryIndexInGroup > 0) {
            this.currentStoryIndexInGroup--;
        } else {
            // Move to previous user's last story
            if (!this.storyGroups || this.storyGroups.length <= 1) return;
            this.currentUserGroupIndex = (this.currentUserGroupIndex - 1 + this.storyGroups.length) % this.storyGroups.length;
            const prevGroup = this.storyGroups[this.currentUserGroupIndex];
            this.currentUserStories = prevGroup.stories;
            this.currentUserInfo = prevGroup.userInfo;
            this.currentStoryIndexInGroup = prevGroup.stories.length - 1;
        }
        
        this.renderStoryViewer();
        this.startStoryAutoAdvance();
    },
    
    startStoryAutoAdvance() {
        this.stopStoryAutoAdvance();
        this.storyAutoAdvanceTimer = setTimeout(() => {
            if (this.isViewerOpen) {
                this.nextStory();
            }
        }, 5000); // 5 seconds per story
    },
    
    stopStoryAutoAdvance() {
        if (this.storyAutoAdvanceTimer) {
            clearTimeout(this.storyAutoAdvanceTimer);
            this.storyAutoAdvanceTimer = null;
        }
    },
    
    formatStoryTime(timestamp) {
        const now = new Date();
        const storyTime = new Date(timestamp);
        const diffMs = now - storyTime;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return storyTime.toLocaleDateString();
    },

    async showAddStoryModal() {
        // Check if user is guest (shouldn't happen but double check)
        if (typeof currentUser !== 'undefined' && currentUser.is_guest) {
            alert('Guest users cannot add stories');
            return;
        }

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,video/*';
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Validate file size (max 10MB for stories)
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxSize) {
                alert('File size exceeds 10MB limit');
                return;
            }

            // Show loading indicator
            const addBtn = document.getElementById('addStoryBtn');
            if (addBtn) {
                addBtn.disabled = true;
                addBtn.innerHTML = '<span class="loading-spinner"></span>';
            }

            try {
                // Upload file
                const formData = new FormData();
                formData.append('file', file);

                const uploadResponse = await fetch('/api/upload.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: formData
                });

                const uploadData = await uploadResponse.json();

                if (!uploadData.success) {
                    throw new Error(uploadData.error || 'Upload failed');
                }

                // Determine media type
                const mediaType = uploadData.data.type === 'video' ? 'video' : 'image';

                // Create story without text (text is optional and can be added later if needed)
                await API.createStory(mediaType, uploadData.data.url, null);

                // Reload stories
                await this.loadStories();

                alert('Story added successfully!');
            } catch (error) {
                console.error('Error adding story:', error);
                alert('Failed to add story: ' + (error.message || 'Unknown error'));
            } finally {
                if (addBtn) {
                    addBtn.disabled = false;
                    addBtn.innerHTML = `
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                        <span class="add-story-text">Add Story</span>
                    `;
                }
            }
        };
        input.click();
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

