document.querySelectorAll('[data-registration-tabs]').forEach((container) => {
    const buttons = [...container.querySelectorAll('[data-registration-tab]')];
    const panels = [...container.querySelectorAll('[data-registration-panel]')];

    const select = (target) => {
        buttons.forEach((button) => {
            const active = button.dataset.registrationTab === target;
            button.setAttribute('aria-selected', String(active));
            button.classList.toggle('bg-maroon-700', active);
            button.classList.toggle('text-cream-50', active);
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.registrationPanel !== target;
        });
    };

    buttons.forEach((button) => button.addEventListener('click', () => select(button.dataset.registrationTab)));
    select(container.dataset.defaultTab || 'buyer');
});
