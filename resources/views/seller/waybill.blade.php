<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Waybill {{ $shipment->tracking_code }} — Lumear</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1ece8; color: #2b2020; font-family: Arial, sans-serif; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; max-width: 210mm; margin: 24px auto 12px; }
        .toolbar a, .toolbar button { border: 0; border-radius: 8px; padding: 10px 14px; background: #7d2830; color: #fff; cursor: pointer; font-weight: 700; text-decoration: none; }
        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto 24px; padding: 14mm; background: #fff; }
        .brand { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #902d36; padding-bottom: 12px; }
        .brand img { width: 142px; height: auto; object-fit: contain; }
        .brand h1 { margin: 0; color: #6d2029; font-size: 25px; letter-spacing: .08em; }
        .eyebrow { color: #835c54; font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .tracking { margin: 20px 0; padding: 15px; border-radius: 10px; background: #f8eded; text-align: center; }
        .tracking strong { display: block; color: #741f28; font-size: 24px; letter-spacing: .08em; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .block { min-height: 124px; padding: 14px; border: 1px solid #dcc7c1; border-radius: 9px; }
        .block h2 { margin: 0 0 9px; color: #7d2830; font-size: 13px; text-transform: uppercase; letter-spacing: .08em; }
        .block p { margin: 4px 0; font-size: 12px; line-height: 1.5; }
        .label { color: #836760; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .contents { margin-top: 12px; padding: 14px; border: 1px solid #dcc7c1; border-radius: 9px; }
        .contents table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .contents th { color: #836760; text-align: left; font-size: 9px; letter-spacing: .06em; text-transform: uppercase; }
        .contents th, .contents td { padding: 7px 4px; border-bottom: 1px solid #eee2de; }
        .qr-area { display: flex; align-items: center; gap: 18px; margin-top: 18px; padding: 16px; border: 2px dashed #a96b63; border-radius: 10px; }
        #waybillQr { width: 140px; height: 140px; background: #fff; image-rendering: pixelated; }
        .qr-area h2 { margin: 0 0 8px; color: #6d2029; font-size: 17px; }
        .qr-area p { margin: 4px 0; color: #634c47; font-size: 11px; line-height: 1.5; }
        .qr-fallback { max-width: 140px; color: #741f28; font-size: 10px; font-weight: 700; line-height: 1.5; }
        .footer { margin-top: 20px; border-top: 1px solid #dcc7c1; padding-top: 10px; color: #836760; font-size: 9px; text-align: center; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; padding: 12mm; width: auto; min-height: auto; } @page { size: A4; margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('seller.orders') }}">← Back to orders</a>
        <button type="button" onclick="window.print()">Print waybill</button>
    </div>
    <main class="sheet">
        <header class="brand">
            <img src="{{ asset('images/lumear-logo.png') }}" alt="Lumear">
            <div><div class="eyebrow">Seller shipment label</div><h1>WAYBILL</h1></div>
        </header>

        <section class="tracking">
            <span class="eyebrow">Scan this QR at parcel handover</span>
            <strong>{{ $shipment->tracking_code }}</strong>
            <span>Order {{ $sellerOrder->order->reference }} · {{ $shipment->provider?->name }}</span>
        </section>

        <section class="grid">
            <article class="block">
                <h2>Pickup from</h2>
                <p><strong>{{ $sellerOrder->seller->name }}</strong></p>
                <p>{{ $sellerOrder->seller->pickupAddress?->recipient ?? $sellerOrder->seller->owner?->name }}</p>
                <p>{{ $sellerOrder->seller->pickupAddress?->line1 }} {{ $sellerOrder->seller->pickupAddress?->line2 }}</p>
                <p>{{ $sellerOrder->seller->pickupAddress?->barangay }}, {{ $sellerOrder->seller->pickupAddress?->city }}, {{ $sellerOrder->seller->pickupAddress?->province }}</p>
                <p>{{ $sellerOrder->seller->pickupAddress?->phone }}</p>
            </article>
            <article class="block">
                <h2>Deliver to</h2>
                <p><strong>{{ $shipping['recipient'] ?? $shipping['recipient_name'] ?? $shipping['name'] ?? $sellerOrder->order->buyer?->name }}</strong></p>
                <p>{{ $shipping['line1'] ?? $shipping['address_line1'] ?? $shipping['street'] ?? '' }}</p>
                <p>{{ $shipping['barangay'] ?? '' }}, {{ $shipping['city'] ?? $shipping['municipality'] ?? '' }}, {{ $shipping['province'] ?? '' }}</p>
                <p>{{ $shipping['phone'] ?? $sellerOrder->order->buyer?->phone ?? '' }}</p>
            </article>
        </section>

        <section class="contents">
            <div class="eyebrow">Parcel contents</div>
            <table><thead><tr><th>Item</th><th>Variant</th><th>Qty</th></tr></thead><tbody>
            @foreach($sellerOrder->items as $item)
                <tr><td>{{ $item->product_name }}</td><td>{{ $item->variant_name ?: '—' }}</td><td>{{ $item->quantity }}</td></tr>
            @endforeach
            </tbody></table>
            <p><span class="label">Payment</span> {{ strtoupper(str_replace('_', ' ', $sellerOrder->order->payment_method)) }} · <span class="label">COD amount</span> ₱{{ number_format($shipment->cod_amount_minor / 100, 2) }}</p>
        </section>

        <section class="qr-area">
            <canvas id="waybillQr" aria-label="Parcel handover waybill QR code"></canvas>
            <div><h2>Parcel handover scan</h2><p><strong>Delivery rider:</strong> after Logistics assigns this parcel and it appears in your Pickups, scan this QR in the Rider app to confirm custody and notify the buyer.</p><p><strong>Logistics:</strong> scan this QR in the Logistics pickup workflow when confirming a seller handover.</p></div>
        </section>
        <footer class="footer">Lumear waybill · Do not alter the tracking code or QR label. Printed {{ now()->format('M j, Y g:i A') }}</footer>
    </main>
    <script id="waybillQrPayload" type="application/json">@json($qrPayload)</script>
    @vite('resources/js/waybill.js')
</body>
</html>
