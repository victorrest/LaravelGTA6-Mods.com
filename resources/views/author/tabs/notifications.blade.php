@if($isOwner)
<div id="notifications-tab" data-tab-content="notifications" class="tab-content hidden">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-gray-800">Notifications</h3>
        <button id="mark-all-read-btn" class="text-sm text-pink-600 hover:text-pink-700 font-semibold">
            Mark all as read
        </button>
    </div>

    <div class="text-center py-12" data-loading>
        <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
        <p class="text-gray-500">Loading notifications...</p>
    </div>

    <div id="notifications-content" class="hidden">
        <div id="notifications-list" class="space-y-2"></div>

        <div id="notifications-empty" class="hidden text-center py-12 text-gray-500">
            <i class="fas fa-bell text-4xl mb-3 text-gray-300"></i>
            <p>No notifications yet</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function loadNotifications() {
    const notificationsContent = document.getElementById('notifications-content');
    const notificationsList = document.getElementById('notifications-list');
    const notificationsEmpty = document.getElementById('notifications-empty');
    const loadingDiv = document.querySelector('#notifications-tab [data-loading]');

    try {
        const response = await fetch('/api/notifications');
        const data = await response.json();

        loadingDiv.classList.add('hidden');
        notificationsContent.classList.remove('hidden');

        if (data.notifications && data.notifications.length > 0) {
            notificationsList.innerHTML = data.notifications.map(notification => `
                <div class="flex items-start gap-3 p-4 rounded-lg ${notification.read ? 'bg-gray-50' : 'bg-pink-50'} hover:bg-gray-100 transition">
                    ${notification.actor ? `
                        <img src="${notification.actor.avatar}" alt="${notification.actor.name}" class="w-10 h-10 rounded-full object-cover">
                    ` : '<div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center"><i class="fas fa-bell text-gray-400"></i></div>'}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800">
                            ${notification.actor ? `<span class="font-semibold">${notification.actor.name}</span>` : ''}
                            ${notification.message}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">${notification.created_at}</p>
                    </div>
                    ${!notification.read ? `
                        <button class="mark-read-btn text-pink-600 hover:text-pink-700" data-notification-id="${notification.id}">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                </div>
            `).join('');

            // Add event listeners to mark-read buttons
            document.querySelectorAll('.mark-read-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const notificationId = this.dataset.notificationId;
                    await markAsRead(notificationId);
                });
            });

            notificationsEmpty.classList.add('hidden');
        } else {
            notificationsList.innerHTML = '';
            notificationsEmpty.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        loadingDiv.innerHTML = '<p class="text-red-500">Failed to load notifications</p>';
    }
}

async function markAsRead(notificationId) {
    try {
        const response = await fetch(`/api/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        if (response.ok) {
            loadNotifications(); // Reload list
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const notificationsTab = document.querySelector('[data-tab="notifications"]');
    if (notificationsTab) {
        notificationsTab.addEventListener('click', function() {
            const notificationsContent = document.getElementById('notifications-content');
            if (notificationsContent.classList.contains('hidden')) {
                loadNotifications();
            }
        });
    }

    // Mark all as read button
    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async function() {
            try {
                const response = await fetch('/api/notifications/read-all', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (response.ok) {
                    loadNotifications();
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        });
    }

    if ('{{ $activeTab }}' === 'notifications') {
        loadNotifications();
    }
});
</script>
@endpush
@endif
