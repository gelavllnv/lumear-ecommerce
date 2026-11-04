@extends('layouts.logistics')

@section('title', 'Pickup Scan — Lumear Logistics')

@section('content')
<style>
    .waybill-scan { max-width: 760px; margin: 0 auto; }
    .waybill-scan-card { padding: 28px; border: 1px solid rgba(207, 163, 149, .25); border-radius: 18px; background: var(--card-bg, #23191a); }
    .waybill-scan h1 { margin: 6px 0 8px; font-size: 30px; }
    .waybill-scan p { color: var(--text-muted, #bda6a2); line-height: 1.6; }
    .waybill-scan label { display:block; margin-top: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
    .waybill-scan input { width: 100%; min-height: 52px; margin-top: 8px; border: 1px solid rgba(207, 163, 149, .42); border-radius: 10px; padding: 12px; background: transparent; color: inherit; font: inherit; }
    .waybill-scan-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top: 14px; }
    .waybill-scan button, .waybill-scan a { min-height:42px; border:0; border-radius:9px; padding:10px 16px; background:#9a3c43; color:#fff; cursor:pointer; font:inherit; font-weight:800; text-decoration:none; }
    .waybill-scan .outline { border:1px solid rgba(207,163,149,.45); background:transparent; }
    #waybillCamera { width:100%; max-height:370px; margin-top:20px; border-radius:12px; background:#120d0e; object-fit:cover; }
    .waybill-scan-status { min-height:22px; margin-top:12px; font-size:13px; }
    .waybill-scan-status.error { color:#f1a0a5; }
    .waybill-scan-notice { margin-top:16px; padding:12px; border-radius:9px; background:rgba(92,171,121,.13); color:#c8efd5; }
</style>
<div class="waybill-scan">
    <section class="waybill-scan-card">
        <p class="eyebrow">Warehouse handover</p>
        <h1>Scan seller waybill</h1>
        <p>Use a USB QR scanner or camera to confirm a parcel pickup. Lumear verifies that the label belongs to your approved logistics provider before changing the order to picked up.</p>

        @if(session('status'))<div class="waybill-scan-notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="waybill-scan-status error">{{ $errors->first() }}</div>@endif

        <form id="waybillScanForm" method="post" action="{{ route('logistics.pickup.scan.store') }}">
            @csrf
            <label for="waybill">Waybill QR value or tracking code</label>
            <input id="waybill" name="waybill" autocomplete="off" autofocus placeholder="Scan QR or enter LM-TRK-..." required>
            <div class="waybill-scan-actions"><button type="submit">Confirm pickup</button><button id="startWaybillCamera" class="outline" type="button">Use camera</button><a class="outline" href="{{ route('logistics.pickup.index') }}">Pickup requests</a></div>
        </form>
        <video id="waybillCamera" hidden playsinline muted></video>
        <p id="waybillScanStatus" class="waybill-scan-status" aria-live="polite">Camera scanning is optional; a USB scanner can type directly into the field.</p>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('startWaybillCamera');
    const input = document.getElementById('waybill');
    const video = document.getElementById('waybillCamera');
    const status = document.getElementById('waybillScanStatus');
    let stream;
    let detector;
    let scanning = false;

    async function scanFrame() {
        if (!scanning || !detector) return;
        try {
            const results = await detector.detect(video);
            if (results.length) {
                input.value = results[0].rawValue;
                status.textContent = 'Waybill detected. Confirming pickup…';
                scanning = false;
                stream?.getTracks().forEach(track => track.stop());
                document.getElementById('waybillScanForm').requestSubmit();
                return;
            }
        } catch (_) { /* Retry while the camera warms up. */ }
        requestAnimationFrame(scanFrame);
    }

    button?.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Camera QR scanning is unavailable in this browser. Use Chrome/Edge, or scan into the input with a USB scanner.';
            return;
        }
        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            scanning = true;
            status.textContent = 'Point the camera at the printed Lumear waybill QR.';
            requestAnimationFrame(scanFrame);
        } catch (error) {
            status.textContent = 'Camera access was not granted. You can still use a USB QR scanner or type the tracking code.';
        }
    });
});
</script>
@endpush
