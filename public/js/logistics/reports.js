
/* =====================================================
   LUMEAR LOGISTICS — GENERATE REPORTS
   Frontend-only sample reporting system
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    if (!document.getElementById("logisticsReportsApp")) return;

    const $ = id => document.getElementById(id);
    const PAGE_SIZE = 7;

    const moduleNames = {
        pickup: "Pickup Requests",
        incoming: "Incoming Parcels",
        sorting: "Parcel Sorting",
        assignment: "Delivery Assignment",
        delivery: "Delivery Monitoring",
        rider: "Rider Performance"
    };

    const locations = [
        "Santa Cruz, Laguna",
        "Victoria, Laguna",
        "Pila, Laguna",
        "Pagsanjan, Laguna",
        "Calamba, Laguna"
    ];

    const personnel = [
        "Carlos Mendoza",
        "Joshua Reyes",
        "Mark Villanueva",
        "Paolo Santos",
        "Logistics Staff"
    ];

    const descriptions = {
        pickup: [
            "Seller parcel pickup completed",
            "Pickup request scheduled",
            "Pickup request cancelled"
        ],
        incoming: [
            "Parcel received at sorting center",
            "Incoming parcel awaiting verification",
            "Parcel discrepancy reported"
        ],
        sorting: [
            "Parcel sorted to delivery zone",
            "Parcel queued for sorting",
            "Sorting exception recorded"
        ],
        assignment: [
            "Parcel assigned to delivery rider",
            "Assignment awaiting confirmation",
            "Rider assignment failed"
        ],
        delivery: [
            "Parcel successfully delivered",
            "Parcel currently out for delivery",
            "Delivery attempt failed"
        ],
        rider: [
            "Rider completed delivery shift",
            "Rider currently handling assignments",
            "Rider activity exception reported"
        ]
    };

    // Deterministic sample records, not actual data from other modules.
    const records = [];

    Object.keys(moduleNames).forEach((module, moduleIndex) => {
        for (let i = 0; i < 12; i++) {
            const day = 2 + ((i * 2 + moduleIndex * 3) % 24);
            const date = `2026-10-${String(day).padStart(2, "0")}`;
            const statusIndex = (i + moduleIndex) % 5;
            const status = statusIndex === 0
                ? "Exception"
                : statusIndex <= 2
                    ? "Completed"
                    : "In Progress";

            const descriptionIndex = status === "Completed"
                ? 0
                : status === "In Progress" ? 1 : 2;

            records.push({
                id: `LMR-${module.toUpperCase().slice(0, 3)}-${String(i + 1).padStart(4, "0")}`,
                date,
                module,
                description: descriptions[module][descriptionIndex],
                assignedTo: personnel[(i + moduleIndex) % personnel.length],
                location: locations[(i + moduleIndex) % locations.length],
                status
            });
        }
    });

    let reportRecords = [];
    let page = 1;
    let toastTimer = null;
    let generatedFrom = "";
    let generatedTo = "";
    let generatedType = "all";

    function create(tag, className = "", content = "") {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (content !== "") node.textContent = String(content);
        return node;
    }

    function toast(message, error = false) {
        const element = $("grToast");
        element.textContent = message;
        element.classList.toggle("error", error);
        element.classList.add("show");

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 3200);
    }

    function formatDate(value) {
        return new Date(`${value}T12:00:00`).toLocaleDateString("en-PH", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }

    function validateFilters() {
        const from = $("grFrom").value;
        const to = $("grTo").value;
        const error = $("grFilterError");

        error.hidden = true;
        error.textContent = "";

        if (!from || !to) {
            error.textContent = "Please select both start and end dates.";
            error.hidden = false;
            return false;
        }

        if (from > to) {
            error.textContent = "Date From cannot be later than Date To.";
            error.hidden = false;
            return false;
        }

        return true;
    }

    function generateReport(showToast = false) {
        if (!validateFilters()) return;

        generatedFrom = $("grFrom").value;
        generatedTo = $("grTo").value;
        generatedType = $("grType").value;

        reportRecords = records.filter(record => {
            const matchesDate =
                record.date >= generatedFrom &&
                record.date <= generatedTo;

            const matchesType =
                generatedType === "all" ||
                record.module === generatedType;

            return matchesDate && matchesType;
        });

        $("grSearch").value = "";
        $("grStatus").value = "all";
        page = 1;

        render();

        if (showToast) {
            toast(`Report generated with ${reportRecords.length} records.`);
        }
    }

    function visibleRecords() {
        const search = $("grSearch").value.trim().toLowerCase();
        const status = $("grStatus").value;

        return reportRecords.filter(record => {
            const searchable = [
                record.id,
                record.description,
                record.assignedTo,
                record.location,
                moduleNames[record.module],
                record.status
            ].join(" ").toLowerCase();

            return searchable.includes(search) &&
                (status === "all" || record.status === status);
        }).sort((a, b) =>
            b.date.localeCompare(a.date) ||
            a.id.localeCompare(b.id)
        );
    }

    function renderStats() {
        $("grTotal").textContent = reportRecords.length;

        $("grCompleted").textContent = reportRecords.filter(
            record => record.status === "Completed"
        ).length;

        $("grProgress").textContent = reportRecords.filter(
            record => record.status === "In Progress"
        ).length;

        $("grExceptions").textContent = reportRecords.filter(
            record => record.status === "Exception"
        ).length;
    }

    function renderBreakdown() {
        const container = $("grBreakdown");
        container.replaceChildren();

        const categories = [
            ["Completed", "green"],
            ["In Progress", "amber"],
            ["Exception", "red"]
        ];

        categories.forEach(([status, color]) => {
            const count = reportRecords.filter(
                record => record.status === status
            ).length;

            const percentage = reportRecords.length
                ? (count / reportRecords.length) * 100
                : 0;

            const item = create("div", "gr-breakdown-item");
            const heading = create("div", "gr-breakdown-heading");

            heading.appendChild(create("span", "", status));
            heading.appendChild(
                create("strong", "", `${count} (${Math.round(percentage)}%)`)
            );

            const track = create("div", "gr-progress-track");
            const fill = create("div", `gr-progress-fill ${color}`);
            fill.style.width = `${percentage}%`;

            track.appendChild(fill);
            item.append(heading, track);
            container.appendChild(item);
        });
    }

    function renderChart() {
        const chart = $("grChart");
        chart.replaceChildren();

        $("grChartPeriod").textContent =
            `${formatDate(generatedFrom)} – ${formatDate(generatedTo)}`;

        if (!reportRecords.length) {
            chart.appendChild(
                create("p", "gr-demo-note", "No chart data for this period.")
            );
            return;
        }

        // Group records by date. Limit displayed columns to 12,
        // combining nearby dates if the period has many active days.
        const totals = new Map();

        reportRecords.forEach(record => {
            totals.set(record.date, (totals.get(record.date) || 0) + 1);
        });

        const dates = [...totals.keys()].sort();
        const groupSize = Math.max(1, Math.ceil(dates.length / 12));
        const groups = [];

        for (let i = 0; i < dates.length; i += groupSize) {
            const batch = dates.slice(i, i + groupSize);
            const count = batch.reduce(
                (sum, date) => sum + totals.get(date), 0
            );

            groups.push({
                label: batch.length === 1
                    ? formatDate(batch[0]).replace(", 2026", "")
                    : `${batch[0].slice(5)}–${batch.at(-1).slice(5)}`,
                count
            });
        }

        const maximum = Math.max(1, ...groups.map(group => group.count));

        groups.forEach(group => {
            const column = create("div", "gr-chart-column");
            const value = create("span", "gr-chart-value", group.count);
            const track = create("div", "gr-chart-bar-track");
            const bar = create("div", "gr-chart-bar");

            bar.style.height = `${group.count / maximum * 100}%`;
            bar.title = `${group.label}: ${group.count} records`;

            track.appendChild(bar);

            column.append(
                value,
                track,
                create("span", "gr-chart-label", group.label)
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

    function makeCell(row, text, strong = false) {
        const td = create("td");

        td.appendChild(
            create(strong ? "strong" : "span",
                strong ? "gr-cell-main" : "", text)
        );

        row.appendChild(td);
        return td;
    }

    function makeRow(record) {
        const row = create("tr");

        makeCell(row, record.id, true);
        makeCell(row, formatDate(record.date));
        makeCell(row, moduleNames[record.module]);
        makeCell(row, record.description);
        makeCell(row, record.assignedTo);
        makeCell(row, record.location);

        const statusCell = create("td");

        const badgeClass = record.status === "Completed"
            ? "gr-badge-completed"
            : record.status === "In Progress"
                ? "gr-badge-progress"
                : "gr-badge-exception";

        statusCell.appendChild(
            create("span", `gr-badge ${badgeClass}`, record.status)
        );

        row.appendChild(statusCell);
        return row;
    }

    function renderTable() {
        const filtered = visibleRecords();
        const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));

        page = Math.min(Math.max(page, 1), pages);

        const start = (page - 1) * PAGE_SIZE;
        const current = filtered.slice(start, start + PAGE_SIZE);

        const tbody = $("grTableBody");
        tbody.replaceChildren();

        current.forEach(record => tbody.appendChild(makeRow(record)));

        $("grEmpty").hidden = filtered.length > 0;
        $("grRecordCount").textContent = `${filtered.length} records`;

        $("grShowing").textContent = filtered.length
            ? `Showing ${start + 1}–${start + current.length} of ${filtered.length} records`
            : "Showing 0 records";

        $("grPageLabel").textContent = `Page ${page} of ${pages}`;
        $("grPrev").disabled = page <= 1;
        $("grNext").disabled = page >= pages;
    }

    function render() {
        renderStats();
        renderBreakdown();
        renderChart();
        renderTable();
    }

    function csvValue(value) {
        let text = String(value ?? "");

        // Prevent spreadsheet formula interpretation.
        if (/^\s*[=+\-@]/.test(text)) {
            text = "'" + text;
        }

        return `"${text.replace(/"/g, '""')}"`;
    }

    function exportCSV() {
        const filtered = visibleRecords();

        if (!filtered.length) {
            toast("There are no matching records to export.", true);
            return;
        }

        const headers = [
            "Reference ID",
            "Date",
            "Module",
            "Description",
            "Assigned To",
            "Location",
            "Status"
        ];

        const rows = filtered.map(record => [
            record.id,
            record.date,
            moduleNames[record.module],
            record.description,
            record.assignedTo,
            record.location,
            record.status
        ]);

        const csv = "\uFEFF" + [headers, ...rows]
            .map(row => row.map(csvValue).join(","))
            .join("\r\n");

        const blob = new Blob([csv], {
            type: "text/csv;charset=utf-8"
        });

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download =
            `lumear-logistics-report-${generatedFrom}-to-${generatedTo}.csv`;

        document.body.appendChild(link);
        link.click();
        link.remove();

        setTimeout(() => URL.revokeObjectURL(url), 1000);

        toast("CSV report exported successfully.");
    }

    function resetFilters() {
        $("grType").value = "all";
        $("grFrom").value = "2026-10-01";
        $("grTo").value = "2026-10-31";
        $("grFilterError").hidden = true;
        generateReport();
        toast("Report filters have been reset.");
    }

    // EVENTS

    $("grGenerate").addEventListener("click", () => {
        generateReport(true);
    });

    $("grReset").addEventListener("click", resetFilters);

    $("grSearch").addEventListener("input", () => {
        page = 1;
        renderTable();
    });

    $("grStatus").addEventListener("change", () => {
        page = 1;
        renderTable();
    });

    $("grPrev").addEventListener("click", () => {
        page--;
        renderTable();
    });

    $("grNext").addEventListener("click", () => {
        page++;
        renderTable();
    });

    $("grExport").addEventListener("click", exportCSV);

    $("grPrint").addEventListener("click", () => {
        if (!reportRecords.length) {
            toast("Generate a report with records before printing.", true);
            return;
        }

        window.print();
    });

    generateReport();
});
