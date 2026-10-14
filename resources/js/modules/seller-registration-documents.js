document.querySelectorAll('[data-seller-documents]').forEach((container) => {
    const type = container.querySelector('[data-seller-type]');
    const dti = container.querySelector('[data-document="dti"]');
    const sec = container.querySelector('[data-document="sec"]');
    const bir = container.querySelector('[data-document="bir"]');

    const setVisible = (element, visible, required = false) => {
        if (!element) return;
        element.hidden = !visible;
        const input = element.querySelector('input');
        if (input) input.required = required;
    };
    const update = () => {
        const value = type?.value;
        setVisible(dti, value === 'sole_proprietorship', value === 'sole_proprietorship');
        setVisible(sec, value === 'corporation', value === 'corporation');
        setVisible(bir, ['sole_proprietorship', 'corporation'].includes(value), ['sole_proprietorship', 'corporation'].includes(value));
    };

    type?.addEventListener('change', update);
    update();
});
