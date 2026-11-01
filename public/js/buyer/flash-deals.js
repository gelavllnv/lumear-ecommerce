(() => {
    const formatRemaining = (milliseconds) => {
        const seconds = Math.max(0, Math.floor(milliseconds / 1000));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainder = seconds % 60;

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
    };

    const updateCountdowns = () => {
        document.querySelectorAll('[data-flash-countdown]').forEach((element) => {
            const rawEnd = element.dataset.flashCountdown;
            const numericEnd = Number(rawEnd);
            const endsAt = Number.isFinite(numericEnd) && numericEnd > 0
                ? numericEnd
                : Date.parse(rawEnd);
            const remaining = endsAt - Date.now();

            if (!Number.isFinite(endsAt) || remaining <= 0) {
                element.textContent = 'Offer ended';
                element.setAttribute('aria-label', 'Flash offer ended. Refresh for current pricing.');
                return;
            }

            const label = `Ends in ${formatRemaining(remaining)}`;
            element.textContent = label;
            element.setAttribute('aria-label', `Flash offer ${label}`);
        });
    };

    updateCountdowns();
    window.setInterval(updateCountdowns, 1000);
})();
