const editors = document.querySelectorAll('[data-variant-editor]');

editors.forEach((editor) => {
    const list = editor.querySelector('[data-variant-list]');
    const template = editor.querySelector('[data-variant-template]');
    const addButton = editor.querySelector('[data-add-variant]');
    let nextIndex = list.querySelectorAll('[data-variant-row]').length;

    const refresh = () => {
        const rows = [...list.querySelectorAll('[data-variant-row]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-variant-number]').textContent = String(index + 1);
            row.querySelector('[data-remove-variant]').hidden = rows.length === 1;
        });
    };

    addButton?.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(nextIndex));
        });
        list.append(fragment);
        nextIndex += 1;
        refresh();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-variant]');
        if (!button) return;

        const rows = list.querySelectorAll('[data-variant-row]');
        if (rows.length > 1) {
            button.closest('[data-variant-row]').remove();
            refresh();
        }
    });

    refresh();
});
