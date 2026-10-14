document.querySelectorAll('[data-checkout-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-checkout-button]');

        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.classList.add('opacity-70', 'cursor-wait');
        button.textContent = 'Placing COD order…';
    });
});
