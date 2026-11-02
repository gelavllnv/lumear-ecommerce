/* LUMEAR LOGISTICS — DELIVERY ASSIGNMENT */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    if (!document.getElementById("deliveryAssignmentApp")) return;

    const $ = id => document.getElementById(id);
    const API = "/logistics/api/delivery";
    const PAGE_SIZE = 6;

    let parcels = [];
    let riders = [];
    let page = 1;
    let selected = new Set();
    let assignmentTargets = [];
    let activeTracking = null;
    let toastTimer;

    function el(tag, className = "", text = "") {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== "") node.textContent = String(text);
        return node;
    }

    function toast(message, error = false) {
        const node = $("daToast");
        if (!node) return;
        node.textContent = message;
        node.classList.toggle("error", error);
        node.classList.add("show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => node.classList.remove("show"), 3500);
    }

    async function request(path, options = {}) {
        const method = (options.method || "GET").toUpperCase();
        const token = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (method !== "GET" && !token) {
            throw new Error("CSRF token missing from Logistics layout.");
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

    async function loadAssignment() {
        try {
            const data = await request("/assignment");
            parcels = Array.isArray(data.parcels) ? data.parcels : [];
            riders = Array.isArray(data.riders) ? data.riders : [];
            selected.clear();
            assignmentTargets = [];
            page = 1;
            render();
        } catch (error) {
            toast(error.message, true);
        }
    }

    function getParcel(tracking) {
        return parcels.find(p => p.tracking === tracking);
    }

    function getRider(id) {
        return riders.find(r => String(r.id) === String(id));
    }

    function initials(name) {
        return String(name || "")
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(w => w[0].toUpperCase())
            .join("");
    }

    function workload(id) {
        return parcels.filter(p =>
            String(p.riderId) === String(id) &&
            p.status === "Assigned"
        ).length;
    }

    function capacity(rider, additional = 1) {
        return rider.available &&
            workload(rider.id) + additional <= Number(rider.capacity || 5);
    }

    function areas() {
        return [...new Set(
            parcels.map(p => p.destination).filter(Boolean)
        )].sort();
    }

    function openModal(id) {
        const modal = $(id);
        if (!modal) return;
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        modal.querySelector("input,select,textarea,button")?.focus();
    }

    function closeModals() {
        document.querySelectorAll(".da-modal.open").forEach(modal => {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
        });
    }

    document.querySelectorAll("[data-da-close]").forEach(button =>
        button.addEventListener("click", closeModals)
    );

    document.querySelectorAll(".da-modal").forEach(modal =>
        modal.addEventListener("click", e => {
            if (e.target === modal) closeModals();
        })
    );

    document.addEventListener("keydown", e => {
        if (e.key === "Escape") closeModals();
    });

    function filteredParcels() {
        const query = $("daSearch").value.trim().toLowerCase();
        const status = $("daStatusFilter").value;
        const area = $("daAreaFilter").value;

        return parcels.filter(p => {
            const rider = getRider(p.riderId);
            const search = [
                p.tracking, p.recipient, p.seller,
                p.destination, p.batch, rider?.name || ""
            ].join(" ").toLowerCase();

            return search.includes(query) &&
                (status === "all" || p.status === status) &&
                (area === "all" || p.destination === area);
        });
    }

    function badge(status) {
        const cls = status === "Assigned"
            ? "da-badge-assigned"
            : status === "Dispatched"
                ? "da-badge-dispatched"
                : "da-badge-waiting";

        return el("span", `da-badge ${cls}`, status);
    }

    function cell(row, main, secondary = "") {
        const td = el("td");
        td.appendChild(el("div", "da-cell-primary", main));
        if (secondary) {
            td.appendChild(el("div", "da-cell-secondary", secondary));
        }
        row.appendChild(td);
    }

    function button(parent, label, callback, primary = false) {
        const btn = el(
            "button",
            primary ? "da-btn da-btn-primary" : "da-btn da-btn-outline",
            label
        );
        btn.type = "button";
        btn.addEventListener("click", callback);
        parent.appendChild(btn);
    }

    function renderStats() {
        const count = status =>
            parcels.filter(p => p.status === status).length;

        $("daPendingCount").textContent = count("Awaiting Assignment");
        $("daAssignedCount").textContent = count("Assigned");
        $("daDispatchedCount").textContent = count("Dispatched");
        $("daAvailableRiders").textContent =
            riders.filter(r => capacity(r)).length;
        $("daRiderTotal").textContent = `${riders.length} riders`;
    }

    function renderRiders() {
        const grid = $("daRiderGrid");
        grid.replaceChildren();

        riders.forEach(rider => {
            const used = workload(rider.id);
            const max = Number(rider.capacity || 5);
            const percent = Math.min(100, used / max * 100);
            const status = !rider.available
                ? "Offline"
                : used >= max ? "Full" : "Available";

            const card = el("article", "da-rider-card");
            const top = el("div", "da-rider-top");
            top.appendChild(el(
                "div", "da-rider-avatar", initials(rider.name)
            ));

            const info = el("div", "da-rider-name");
            info.appendChild(el("strong", "", rider.name));
            info.appendChild(el("small", "", rider.id));
            top.appendChild(info);
            top.appendChild(el(
                "span",
                `da-badge ${status === "Available"
                    ? "da-badge-dispatched"
                    : "da-badge-offline"}`,
                status
            ));
            card.appendChild(top);

            const meta = el("div", "da-rider-meta");
            meta.appendChild(el("span", "", "Current workload"));
            meta.appendChild(el("strong", "", `${used} / ${max} parcels`));
            card.appendChild(meta);

            const track = el("div", "da-progress-track");
            const fill = el("div", "da-progress-fill");
            fill.style.width = `${percent}%`;
            track.appendChild(fill);
            card.appendChild(track);

            const footer = el("div", "da-rider-footer");
            footer.appendChild(el("span", "", rider.area));
            footer.appendChild(el("span", "", rider.vehicle));
            card.appendChild(footer);
            grid.appendChild(card);
        });
    }

    function renderAreas() {
        const select = $("daAreaFilter");
        const previous = select.value;
        select.replaceChildren();

        const all = el("option", "", "All Areas");
        all.value = "all";
        select.appendChild(all);

        areas().forEach(area => {
            const option = el("option", "", area);
            option.value = area;
            select.appendChild(option);
        });

        select.value = areas().includes(previous) ? previous : "all";
    }

    function pageData() {
        const filtered = filteredParcels();
        const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        page = Math.min(Math.max(1, page), pages);
        const start = (page - 1) * PAGE_SIZE;

        return {
            filtered,
            visible: filtered.slice(start, start + PAGE_SIZE),
            pages,
            start
        };
    }

    function makeRow(p) {
        const row = el("tr");
        const checkCell = el("td");
        const check = el("input");
        check.type = "checkbox";
        check.checked = selected.has(p.tracking);
        check.disabled = p.status !== "Awaiting Assignment";
        check.setAttribute("aria-label", `Select ${p.tracking}`);

        check.addEventListener("change", () => {
            check.checked
                ? selected.add(p.tracking)
                : selected.delete(p.tracking);
            updateSelection();
        });

        checkCell.appendChild(check);
        row.appendChild(checkCell);

        cell(row, p.tracking, p.batch);
        cell(row, p.recipient, p.contact);
        cell(row, p.destination);
        cell(row, p.type, p.weight === "—" ? "—" : `${p.weight} kg`);

        const rider = getRider(p.riderId);
        cell(
            row,
            rider?.name || "Not assigned",
            rider?.id || "Awaiting rider"
        );

        const statusCell = el("td");
        statusCell.appendChild(badge(p.status));
        row.appendChild(statusCell);

        const actionCell = el("td");
        const actions = el("div", "da-table-actions");

        button(actions, "Details", () => showDetails(p.tracking));

        if (p.status === "Awaiting Assignment") {
            button(
                actions, "Assign",
                () => showAssign([p.tracking]), true
            );
        } else if (p.status === "Assigned") {
            button(
                actions, "Dispatch",
                () => showDispatch(p.tracking), true
            );
            button(
                actions, "Reassign",
                () => showAssign([p.tracking], true)
            );
        }

        actionCell.appendChild(actions);
        row.appendChild(actionCell);
        return row;
    }

    function updateSelection() {
        const visible = pageData().visible.filter(
            p => p.status === "Awaiting Assignment"
        );

        const checkbox = $("daSelectAll");
        checkbox.disabled = visible.length === 0;
        checkbox.checked = visible.length > 0 &&
            visible.every(p => selected.has(p.tracking));
        checkbox.indeterminate = !checkbox.checked &&
            visible.some(p => selected.has(p.tracking));

        $("daBulkBar").hidden = selected.size === 0;
        $("daSelectedCount").textContent =
            `${selected.size} parcel(s) selected`;
    }

    function renderTable() {
        const { filtered, visible, pages, start } = pageData();
        const tbody = $("daTableBody");
        tbody.replaceChildren();

        visible.forEach(p => tbody.appendChild(makeRow(p)));

        $("daEmpty").hidden = filtered.length > 0;
        $("daVisibleCount").textContent = `${filtered.length} parcels`;
        $("daShowing").textContent = filtered.length
            ? `Showing ${start + 1}–${start + visible.length} of ${filtered.length} parcels`
            : "Showing 0 parcels";
        $("daPageLabel").textContent = `Page ${page} of ${pages}`;
        $("daPrev").disabled = page === 1;
        $("daNext").disabled = page === pages;

        updateSelection();
    }

    function renderOverview() {
        const grid = $("daOverviewGrid");
        grid.replaceChildren();

        [
            ["Unassigned Queue", "Sorted parcels awaiting rider assignment", "Awaiting Assignment"],
            ["Dispatch Preparation", "Assigned parcels waiting for rider handover", "Assigned"],
            ["Completed Handover", "Parcels released for delivery", "Dispatched"]
        ].forEach(([title, description, status]) => {
            const card = el("article", "da-overview-card");
            card.appendChild(el("strong", "", title));
            card.appendChild(el("p", "", description));
            card.appendChild(el(
                "span", "da-overview-value",
                parcels.filter(p => p.status === status).length
            ));
            grid.appendChild(card);
        });
    }

    function render() {
        renderStats();
        renderRiders();
        renderAreas();
        renderTable();
        renderOverview();
    }

    function showDetails(tracking) {
        const p = getParcel(tracking);
        if (!p) return;

        activeTracking = tracking;
        $("daDetailsSubtitle").textContent = tracking;
        const grid = $("daDetailsGrid");
        grid.replaceChildren();

        const rider = getRider(p.riderId);
        [
            ["Tracking ID", p.tracking],
            ["Recipient", p.recipient],
            ["Contact", p.contact],
            ["Seller", p.seller],
            ["Destination", p.destination],
            ["Parcel Type", p.type],
            ["Weight", p.weight === "—" ? "—" : `${p.weight} kg`],
            ["Sorting Batch", p.batch],
            ["Assigned Rider", rider?.name || "Not assigned"],
            ["Status", p.status]
        ].forEach(([label, value]) => {
            const item = el("div", "da-detail-item");
            item.appendChild(el("span", "", label));
            item.appendChild(el("strong", "", value || "—"));
            grid.appendChild(item);
        });

        $("daDetailsNote").textContent =
            `Delivery Instructions: ${p.notes || "None provided."}`;

        $("daDetailsAction").hidden = p.status === "Dispatched";
        $("daDetailsAction").textContent =
            p.status === "Assigned" ? "Confirm Dispatch" : "Assign Rider";

        closeModals();
        openModal("daDetailsModal");
    }

    function showAssign(trackings, reassign = false) {
        const targets = trackings.map(getParcel).filter(p =>
            p && p.status === (
                reassign ? "Assigned" : "Awaiting Assignment"
            )
        );

        if (!targets.length) {
            toast("No eligible parcels selected.", true);
            return;
        }

        const destinations = [...new Set(targets.map(p => p.destination))];

        if (destinations.length !== 1) {
            toast("Select parcels from the same destination.", true);
            return;
        }

        assignmentTargets = targets.map(p => p.tracking);

        $("daAssignTitle").textContent = reassign
            ? "Reassign Delivery Rider"
            : "Assign Delivery Rider";
        $("daAssignSummary").textContent =
            `${targets.length} parcel(s)`;
        $("daAssignDestinations").textContent = destinations[0];
        $("daAssignmentNotes").value =
            targets.length === 1 ? targets[0].notes || "" : "";

        const select = $("daRiderSelect");
        select.replaceChildren();

        const placeholder = el("option", "", "Select a rider");
        placeholder.value = "";
        select.appendChild(placeholder);

        riders.filter(r =>
            r.area === destinations[0] && r.available
        ).forEach(r => {
            const option = el("option", "", `${r.name} — ${r.id}`);
            option.value = r.id;
            select.appendChild(option);
        });

        $("daRiderPreview").textContent =
            "Select a rider to view delivery capacity.";

        if (select.options.length === 1) {
            toast("No available rider serves this destination.", true);
            return;
        }

        closeModals();
        openModal("daAssignModal");
    }

    function updateRiderPreview() {
        const rider = getRider($("daRiderSelect").value);
        if (!rider) {
            $("daRiderPreview").textContent = "Select a rider.";
            return;
        }

        const already = assignmentTargets
            .map(getParcel)
            .filter(p =>
                p && p.riderId === rider.id && p.status === "Assigned"
            ).length;

        const used = workload(rider.id);
        const max = Number(rider.capacity || 5);

        $("daRiderPreview").textContent =
            `${rider.name} · ${rider.area} · ${used}/${max} parcels · ` +
            `${Math.max(0, max - used)} available slots · ` +
            `${assignmentTargets.length - already} additional required.`;
    }

    async function saveAssignment(event) {
        event.preventDefault();

        const rider = getRider($("daRiderSelect").value);
        const targets = assignmentTargets.map(getParcel);

        if (!rider || !targets.length || !rider.available ||
            targets.some(p =>
                !p ||
                p.destination !== rider.area ||
                !["Awaiting Assignment", "Assigned"].includes(p.status)
            )) {
            toast("Invalid rider or parcel selection.", true);
            return;
        }

        const already = targets.filter(p =>
            p.riderId === rider.id && p.status === "Assigned"
        ).length;

        if (!capacity(rider, targets.length - already)) {
            toast("Rider capacity exceeded.", true);
            return;
        }

        const submit = $("daAssignForm").querySelector('[type="submit"]');
        if (submit) submit.disabled = true;

        try {
            const riderId = Number(
                String(rider.id).replace(/^RDR-/, "")
            );

            await request("/assign", {
                method: "POST",
                body: JSON.stringify({
                    rider_id: riderId,
                    shipment_ids: targets.map(p => p.id),
                    notes: $("daAssignmentNotes").value.trim()
                })
            });

            closeModals();
            toast("Assignment saved successfully.");
            await loadAssignment();
        } catch (error) {
            toast(error.message, true);
        } finally {
            if (submit) submit.disabled = false;
        }
    }

    function showDispatch(tracking) {
        const p = getParcel(tracking);

        if (!p || p.status !== "Assigned" || !p.riderId) {
            toast("Only assigned parcels can be dispatched.", true);
            return;
        }

        activeTracking = tracking;
        $("daDispatchTracking").textContent = tracking;
        $("daDispatchRider").textContent =
            `Assigned Rider: ${getRider(p.riderId)?.name || "Unknown"}`;

        closeModals();
        openModal("daDispatchModal");
    }

    async function confirmDispatch() {
        const p = getParcel(activeTracking);

        if (!p || p.status !== "Assigned" || !p.riderId) {
            toast("Parcel is not assigned.", true);
            return;
        }

        const btn = $("daConfirmDispatch");
        btn.disabled = true;

        try {
            await request(`/dispatch/${p.id}`, { method: "POST" });
            closeModals();
            toast("Parcel dispatched successfully.");
            await loadAssignment();
        } catch (error) {
            toast(error.message, true);
        } finally {
            btn.disabled = false;
        }
    }

    function exportCSV() {
        const data = filteredParcels();

        if (!data.length) {
            toast("No assignments available to export.", true);
            return;
        }

        const rows = [[
            "Tracking ID", "Recipient", "Contact", "Seller",
            "Destination", "Type", "Weight", "Batch",
            "Rider ID", "Rider Name", "Status", "Instructions"
        ]];

        data.forEach(p => {
            const rider = getRider(p.riderId);
            rows.push([
                p.tracking, p.recipient, p.contact, p.seller,
                p.destination, p.type, p.weight, p.batch,
                p.riderId || "", rider?.name || "", p.status, p.notes
            ]);
        });

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
        link.download = "lumear-delivery-assignments.csv";
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);

        toast("Delivery assignments exported.");
    }

    $("daSearch").addEventListener("input", () => {
        page = 1;
        renderTable();
    });

    $("daStatusFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("daAreaFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("daPrev").addEventListener("click", () => {
        page--;
        renderTable();
    });

    $("daNext").addEventListener("click", () => {
        page++;
        renderTable();
    });

    $("daSelectAll").addEventListener("change", event => {
        pageData().visible
            .filter(p => p.status === "Awaiting Assignment")
            .forEach(p => {
                event.target.checked
                    ? selected.add(p.tracking)
                    : selected.delete(p.tracking);
            });
        renderTable();
    });

    $("daClearSelected").addEventListener("click", () => {
        selected.clear();
        renderTable();
    });

    $("daAssignSelected").addEventListener("click", () =>
        showAssign([...selected])
    );

    $("daBulkAssign").addEventListener("click", () =>
        showAssign([...selected])
    );

    $("daRiderSelect").addEventListener("change", updateRiderPreview);
    $("daAssignForm").addEventListener("submit", saveAssignment);

    $("daDetailsAction").addEventListener("click", () => {
        const p = getParcel(activeTracking);
        if (!p) return;
        p.status === "Assigned"
            ? showDispatch(p.tracking)
            : showAssign([p.tracking]);
    });

    $("daConfirmDispatch").addEventListener("click", confirmDispatch);
    $("daExport").addEventListener("click", exportCSV);
    $("daRefresh").addEventListener("click", loadAssignment);

    loadAssignment();
});
