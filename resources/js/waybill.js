import QRCode from 'qrcode';

window.addEventListener('DOMContentLoaded', async () => {
    const canvas = document.getElementById('waybillQr');
    const payloadElement = document.getElementById('waybillQrPayload');

    if (!canvas || !payloadElement?.textContent) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadElement.textContent);
    } catch {
        payload = payloadElement.textContent.trim();
    }

    if (typeof payload !== 'string' || !payload.trim()) {
        return;
    }

    try {
        await QRCode.toCanvas(canvas, payload, {
            width: 140,
            margin: 1,
            errorCorrectionLevel: 'M',
            color: {
                dark: '#281d1d',
                light: '#ffffff',
            },
        });
    } catch (error) {
        canvas.replaceWith(Object.assign(document.createElement('p'), {
            className: 'qr-fallback',
            textContent: 'QR could not be generated. Scan the tracking code above instead.',
        }));
    }
});
