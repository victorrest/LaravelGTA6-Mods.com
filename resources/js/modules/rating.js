const initialiseRatingForms = () => {
    const forms = document.querySelectorAll('[data-rating-form]');

    forms.forEach((form) => {
        const input = form.querySelector('[data-rating-input]');
        const stars = form.querySelectorAll('[data-rating-stars] [data-rating-value]');
        const submitButton = form.querySelector('[data-rating-submit]');
        const feedback = form.querySelector('[data-rating-feedback]');
        const initialValue = parseInt(form.dataset.ratingInitial || '0', 10);
        let currentValue = initialValue;

        const updateStars = (value) => {
            stars.forEach((star) => {
                const starValue = parseInt(star.dataset.ratingValue || '0', 10);
                const icon = star.querySelector('i');

                if (!icon) {
                    return;
                }

                if (value >= starValue) {
                    icon.classList.remove('fa-regular', 'text-gray-300');
                    icon.classList.add('fa-solid', 'text-amber-400');
                } else {
                    icon.classList.remove('fa-solid', 'text-amber-400');
                    icon.classList.add('fa-regular', 'text-gray-300');
                }
            });
        };

        const setSelection = (value) => {
            currentValue = value;
            if (input) {
                input.value = String(value);
            }

            if (submitButton) {
                submitButton.disabled = false;
            }

            if (feedback) {
                feedback.textContent = `Kiválasztott értékelés: ${value}/5`;
            }

            updateStars(value);
        };

        stars.forEach((star) => {
            const starValue = parseInt(star.dataset.ratingValue || '0', 10);

            star.addEventListener('mouseenter', () => {
                updateStars(starValue);
            });

            star.addEventListener('mouseleave', () => {
                updateStars(currentValue || initialValue);
            });

            star.addEventListener('click', (event) => {
                event.preventDefault();
                setSelection(starValue);
            });

            star.addEventListener('touchstart', (event) => {
                event.preventDefault();
                setSelection(starValue);
            }, { passive: false });
        });

        form.addEventListener('mouseleave', () => {
            updateStars(currentValue || initialValue);
        });

        if (submitButton && initialValue > 0) {
            submitButton.disabled = false;
        }

        updateStars(currentValue || initialValue);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseRatingForms);
} else {
    initialiseRatingForms();
}
