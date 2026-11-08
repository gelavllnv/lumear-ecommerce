/*
|--------------------------------------------------------------------------
| LUMEAR LOGISTICS — WAYBILL SCANNER
|--------------------------------------------------------------------------
| QR detection, manual entry, server verification,
| duplicate protection, and session history.
*/

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const root = document.getElementById("lumearQrScanner");
    if (!root) return;

    const $ = id => document.getElementById(id);

    const LOOKUP_URL = "/logistics/api/scanner/lookup";

    let scanner = null;
    let running = false;
    let starting = false;
    let busy = false;
    let history = [];
    let toastTimer = null;
    let lastScan = "";
    let lastScanTime = 0;

    function notify(message, isError = false) {
        const element = $("lqsToast");
        element.textContent = message;
        element.classList.toggle("error", isError);
        element.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 3500);
    }

    function setError(message = "") {
        const element = $("lqsError");
        element.hidden = !message;
        element.textContent = message;
    }

    function setCameraButtons() {
        $("lqsStart").disabled = running || starting;
        $("lqsStop").disabled = !running;
        $("lqsStart").textContent = starting
            ? "Starting Camera..."
            : "Start Camera";
    }

    function formatDate(value) {
        if (!value) return "—";

        const date = new Date(
            String(value).replace(" ", "T")
        );

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleString("en-PH", {
            dateStyle: "medium",
            timeStyle: "short"
        });
    }

    function validateCode(value) {
        const code = String(value ?? "").trim();

        if (!code || code.length > 255) {
            throw new Error(
                "A valid tracking code is required."
            );
        }

        if (/[\u0000-\u001F\u007F]/.test(code)) {
            throw new Error(
                "Tracking code contains invalid characters."
            );
        }

        return code;
    }

    function extractTrackingCode(rawValue) {
        const raw = String(rawValue ?? "").trim();

        if (!raw || raw.length > 2048) {
            throw new Error("Invalid QR code content.");
        }

        if (!/^https?:\/\//i.test(raw)) {
            return validateCode(raw);
        }

        let url;

        try {
            url = new URL(raw);
        } catch {
            throw new Error("Invalid waybill URL.");
        }

        // Do not accept arbitrary websites as waybill URLs.
        const allowedHosts = [
            window.location.hostname.toLowerCase()
        ];

        if (!allowedHosts.includes(
            url.hostname.toLowerCase()
        )) {
            throw new Error(
                "This QR code is not from the current LUMEAR website."
            );
        }

        const keys = [
            "tracking_code",
            "tracking",
            "tracking_number"
        ];

        for (const key of keys) {
            const value = url.searchParams.get(key);

            if (value && value.trim()) {
                return validateCode(value);
            }
        }

        throw new Error(
            "QR code does not contain a recognized tracking number."
        );
    }

    function resetResult() {
        $("lqsResult").hidden = true;
        $("lqsEmpty").hidden = false;
    }

    function renderShipment(shipment) {
        $("lqsEmpty").hidden = true;
        $("lqsResult").hidden = false;

        $("lqsResultTracking").textContent =
            shipment.tracking_code || "—";

        $("lqsResultId").textContent =
            shipment.id ?? "—";

        $("lqsResultStatus").textContent =
            shipment.status || "Unknown";

        $("lqsResultRider").textContent =
            shipment.rider_id ?? "Not assigned";

        $("lqsResultOrder").textContent =
            shipment.seller_order_id ?? "—";

        $("lqsResultCreated").textContent =
            formatDate(shipment.created_at);

        $("lqsResultUpdated").textContent =
            formatDate(shipment.updated_at);

        const verified = root.querySelector(
            ".lqs-verified strong"
        );

        if (verified) {
            verified.textContent =
                "Waybill Verified";
        }

        const verifiedMessage = root.querySelector(
            ".lqs-verified p"
        );

        if (verifiedMessage) {
            verifiedMessage.textContent =
                shipment.ready_for_sorting
                    ? "Parcel verified and ready for sorting."
                    : "Parcel verified in LUMEAR records.";
        }

        const readonly = root.querySelector(
            ".lqs-readonly"
        );

        if (readonly) {
            readonly.textContent =
                shipment.ready_for_sorting
                    ? "Verification saved. Open Parcel Sorting to continue processing this parcel."
                    : "Verification saved. Check the parcel's current sorting stage.";
        }
    }

    function addHistory(shipment) {
        history = history.filter(
            item =>
                item.tracking_code !==
                shipment.tracking_code
        );

        history.unshift({
            tracking_code: shipment.tracking_code,
            status: shipment.ready_for_sorting
                ? "Ready for Sorting"
                : "Verified",
            scanned_at: new Date()
        });

        history = history.slice(0, 15);

        renderHistory();
    }

    function renderHistory() {
        const container = $("lqsHistory");
        container.replaceChildren();

        if (!history.length) {
            const empty = document.createElement("div");
            empty.className = "lqs-history-empty";
            empty.textContent = "No waybills verified yet.";
            container.appendChild(empty);
            return;
        }

        history.forEach(item => {
            const row = document.createElement("div");
            row.className = "lqs-history-row";

            const information = document.createElement("div");

            const code = document.createElement("strong");
            code.textContent = item.tracking_code;

            const detail = document.createElement("span");
            detail.textContent =
                `${item.status} • ${formatDate(item.scanned_at)}`;

            information.append(code, detail);

            const button = document.createElement("button");
            button.type = "button";
            button.className = "lqs-btn lqs-btn-outline";
            button.textContent = "View";

            button.addEventListener("click", () => {
                lookupTracking(item.tracking_code, "manual");
            });

            row.append(information, button);
            container.appendChild(row);
        });
    }

    async function lookupTracking(rawValue, method = "manual") {
        if (busy) return;

        let trackingCode;

        try {
            trackingCode = extractTrackingCode(rawValue);
        } catch (error) {
            resetResult();
            setError(error.message);
            notify(error.message, true);
            return;
        }

        busy = true;
        resetResult();
        setError("");

        const searchButton = $("lqsSearch");
        const originalText = searchButton.textContent;

        searchButton.disabled = true;
        searchButton.textContent = "Verifying...";

        try {
            const token = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

            if (!token) {
                throw new Error(
                    "CSRF token missing from Logistics layout."
                );
            }

            const response = await fetch(LOOKUP_URL, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": token
                },
                body: JSON.stringify({
                    tracking_code: trackingCode,
                    verification_method: method
                })
            });

            const result = await response.json()
                .catch(() => ({}));

            if (
                !response.ok
                || result.success !== true
                || !result.shipment
                || result.shipment.waybill_verified !== true
            ) {
                const validationMessage =
                    result.errors?.tracking_code?.[0];

                throw new Error(
                    validationMessage
                    || result.message
                    || `Verification failed (${response.status}).`
                );
            }

            renderShipment(result.shipment);
            addHistory(result.shipment);

            $("lqsTrackingInput").value =
                result.shipment.tracking_code;

            notify(
                result.message ||
                "Waybill verified successfully."
            );
        } catch (error) {
            resetResult();
            setError(error.message);
            notify(error.message, true);
        } finally {
            busy = false;
            searchButton.disabled = false;
            searchButton.textContent = originalText;
        }
    }

    async function startCamera() {
        if (running || starting) return;

        setError("");

        if (!window.isSecureContext) {
            notify(
                "Camera scanning requires HTTPS or localhost.",
                true
            );
            return;
        }

        if (typeof Html5Qrcode === "undefined") {
            notify(
                "QR scanner library could not load.",
                true
            );
            return;
        }

        starting = true;
        setCameraButtons();

        try {
            scanner = new Html5Qrcode("lqsReader", {
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.QR_CODE
                ],
                verbose: false
            });

            await scanner.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: {
                        width: 240,
                        height: 240
                    }
                },
                decodedText => {
                    // This callback only receives decoded QR data.
                    // Empty frames do not trigger verification.
                    if (
                        typeof decodedText !== "string"
                        || !decodedText.trim()
                    ) {
                        return;
                    }

                    const now = Date.now();

                    if (
                        decodedText === lastScan
                        && now - lastScanTime < 5000
                    ) {
                        return;
                    }

                    if (busy) return;

                    lastScan = decodedText;
                    lastScanTime = now;

                    lookupTracking(decodedText, "qr");
                },
                () => {
                    // No QR decoded in this frame.
                    // Keep scanning silently.
                }
            );

            running = true;
            $("lqsPlaceholder").hidden = true;

            notify("Camera scanner started.");
        } catch (error) {
            if (scanner) {
                try {
                    scanner.clear();
                } catch (_) {}
            }

            scanner = null;
            running = false;
            $("lqsPlaceholder").hidden = false;

            setError(
                "Unable to access the camera. Check browser permissions."
            );

            notify(
                "Camera unavailable. You can enter the waybill manually.",
                true
            );
        } finally {
            starting = false;
            setCameraButtons();
        }
    }

    async function stopCamera() {
        if (!scanner || !running) return;

        try {
            await scanner.stop();
            scanner.clear();

            running = false;
            scanner = null;

            $("lqsPlaceholder").hidden = false;

            notify("Camera scanner stopped.");
        } catch (error) {
            notify(
                "Could not stop the camera cleanly.",
                true
            );
        } finally {
            setCameraButtons();
        }
    }

    $("lqsStart").addEventListener(
        "click",
        startCamera
    );

    $("lqsStop").addEventListener(
        "click",
        stopCamera
    );

    $("lqsManualForm").addEventListener(
        "submit",
        event => {
            event.preventDefault();

            lookupTracking(
                $("lqsTrackingInput").value,
                "manual"
            );
        }
    );

    $("lqsClearHistory").addEventListener(
        "click",
        () => {
            history = [];
            renderHistory();
            notify("Recent scan list cleared.");
        }
    );

    window.addEventListener("pagehide", () => {
        if (scanner && running) {
            scanner.stop().catch(() => {});
        }
    });

    setCameraButtons();
    renderHistory();
});
