/* =====================================================
   LUMEAR LOGISTICS — GENERAL REPORTS
   Live Laravel + MySQL Reporting
   PDF-ONLY EXPORT + REPORT HISTORY
   Original Reports UI preserved
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const app = document.getElementById("logisticsReportsApp");
    if (!app) return;

    const $ = id => document.getElementById(id);

    const API = "/logistics/api/reports";
    const PDF_API = "/logistics/api/reports/pdf";
    const HISTORY_API = "/logistics/api/reports/history";

    const PAGE_SIZE = 7;

    const moduleNames = {
        all: "All Logistics Modules",
        pickup: "Pickup Requests",
        incoming: "Incoming Parcels",
        sorting: "Parcel Sorting",
        assignment: "Delivery Assignment",
        delivery: "Delivery Monitoring",
        rider: "Rider Performance"
    };

    let reportRecords = [];
    let page = 1;
    let toastTimer = null;

    let generatedFrom = "";
    let generatedTo = "";
    let generatedType = "all";

    let loading = false;
    let exporting = false;

    /* =====================================================
       HELPER FUNCTIONS
       ===================================================== */

    function create(tag, className = "", content = "") {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (content !== "") {
            node.textContent = String(content);
        }

        return node;
    }

    function toast(message, error = false) {
        const element = $("grToast");

        if (!element) {
            if (error) console.error(message);
            return;
        }

        element.textContent = message;
        element.classList.toggle("error", error);
        element.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 3200);
    }

    function formatDate(value) {
        if (!value) return "—";

        const date = new Date(
            `${String(value).slice(0, 10)}T12:00:00`
        );

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleDateString("en-PH", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }

    function localDate(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, "0"),
            String(date.getDate()).padStart(2, "0")
        ].join("-");
    }

    function setDefaultDates() {
        const today = new Date();

        const firstDay = new Date(
            today.getFullYear(),
            today.getMonth(),
            1
        );

        $("grFrom").value = localDate(firstDay);
        $("grTo").value = localDate(today);
    }

    function syncQuickRange() {
        const quickRange = $("grQuickRange");

        if (quickRange) {
            quickRange.value = "custom";
        }
    }

    function getSelectedFilters() {
        return {
            from: $("grFrom").value,
            to: $("grTo").value,
            type: $("grType").value,
            status: $("grStatus").value
        };
    }

    /* =====================================================
       DATE FILTER VALIDATION
       ===================================================== */

    function validateFilters() {
        const from = $("grFrom").value;
        const to = $("grTo").value;

        const error = $("grFilterError");

        if (error) {
            error.hidden = true;
            error.textContent = "";
        }

        function showError(message) {
            if (error) {
                error.textContent = message;
                error.hidden = false;
            }

            toast(message, true);
            return false;
        }

        if (!from || !to) {
            return showError(
                "Please select both start and end dates."
            );
        }

        if (from > to) {
            return showError(
                "Date From cannot be later than Date To."
            );
        }

        const fromDate = new Date(`${from}T12:00:00`);
        const toDate = new Date(`${to}T12:00:00`);

        const days = Math.round(
            (toDate - fromDate) / 86400000
        );

        if (
            Number.isNaN(days) ||
            days < 0 ||
            days > 366
        ) {
            return showError(
                "Please select a valid period of 366 days or less."
            );
        }

        return true;
    }

    /* =====================================================
       QUICK DATE RANGE FILTERS
       ===================================================== */

    function applyQuickRange() {
        const quickRange = $("grQuickRange");

        if (!quickRange) return;

        const range = quickRange.value;

        if (range === "custom") {
            return;
        }

        const today = new Date();
        const from = new Date(today);

        if (range === "today") {
            // Today only.
        } else {
            const days = Number(range);

            if (!Number.isInteger(days) || days < 1) {
                return;
            }

            // Include today in the selected number of days.
            from.setDate(today.getDate() - days + 1);
        }

        $("grFrom").value = localDate(from);
        $("grTo").value = localDate(today);

        generateReport(true);
    }

    /* =====================================================
       API REQUESTS
       ===================================================== */

    async function apiRequest(params) {
        const query = new URLSearchParams(params);

        const response = await fetch(
            `${API}?${query.toString()}`,
            {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            }
        );

        const result = await response.json()
            .catch(() => ({}));

        if (!response.ok) {
            const errors = result.errors
                ? Object.values(result.errors)
                    .flat()
                    .join(" ")
                : null;

            throw new Error(
                errors ||
                result.message ||
                `Report request failed (${response.status}).`
            );
        }

        return result;
    }

    /* =====================================================
       GENERATE REPORT PREVIEW
       ===================================================== */

    async function generateReport(showToast = false) {
        if (loading || !validateFilters()) return;

        const filters = getSelectedFilters();

        const button = $("grGenerate");
        const previousText = button.textContent;

        loading = true;
        button.disabled = true;
        button.textContent = "Generating...";

        try {
            const result = await apiRequest({
                from: filters.from,
                to: filters.to,
                type: filters.type
            });

            reportRecords = Array.isArray(result.records)
                ? result.records
                : [];

            generatedFrom = filters.from;
            generatedTo = filters.to;
            generatedType = filters.type;

            // Keep the selected status filter.
            // Do not reset it to "all".

            if ($("grSearch")) {
                $("grSearch").value = "";
            }

            page = 1;

            render();

            if (showToast) {
                toast(
                    `Report preview generated with ${
                        visibleRecords().length
                    } matching records.`
                );
            }
        } catch (error) {
            toast(error.message, true);
        } finally {
            loading = false;
            button.disabled = false;
            button.textContent = previousText;
        }
    }

    /* =====================================================
       FILTERED RECORDS
       ===================================================== */

    function visibleRecords() {
        const search = $("grSearch").value
            .trim()
            .toLowerCase();

        const status = $("grStatus").value;

        return reportRecords.filter(record => {
            const searchable = [
                record.id,
                record.description,
                record.assignedTo,
                record.location,
                moduleNames[record.module] ||
                    record.module,
                record.status
            ].join(" ").toLowerCase();

            const matchesSearch =
                searchable.includes(search);

            const matchesStatus =
                status === "all" ||
                record.status === status;

            return matchesSearch && matchesStatus;
        }).sort((a, b) =>
            String(b.date).localeCompare(
                String(a.date)
            ) ||
            String(a.id).localeCompare(
                String(b.id)
            )
        );
    }

    function statusFilteredRecords() {
        const status = $("grStatus").value;

        return reportRecords.filter(record =>
            status === "all" ||
            record.status === status
        );
    }

    /* =====================================================
       STATISTICS CARDS
       ===================================================== */

    function renderStats() {
        const records = statusFilteredRecords();

        $("grTotal").textContent = records.length;

        $("grCompleted").textContent = records.filter(
            record => record.status === "Completed"
        ).length;

        $("grProgress").textContent = records.filter(
            record => record.status === "In Progress"
        ).length;

        $("grExceptions").textContent = records.filter(
            record => record.status === "Exception"
        ).length;
    }

    /* =====================================================
       STATUS BREAKDOWN
       ===================================================== */

    function renderBreakdown() {
        const container = $("grBreakdown");
        container.replaceChildren();

        const records = statusFilteredRecords();

        const categories = [
            ["Completed", "green"],
            ["In Progress", "amber"],
            ["Exception", "red"]
        ];

        categories.forEach(([status, color]) => {
            const count = records.filter(
                record => record.status === status
            ).length;

            const percentage = records.length
                ? (count / records.length) * 100
                : 0;

            const item = create(
                "div",
                "gr-breakdown-item"
            );

            const heading = create(
                "div",
                "gr-breakdown-heading"
            );

            heading.appendChild(
                create("span", "", status)
            );

            heading.appendChild(
                create(
                    "strong",
                    "",
                    `${count} (${Math.round(percentage)}%)`
                )
            );

            const track = create(
                "div",
                "gr-progress-track"
            );

            const fill = create(
                "div",
                `gr-progress-fill ${color}`
            );

            fill.style.width = `${percentage}%`;

            track.appendChild(fill);
            item.append(heading, track);

            container.appendChild(item);
        });
    }

    /* =====================================================
       ACTIVITY CHART
       ===================================================== */

    function renderChart() {
        const chart = $("grChart");
        chart.replaceChildren();

        if (!generatedFrom || !generatedTo) return;

        $("grChartPeriod").textContent =
            `${formatDate(generatedFrom)} – ${
                formatDate(generatedTo)
            }`;

        const records = statusFilteredRecords();

        if (!records.length) {
            chart.appendChild(
                create(
                    "p",
                    "gr-demo-note",
                    "No recorded logistics activity for this period."
                )
            );

            return;
        }

        const totals = new Map();

        records.forEach(record => {
            totals.set(
                record.date,
                (totals.get(record.date) || 0) + 1
            );
        });

        const dates = [...totals.keys()].sort();

        const groupSize = Math.max(
            1,
            Math.ceil(dates.length / 12)
        );

        const groups = [];

        for (
            let i = 0;
            i < dates.length;
            i += groupSize
        ) {
            const batch = dates.slice(
                i,
                i + groupSize
            );

            const count = batch.reduce(
                (sum, date) =>
                    sum + totals.get(date),
                0
            );

            groups.push({
                label: batch.length === 1
                    ? batch[0].slice(5)
                    : `${batch[0].slice(5)}–${
                        batch[batch.length - 1].slice(5)
                    }`,
                count
            });
        }

        const maximum = Math.max(
            1,
            ...groups.map(group => group.count)
        );

        groups.forEach(group => {
            const column = create(
                "div",
                "gr-chart-column"
            );

            const value = create(
                "span",
                "gr-chart-value",
                group.count
            );

            const track = create(
                "div",
                "gr-chart-bar-track"
            );

            const bar = create(
                "div",
                "gr-chart-bar"
            );

            bar.style.height =
                `${group.count / maximum * 100}%`;

            bar.title =
                `${group.label}: ${group.count} records`;

            track.appendChild(bar);

            column.append(
                value,
                track,
                create(
                    "span",
                    "gr-chart-label",
                    group.label
                )
            );

            chart.appendChild(column);
        });

        chart.setAttribute(
            "aria-label",
            groups.map(group =>
                `${group.label}: ${group.count} records`
            ).join("; ")
        );
    }

    /* =====================================================
       REPORT TABLE
       ===================================================== */

    function makeCell(row, text, strong = false) {
        const td = create("td");

        td.appendChild(
            create(
                strong ? "strong" : "span",
                strong ? "gr-cell-main" : "",
                text ?? "—"
            )
        );

        row.appendChild(td);
    }

    function makeRow(record) {
        const row = create("tr");

        makeCell(row, record.id, true);
        makeCell(row, formatDate(record.date));

        makeCell(
            row,
            moduleNames[record.module] ||
                record.module
        );

        makeCell(row, record.description);
        makeCell(row, record.assignedTo);
        makeCell(row, record.location);

        const statusCell = create("td");

        const badgeClass =
            record.status === "Completed"
                ? "gr-badge-completed"
                : record.status === "In Progress"
                    ? "gr-badge-progress"
                    : "gr-badge-exception";

        statusCell.appendChild(
            create(
                "span",
                `gr-badge ${badgeClass}`,
                record.status
            )
        );

        row.appendChild(statusCell);

        return row;
    }

    function renderTable() {
        const filtered = visibleRecords();

        const pages = Math.max(
            1,
            Math.ceil(filtered.length / PAGE_SIZE)
        );

        page = Math.min(
            Math.max(page, 1),
            pages
        );

        const start = (page - 1) * PAGE_SIZE;

        const current = filtered.slice(
            start,
            start + PAGE_SIZE
        );

        const tbody = $("grTableBody");
        tbody.replaceChildren();

        current.forEach(record => {
            tbody.appendChild(makeRow(record));
        });

        $("grEmpty").hidden = filtered.length > 0;

        $("grRecordCount").textContent =
            `${filtered.length} records`;

        $("grShowing").textContent =
            filtered.length
                ? `Showing ${start + 1}–${
                    start + current.length
                } of ${filtered.length} records`
                : "Showing 0 records";

        $("grPageLabel").textContent =
            `Page ${page} of ${pages}`;

        $("grPrev").disabled = page <= 1;
        $("grNext").disabled = page >= pages;
    }

    function render() {
        renderStats();
        renderBreakdown();
        renderChart();
        renderTable();
    }

    /* =====================================================
       OFFICIAL PDF REPORT GENERATION
       ===================================================== */

    async function exportPDF() {
        if (exporting || !validateFilters()) return;

        const filters = getSelectedFilters();

        const button = $("grExport");
        const originalText = button.textContent;

        exporting = true;
        button.disabled = true;
        button.textContent = "Generating PDF...";

        try {
            const token = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

            if (!token) {
                throw new Error(
                    "CSRF token is missing from the Logistics layout."
                );
            }

            const response = await fetch(PDF_API, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/pdf, application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": token
                },
                body: JSON.stringify({
                    from: filters.from,
                    to: filters.to,
                    type: filters.type,
                    status: filters.status
                })
            });

            if (!response.ok) {
                const error = await response.json()
                    .catch(() => ({}));

                const details = error.errors
                    ? Object.values(error.errors)
                        .flat()
                        .join(" ")
                    : null;

                throw new Error(
                    details ||
                    error.message ||
                    `PDF generation failed (${response.status}).`
                );
            }

            const contentType = response.headers.get(
                "Content-Type"
            ) || "";

            if (!contentType.includes("application/pdf")) {
                throw new Error(
                    "Server did not return a PDF file."
                );
            }

            const blob = await response.blob();

            const disposition = response.headers.get(
                "Content-Disposition"
            ) || "";

            const match = disposition.match(
                /filename="?([^";]+)"?/i
            );

            const filename = match
                ? match[1]
                : `LUMEAR-Logistics-Report-${
                    filters.from
                }-to-${filters.to}.pdf`;

            downloadBlob(blob, filename);

            toast(
                "Official LUMEAR PDF report generated successfully."
            );

            await loadReportHistory();

        } catch (error) {
            toast(error.message, true);

        } finally {
            exporting = false;
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);

        const link = document.createElement("a");

        link.href = url;
        link.download = filename;

        document.body.appendChild(link);

        link.click();
        link.remove();

        setTimeout(() => {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    /* =====================================================
       GENERATED REPORTS HISTORY
       ===================================================== */

    async function loadReportHistory() {
        const tbody = $("grHistoryBody");

        if (!tbody) return;

        try {
            const response = await fetch(HISTORY_API, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            });

            if (!response.ok) {
                throw new Error(
                    "Could not load generated reports history."
                );
            }

            const result = await response.json();

            const reports = Array.isArray(result.reports)
                ? result.reports
                : [];

            tbody.replaceChildren();

            if (!reports.length) {
                const row = create("tr");
                const cell = create(
                    "td",
                    "",
                    "No generated PDF reports yet."
                );

                cell.colSpan = 6;

                row.appendChild(cell);
                tbody.appendChild(row);

                return;
            }

            reports.forEach(report => {
                const row = create("tr");

                const values = [
                    `RPT-${String(report.id).padStart(5, "0")}`,
                    moduleNames[report.report_type] ||
                        report.report_type,
                    `${formatDate(report.date_from)} – ${
                        formatDate(report.date_to)
                    }`,
                    report.record_count,
                    formatDate(
                        String(report.created_at).slice(0, 10)
                    )
                ];

                values.forEach(value => {
                    const cell = create("td");

                    cell.textContent = value ?? "—";

                    row.appendChild(cell);
                });

                const actionCell = create("td");

                const link = create(
                    "a",
                    "gr-btn gr-btn-outline",
                    "Download PDF"
                );

                link.href =
                    `${HISTORY_API}/${report.id}/download`;

                link.setAttribute(
                    "aria-label",
                    `Download report RPT-${
                        String(report.id).padStart(5, "0")
                    }`
                );

                actionCell.appendChild(link);
                row.appendChild(actionCell);

                tbody.appendChild(row);
            });

        } catch (error) {
            toast(error.message, true);
        }
    }

    /* =====================================================
       RESET FILTERS
       ===================================================== */

    function resetFilters() {
        $("grType").value = "all";
        $("grStatus").value = "all";

        if ($("grSearch")) {
            $("grSearch").value = "";
        }

        setDefaultDates();
        syncQuickRange();

        const error = $("grFilterError");

        if (error) {
            error.hidden = true;
            error.textContent = "";
        }

        generateReport(true);
    }

    /* =====================================================
       EVENT LISTENERS
       ===================================================== */

    // Generate report preview.
    $("grGenerate").addEventListener(
        "click",
        () => generateReport(true)
    );

    // Reset filters.
    $("grReset").addEventListener(
        "click",
        resetFilters
    );

    // Search table records.
    $("grSearch").addEventListener(
        "input",
        () => {
            page = 1;
            renderTable();
        }
    );

    // Filter by record status.
    $("grStatus").addEventListener(
        "change",
        () => {
            page = 1;
            render();
        }
    );

    // Previous page.
    $("grPrev").addEventListener(
        "click",
        () => {
            page--;
            renderTable();
        }
    );

    // Next page.
    $("grNext").addEventListener(
        "click",
        () => {
            page++;
            renderTable();
        }
    );

    // PDF download.
    $("grExport").addEventListener(
        "click",
        exportPDF
    );

    // Quick date range.
    if ($("grQuickRange")) {
        $("grQuickRange").addEventListener(
            "change",
            applyQuickRange
        );
    }

    // Manual date changes switch to custom range.
    $("grFrom").addEventListener(
        "change",
        syncQuickRange
    );

    $("grTo").addEventListener(
        "change",
        syncQuickRange
    );

    /* =====================================================
       INITIAL LOAD
       ===================================================== */

    setDefaultDates();

    if ($("grQuickRange")) {
        $("grQuickRange").value = "custom";
    }

    generateReport();
    loadReportHistory();
});
