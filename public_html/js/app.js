// Main application initialization
document.addEventListener('DOMContentLoaded', () => {
    // Ensure body is visible immediately
    document.body.style.opacity = '1';
    document.body.classList.add('loaded');
    
    // Initialize chat if on dashboard
    if (typeof chat !== 'undefined' && typeof currentUser !== 'undefined') {
        chat.init();
    }
    
    // Add smooth scroll behavior
    document.documentElement.style.scrollBehavior = 'smooth';

    // Close user profile dropdown when clicking outside
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('userProfileDropdown');
        const iconBtn = document.getElementById('userIconBtn');
        
        if (dropdown && iconBtn && !dropdown.contains(e.target) && !iconBtn.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Close dropdown on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const dropdown = document.getElementById('userProfileDropdown');
            if (dropdown) {
                dropdown.classList.remove('active');
            }
            // Also close recipient profile dropdown
            if (typeof chat !== 'undefined' && chat.recipientProfileDropdownOpen) {
                const recipientDropdown = document.getElementById('recipientProfileDropdown');
                if (recipientDropdown) {
                    recipientDropdown.classList.remove('active');
                    chat.recipientProfileDropdownOpen = false;
                }
            }
        }
    });

// Profile Edit Modal - Make sure it's globally accessible
window.openProfileEditModal = function() {
    console.log('openProfileEditModal called');
    const modal = document.getElementById('profileEditModal');
    console.log('Modal element:', modal);
    
    if (!modal) {
        console.error('Profile edit modal not found!');
        alert('Profile edit modal not found. Please refresh the page.');
        return false;
    }
    
    // Force display with inline style (highest priority)
    modal.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; z-index: 10000 !important;';
    modal.classList.add('active');
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    
    const computedStyle = window.getComputedStyle(modal);
    console.log('Modal display:', computedStyle.display);
    console.log('Modal visibility:', computedStyle.visibility);
    console.log('Modal z-index:', computedStyle.zIndex);
    
    return true;
};

// Also define it as a regular function for backwards compatibility
function openProfileEditModal() {
    if (typeof window.openProfileEditModal === 'function') {
        window.openProfileEditModal();
    } else {
        console.error('openProfileEditModal function not available');
    }
}

// Try to set up button immediately if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupProfileEditButton);
} else {
    // DOM is already loaded
    setupProfileEditButton();
}

window.closeProfileEditModal = function() {
    const modal = document.getElementById('profileEditModal');
    if (modal) {
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.classList.remove('active');
        // Restore body scroll
        document.body.style.overflow = '';
    }
};

// Also define it as a regular function for backwards compatibility
function closeProfileEditModal() {
    window.closeProfileEditModal();
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('profileEditModal');
    if (modal && e.target === modal) {
        closeProfileEditModal();
    }
});

// Profile edit form submission and button event listeners
function setupProfileEditButton() {
    const editProfileBtn = document.getElementById('editProfileBtn');
    if (editProfileBtn && !editProfileBtn.dataset.listenerAttached) {
        editProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Edit profile button clicked');
            if (typeof openProfileEditModal === 'function') {
                openProfileEditModal();
            } else if (typeof window.openProfileEditModal === 'function') {
                window.openProfileEditModal();
            } else {
                console.error('openProfileEditModal function not found');
                alert('Profile edit function not available. Please refresh the page.');
            }
        });
        editProfileBtn.dataset.listenerAttached = 'true';
        console.log('Edit profile button listener attached successfully');
    } else if (!editProfileBtn) {
        console.warn('Edit profile button not found');
    }
}

// Set up immediately and also on DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupProfileEditButton);
} else {
    setupProfileEditButton();
}

document.addEventListener('DOMContentLoaded', () => {
    // Also try again in case button loads later
    setTimeout(setupProfileEditButton, 100);

    // Set up close modal buttons
    const closeModalBtn = document.getElementById('closeProfileModalBtn');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeProfileEditModal();
        });
    }

    const cancelEditBtn = document.getElementById('cancelProfileEditBtn');
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeProfileEditModal();
        });
    }

    const profileForm = document.getElementById('profileEditForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const usernameInput = document.getElementById('edit-username');
            const ageInput = document.getElementById('edit-age');
            const bioInput = document.getElementById('edit-bio');
            const profilePicInput = document.getElementById('edit-profile-picture');
            
            const formData = {
                username: usernameInput.value.trim(),
                age: ageInput.value ? parseInt(ageInput.value) : null,
                bio: bioInput.value.trim(),
                profile_picture: profilePicInput.value.trim()
            };

            // Remove empty strings and convert to null for optional fields
            if (formData.bio === '') formData.bio = null;
            if (formData.profile_picture === '') formData.profile_picture = null;
            if (formData.age === null || isNaN(formData.age)) formData.age = null;

            console.log('Submitting profile update:', formData);

            try {
                const response = await API.updateProfile(formData);
                console.log('Profile update response:', response);
                
                if (response && response.success) {
                    alert('Profile updated successfully!');
                    closeProfileEditModal();
                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    const errorMsg = response?.error || response?.message || response?.data?.error || 'Unknown error';
                    console.error('Profile update failed:', response);
                    alert('Failed to update profile: ' + errorMsg);
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                alert('Failed to update profile: ' + (error.message || 'Unknown error'));
            }
        });
    }
});

// Close recipient profile dropdown when clicking outside
document.addEventListener('click', (e) => {
        if (typeof chat !== 'undefined' && chat.recipientProfileDropdownOpen) {
            const dropdown = document.getElementById('recipientProfileDropdown');
            const btn = document.getElementById('recipientProfileBtn');
            
            if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.classList.remove('active');
                chat.recipientProfileDropdownOpen = false;
            }
        }
    });

    // Close SSE connection when page unloads
    window.addEventListener('beforeunload', () => {
        if (typeof chat !== 'undefined' && chat.sseConnection) {
            chat.sseConnection.close();
            chat.sseConnection = null;
        }
    });
});

// Fallback: ensure body is visible even if DOMContentLoaded doesn't fire
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        document.body.style.opacity = '1';
    });
} else {
    document.body.style.opacity = '1';
    document.body.classList.add('loaded');
}

// Toggle user profile dropdown
function toggleUserProfile() {
    const dropdown = document.getElementById('userProfileDropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Email verification - must be global for onclick attribute
window.resendVerificationEmail = async function() {
    console.log('[resendVerificationEmail] Function called');
    const btn = document.querySelector('.btn-verify-email');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending...';
    } else {
        console.warn('[resendVerificationEmail] Button not found');
    }

    try {
        console.log('[resendVerificationEmail] Calling API...');
        const response = await API.resendVerificationEmail();
        console.log('[resendVerificationEmail] API Response:', response);
        
        // Handle response - check both success and errorResponse format
        if (response && response.success) {
            const message = response.message || 'Verification email sent! Please check your inbox.';
            alert(message);
            if (btn) {
                btn.textContent = 'Email Sent!';
                // Reset button after 3 seconds
                setTimeout(() => {
                    btn.disabled = false;
                    btn.textContent = 'Resend Verification Email';
                }, 3000);
            }
        } else {
            const errorMsg = response?.error || response?.message || 'Unknown error occurred';
            alert('Failed to send verification email: ' + errorMsg);
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Resend Verification Email';
            }
        }
    } catch (error) {
        console.error('[resendVerificationEmail] Exception caught:', error);
        const errorMsg = error.message || error.error || 'Unknown error occurred';
        alert('Failed to send verification email: ' + errorMsg);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Resend Verification Email';
        }
    }
};

