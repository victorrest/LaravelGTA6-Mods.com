const PINNED_CLASSES = {
    true: ['bg-purple-600', 'hover:bg-purple-700'],
    false: ['bg-gray-600', 'hover:bg-gray-700'],
};

function updateButtonState(button, pinned) {
    const textElement = button.querySelector('[data-pin-text]');
    const iconElement = button.querySelector('[data-pin-icon]');

    if (textElement) {
        textElement.textContent = pinned ? 'Unpin from Profile' : 'Pin to Profile';
    }

    if (iconElement) {
        if (pinned) {
            iconElement.classList.remove('rotate-45');
        } else {
            iconElement.classList.add('rotate-45');
        }
    }

    button.dataset.isPinned = pinned ? '1' : '0';

    Object.values(PINNED_CLASSES).forEach((classes) => {
        classes.forEach((className) => button.classList.remove(className));
    });

    PINNED_CLASSES[pinned].forEach((className) => button.classList.add(className));
}

async function togglePin(button) {
    const isPinned = button.dataset.isPinned === '1';
    const url = isPinned ? button.dataset.unpinUrl : button.dataset.pinUrl;
    const method = isPinned ? 'DELETE' : 'POST';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!url) {
        console.error('Missing pin toggle URL.');
        return;
    }

    button.disabled = true;
    button.classList.add('opacity-70');

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'X-CSRF-TOKEN': csrfToken ?? '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: method === 'POST' ? JSON.stringify({}) : null,
        });

        const data = await response.json().catch(() => ({ success: false }));

        if (response.ok && data?.success) {
            updateButtonState(button, !isPinned);
        } else {
            const message = data?.message || 'Unable to update pin state. Please try again.';
            alert(message);
        }
    } catch (error) {
        console.error('Failed to toggle pin state:', error);
        alert('Unexpected error while updating pin. Please try again.');
    } finally {
        button.disabled = false;
        button.classList.remove('opacity-70');
    }
}

function registerPinButton() {
    const button = document.getElementById('pin-mod-btn');
    if (!button) {
        return;
    }

    button.addEventListener('click', () => togglePin(button));
}

document.addEventListener('DOMContentLoaded', registerPinButton);
