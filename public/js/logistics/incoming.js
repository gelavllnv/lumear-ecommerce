/* =========================================================
   LUMEAR LOGISTICS — INCOMING PARCELS
   Database-connected JavaScript
   Original UI preserved
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    const app = document.getElementById("incomingApp");
    if (!app) return;

    const $ = (id) => document.getElementById(id);

    const API = "/logistics/api/incoming-parcels";

    const PAGE_SIZE = 6;

    let parcels = [];
    let currentPage = 1;
    let selectedTracking = null;
    let currentAction = null;
    let toastTimer = null;
    let loading = false;

    const today = new Date();

    function dateString(daysOffset = 0) {
        const date = new Date(today);
        date.setDate(date.getDate() + daysOffset);

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }

    function makeElement(tag, className = "", text = "") {
        const element = document.createElement(tag);

        if (className) element.className = className;
        if (text !== "") element.textContent = String(text);

        return element;
    }

    function showToast(message, isError = false) {
        const toast = $("incToast");
        if (!toast) return;

        toast.textContent = message;
        toast.classList.toggle("error", isError);
        toast.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            toast.classList.remove("show");
        }, 3500);
    }

    function csrfToken() {
        return document.querySelector(
            'meta[name="csrf-token"]'
        )?.content || "";
    }

    async function apiRequest(url, options = {}) {
        const headers = {
            Accept: "application/json",
            ...options.headers
        };

        if (options.body && !(options.body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
        }

        if (options.method && options.method.toUpperCase() !== "GET") {
            const token = csrfToken();

            if (!token) {
                throw new Error(
                    "Missing CSRF token. Add the csrf-token meta tag to your Logistics layout."
                );
            }

            headers["X-CSRF-TOKEN"] = token;
        }

        const response = await fetch(url, {
            credentials: "same-origin",
            ...options,
            headers
        });

        let result;

        try {
            result = await response.json();
        } catch {
            throw new Error(
                `Server returned an invalid response (${response.status}).`
            );
        }

        if (!response.ok) {
            const validationMessage = result.errors
                ? Object.values(result.errors).flat().join(" ")
                : null;

            throw new Error(
                validationMessage ||
                result.message ||
                `Request failed (${response.status}).`
            );
        }

        return result;
    }

    async function loadIncomingParcels() {
        if (loading) return;

        loading = true;

        const refresh = $("incRefresh");

        if (refresh) refresh.disabled = true;

        try {
            const data = await apiRequest(API);

            parcels = Array.isArray(data.parcels)
                ? data.parcels.map(normalizeParcel)
                : [];

            currentPage = 1;
            render();

        } catch (error) {
            showToast(error.message, true);
        } finally {
            loading = false;

            if (refresh) refresh.disabled = false;
        }
    }

    function normalizeParcel(parcel) {
        return {
            id: parcel.id,
            tracking: String(parcel.tracking ?? ""),
            seller: String(parcel.seller ?? "Seller"),
            recipient: String(parcel.recipient ?? "Recipient"),
            contact: String(parcel.contact ?? ""),
            destination: String(parcel.destination ?? ""),
            type: String(parcel.type ?? "Standard"),
            weight: parcel.weight ?? "—",
            expected: String(parcel.expected ?? ""),
            status: String(parcel.status ?? "Awaiting Arrival"),
            notes: String(parcel.notes ?? ""),
            receivedAt: String(parcel.receivedAt ?? ""),
            condition: String(parcel.condition ?? ""),
            remarks: String(parcel.remarks ?? ""),
            verifiedAt: String(parcel.verifiedAt ?? "")
        };
    }

    function openModal(id) {
        const modal = $(id);
        if (!modal) return;

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");

        const focusable = modal.querySelector(
            "input, select, textarea, button"
        );

        if (focusable) focusable.focus();
    }

    function closeModal(id) {
        const modal = $(id);
        if (!modal) return;

        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
    }

    function closeAllModals() {
        document.querySelectorAll(".inc-modal.open").forEach(modal => {
            closeModal(modal.id);
        });
    }

    document.querySelectorAll("[data-close-modal]").forEach(button => {
        button.addEventListener("click", closeAllModals);
    });

    document.querySelectorAll(".inc-modal").forEach(modal => {
        modal.addEventListener("click", event => {
            if (event.target === modal) closeAllModals();
        });
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") closeAllModals();
    });

    function getParcel(tracking) {
        return parcels.find(parcel => parcel.tracking === tracking);
    }

    function badgeClass(status) {
        switch (status) {
            case "Received":
                return "inc-badge-received";

            case "Ready for Sorting":
                return "inc-badge-ready";

            case "Discrepancy":
                return "inc-badge-issue";

            default:
                return "inc-badge-awaiting";
        }
    }

    function initials(name) {
        return String(name || "")
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(word => word[0].toUpperCase())
            .join("");
    }

    function formatDate(value) {
        if (!value) return "—";

        const datePart = String(value).substring(0, 10);
        const parts = datePart.split("-");

        if (parts.length !== 3) return value;

        return `${parts[1]}/${parts[2]}/${parts[0]}`;
    }

    function getFilteredParcels() {
        const search = $("incSearch").value.trim().toLowerCase();
        const status = $("incStatusFilter").value;
        const area = $("incAreaFilter").value;

        return parcels.filter(parcel => {
            const searchable = [
                parcel.tracking,
                parcel.seller,
                parcel.recipient,
                parcel.destination,
                parcel.contact
            ].join(" ").toLowerCase();

            return (
                searchable.includes(search) &&
                (status === "all" || parcel.status === status) &&
                (area === "all" || parcel.destination === area)
            );
        });
    }

    function updateStatistics() {
        const count = status =>
            parcels.filter(parcel => parcel.status === status).length;

        $("incAwaitingCount").textContent = count("Awaiting Arrival");
        $("incReceivedCount").textContent = count("Received");
        $("incReadyCount").textContent = count("Ready for Sorting");
        $("incIssueCount").textContent = count("Discrepancy");
    }

    function updateAreaOptions() {
        const select = $("incAreaFilter");
        const previous = select.value;

        const areas = [...new Set(
            parcels.map(parcel => parcel.destination).filter(Boolean)
        )].sort();

        select.replaceChildren();

        const all = makeElement("option", "", "All Areas");
        all.value = "all";
        select.appendChild(all);

        areas.forEach(area => {
            const option = makeElement("option", "", area);
            option.value = area;
            select.appendChild(option);
        });

        select.value = areas.includes(previous) ? previous : "all";
    }

    function addCell(row, primary, secondary = "") {
        const cell = makeElement("td");

        cell.appendChild(
            makeElement("div", "inc-cell-primary", primary)
        );

        if (secondary) {
            cell.appendChild(
                makeElement("div", "inc-cell-secondary", secondary)
            );
        }

        row.appendChild(cell);

        return cell;
    }

    function addActionButton(
        container,
        label,
        action,
        tracking,
        primary = false
    ) {
        const button = makeElement(
            "button",
            primary
                ? "inc-btn inc-btn-primary"
                : "inc-btn inc-btn-outline",
            label
        );

        button.type = "button";

        button.addEventListener("click", () => {
            if (action === "details") {
                showDetails(tracking);
            } else {
                showAction(tracking, action);
            }
        });

        container.appendChild(button);
    }

    function buildRow(parcel) {
        const row = makeElement("tr");

        addCell(row, parcel.tracking, "Inbound Shipment");

        const sellerCell = makeElement("td");
        const sellerWrap = makeElement("div", "inc-seller");

        const avatar = makeElement(
            "div",
            "inc-seller-avatar",
            initials(parcel.seller)
        );

        const sellerText = makeElement("div");

        sellerText.appendChild(
            makeElement("div", "inc-cell-primary", parcel.seller)
        );

        sellerText.appendChild(
            makeElement("div", "inc-cell-secondary", "Seller")
        );

        sellerWrap.append(avatar, sellerText);
        sellerCell.appendChild(sellerWrap);
        row.appendChild(sellerCell);

        addCell(row, parcel.recipient, parcel.contact);
        addCell(row, parcel.destination);

        const weightLabel = parcel.weight === "—"
            ? "—"
            : `${parcel.weight} kg`;

        addCell(row, parcel.type, weightLabel);
        addCell(row, formatDate(parcel.expected));

        const statusCell = makeElement("td");

        statusCell.appendChild(
            makeElement(
                "span",
                `inc-badge ${badgeClass(parcel.status)}`,
                parcel.status
            )
        );

        row.appendChild(statusCell);

        const actionCell = makeElement("td");
        const actionWrap = makeElement("div", "inc-table-actions");

        addActionButton(
            actionWrap,
            "Details",
            "details",
            parcel.tracking
        );

        if (parcel.status === "Awaiting Arrival") {
            addActionButton(
                actionWrap,
                "Receive",
                "receive",
                parcel.tracking,
                true
            );
        } else if (parcel.status === "Received") {
            addActionButton(
                actionWrap,
                parcel.verifiedAt ? "Update Verification" : "Verify",
                "verify",
                parcel.tracking,
                true
            );
        } else if (parcel.status === "Discrepancy") {
            addActionButton(
                actionWrap,
                "Review",
                "review",
                parcel.tracking,
                true
            );
        }

        actionCell.appendChild(actionWrap);
        row.appendChild(actionCell);

        return row;
    }

    function render() {
        updateStatistics();
        updateAreaOptions();

        const filtered = getFilteredParcels();
        const pageCount = Math.max(
            1,
            Math.ceil(filtered.length / PAGE_SIZE)
        );

        currentPage = Math.min(
            Math.max(1, currentPage),
            pageCount
        );

        const start = (currentPage - 1) * PAGE_SIZE;
        const visible = filtered.slice(start, start + PAGE_SIZE);

        const body = $("incTableBody");
        body.replaceChildren();

        visible.forEach(parcel => {
            body.appendChild(buildRow(parcel));
        });

        $("incEmpty").hidden = filtered.length > 0;

        $("incVisibleCount").textContent =
            `${filtered.length} ${
                filtered.length === 1 ? "parcel" : "parcels"
            }`;

        $("incShowing").textContent = filtered.length
            ? `Showing ${start + 1}–${start + visible.length} of ${filtered.length} parcels`
            : "Showing 0 parcels";

        $("incPageLabel").textContent =
            `Page ${currentPage} of ${pageCount}`;

        $("incPrev").disabled = currentPage === 1;
        $("incNext").disabled = currentPage === pageCount;
    }

    function addDetail(container, label, value) {
        const item = makeElement("div", "inc-detail-item");

        item.appendChild(makeElement("span", "", label));
        item.appendChild(makeElement("strong", "", value || "—"));

        container.appendChild(item);
    }

    function showDetails(tracking) {
        const parcel = getParcel(tracking);
        if (!parcel) return;

        selectedTracking = tracking;

        $("incDetailsSubtitle").textContent =
            `Tracking ID: ${parcel.tracking}`;

        const grid = $("incDetailsGrid");
        grid.replaceChildren();

        const weightLabel = parcel.weight === "—"
            ? "—"
            : `${parcel.weight} kg`;

        [
            ["Tracking ID", parcel.tracking],
            ["Seller / Sender", parcel.seller],
            ["Recipient", parcel.recipient],
            ["Contact Number", parcel.contact],
            ["Destination", parcel.destination],
            ["Parcel Type", parcel.type],
            ["Weight", weightLabel],
            ["Expected Arrival", formatDate(parcel.expected)],
            ["Current Status", parcel.status],
            ["Received At", formatDate(parcel.receivedAt)],
            ["Parcel Condition", parcel.condition],
            ["Receiving Remarks", parcel.remarks]
        ].forEach(([label, value]) => {
            addDetail(grid, label, value);
        });

        $("incDetailsNote").textContent =
            `Handling Instructions: ${
                parcel.notes || "None provided."
            }`;

        const button = $("incDetailsAction");
        button.hidden = false;

        if (parcel.status === "Awaiting Arrival") {
            button.textContent = "Receive Parcel";
            button.dataset.action = "receive";
        } else if (parcel.status === "Received") {
            button.textContent = parcel.verifiedAt
                ? "Update Verification"
                : "Verify Parcel";
            button.dataset.action = "verify";
        } else if (parcel.status === "Discrepancy") {
            button.textContent = "Review Discrepancy";
            button.dataset.action = "review";
        } else {
            button.hidden = true;
        }

        openModal("incDetailsModal");
    }

    function showAction(tracking, action) {
        const parcel = getParcel(tracking);
        if (!parcel) return;

        selectedTracking = tracking;
        currentAction = action;

        $("incSelectedTracking").textContent = tracking;
        $("incActionForm").reset();

        const title = $("incActionTitle");
        const subtitle = $("incActionSubtitle");
        const confirm = $("incConfirmAction");
        const conditionField = $("incConditionField");

        conditionField.hidden = false;

        if (action === "receive") {
            title.textContent = "Receive Incoming Parcel";
            subtitle.textContent =
                "Confirm that this parcel has physically arrived at the sorting center.";
            confirm.textContent = "Confirm Receipt";
        } else if (action === "verify") {
            title.textContent = "Verify Received Parcel";
            subtitle.textContent =
                "Check parcel information and condition before sorting.";
            confirm.textContent = "Save Verification";
        } else if (action === "review") {
            title.textContent = "Review Discrepancy";
            subtitle.textContent =
                "Review the reported issue and record the next decision.";
            confirm.textContent = "Save Review";
            $("incCondition").value =
                parcel.condition || "Other concern";
        }

        $("incActionNotes").value = parcel.remarks || "";

        closeModal("incDetailsModal");
        openModal("incActionModal");
    }

    async function processAction(event) {
        event.preventDefault();

        const parcel = getParcel(selectedTracking);

        if (!parcel) {
            showToast("Parcel not found.", true);
            return;
        }

        if (
            currentAction === "receive" &&
            parcel.status !== "Awaiting Arrival"
        ) {
            showToast("Parcel has already been received.", true);
            return;
        }

        if (currentAction === "verify" && parcel.status !== "Received") {
            showToast("Only received parcels can be verified.", true);
            return;
        }

        if (
            currentAction === "review" &&
            parcel.status !== "Discrepancy"
        ) {
            showToast("This parcel has no discrepancy to review.", true);
            return;
        }

        const condition = $("incCondition").value;
        const remarks = $("incActionNotes").value.trim();
        const confirm = $("incConfirmAction");

        confirm.disabled = true;

        try {
            const endpoint = currentAction === "receive"
                ? "receive"
                : currentAction === "verify"
                    ? "verify"
                    : "review";

            const result = await apiRequest(
                `${API}/${parcel.id}/${endpoint}`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        condition,
                        remarks
                    })
                }
            );

            closeAllModals();

            showToast(
                result.message || "Parcel received successfully."
            );

            await loadIncomingParcels();

        } catch (error) {
            showToast(error.message, true);
        } finally {
            confirm.disabled = false;
        }
    }

    function registerParcel(event) {
        event.preventDefault();

        showToast(
            "Parcel registration must be linked to an existing Seller Order. Manual shipment creation is not yet enabled.",
            true
        );
    }

    function exportCSV() {
        const data = getFilteredParcels();

        if (!data.length) {
            showToast("No parcels available for export.", true);
            return;
        }

        const headers = [
            "Tracking ID",
            "Seller",
            "Recipient",
            "Contact",
            "Destination",
            "Parcel Type",
            "Weight (kg)",
            "Expected Arrival",
            "Status",
            "Received At",
            "Condition",
            "Remarks",
            "Handling Notes"
        ];

        const escapeCSV = value => {
            let text = String(value ?? "");

            if (/^[\s]*[=+\-@\t\r]/.test(text)) {
                text = "'" + text;
            }

            return `"${text.replace(/"/g, '""')}"`;
        };

        const rows = data.map(p => [
            p.tracking,
            p.seller,
            p.recipient,
            p.contact,
            p.destination,
            p.type,
            p.weight,
            p.expected,
            p.status,
            p.receivedAt,
            p.condition,
            p.remarks,
            p.notes
        ]);

        const csv = "\uFEFF" + [
            headers.map(escapeCSV).join(","),
            ...rows.map(row => row.map(escapeCSV).join(","))
        ].join("\r\n");

        const blob = new Blob([csv], {
            type: "text/csv;charset=utf-8;"
        });

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download =
            `lumear-incoming-parcels-${dateString()}.csv`;

        document.body.appendChild(link);
        link.click();
        link.remove();

        URL.revokeObjectURL(url);

        showToast("Incoming parcel list exported.");
    }

    // EVENT LISTENERS

    $("incRegister").addEventListener("click", () => {
        $("incRegisterForm").reset();
        $("incExpected").value = dateString();
        openModal("incRegisterModal");
    });

    $("incRegisterForm").addEventListener(
        "submit",
        registerParcel
    );

    $("incActionForm").addEventListener(
        "submit",
        processAction
    );

    $("incDetailsAction").addEventListener("click", () => {
        showAction(
            selectedTracking,
            $("incDetailsAction").dataset.action
        );
    });

    $("incSearch").addEventListener("input", () => {
        currentPage = 1;
        render();
    });

    $("incStatusFilter").addEventListener("change", () => {
        currentPage = 1;
        render();
    });

    $("incAreaFilter").addEventListener("change", () => {
        currentPage = 1;
        render();
    });

    $("incPrev").addEventListener("click", () => {
        currentPage--;
        render();
    });

    $("incNext").addEventListener("click", () => {
        currentPage++;
        render();
    });

    $("incRefresh").addEventListener("click", async () => {
        await loadIncomingParcels();
    });

    $("incExport").addEventListener("click", exportCSV);

    // INITIAL DATABASE LOAD
    loadIncomingParcels();
});
