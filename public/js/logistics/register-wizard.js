document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-logistics-wizard]');
    if (!form) return;

    const panels = [...form.querySelectorAll('[data-wizard-panel]')];
    const indicators = [...document.querySelectorAll('[data-wizard-indicator]')];
    const next = form.querySelector('[data-wizard-next]');
    const back = form.querySelector('[data-wizard-back]');
    const submit = form.querySelector('[data-wizard-submit]');
    const label = document.querySelector('[data-wizard-step-label]');
    const progressText = document.querySelector('[data-wizard-progress-text]');
    const bar = document.querySelector('[data-wizard-progress-bar]');
    const labels = ['Step 1 of 3 · Operator details', 'Step 2 of 3 · Account security', 'Step 3 of 3 · Review application'];
    let active = Number.parseInt(form.dataset.initialStep || '0', 10) || 0;

    const updateReview = () => form.querySelectorAll('[data-review]').forEach((node) => {
        const source = form.elements.namedItem(node.dataset.review);
        node.textContent = source?.value.trim() || '—';
    });

    const showStep = (step) => {
        active = Math.max(0, Math.min(step, panels.length - 1));
        panels.forEach((panel, index) => { panel.hidden = index !== active; });
        indicators.forEach((item, index) => { item.classList.toggle('is-current', index === active); item.classList.toggle('is-complete', index < active); });
        label.textContent = labels[active];
        progressText.textContent = `${Math.round(((active + 1) / panels.length) * 100)}% complete`;
        bar.style.width = `${((active + 1) / panels.length) * 100}%`;
        back.hidden = active === 0;
        next.hidden = active === panels.length - 1;
        submit.hidden = active !== panels.length - 1;
        if (active === panels.length - 1) updateReview();
    };

    const validCurrentPanel = () => {
        const fields = [...panels[active].querySelectorAll('input, select, textarea')].filter((field) => !field.disabled);
        for (const field of fields) { if (!field.checkValidity()) { field.reportValidity(); return false; } }
        if (active === 1 && form.elements.password.value !== form.elements.password_confirmation.value) {
            form.elements.password_confirmation.setCustomValidity('Passwords do not match.');
            form.elements.password_confirmation.reportValidity();
            form.elements.password_confirmation.setCustomValidity('');
            return false;
        }
        return true;
    };

    next.addEventListener('click', () => { if (validCurrentPanel()) showStep(active + 1); });
    back.addEventListener('click', () => showStep(active - 1));
    form.addEventListener('submit', (event) => { if (!validCurrentPanel()) event.preventDefault(); });
    showStep(0);
});
