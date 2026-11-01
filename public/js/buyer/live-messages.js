document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('buyerMessageForm');

    if (!form) {
        return;
    }

    const body = document.getElementById('buyer-message-body');
    const imageInput = document.getElementById('buyer-message-images');
    const preview = document.getElementById('buyerImagePreview');
    const count = document.getElementById('buyerMessageCharacterCount');
    const submit = form.querySelector('button[type="submit"]');
    const maximumImages = 5;
    const maximumBytes = 5 * 1024 * 1024;
    const acceptedTypes = new Set(['image/jpeg', 'image/png']);

    const updateCharacterCount = () => {
        count.textContent = `${body.value.length} / 1000`;
    };

    const renderPreview = () => {
        const files = Array.from(imageInput.files || []);
        preview.replaceChildren();

        if (files.length === 0) {
            preview.hidden = true;
            return;
        }

        preview.hidden = false;
        files.forEach((file) => {
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = `Selected image: ${file.name}`;
            image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
            preview.append(image);
        });

        const summary = document.createElement('span');
        summary.textContent = `${files.length} image${files.length === 1 ? '' : 's'} ready to send`;
        preview.append(summary);
    };

    body.addEventListener('input', updateCharacterCount);

    imageInput.addEventListener('change', () => {
        const files = Array.from(imageInput.files || []);
        const hasUnsupportedImage = files.some((file) => !acceptedTypes.has(file.type));
        const hasOversizedImage = files.some((file) => file.size > maximumBytes);

        if (files.length > maximumImages || hasUnsupportedImage || hasOversizedImage) {
            imageInput.value = '';
            preview.hidden = false;
            preview.textContent = files.length > maximumImages
                ? 'You can attach up to 5 JPG or PNG images.'
                : 'Each attachment must be a JPG or PNG image no larger than 5 MB.';
            return;
        }

        renderPreview();
    });

    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.textContent = 'Sending…';
    });

    updateCharacterCount();
});
