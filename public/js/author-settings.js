// Author Settings JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initSettingsTabs();
    initProfileSettings();
    initAvatarSettings();
    initBannerSettings();
    initSocialSettings();
    initPasswordSettings();
});

// Settings Tabs
function initSettingsTabs() {
    const settingsTabBtns = document.querySelectorAll('.settings-tab-btn');
    const settingsTabContents = document.querySelectorAll('.settings-tab-content');

    settingsTabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.settingsTab;

            // Update buttons
            settingsTabBtns.forEach(b => {
                b.classList.remove('active');
            });
            this.classList.add('active');

            // Update content
            settingsTabContents.forEach(content => {
                content.classList.add('hidden');
            });
            const targetContent = document.getElementById(`settings-${tab}`);
            if (targetContent) {
                targetContent.classList.remove('hidden');
            }
        });
    });
}

// Profile Settings
function initProfileSettings() {
    const bioTextarea = document.getElementById('settings-bio');
    const bioCounter = document.getElementById('bio-counter');
    const saveBtn = document.getElementById('save-profile-btn');

    if (bioTextarea && bioCounter) {
        bioTextarea.addEventListener('input', function() {
            const length = this.value.length;
            bioCounter.textContent = `${length}/160`;
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', async function() {
            const email = document.getElementById('settings-email').value;
            const profileTitle = document.getElementById('settings-profile-title').value;
            const bio = bioTextarea ? bioTextarea.value : '';

            try {
                const response = await fetch('/profile/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        email,
                        profile_title: profileTitle,
                        bio
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Profile updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                showNotification('Failed to update profile', 'error');
            }
        });
    }
}

// Avatar Settings
function initAvatarSettings() {
    const avatarInput = document.getElementById('settings-avatar');
    const avatarPreview = document.getElementById('avatar-preview');
    const deleteAvatarBtn = document.getElementById('delete-avatar-btn');
    const presetAvatars = document.querySelectorAll('.preset-avatar');

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;

            // Validate file size (1MB)
            if (file.size > 1024 * 1024) {
                showNotification('Avatar file must be less than 1 MB', 'error');
                this.value = '';
                return;
            }

            // Preview
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);

            // Upload
            const formData = new FormData();
            formData.append('avatar', file);

            try {
                const response = await fetch('/profile/avatar', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Avatar uploaded successfully', 'success');
                    avatarPreview.src = data.avatar_url;

                    // Update main avatar
                    const mainAvatar = document.getElementById('author-avatar');
                    if (mainAvatar) {
                        mainAvatar.src = data.avatar_url;
                    }

                    // Show delete button
                    if (deleteAvatarBtn) {
                        deleteAvatarBtn.classList.remove('hidden');
                    }

                    // Deselect preset avatars
                    presetAvatars.forEach(avatar => {
                        avatar.classList.remove('selected');
                    });
                } else {
                    showNotification(data.message || 'Failed to upload avatar', 'error');
                }
            } catch (error) {
                console.error('Error uploading avatar:', error);
                showNotification('Failed to upload avatar', 'error');
            }
        });
    }

    if (deleteAvatarBtn) {
        deleteAvatarBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to delete your avatar?')) {
                return;
            }

            try {
                const response = await fetch('/profile/avatar', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Avatar deleted successfully', 'success');
                    avatarPreview.src = data.avatar_url;

                    // Update main avatar
                    const mainAvatar = document.getElementById('author-avatar');
                    if (mainAvatar) {
                        mainAvatar.src = data.avatar_url;
                    }

                    // Hide delete button
                    this.classList.add('hidden');
                } else {
                    showNotification(data.message || 'Failed to delete avatar', 'error');
                }
            } catch (error) {
                console.error('Error deleting avatar:', error);
                showNotification('Failed to delete avatar', 'error');
            }
        });
    }

    // Preset avatar selection
    presetAvatars.forEach(avatar => {
        avatar.addEventListener('click', async function() {
            const presetId = this.dataset.avatarId;
            const presetUrl = this.dataset.avatarUrl;

            try {
                const response = await fetch('/profile/avatar/preset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ preset_id: presetId })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Preset avatar selected', 'success');

                    // Update preview
                    avatarPreview.src = data.avatar_url;

                    // Update main avatar
                    const mainAvatar = document.getElementById('author-avatar');
                    if (mainAvatar) {
                        mainAvatar.src = data.avatar_url;
                    }

                    // Update selected state
                    presetAvatars.forEach(a => a.classList.remove('selected'));
                    this.classList.add('selected');

                    // Hide delete button (preset avatars can't be deleted)
                    if (deleteAvatarBtn) {
                        deleteAvatarBtn.classList.add('hidden');
                    }
                } else {
                    showNotification(data.message || 'Failed to select preset', 'error');
                }
            } catch (error) {
                console.error('Error selecting preset:', error);
                showNotification('Failed to select preset', 'error');
            }
        });
    });
}

// Banner Settings
function initBannerSettings() {
    const bannerInput = document.getElementById('settings-banner');
    const bannerPreview = document.getElementById('banner-preview');
    const removeBannerBtn = document.getElementById('remove-banner-btn');

    if (bannerInput && bannerPreview) {
        bannerInput.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;

            // Validate file size (2MB)
            if (file.size > 2 * 1024 * 1024) {
                showNotification('Banner file must be less than 2 MB', 'error');
                this.value = '';
                return;
            }

            // Upload
            const formData = new FormData();
            formData.append('banner', file);

            try {
                const response = await fetch('/profile/banner', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Banner uploaded successfully', 'success');
                    bannerPreview.style.backgroundImage = `url(${data.banner_url})`;
                    bannerPreview.classList.add('has-banner');

                    const emptyText = bannerPreview.querySelector('.gta6mods-banner-empty');
                    if (emptyText) {
                        emptyText.classList.add('hidden');
                    }

                    if (removeBannerBtn) {
                        removeBannerBtn.classList.remove('hidden');
                    }
                } else {
                    showNotification(data.message || 'Failed to upload banner', 'error');
                }
            } catch (error) {
                console.error('Error uploading banner:', error);
                showNotification('Failed to upload banner', 'error');
            }
        });
    }

    if (removeBannerBtn) {
        removeBannerBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to remove your banner?')) {
                return;
            }

            try {
                const response = await fetch('/profile/banner', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Banner removed successfully', 'success');
                    bannerPreview.style.backgroundImage = '';
                    bannerPreview.classList.remove('has-banner');

                    const emptyText = bannerPreview.querySelector('.gta6mods-banner-empty');
                    if (emptyText) {
                        emptyText.classList.remove('hidden');
                    }

                    this.classList.add('hidden');

                    // Reload to update header banner
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification(data.message || 'Failed to remove banner', 'error');
                }
            } catch (error) {
                console.error('Error removing banner:', error);
                showNotification('Failed to remove banner', 'error');
            }
        });
    }
}

// Social Settings
function initSocialSettings() {
    const saveSocialBtn = document.getElementById('save-social-btn');

    if (saveSocialBtn) {
        saveSocialBtn.addEventListener('click', async function() {
            const linkInputs = document.querySelectorAll('#social-links-form [data-link-key]');
            const links = [];

            linkInputs.forEach(input => {
                const platform = input.dataset.linkKey;
                const url = input.value.trim();

                links.push({ platform, url });
            });

            try {
                const response = await fetch('/profile/social-links', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ links })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Social links updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Failed to update social links', 'error');
                }
            } catch (error) {
                console.error('Error updating social links:', error);
                showNotification('Failed to update social links', 'error');
            }
        });
    }
}

// Password Settings
function initPasswordSettings() {
    const passwordForm = document.getElementById('password-form');
    const newPasswordInput = document.getElementById('new-password');
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');

    if (newPasswordInput && strengthBar && strengthText) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);

            // Update bar
            strengthBar.style.width = strength.percentage + '%';
            strengthBar.className = `h-2 transition-all duration-300 ${strength.colorClass}`;

            // Update text
            strengthText.textContent = strength.text;
        });
    }

    if (passwordForm) {
        passwordForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            // Validate
            if (!currentPassword || !newPassword || !confirmPassword) {
                showNotification('All fields are required', 'error');
                return;
            }

            if (newPassword !== confirmPassword) {
                showNotification('Passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 12) {
                showNotification('Password must be at least 12 characters', 'error');
                return;
            }

            try {
                const response = await fetch('/profile/password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword,
                        new_password_confirmation: confirmPassword
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Password changed successfully', 'success');
                    passwordForm.reset();
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                } else {
                    showNotification(data.message || 'Failed to change password', 'error');
                }
            } catch (error) {
                console.error('Error changing password:', error);
                showNotification('Failed to change password', 'error');
            }
        });
    }
}

// Helper Functions
function calculatePasswordStrength(password) {
    let strength = 0;

    if (password.length >= 12) strength += 25;
    if (/[a-z]/.test(password)) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 15;
    if (/[^a-zA-Z0-9]/.test(password)) strength += 10;

    let text = '';
    let colorClass = 'bg-red-500';

    if (strength <= 25) {
        text = 'Very weak';
        colorClass = 'bg-red-500';
    } else if (strength <= 50) {
        text = 'Weak';
        colorClass = 'bg-orange-500';
    } else if (strength <= 75) {
        text = 'Medium';
        colorClass = 'bg-yellow-500';
    } else if (strength <= 90) {
        text = 'Strong';
        colorClass = 'bg-green-500';
    } else {
        text = 'Very strong';
        colorClass = 'bg-green-600';
    }

    return { percentage: strength, text, colorClass };
}

function showNotification(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };

    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
