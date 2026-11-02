/* ==========================================================
   LUMEAR LOGISTICS — PARCEL SORTING
   MySQL-connected via Laravel API
   Original UI, modals, filters and tables preserved
   ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    if (!document.getElementById("sortingApp")) return;

    const $ = id => document.getElementById(id);
    const API = "/logistics/api/sorting";
    const PAGE_SIZE = 6;

    const today = new Date();
    const todayString = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, "0"),
        String(today.getDate()).padStart(2, "0")
    ].join("-");

    let parcels = [];
    let batches = [];
    let page = 1;
    let selected = new Set();
    let assignmentTargets = [];
    let activeTracking = null;
    let toastTimer = null;
    let loading = false;

    /* ==========================================================
       API CONNECTION
       ========================================================== */

    async function apiRequest(path = "", options = {}) {
        const method = (options.method || "GET").toUpperCase();

        const headers = {
            Accept: "application/json",
            ...options.headers
        };

        if (method !== "GET") {
            const token = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

            if (!token) {
                throw new Error(
                    "Missing CSRF token in Logistics layout."
                );
            }

            headers["X-CSRF-TOKEN"] = token;
            headers["Content-Type"] = "application/json";
        }

        const response = await fetch(API + path, {
            ...options,
            method,
            credentials: "same-origin",
            headers
        });

        let data;

        try {
            data = await response.json();
        } catch {
            throw new Error(
                `Invalid server response (${response.status}).`
            );
        }

        if (!response.ok) {
            const errors = data.errors
                ? Object.values(data.errors).flat().join(" ")
                : null;

            throw new Error(
                errors ||
                data.message ||
                `Request failed (${response.status}).`
            );
        }

        return data;
    }

    async function loadSorting() {
        if (loading) return;

        loading = true;

        const refresh = $("sortRefresh");
        if (refresh) refresh.disabled = true;

        try {
            const data = await apiRequest();

            parcels = Array.isArray(data.parcels)
                ? data.parcels.map(normalizeParcel)
                : [];

            batches = Array.isArray(data.batches)
                ? data.batches.map(normalizeBatch)
                : [];

            selected.clear();
            assignmentTargets = [];
            page = 1;

            render();

        } catch (error) {
            toast(error.message, true);

        } finally {
            loading = false;

            if (refresh) refresh.disabled = false;
        }
    }

    function normalizeParcel(p) {
        return {
            id: Number(p.id),
            tracking: String(p.tracking ?? ""),
            recipient: String(p.recipient ?? "Recipient"),
            seller: String(p.seller ?? "Seller"),
            destination: String(p.destination ?? ""),
            type: String(p.type ?? "Standard"),
            weight: p.weight ?? "—",
            status: String(p.status ?? "Unsorted"),
            batchId: p.batchId ?? null
        };
    }

    function normalizeBatch(b) {
        return {
            id: String(b.id ?? ""),
            name: String(b.name ?? ""),
            destination: String(b.destination ?? ""),
            notes: String(b.notes ?? ""),
            created: String(b.created ?? "")
        };
    }

    function numericBatchId(id) {
        const match = /^BATCH-(\d+)$/.exec(String(id));

        if (!match) {
            throw new Error("Invalid sorting batch ID.");
        }

        return Number(match[1]);
    }

    /* ==========================================================
       UI HELPERS
       ========================================================== */

    function element(tag, className = "", text = "") {
        const node = document.createElement(tag);

        if (className) node.className = className;
        if (text !== "") node.textContent = String(text);

        return node;
    }

    function toast(message, error = false) {
        const node = $("sortToast");
        if (!node) return;

        node.textContent = message;
        node.classList.toggle("error", error);
        node.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            node.classList.remove("show");
        }, 3500);
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

    function closeAllModals() {
        document.querySelectorAll(".sort-modal.open").forEach(modal => {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
        });
    }

    document.querySelectorAll("[data-sort-close]").forEach(button => {
        button.addEventListener("click", closeAllModals);
    });

    document.querySelectorAll(".sort-modal").forEach(modal => {
        modal.addEventListener("click", event => {
            if (event.target === modal) closeAllModals();
        });
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") closeAllModals();
    });

    function getParcel(tracking) {
        return parcels.find(p => p.tracking === tracking);
    }

    function getBatch(id) {
        return batches.find(b => b.id === id);
    }

    function uniqueAreas() {
        return [...new Set(
            parcels.map(p => p.destination).filter(Boolean)
        )].sort();
    }

    function weightLabel(weight) {
        return weight === "—" || weight == null
            ? "—"
            : `${weight} kg`;
    }

    /* ==========================================================
       FILTERING
       ========================================================== */

    function filteredParcels() {
        const query = $("sortSearch").value.trim().toLowerCase();
        const status = $("sortStatusFilter").value;
        const area = $("sortAreaFilter").value;

        return parcels.filter(parcel => {
            const batch = getBatch(parcel.batchId);

            const searchable = [
                parcel.tracking,
                parcel.recipient,
                parcel.seller,
                parcel.destination,
                parcel.type,
                parcel.batchId || "",
                batch ? batch.name : ""
            ].join(" ").toLowerCase();

            return searchable.includes(query)
                && (status === "all" || parcel.status === status)
                && (area === "all" || parcel.destination === area);
        });
    }

    /* ==========================================================
       TABLE COMPONENTS
       ========================================================== */

    function badge(status) {
        const className = status === "Sorted"
            ? "sort-badge-sorted"
            : status === "In Progress"
                ? "sort-badge-progress"
                : "sort-badge-unsorted";

        return element(
            "span",
            `sort-badge ${className}`,
            status
        );
    }

    function cell(row, mainText, secondary = "") {
        const td = element("td");

        td.appendChild(
            element("div", "sort-cell-primary", mainText)
        );

        if (secondary) {
            td.appendChild(
                element("div", "sort-cell-secondary", secondary)
            );
        }

        row.appendChild(td);
        return td;
    }

    function actionButton(parent, label, callback, primary = false) {
        const button = element(
            "button",
            primary
                ? "sort-btn sort-btn-primary"
                : "sort-btn",
            label
        );

        button.type = "button";
        button.addEventListener("click", callback);

        parent.appendChild(button);
    }

    function makeRow(parcel) {
        const tr = element("tr");

        const checkCell = element("td");
        const checkbox = element("input");

        checkbox.type = "checkbox";
        checkbox.setAttribute(
            "aria-label",
            `Select ${parcel.tracking}`
        );

        checkbox.checked = selected.has(parcel.tracking);
        checkbox.disabled = parcel.status !== "Unsorted";

        checkbox.addEventListener("change", () => {
            if (checkbox.checked) {
                selected.add(parcel.tracking);
            } else {
                selected.delete(parcel.tracking);
            }

            updateBulkBar();
            updateSelectAll();
        });

        checkCell.appendChild(checkbox);
        tr.appendChild(checkCell);

        cell(tr, parcel.tracking, "Verified Incoming Parcel");
        cell(tr, parcel.recipient, parcel.seller);
        cell(tr, parcel.destination);
        cell(tr, parcel.type, weightLabel(parcel.weight));

        const batch = getBatch(parcel.batchId);

        cell(
            tr,
            batch ? batch.id : "Not assigned",
            batch ? batch.name : "Waiting for batch"
        );

        const statusCell = element("td");
        statusCell.appendChild(badge(parcel.status));
        tr.appendChild(statusCell);

        const actionCell = element("td");
        const actions = element("div", "sort-table-actions");

        actionButton(actions, "Details", () => {
            showDetails(parcel.tracking);
        });

        if (parcel.status === "Unsorted") {
            actionButton(
                actions,
                "Assign",
                () => showAssign([parcel.tracking]),
                true
            );
        } else if (parcel.status === "In Progress") {
            actionButton(
                actions,
                "Mark Sorted",
                () => showConfirm(parcel.tracking),
                true
            );
        }

        actionCell.appendChild(actions);
        tr.appendChild(actionCell);

        return tr;
    }

    /* ==========================================================
       DASHBOARD STATISTICS
       ========================================================== */

    function renderStatistics() {
        const count = status =>
            parcels.filter(p => p.status === status).length;

        $("sortUnsortedCount").textContent = count("Unsorted");
        $("sortProgressCount").textContent = count("In Progress");
        $("sortSortedCount").textContent = count("Sorted");

        const active = batches.filter(batch =>
            parcels.some(p =>
                p.batchId === batch.id &&
                p.status === "In Progress"
            )
        ).length;

        $("sortBatchCount").textContent = active;
    }

    /* ==========================================================
       AREA FILTER
       ========================================================== */

    function renderAreaOptions() {
        const select = $("sortAreaFilter");
        const previous = select.value;

        select.replaceChildren();

        const all = element("option", "", "All Areas");
        all.value = "all";
        select.appendChild(all);

        const areas = uniqueAreas();

        areas.forEach(area => {
            const option = element("option", "", area);
            option.value = area;
            select.appendChild(option);
        });

        select.value = areas.includes(previous)
            ? previous
            : "all";
    }

    /* ==========================================================
       DESTINATION CARDS
       ========================================================== */

    function renderDestinations() {
        const grid = $("sortDestinationGrid");
        grid.replaceChildren();

        uniqueAreas().forEach(area => {
            const areaParcels = parcels.filter(
                p => p.destination === area
            );

            const sorted = areaParcels.filter(
                p => p.status === "Sorted"
            ).length;

            const percent = areaParcels.length
                ? Math.round(sorted / areaParcels.length * 100)
                : 0;

            const card = element(
                "article",
                "sort-destination-card"
            );

            card.appendChild(element("strong", "", area));

            card.appendChild(
                element(
                    "div",
                    "sort-destination-number",
                    areaParcels.length
                )
            );

            card.appendChild(
                element("small", "", "Total parcels")
            );

            const track = element(
                "div",
                "sort-progress-track"
            );

            const fill = element(
                "div",
                "sort-progress-fill"
            );

            fill.style.width = `${percent}%`;

            track.appendChild(fill);
            card.appendChild(track);

            card.appendChild(
                element(
                    "small",
                    "",
                    `${sorted} sorted · ${percent}% completed`
                )
            );

            grid.appendChild(card);
        });
    }

    /* ==========================================================
       SORTING BATCH CARDS
       ========================================================== */

    function renderBatches() {
        const grid = $("sortBatchesGrid");
        grid.replaceChildren();

        $("sortTotalBatches").textContent =
            `${batches.length} batches`;

        if (!batches.length) {
            grid.appendChild(
                element(
                    "p",
                    "",
                    "No sorting batches created yet."
                )
            );
            return;
        }

        batches.forEach(batch => {
            const assigned = parcels.filter(
                p => p.batchId === batch.id
            );

            const completed = assigned.filter(
                p => p.status === "Sorted"
            ).length;

            const percent = assigned.length
                ? Math.round(
                    completed / assigned.length * 100
                )
                : 0;

            const card = element(
                "article",
                "sort-batch-card"
            );

            const top = element(
                "div",
                "sort-batch-top"
            );

            top.appendChild(
                element("strong", "", batch.name)
            );

            const status = assigned.length > 0 &&
                completed === assigned.length
                ? "Sorted"
                : assigned.length > 0
                    ? "In Progress"
                    : "Unsorted";

            top.appendChild(badge(status));
            card.appendChild(top);

            card.appendChild(
                element(
                    "p",
                    "",
                    `${batch.id} · ${batch.destination}`
                )
            );

            const meta = element(
                "div",
                "sort-batch-meta"
            );

            meta.appendChild(
                element(
                    "span",
                    "",
                    `${assigned.length} parcels`
                )
            );

            meta.appendChild(
                element(
                    "span",
                    "",
                    `${percent}% completed`
                )
            );

            card.appendChild(meta);

            const track = element(
                "div",
                "sort-progress-track"
            );

            const fill = element(
                "div",
                "sort-progress-fill"
            );

            fill.style.width = `${percent}%`;

            track.appendChild(fill);
            card.appendChild(track);

            actionButton(
                card,
                "View Batch Details",
                () => showBatchDetails(batch.id)
            );

            grid.appendChild(card);
        });
    }

    /* ==========================================================
       PAGINATION
       ========================================================== */

    function visiblePageParcels() {
        const filtered = filteredParcels();

        const pageCount = Math.max(
            1,
            Math.ceil(filtered.length / PAGE_SIZE)
        );

        page = Math.min(
            Math.max(page, 1),
            pageCount
        );

        const start = (page - 1) * PAGE_SIZE;

        return {
            filtered,
            visible: filtered.slice(
                start,
                start + PAGE_SIZE
            ),
            start,
            pageCount
        };
    }

    function updateSelectAll() {
        const visible = visiblePageParcels().visible.filter(
            p => p.status === "Unsorted"
        );

        const checkbox = $("sortSelectAll");

        checkbox.disabled = visible.length === 0;

        checkbox.checked = visible.length > 0 &&
            visible.every(p => selected.has(p.tracking));

        checkbox.indeterminate = visible.some(
            p => selected.has(p.tracking)
        ) && !checkbox.checked;
    }

    function updateBulkBar() {
        const bar = $("sortBulkBar");

        bar.hidden = selected.size === 0;

        $("sortSelectedCount").textContent =
            `${selected.size} ${
                selected.size === 1 ? "parcel" : "parcels"
            } selected`;
    }

    function renderTable() {
        const {
            filtered,
            visible,
            start,
            pageCount
        } = visiblePageParcels();

        const tbody = $("sortTableBody");
        tbody.replaceChildren();

        visible.forEach(parcel => {
            tbody.appendChild(makeRow(parcel));
        });

        $("sortEmpty").hidden = filtered.length > 0;

        $("sortVisibleCount").textContent =
            `${filtered.length} parcels`;

        $("sortShowing").textContent = filtered.length
            ? `Showing ${start + 1}–${start + visible.length} of ${filtered.length} parcels`
            : "Showing 0 parcels";

        $("sortPageLabel").textContent =
            `Page ${page} of ${pageCount}`;

        $("sortPrev").disabled = page === 1;
        $("sortNext").disabled = page === pageCount;

        updateSelectAll();
        updateBulkBar();
    }

    function render() {
        renderStatistics();
        renderAreaOptions();
        renderDestinations();
        renderTable();
        renderBatches();
    }

    /* ==========================================================
       PARCEL DETAILS MODAL
       ========================================================== */

    function addDetail(grid, label, value) {
        const item = element(
            "div",
            "sort-detail-item"
        );

        item.appendChild(
            element("span", "", label)
        );

        item.appendChild(
            element("strong", "", value || "—")
        );

        grid.appendChild(item);
    }

    function showDetails(tracking) {
        const parcel = getParcel(tracking);
        if (!parcel) return;

        activeTracking = tracking;

        $("sortDetailsSubtitle").textContent =
            `Tracking ID: ${tracking}`;

        const grid = $("sortDetailsGrid");
        grid.replaceChildren();

        const batch = getBatch(parcel.batchId);

        [
            ["Tracking ID", parcel.tracking],
            ["Recipient", parcel.recipient],
            ["Seller", parcel.seller],
            ["Destination", parcel.destination],
            ["Parcel Type", parcel.type],
            ["Weight", weightLabel(parcel.weight)],
            [
                "Sorting Batch",
                batch ? batch.name : "Not assigned"
            ],
            ["Sorting Status", parcel.status]
        ].forEach(([label, value]) => {
            addDetail(grid, label, value);
        });

        $("sortDetailsNote").textContent =
            parcel.status === "Unsorted"
                ? "This parcel is ready to be assigned to a sorting batch."
                : parcel.status === "In Progress"
                    ? "This parcel is assigned to a batch and awaiting sorting confirmation."
                    : "Sorting completed. Parcel is ready for delivery assignment.";

        const action = $("sortDetailsAction");

        action.hidden = parcel.status === "Sorted";

        action.textContent = parcel.status === "Unsorted"
            ? "Assign to Batch"
            : "Mark as Sorted";

        closeAllModals();
        openModal("sortDetailsModal");
    }

    /* ==========================================================
       ASSIGN PARCELS TO BATCH
       ========================================================== */

    function showAssign(trackings) {
        const valid = trackings
            .map(getParcel)
            .filter(p => p && p.status === "Unsorted");

        if (!valid.length) {
            toast(
                "Select at least one unsorted parcel.",
                true
            );
            return;
        }

        const destinations = [...new Set(
            valid.map(p => p.destination)
        )];

        if (destinations.length !== 1) {
            toast(
                "Select parcels from the same destination.",
                true
            );
            return;
        }

        assignmentTargets = valid.map(p => p.tracking);

        $("sortAssignSelected").textContent =
            `${valid.length} parcel(s) · ${destinations[0]}`;

        const select = $("sortAssignBatch");
        select.replaceChildren();

        const placeholder = element(
            "option",
            "",
            "Select batch"
        );

        placeholder.value = "";
        select.appendChild(placeholder);

        batches
            .filter(
                batch => batch.destination === destinations[0]
            )
            .forEach(batch => {
                const option = element(
                    "option",
                    "",
                    `${batch.id} — ${batch.name}`
                );

                option.value = batch.id;
                select.appendChild(option);
            });

        if (select.options.length === 1) {
            toast(
                "No batch exists for this destination. Create a batch first.",
                true
            );
            return;
        }

        closeAllModals();
        openModal("sortAssignModal");
    }

    async function assignParcels(event) {
        event.preventDefault();

        const batch = getBatch(
            $("sortAssignBatch").value
        );

        if (!batch) {
            toast("Please select a sorting batch.", true);
            return;
        }

        const targets = assignmentTargets.map(getParcel);

        if (
            !targets.length ||
            targets.some(p =>
                !p ||
                p.status !== "Unsorted" ||
                p.destination !== batch.destination
            )
        ) {
            toast(
                "Parcel destination or status does not match.",
                true
            );
            return;
        }

        const button = $("sortAssignForm").querySelector(
            '[type="submit"]'
        );

        if (button) button.disabled = true;

        try {
            await apiRequest("/assign", {
                method: "POST",
                body: JSON.stringify({
                    batch_id: numericBatchId(batch.id),
                    shipment_ids: targets.map(p => p.id)
                })
            });

            assignmentTargets = [];

            closeAllModals();

            toast(
                `${targets.length} parcel(s) assigned to ${batch.id}.`
            );

            await loadSorting();

        } catch (error) {
            toast(error.message, true);

        } finally {
            if (button) button.disabled = false;
        }
    }

    /* ==========================================================
       MARK PARCEL AS SORTED
       ========================================================== */

    function showConfirm(tracking) {
        const parcel = getParcel(tracking);

        if (!parcel || parcel.status !== "In Progress") {
            toast(
                "Only parcels in progress can be marked sorted.",
                true
            );
            return;
        }

        activeTracking = tracking;

        $("sortConfirmTracking").textContent = tracking;

        closeAllModals();
        openModal("sortConfirmModal");
    }

    async function confirmSorting() {
        const parcel = getParcel(activeTracking);

        if (
            !parcel ||
            parcel.status !== "In Progress" ||
            !parcel.batchId
        ) {
            toast(
                "Unable to complete sorting for this parcel.",
                true
            );
            return;
        }

        const button = $("sortConfirmButton");
        button.disabled = true;

        try {
            await apiRequest(
                `/${parcel.id}/complete`,
                {
                    method: "POST"
                }
            );

            closeAllModals();

            toast(
                `${parcel.tracking} marked as sorted.`
            );

            await loadSorting();

        } catch (error) {
            toast(error.message, true);

        } finally {
            button.disabled = false;
        }
    }

    /* ==========================================================
       BATCH DETAILS MODAL
       ========================================================== */

    function showBatchDetails(id) {
        const batch = getBatch(id);
        if (!batch) return;

        const assigned = parcels.filter(
            p => p.batchId === id
        );

        const sorted = assigned.filter(
            p => p.status === "Sorted"
        ).length;

        $("sortBatchDetailsTitle").textContent =
            batch.name;

        $("sortBatchDetailsSubtitle").textContent =
            batch.id;

        const grid = $("sortBatchDetailsGrid");
        grid.replaceChildren();

        [
            ["Batch ID", batch.id],
            ["Batch Name", batch.name],
            ["Destination", batch.destination],
            ["Created", batch.created],
            ["Total Parcels", String(assigned.length)],
            ["Sorted Parcels", String(sorted)],
            [
                "In Progress",
                String(assigned.length - sorted)
            ],
            [
                "Completion",
                assigned.length
                    ? `${Math.round(
                        sorted / assigned.length * 100
                    )}%`
                    : "0%"
            ]
        ].forEach(([label, value]) => {
            addDetail(grid, label, value);
        });

        const parcelList = assigned.length
            ? assigned
                .map(p => `${p.tracking} (${p.status})`)
                .join("\n")
            : "No parcels assigned yet.";

        $("sortBatchDetailsNote").textContent =
            `Instructions: ${batch.notes || "None"}\n\nParcels:\n${parcelList}`;

        closeAllModals();
        openModal("sortBatchDetailsModal");
    }

    /* ==========================================================
       CREATE SORTING BATCH
       ========================================================== */

    async function createBatch(event) {
        event.preventDefault();

        const form = $("sortCreateBatchForm");

        if (!form.reportValidity()) return;

        const name = $("sortBatchName").value.trim();
        const destination = $("sortBatchArea").value;
        const notes = $("sortBatchNotes").value.trim();

        if (
            !name ||
            !uniqueAreas().includes(destination)
        ) {
            toast(
                "Please provide a valid batch name and destination.",
                true
            );
            return;
        }

        const button = form.querySelector(
            '[type="submit"]'
        );

        if (button) button.disabled = true;

        try {
            const result = await apiRequest(
                "/batches",
                {
                    method: "POST",
                    body: JSON.stringify({
                        name,
                        destination,
                        notes
                    })
                }
            );

            form.reset();
            closeAllModals();

            toast(
                result.message ||
                "Sorting batch created successfully."
            );

            await loadSorting();

        } catch (error) {
            toast(error.message, true);

        } finally {
            if (button) button.disabled = false;
        }
    }

    /* ==========================================================
       EXPORT SORTING MANIFEST
       ========================================================== */

    function exportManifest() {
        const data = filteredParcels();

        if (!data.length) {
            toast(
                "No parcels available for export.",
                true
            );
            return;
        }

        const headers = [
            "Tracking ID",
            "Recipient",
            "Seller",
            "Destination",
            "Parcel Type",
            "Weight (kg)",
            "Batch ID",
            "Status"
        ];

        const escapeCSV = value => {
            let text = String(value ?? "");

            // Prevent spreadsheet formula execution.
            if (/^\s*[=+\-@\t\r]/.test(text)) {
                text = "'" + text;
            }

            return `"${text.replace(/"/g, '""')}"`;
        };

        const rows = data.map(p => [
            p.tracking,
            p.recipient,
            p.seller,
            p.destination,
            p.type,
            p.weight,
            p.batchId || "",
            p.status
        ]);

        const csv = "\uFEFF" + [
            headers.map(escapeCSV).join(","),
            ...rows.map(
                row => row.map(escapeCSV).join(",")
            )
        ].join("\r\n");

        const blob = new Blob(
            [csv],
            { type: "text/csv;charset=utf-8;" }
        );

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download =
            `lumear-sorting-manifest-${todayString}.csv`;

        document.body.appendChild(link);
        link.click();
        link.remove();

        URL.revokeObjectURL(url);

        toast("Sorting manifest exported.");
    }

    /* ==========================================================
       EVENT LISTENERS
       ========================================================== */

    $("sortSearch").addEventListener("input", () => {
        page = 1;
        renderTable();
    });

    $("sortStatusFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("sortAreaFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("sortPrev").addEventListener("click", () => {
        page--;
        renderTable();
    });

    $("sortNext").addEventListener("click", () => {
        page++;
        renderTable();
    });

    $("sortSelectAll").addEventListener("change", event => {
        visiblePageParcels().visible
            .filter(p => p.status === "Unsorted")
            .forEach(p => {
                if (event.target.checked) {
                    selected.add(p.tracking);
                } else {
                    selected.delete(p.tracking);
                }
            });

        renderTable();
    });

    $("sortClearSelection").addEventListener("click", () => {
        selected.clear();
        renderTable();
    });

    $("sortBulkAssign").addEventListener("click", () => {
        showAssign([...selected]);
    });

    $("sortNewBatch").addEventListener("click", () => {
        const select = $("sortBatchArea");
        select.replaceChildren();

        const placeholder = element(
            "option",
            "",
            "Select destination"
        );

        placeholder.value = "";
        select.appendChild(placeholder);

        uniqueAreas().forEach(area => {
            const option = element("option", "", area);
            option.value = area;
            select.appendChild(option);
        });

        $("sortCreateBatchForm").reset();
        openModal("sortCreateBatchModal");
    });

    $("sortCreateBatchForm").addEventListener(
        "submit",
        createBatch
    );

    $("sortAssignForm").addEventListener(
        "submit",
        assignParcels
    );

    $("sortDetailsAction").addEventListener("click", () => {
        const parcel = getParcel(activeTracking);
        if (!parcel) return;

        if (parcel.status === "Unsorted") {
            showAssign([parcel.tracking]);
        } else if (parcel.status === "In Progress") {
            showConfirm(parcel.tracking);
        }
    });

    $("sortConfirmButton").addEventListener(
        "click",
        confirmSorting
    );

    $("sortExport").addEventListener(
        "click",
        exportManifest
    );

    $("sortRefresh").addEventListener("click", async () => {
        await loadSorting();
    });

    /* ==========================================================
       INITIAL DATABASE LOAD
       ========================================================== */

    loadSorting();
});
