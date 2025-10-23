const initialiseWaitingRoom = () => {
    const root = document.querySelector('[data-waiting-room-root]');
    if (!root) {
        return;
    }

    const counter = root.querySelector('[data-waiting-room-counter]');
    const progress = root.querySelector('[data-waiting-room-progress]');
    const message = root.querySelector('[data-waiting-room-message]');
    const form = root.querySelector('[data-waiting-room-form]');
    const button = root.querySelector('[data-waiting-room-button]');

    const initialCountdown = counter ? parseInt(counter.dataset.countdown || '5', 10) : 5;
    let remaining = Number.isFinite(initialCountdown) ? initialCountdown : 5;
    const template = message ? message.dataset.template || message.textContent || '' : '';
    const domain = message ? message.dataset.domain || '' : '';

    const updateProgress = () => {
        if (!progress) {
            return;
        }

        const percentage = Math.min(100, Math.max(0, ((initialCountdown - remaining) / initialCountdown) * 100));
        progress.style.width = `${percentage}%`;
    };

    const updateMessage = () => {
        if (!message) {
            return;
        }

        if (!template) {
            message.textContent = `A letöltés ${Math.max(remaining, 0)} másodpercen belül indul.`;
            return;
        }

        let rendered = template.replace(':seconds', Math.max(remaining, 0).toString());
        rendered = rendered.replace(':domain', domain);
        message.textContent = rendered;
    };

    const enableButton = () => {
        if (!button) {
            return;
        }

        const label = button.dataset.readyLabel;
        const icon = button.querySelector('i');

        button.disabled = false;

        if (label) {
            const span = button.querySelector('span');
            if (span) {
                span.textContent = label;
            }
        }

        if (icon) {
            icon.classList.remove('fa-hourglass-half');
            icon.classList.add('fa-download');
        }
    };

    const startDownload = () => {
        if (form) {
            form.submit();
        }
    };

    updateProgress();
    updateMessage();

    const interval = window.setInterval(() => {
        remaining -= 1;
        if (counter) {
            counter.textContent = Math.max(remaining, 0).toString();
        }

        updateProgress();
        updateMessage();

        if (remaining <= 0) {
            window.clearInterval(interval);
            enableButton();
            window.setTimeout(startDownload, 750);
        }
    }, 1000);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseWaitingRoom);
} else {
    initialiseWaitingRoom();
}
