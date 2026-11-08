/* LUMEAR LOGISTICS — DELIVERY MONITORING */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    if (!document.getElementById("deliveryMonitoringApp")) return;

    const $ = id => document.getElementById(id);
    const API = "/logistics/api/delivery";
    const PAGE_SIZE = 6;

    const STATUSES = [
        "Dispatched",
        "In Transit",
        "Out for Delivery",
        "Delivered",
        "Delay Reported",
        "Delayed",
        "Failed Attempt"
    ];

    let RIDERS = [];
    let deliveries = [];
    let page = 1;
    let activeTracking = null;
    let toastTimer;

    function el(tag, className = "", text = "") {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== "") node.textContent = String(text);
        return node;
    }

    function toast(message, error = false) {
        const node = $("dmToast");
        if (!node) return;

        node.textContent = message;
        node.classList.toggle("error", error);
        node.classList.add("show");

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            node.classList.remove("show");
        }, 3500);
    }

    async function request(path, options = {}) {
        const method = (options.method || "GET").toUpperCase();

        const token = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (method !== "GET" && !token) {
            throw new Error("Missing CSRF token in Logistics layout.");
        }

        const response = await fetch(API + path, {
            ...options,
            method,
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                ...(method !== "GET" ? {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": token
                } : {}),
                ...options.headers
            }
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                Object.values(data.errors || {}).flat().join(" ") ||
                data.message ||
                `Request failed (${response.status})`
            );
        }

        return data;
    }

    async function loadMonitoring() {
        try {
            const data = await request("/monitoring");

            RIDERS = Array.isArray(data.riders) ? data.riders : [];
            deliveries = Array.isArray(data.deliveries)
                ? data.deliveries
                : [];

            page = 1;
            render();
        } catch (error) {
            toast(error.message, true);
        }
    }

    function findDelivery(tracking) {
        return deliveries.find(d => d.tracking === tracking);
    }

    function findRider(id) {
        return RIDERS.find(r => String(r.id) === String(id));
    }

    function formatDate(value) {
        if (!value) return "—";

        const date = new Date(
            `${String(value).slice(0, 10)}T12:00:00`
        );

        return Number.isNaN(date.getTime())
            ? String(value)
            : date.toLocaleDateString("en-PH", {
                month: "short",
                day: "numeric",
                year: "numeric"
            });
    }

    function initials(name) {
        return String(name || "")
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(w => w[0].toUpperCase())
            .join("");
    }

    function isActive(status) {
        return [
            "Dispatched",
            "In Transit",
            "Out for Delivery",
            "Delay Reported"
        ].includes(status);
    }

    function progress(status) {
        return ({
            "Dispatched": 20,
            "In Transit": 50,
            "Out for Delivery": 80,
            "Delivered": 100,
            "Delay Reported": 50,
            "Delayed": 50,
            "Failed Attempt": 80
        })[status] ?? 0;
    }

    function syncRescheduleFields() {
        const isRescheduling = $("dmNewStatus").value === "Delayed";
        const field = $("dmRescheduleField");
        const input = $("dmRescheduleFor");

        field.hidden = !isRescheduling;
        input.required = isRescheduling;

        if (isRescheduling) {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            input.min = now.toISOString().slice(0, 16);
        } else {
            input.value = "";
        }
    }

    function statusColor(status) {
        if (status === "Delivered") return "green";
        if (status === "Delayed" || status === "Delay Reported") return "amber";
        if (status === "Failed Attempt") return "red";
        return "blue";
    }

    function badge(status) {
        return el(
            "span",
            `dm-badge dm-badge-${statusColor(status)}`,
            status
        );
    }

    function openModal(id) {
        const modal = $(id);
        if (!modal) return;

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        modal.querySelector("button,select,input")?.focus();
    }

    function closeModals() {
        document.querySelectorAll(".dm-modal.open").forEach(modal => {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
        });
    }

    document.querySelectorAll("[data-dm-close]").forEach(button =>
        button.addEventListener("click", closeModals)
    );

    document.querySelectorAll(".dm-modal").forEach(modal =>
        modal.addEventListener("click", e => {
            if (e.target === modal) closeModals();
        })
    );

    document.addEventListener("keydown", e => {
        if (e.key === "Escape") closeModals();
    });

    function filteredDeliveries() {
        const query = $("dmSearch").value.trim().toLowerCase();
        const status = $("dmStatusFilter").value;
        const rider = $("dmRiderFilter").value;

        return deliveries.filter(d => {
            const riderName = findRider(d.riderId)?.name || "";
            const search = [
                d.tracking, d.recipient, d.destination,
                d.riderId, riderName
            ].join(" ").toLowerCase();

            return search.includes(query) &&
                (status === "all" || d.status === status) &&
                (rider === "all" || String(d.riderId) === rider);
        });
    }

    function renderStats() {
        $("dmActiveCount").textContent =
            deliveries.filter(d => isActive(d.status)).length;

        $("dmDeliveredCount").textContent =
            deliveries.filter(d => d.status === "Delivered").length;

        $("dmDelayedCount").textContent =
            deliveries.filter(d =>
                ["Delayed", "Delay Reported"].includes(d.status)
            ).length;

        $("dmFailedCount").textContent =
            deliveries.filter(d => d.status === "Failed Attempt").length;
    }

    function renderProgress() {
        const container = $("dmProgressContent");
        container.replaceChildren();

        const total = deliveries.length;

        STATUSES.forEach(status => {
            const count = deliveries.filter(
                d => d.status === status
            ).length;

            const percentage = total
                ? Math.round(count / total * 100)
                : 0;

            const row = el("div", "dm-progress-row");
            const meta = el("div", "dm-progress-meta");

            meta.appendChild(el("span", "", status));
            meta.appendChild(el("strong", "", `${count} parcels`));

            const track = el("div", "dm-progress-track");
            const fill = el(
                "div",
                `dm-progress-fill dm-fill-${statusColor(status)}`
            );

            fill.style.width = `${percentage}%`;
            track.appendChild(fill);

            row.append(meta, track);
            container.appendChild(row);
        });
    }

    function renderAlerts() {
        const alerts = deliveries.filter(d =>
            ["Delayed", "Delay Reported", "Failed Attempt"].includes(d.status)
        );

        $("dmAlertCount").textContent = `${alerts.length} alerts`;

        const container = $("dmAlertList");
        container.replaceChildren();

        if (!alerts.length) {
            container.appendChild(
                el("p", "dm-no-alerts", "No delivery exceptions reported.")
            );
            return;
        }

        alerts.forEach(d => {
            const item = el("article", "dm-alert-item");
            const info = el("div");

            info.appendChild(el("strong", "", d.tracking));
            info.appendChild(el(
                "p", "", `${d.status} · ${d.destination}`
            ));

            const button = el("button", "", "Review");
            button.type = "button";
            button.addEventListener("click", () =>
                showDetails(d.tracking)
            );

            item.append(info, button);
            container.appendChild(item);
        });
    }

    function renderRiderOptions() {
        const select = $("dmRiderFilter");
        const previous = select.value;
        select.replaceChildren();

        const all = el("option", "", "All Riders");
        all.value = "all";
        select.appendChild(all);

        RIDERS.forEach(rider => {
            const option = el("option", "", rider.name);
            option.value = rider.id;
            select.appendChild(option);
        });

        select.value = RIDERS.some(
            r => String(r.id) === previous
        ) ? previous : "all";
    }

    function cell(row, main, secondary = "") {
        const td = el("td");
        td.appendChild(el("div", "dm-cell-primary", main));

        if (secondary) {
            td.appendChild(el("div", "dm-cell-secondary", secondary));
        }

        row.appendChild(td);
    }

    function makeRow(d) {
        const row = el("tr");
        const rider = findRider(d.riderId);

        cell(row, d.tracking);
        cell(row, d.recipient);
        cell(row, d.destination);
        cell(row, rider?.name || "Unknown Rider", d.riderId);
        cell(row, formatDate(d.date));

        const statusCell = el("td");
        statusCell.appendChild(badge(d.status));
        row.appendChild(statusCell);

        const progressCell = el("td");
        const track = el("div", "dm-mini-progress");
        const fill = el("span");
        fill.style.width = `${progress(d.status)}%`;
        track.appendChild(fill);

        progressCell.appendChild(track);
        progressCell.appendChild(el(
            "div", "dm-cell-secondary", `${progress(d.status)}%`
        ));
        row.appendChild(progressCell);

        const actionsCell = el("td");
        const actions = el("div", "dm-table-actions");

        const details = el(
            "button", "dm-btn dm-btn-outline", "Details"
        );
        details.type = "button";
        details.addEventListener("click", () =>
            showDetails(d.tracking)
        );
        actions.appendChild(details);

        if (d.status !== "Delivered") {
            const update = el(
                "button", "dm-btn dm-btn-primary", "Update"
            );
            update.type = "button";
            update.addEventListener("click", () =>
                showUpdate(d.tracking)
            );
            actions.appendChild(update);
        }

        actionsCell.appendChild(actions);
        row.appendChild(actionsCell);

        return row;
    }

    function renderTable() {
        const filtered = filteredDeliveries();
        const pages = Math.max(
            1,
            Math.ceil(filtered.length / PAGE_SIZE)
        );

        page = Math.min(Math.max(1, page), pages);

        const start = (page - 1) * PAGE_SIZE;
        const visible = filtered.slice(start, start + PAGE_SIZE);

        const tbody = $("dmTableBody");
        tbody.replaceChildren();

        visible.forEach(d => tbody.appendChild(makeRow(d)));

        $("dmEmpty").hidden = filtered.length > 0;
        $("dmResultCount").textContent = `${filtered.length} records`;

        $("dmShowing").textContent = filtered.length
            ? `Showing ${start + 1}–${start + visible.length} of ${filtered.length} deliveries`
            : "Showing 0 deliveries";

        $("dmPageLabel").textContent = `Page ${page} of ${pages}`;
        $("dmPrev").disabled = page <= 1;
        $("dmNext").disabled = page >= pages;
    }

    function renderRiders() {
        const grid = $("dmRiderGrid");
        grid.replaceChildren();

        RIDERS.forEach(rider => {
            const own = deliveries.filter(
                d => String(d.riderId) === String(rider.id)
            );
            const active = own.filter(
                d => isActive(d.status)
            ).length;
            const completed = own.filter(
                d => d.status === "Delivered"
            ).length;

            const card = el("article", "dm-rider-card");
            const top = el("div", "dm-rider-top");

            top.appendChild(el(
                "div", "dm-rider-avatar", initials(rider.name)
            ));

            const info = el("div");
            info.appendChild(el("strong", "", rider.name));
            info.appendChild(el("small", "", rider.area));
            top.appendChild(info);
            card.appendChild(top);

            const stats = el("div", "dm-rider-stats");

            [
                ["Active Deliveries", active],
                ["Delivered", completed]
            ].forEach(([label, value]) => {
                const box = el("div", "dm-rider-stat");
                box.appendChild(el("span", "", label));
                box.appendChild(el("strong", "", value));
                stats.appendChild(box);
            });

            card.appendChild(stats);
            grid.appendChild(card);
        });
    }

    function detailItem(label, value) {
        const item = el("div", "dm-detail-item");
        item.appendChild(el("span", "", label));
        item.appendChild(el("strong", "", value || "—"));
        return item;
    }

    function showDetails(tracking) {
        const d = findDelivery(tracking);
        if (!d) return;

        activeTracking = tracking;
        $("dmDetailsSubtitle").textContent = tracking;

        const rider = findRider(d.riderId);
        const grid = $("dmDetailsGrid");
        grid.replaceChildren();

        [
            ["Tracking ID", d.tracking],
            ["Recipient", d.recipient],
            ["Destination", d.destination],
            ["Assigned Rider", rider?.name || "Unknown"],
            ["Rider ID", d.riderId],
            ["Dispatch Date", formatDate(d.date)],
            ["Current Status", d.status],
            ["Latest Remarks", d.remarks || "None"]
        ].forEach(([label, value]) =>
            grid.appendChild(detailItem(label, value))
        );

        const timeline = $("dmTimeline");
        timeline.replaceChildren();

        [...(d.history || [])].reverse().forEach(event => {
            const item = el("div", "dm-timeline-item");
            item.appendChild(el("strong", "", event.status));
            item.appendChild(el("span", "", event.time));
            item.appendChild(el("p", "", event.note));
            timeline.appendChild(item);
        });

        $("dmDetailsUpdate").hidden = d.status === "Delivered";

        closeModals();
        openModal("dmDetailsModal");
    }

    function allowedStatuses(status) {
        const transitions = {
            "Dispatched": ["In Transit", "Delayed", "Failed Attempt"],
            "In Transit": [
                "Out for Delivery", "Delayed", "Failed Attempt"
            ],
            "Out for Delivery": [
                "Delivered", "Delayed", "Failed Attempt"
            ],
            "Delayed": [
                "In Transit", "Out for Delivery", "Delayed", "Failed Attempt"
            ],
            "Delay Reported": [
                "Delayed", "In Transit", "Out for Delivery", "Failed Attempt"
            ],
            "Failed Attempt": [
                "Out for Delivery", "Delayed"
            ],
            "Delivered": []
        };

        return transitions[status] || [];
    }

    function showUpdate(tracking) {
        const d = findDelivery(tracking);
        if (!d) return;

        const allowed = allowedStatuses(d.status);

        if (!allowed.length) {
            toast("No status transitions available.", true);
            return;
        }

        activeTracking = tracking;

        $("dmUpdateSubtitle").textContent =
            `${tracking} · Current status: ${d.status}`;

        const select = $("dmNewStatus");
        select.replaceChildren();

        const placeholder = el("option", "", "Select status");
        placeholder.value = "";
        select.appendChild(placeholder);

        allowed.forEach(status => {
            const option = el("option", "", status);
            option.value = status;
            select.appendChild(option);
        });

        $("dmUpdateNotes").value = "";
        $("dmRescheduleFor").value = "";
        syncRescheduleFields();

        closeModals();
        openModal("dmUpdateModal");
    }

    async function saveUpdate(event) {
        event.preventDefault();

        const d = findDelivery(activeTracking);
        const status = $("dmNewStatus").value;
        const remarks = $("dmUpdateNotes").value.trim();
        const rescheduledFor = $("dmRescheduleFor").value;

        if (!d || !allowedStatuses(d.status).includes(status)) {
            toast("Invalid delivery status change.", true);
            return;
        }

        if (["Delayed", "Failed Attempt"].includes(status) && !remarks) {
            toast("Remarks are required for delivery exceptions.", true);
            return;
        }

        if (status === "Delayed" && !rescheduledFor) {
            toast("Choose the new delivery date and time.", true);
            return;
        }

        const button = $("dmUpdateForm").querySelector(
            '[type="submit"]'
        );

        if (button) button.disabled = true;

        try {
            await request(`/monitoring/${d.id}/status`, {
                method: "POST",
                body: JSON.stringify({
                    status,
                    remarks,
                    ...(status === "Delayed"
                        ? { rescheduled_for: rescheduledFor }
                        : {})
                })
            });

            closeModals();
            toast(status === "Delayed"
                ? "Delivery rescheduled. The buyer has been notified."
                : "Delivery status saved successfully.");
            await loadMonitoring();
        } catch (error) {
            toast(error.message, true);
        } finally {
            if (button) button.disabled = false;
        }
    }

    function exportCSV() {
        const data = filteredDeliveries();

        if (!data.length) {
            toast("No delivery records to export.", true);
            return;
        }

        const rows = [[
            "Tracking ID", "Recipient", "Destination",
            "Rider ID", "Rider Name", "Dispatch Date",
            "Status", "Remarks"
        ]];

        data.forEach(d => rows.push([
            d.tracking,
            d.recipient,
            d.destination,
            d.riderId,
            findRider(d.riderId)?.name || "",
            d.date,
            d.status,
            d.remarks
        ]));

        const escapeCSV = value => {
            let text = String(value ?? "");
            if (/^\s*[=+\-@\t\r]/.test(text)) text = "'" + text;
            return `"${text.replace(/"/g, '""')}"`;
        };

        const csv = "\uFEFF" + rows.map(
            row => row.map(escapeCSV).join(",")
        ).join("\r\n");

        const url = URL.createObjectURL(
            new Blob([csv], { type: "text/csv;charset=utf-8;" })
        );

        const link = document.createElement("a");
        link.href = url;
        link.download = "lumear-delivery-monitoring.csv";
        document.body.appendChild(link);
        link.click();
        link.remove();

        setTimeout(() => URL.revokeObjectURL(url), 1000);
        toast("Delivery monitoring report exported.");
    }

    function render() {
        renderStats();
        renderProgress();
        renderAlerts();
        renderRiderOptions();
        renderTable();
        renderRiders();
    }

    $("dmSearch").addEventListener("input", () => {
        page = 1;
        renderTable();
    });

    $("dmStatusFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("dmRiderFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("dmPrev").addEventListener("click", () => {
        page--;
        renderTable();
    });

    $("dmNext").addEventListener("click", () => {
        page++;
        renderTable();
    });

    $("dmDetailsUpdate").addEventListener("click", () =>
        showUpdate(activeTracking)
    );

    $("dmUpdateForm").addEventListener("submit", saveUpdate);
    $("dmNewStatus").addEventListener("change", syncRescheduleFields);
    $("dmRefresh").addEventListener("click", loadMonitoring);
    $("dmExport").addEventListener("click", exportCSV);

    loadMonitoring();
});
