/* =====================================================
   LUMEAR LOGISTICS — WAYBILL QR SCANNER
   Camera scanning + Laravel shipment verification
   ===================================================== */

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

    function extractTrackingCode(rawValue) {
        const raw = String(rawValue || "").trim();

        if (!raw || raw.length > 2048) {
            throw new Error("Invalid QR code content.");
        }

        // A plain tracking code is the preferred format.
        if (!/^https?:\/\//i.test(raw)) {
            return raw;
        }

        // Some waybills encode a tracking URL instead.
        // Accept only explicitly named tracking parameters.
        let url;

        try {
            url = new URL(raw);
        } catch {
            throw new Error("Invalid waybill URL.");
        }

        const keys = [
            "tracking_code",
            "tracking",
            "tracking_number"
        ];

        for (const key of keys) {
            const value = url.searchParams.get(key);

            if (value && value.trim()) {
                return value.trim();
            }
        }

        throw new Error(
            "The QR code contains a URL without a recognized tracking parameter."
        );
    }

    async function lookupTracking(rawValue) {
        if (busy) return;

        let trackingCode;

        try {
            trackingCode = extractTrackingCode(rawValue);
        } catch (error) {
            setError(error.message);
            notify(error.message, true);
            return;
        }

        if (trackingCode.length > 255) {
            setError("Tracking code is too long.");
            return;
        }

        busy = true;
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
                    tracking_code: trackingCode
                })
            });

            const result = await response.json()
                .catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    result.message ||
                    `Lookup failed (${response.status}).`
                );
            }

            if (!result.shipment) {
                throw new Error(
                    "Shipment details were not returned."
                );
            }

            renderShipment(result.shipment);
            addHistory(result.shipment);

            $("lqsTrackingInput").value =
                result.shipment.tracking_code;

            notify("Waybill verified successfully.");
        } catch (error) {
            $("lqsResult").hidden = true;
            $("lqsEmpty").hidden = false;

            setError(error.message);
            notify(error.message, true);
        } finally {
            busy = false;
            searchButton.disabled = false;
            searchButton.textContent = originalText;
        }
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
    }

    function addHistory(shipment) {
        history = history.filter(
            item => item.tracking_code !==
                shipment.tracking_code
        );

        history.unshift({
            tracking_code: shipment.tracking_code,
            status: shipment.status || "Unknown",
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
            empty.textContent = "No waybills scanned yet.";
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
                lookupTracking(item.tracking_code);
            });

            row.append(information, button);
            container.appendChild(row);
        });
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
            scanner = new Html5Qrcode("lqsReader");

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
                    const now = Date.now();

                    // Ignore repeated frames of the same code.
                    if (
                        decodedText === lastScan &&
                        now - lastScanTime < 4000
                    ) {
                        return;
                    }

                    if (busy) return;

                    lastScan = decodedText;
                    lastScanTime = now;

                    lookupTracking(decodedText);
                },
                () => {
                    // Normal frames without a QR code
                    // do not need error messages.
                }
            );

            running = true;
            $("lqsPlaceholder").hidden = true;

            notify("Camera scanner started.");
        } catch (error) {
            scanner = null;

            setError(
                "Unable to access the camera. Check browser permissions."
            );

            notify(
                "Camera unavailable. You can use manual lookup.",
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
                $("lqsTrackingInput").value
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
