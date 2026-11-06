document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const app = document.getElementById("riderManagementApp");
    if (!app) return;

    const $ = id => document.getElementById(id);
    const isApplications = app.dataset.page === "applications";
    const API = "/logistics/api/rider-management";
    const PAGE_SIZE = 6;

    let data = { applications: [], riders: [] };
    let page = 1;
    let activeId = null;
    let decision = null;
    let toastTimer = null;
    let loading = false;

    function create(tag, className = "", text = "") {
        const node = document.createElement(tag);

        if (className) node.className = className;
        if (text !== "") node.textContent = String(text);

        return node;
    }

    function fullName(applicant) {
        return [
            applicant.firstName,
            applicant.lastName
        ].filter(Boolean).join(" ");
    }

    function getApplication(id) {
        return data.applications.find(item => item.id === id);
    }

    function getRider(id) {
        return data.riders.find(item => item.id === id);
    }

    function numericId(id, prefix) {
        const value = String(id);

        if (!value.startsWith(prefix)) {
            throw new Error("Invalid record ID.");
        }

        const number = Number(value.slice(prefix.length));

        if (!Number.isSafeInteger(number) || number < 1) {
            throw new Error("Invalid record ID.");
        }

        return number;
    }

    function toast(message, error = false) {
        const node = $("rmToast");
        if (!node) return;

        node.textContent = message;
        node.classList.toggle("error", error);
        node.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            node.classList.remove("show");
        }, 3500);
    }

    async function request(path = "", options = {}) {
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
                    "CSRF token missing from Logistics layout."
                );
            }

            headers["Content-Type"] = "application/json";
            headers["X-CSRF-TOKEN"] = token;
        }

        const response = await fetch(API + path, {
            ...options,
            method,
            credentials: "same-origin",
            headers
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = result.errors
                ? Object.values(result.errors).flat().join(" ")
                : null;

            throw new Error(
                errors ||
                result.message ||
                `Request failed (${response.status}).`
            );
        }

        return result;
    }

    async function loadRiders() {
        if (loading) return;

        loading = true;

        try {
            const result = await request();

            data.applications = Array.isArray(result.applications)
                ? result.applications
                : [];

            data.riders = Array.isArray(result.riders)
                ? result.riders
                : [];

            page = 1;
            render();
        } catch (error) {
            toast(error.message, true);
        } finally {
            loading = false;
        }
    }

    function openModal(id) {
        const modal = $(id);
        if (!modal) return;

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");

        modal.querySelector(
            "button, input, select, textarea"
        )?.focus();
    }

    function closeModals() {
        document.querySelectorAll(".rm-modal.open").forEach(modal => {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
        });
    }

    document.querySelectorAll("[data-rm-close]").forEach(button => {
        button.addEventListener("click", closeModals);
    });

    document.querySelectorAll(".rm-modal").forEach(modal => {
        modal.addEventListener("click", event => {
            if (event.target === modal) {
                closeModals();
            }
        });
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            closeModals();
        }
    });

    function currentItems() {
        return isApplications
            ? data.applications
            : data.riders;
    }

    function filteredItems() {
        const query = $("rmSearch").value.trim().toLowerCase();
        const filter = $("rmFilter").value;

        return currentItems().filter(item => {
            const searchable = [
                item.id,
                isApplications ? fullName(item) : item.name,
                item.email,
                item.municipality,
                item.phone,
                item.jobType
            ];

            return searchable.join(" ").toLowerCase().includes(query)
                && (filter === "All" || item.status === filter);
        });
    }

    function badge(status) {
        return create(
            "span",
            `rm-badge rm-badge-${String(status).toLowerCase()}`,
            status
        );
    }

    function cell(row, main, sub = "") {
        const td = create("td");

        td.appendChild(
            create("div", "rm-cell-main", main)
        );

        if (sub) {
            td.appendChild(
                create("div", "rm-cell-sub", sub)
            );
        }

        row.appendChild(td);
    }

    function button(parent, label, callback, variant = "outline") {
        const node = create(
            "button",
            `rm-button rm-button-${variant}`,
            label
        );

        node.type = "button";
        node.addEventListener("click", callback);

        parent.appendChild(node);
    }

    function renderStats() {
        if (isApplications) {
            $("rmTotal").textContent = data.applications.length;

            $("rmPending").textContent = data.applications.filter(
                item => item.status === "Pending"
            ).length;

            $("rmApproved").textContent = data.applications.filter(
                item => item.status === "Approved"
            ).length;

            $("rmRejected").textContent = data.applications.filter(
                item => item.status === "Rejected"
            ).length;
        } else {
            $("rmTotal").textContent = data.riders.length;

            $("rmAvailable").textContent = data.riders.filter(
                item => item.status === "Available"
            ).length;

            $("rmOffline").textContent = data.riders.filter(
                item => item.status === "Offline"
            ).length;

            $("rmSuspended").textContent = data.riders.filter(
                item => item.status === "Suspended"
            ).length;
        }
    }

    function makeApplicationRow(item) {
        const row = create("tr");

        cell(row, item.id);
        cell(row, fullName(item), item.email);
        cell(row, item.municipality);

        cell(
            row,
            item.vehicle,
            [
                item.plate,
                item.jobType || "Job type not selected"
            ].filter(Boolean).join(" · ")
        );

        cell(row, item.submitted);

        const statusCell = create("td");
        statusCell.appendChild(badge(item.status));
        row.appendChild(statusCell);

        const actionCell = create("td");
        const actions = create("div", "rm-actions");

        button(actions, "Review", () => showApplication(item.id));

        if (item.status === "Pending") {
            button(
                actions,
                "Approve",
                () => showDecision(item.id, "Approved"),
                "primary"
            );

            button(
                actions,
                "Reject",
                () => showDecision(item.id, "Rejected"),
                "danger"
            );
        }

        actionCell.appendChild(actions);
        row.appendChild(actionCell);

        return row;
    }

    function makeRiderRow(item) {
        const row = create("tr");

        cell(row, item.id);
        cell(row, item.name, item.email);
        cell(row, item.municipality);

        cell(
            row,
            item.vehicle,
            [
                item.plate,
                item.jobType || "Job type not selected"
            ].filter(Boolean).join(" · ")
        );

        cell(row, item.phone);

        const statusCell = create("td");
        statusCell.appendChild(badge(item.status));
        row.appendChild(statusCell);

        const actionCell = create("td");
        const actions = create("div", "rm-actions");

        button(actions, "Details", () => showRider(item.id));

        button(
            actions,
            "Manage",
            () => showStatus(item.id),
            "primary"
        );

        actionCell.appendChild(actions);
        row.appendChild(actionCell);

        return row;
    }

    function renderTable() {
        const filtered = filteredItems();

        const pages = Math.max(
            1,
            Math.ceil(filtered.length / PAGE_SIZE)
        );

        page = Math.max(1, Math.min(page, pages));

        const start = (page - 1) * PAGE_SIZE;
        const visible = filtered.slice(start, start + PAGE_SIZE);

        const tbody = $("rmTableBody");
        tbody.replaceChildren();

        visible.forEach(item => {
            tbody.appendChild(
                isApplications
                    ? makeApplicationRow(item)
                    : makeRiderRow(item)
            );
        });

        $("rmEmpty").hidden = filtered.length > 0;

        $("rmCount").textContent =
            `${filtered.length} ${
                isApplications ? "applications" : "riders"
            }`;

        $("rmShowing").textContent = filtered.length
            ? `Showing ${start + 1}–${start + visible.length} of ${filtered.length}`
            : "Showing 0 records";

        $("rmPageLabel").textContent = `Page ${page} of ${pages}`;

        $("rmPrev").disabled = page === 1;
        $("rmNext").disabled = page === pages;
    }

    function render() {
        renderStats();
        renderTable();
    }

    function addDetail(grid, label, value) {
        const item = create("div", "rm-detail-item");

        item.appendChild(create("span", "", label));
        item.appendChild(create("strong", "", value || "—"));

        grid.appendChild(item);
    }

    function showApplication(id) {
        const applicant = getApplication(id);
        if (!applicant) return;

        activeId = id;

        $("rmDetailSubtitle").textContent =
            `${applicant.id} · ${applicant.status}`;

        const grid = $("rmDetailGrid");
        grid.replaceChildren();

        [
            ["Full Name", fullName(applicant)],
            ["Email", applicant.email],
            ["Contact Number", applicant.phone],
            ["Sex", applicant.sex],
            ["Birthday", applicant.birthday],
            ["Municipality", applicant.municipality],
            ["Complete Address", applicant.address],
            ["Vehicle Type", applicant.vehicle],
            ["Selected Job Type", applicant.jobType || "Not selected"],
            ["Plate Number", applicant.plate],
            ["License Reference", applicant.license],
            ["Date Submitted", applicant.submitted],
            ["Review Status", applicant.status],
            ["Review Remarks", applicant.remarks || "None"]
        ].forEach(([label, value]) => {
            addDetail(grid, label, value);
        });

        const documents = $("rmDocuments");
        documents.replaceChildren();

        [
            "Driver's License",
            "Valid Identification"
        ].forEach(name => {
            const card = create("div", "rm-document");

            card.appendChild(create("strong", "", name));

            card.appendChild(
                create(
                    "span",
                    "",
                    "Document not available through this API"
                )
            );

            documents.appendChild(card);
        });

        $("rmApproveFromDetail").hidden =
            applicant.status !== "Pending";

        $("rmRejectFromDetail").hidden =
            applicant.status !== "Pending";

        closeModals();
        openModal("rmDetailModal");
    }

    function showDecision(id, nextStatus) {
        const applicant = getApplication(id);

        if (!applicant || applicant.status !== "Pending") {
            toast("This application has already been reviewed.", true);
            return;
        }

        activeId = id;
        decision = nextStatus;

        $("rmDecisionTitle").textContent =
            nextStatus === "Approved"
                ? "Approve Rider Application"
                : "Reject Rider Application";

        $("rmDecisionSubtitle").textContent =
            `${fullName(applicant)} · ${applicant.id}`;

        $("rmDecisionReason").value = "";

        const submit = $("rmDecisionSubmit");

        submit.textContent = nextStatus === "Approved"
            ? "Confirm Approval"
            : "Confirm Rejection";

        submit.className = nextStatus === "Approved"
            ? "rm-button rm-button-primary"
            : "rm-button rm-button-danger";

        closeModals();
        openModal("rmDecisionModal");
    }

    async function submitDecision(event) {
        event.preventDefault();

        const applicant = getApplication(activeId);
        const remarks = $("rmDecisionReason").value.trim();

        if (!applicant || applicant.status !== "Pending") {
            toast("Application is no longer pending.", true);
            return;
        }

        if (decision === "Rejected" && !remarks) {
            toast(
                "A reason is required when rejecting an application.",
                true
            );
            return;
        }

        const submit = $("rmDecisionSubmit");
        submit.disabled = true;

        try {
            const id = numericId(applicant.id, "APP-");

            const result = await request(
                `/applications/${id}/decide`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        status: decision,
                        remarks
                    })
                }
            );

            closeModals();
            toast(result.message || "Application reviewed.");

            await loadRiders();
        } catch (error) {
            toast(error.message, true);
        } finally {
            submit.disabled = false;
        }
    }

    function showRider(id) {
        const rider = getRider(id);
        if (!rider) return;

        activeId = id;

        $("rmDetailSubtitle").textContent =
            `${rider.id} · ${rider.status}`;

        const grid = $("rmDetailGrid");
        grid.replaceChildren();

        [
            ["Rider ID", rider.id],
            ["Full Name", rider.name],
            ["Email", rider.email],
            ["Contact Number", rider.phone],
            ["Assigned Municipality", rider.municipality],
            ["Address", rider.address],
            ["Vehicle Type", rider.vehicle],
            ["Selected Job Type", rider.jobType || "Not selected"],
            ["Plate Number", rider.plate],
            ["License Reference", rider.license],
            ["Account Status", rider.status],
            ["Application ID", rider.applicationId || "—"],
            ["Remarks", rider.remarks || "None"]
        ].forEach(([label, value]) => {
            addDetail(grid, label, value);
        });

        closeModals();
        openModal("rmDetailModal");
    }

    function showStatus(id) {
        const rider = getRider(id);
        if (!rider) return;

        activeId = id;

        $("rmStatusSubtitle").textContent =
            `${rider.name} · ${rider.id}`;

        $("rmNewStatus").value = rider.status;
        $("rmStatusReason").value = rider.remarks || "";

        closeModals();
        openModal("rmStatusModal");
    }

    async function submitStatus(event) {
        event.preventDefault();

        const rider = getRider(activeId);
        if (!rider) return;

        const status = $("rmNewStatus").value;
        const remarks = $("rmStatusReason").value.trim();

        if (!["Available", "Offline", "Suspended"].includes(status)) {
            toast("Invalid rider status.", true);
            return;
        }

        if (status === "Suspended" && !remarks) {
            toast("Please enter a reason for suspension.", true);
            return;
        }

        const submit = $("rmStatusForm").querySelector(
            '[type="submit"]'
        );

        if (submit) submit.disabled = true;

        try {
            const id = numericId(rider.id, "RDR-");

            const result = await request(
                `/riders/${id}/status`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        status,
                        remarks
                    })
                }
            );

            closeModals();

            toast(
                result.message ||
                `${rider.name} is now ${status}.`
            );

            await loadRiders();
        } catch (error) {
            toast(error.message, true);
        } finally {
            if (submit) submit.disabled = false;
        }
    }

    function exportCSV() {
        const rows = filteredItems();

        if (!rows.length) {
            toast("No records available to export.", true);
            return;
        }

        const headers = isApplications
            ? [
                "ID", "Name", "Email", "Phone",
                "Municipality", "Vehicle", "Submitted",
                "Status", "Job Type", "Remarks"
            ]
            : [
                "ID", "Name", "Email", "Phone",
                "Municipality", "Vehicle", "Plate",
                "Status", "Job Type", "Remarks"
            ];

        const values = rows.map(item => [
            item.id,
            isApplications ? fullName(item) : item.name,
            item.email,
            item.phone,
            item.municipality,
            item.vehicle,
            isApplications ? item.submitted : item.plate,
            item.status,
            item.jobType || "Not selected",
            item.remarks
        ]);

        const escapeCSV = value => {
            let text = String(value ?? "");

            if (/^\s*[=+\-@\t\r]/.test(text)) {
                text = "'" + text;
            }

            return `"${text.replace(/"/g, '""')}"`;
        };

        const csv = "\uFEFF" + [
            headers,
            ...values
        ].map(
            row => row.map(escapeCSV).join(",")
        ).join("\r\n");

        const blob = new Blob([csv], {
            type: "text/csv;charset=utf-8"
        });

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = isApplications
            ? "lumear-rider-applications.csv"
            : "lumear-rider-list.csv";

        document.body.appendChild(link);
        link.click();
        link.remove();

        setTimeout(() => URL.revokeObjectURL(url), 1000);

        toast("CSV exported successfully.");
    }

    $("rmSearch").addEventListener("input", () => {
        page = 1;
        renderTable();
    });

    $("rmFilter").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("rmPrev").addEventListener("click", () => {
        page--;
        renderTable();
    });

    $("rmNext").addEventListener("click", () => {
        page++;
        renderTable();
    });

    $("rmExport").addEventListener("click", exportCSV);

    if (isApplications) {
        $("rmApproveFromDetail").addEventListener("click", () => {
            showDecision(activeId, "Approved");
        });

        $("rmRejectFromDetail").addEventListener("click", () => {
            showDecision(activeId, "Rejected");
        });

        $("rmDecisionForm").addEventListener(
            "submit",
            submitDecision
        );
    } else {
        $("rmManageFromDetail").addEventListener("click", () => {
            showStatus(activeId);
        });

        $("rmStatusForm").addEventListener(
            "submit",
            submitStatus
        );
    }

    loadRiders();
});