document.addEventListener("DOMContentLoaded", () => {
    const app = document.getElementById("operationsApp");
    if (!app) return;

    const module = app.dataset.module;
    const KEY = "lumear_operations_demo_v1";

    const $ = (id) => document.getElementById(id);

    const seed = [
        {
            id: "LMR-240128",
            seller: "Maven Apparel",
            buyer: "Angela Cruz",
            destination: "Santa Cruz, Laguna",
            quantity: 4,
            status: "awaiting",
            zone: "",
            rider: "",
            history: ["Pickup completed by rider"]
        },
        {
            id: "LMR-240127",
            seller: "Home Studio",
            buyer: "Maria Santos",
            destination: "Pila, Laguna",
            quantity: 7,
            status: "awaiting",
            zone: "",
            rider: "",
            history: ["Pickup completed by rider"]
        },
        {
            id: "LMR-240126",
            seller: "Galleria Crafts",
            buyer: "Mark Reyes",
            destination: "Victoria, Laguna",
            quantity: 2,
            status: "received",
            zone: "",
            rider: "",
            history: ["Pickup completed by rider", "Received at sorting center"]
        },
        {
            id: "LMR-240125",
            seller: "Luna Essentials",
            buyer: "Andrea Flores",
            destination: "Santa Cruz, Laguna",
            quantity: 5,
            status: "received",
            zone: "",
            rider: "",
            history: ["Pickup completed by rider", "Received at sorting center"]
        },
        {
            id: "LMR-240124",
            seller: "North & Co.",
            buyer: "John Garcia",
            destination: "Pagsanjan, Laguna",
            quantity: 3,
            status: "ready",
            zone: "Pagsanjan Zone",
            rider: "",
            history: [
                "Received at sorting center",
                "Sorted to Pagsanjan Zone",
                "Ready for delivery"
            ]
        },
        {
            id: "LMR-240123",
            seller: "Crafted Daily",
            buyer: "Sophia Mendoza",
            destination: "Pila, Laguna",
            quantity: 6,
            status: "ready",
            zone: "Pila Zone",
            rider: "",
            history: [
                "Received at sorting center",
                "Sorted to Pila Zone",
                "Ready for delivery"
            ]
        },
        {
            id: "LMR-240122",
            seller: "The Daily Market",
            buyer: "Carlo Rivera",
            destination: "Victoria, Laguna",
            quantity: 8,
            status: "assigned",
            zone: "Victoria Zone",
            rider: "Andrea Santos",
            history: [
                "Received at sorting center",
                "Ready for delivery",
                "Assigned to Andrea Santos"
            ]
        },
        {
            id: "LMR-240121",
            seller: "Everyday Finds",
            buyer: "Julia Ramos",
            destination: "Santa Cruz, Laguna",
            quantity: 1,
            status: "out",
            zone: "Santa Cruz Zone",
            rider: "Juan Reyes",
            history: [
                "Received at sorting center",
                "Ready for delivery",
                "Assigned to Juan Reyes",
                "Out for delivery"
            ]
        },
        {
            id: "LMR-240120",
            seller: "Bloom Store",
            buyer: "Daniel Lopez",
            destination: "Pila, Laguna",
            quantity: 2,
            status: "delivered",
            zone: "Pila Zone",
            rider: "Miguel Cruz",
            history: [
                "Received at sorting center",
                "Ready for delivery",
                "Assigned to Miguel Cruz",
                "Delivered successfully"
            ]
        }
    ];

    const statusNames = {
        awaiting: "Awaiting Receipt",
        received: "Received",
        ready: "Ready for Delivery",
        assigned: "Assigned to Rider",
        out: "Out for Delivery",
        delivered: "Delivered",
        failed: "Delivery Failed"
    };

    const configs = {
        incoming: {
            subtitle: "Verify parcels arriving from pickup riders at the sorting center.",
            title: "Incoming Parcel Queue",
            description: "Confirm physical receipt before parcels enter sorting.",
            statuses: ["awaiting", "received"],
            action: "Confirm Received"
        },
        sorting: {
            subtitle: "Organize received parcels by their destination zones.",
            title: "Parcel Sorting Queue",
            description: "Choose a sorting zone and prepare parcels for delivery.",
            statuses: ["received", "ready"],
            action: "Complete Sorting"
        },
        assignment: {
            subtitle: "Assign delivery-ready parcels to available riders.",
            title: "Delivery Assignment Queue",
            description: "Select a rider to handle each delivery.",
            statuses: ["ready", "assigned"],
            action: "Assign Rider"
        },
        monitoring: {
            subtitle: "Monitor delivery progress and update parcel outcomes.",
            title: "Delivery Monitoring",
            description: "Track rider assignments and delivery status.",
            statuses: ["assigned", "out", "delivered", "failed"],
            action: "Update Delivery"
        }
    };

    const config = configs[module];
    if (!config) return;

    function load() {
        try {
            const stored = JSON.parse(localStorage.getItem(KEY));
            return Array.isArray(stored) ? stored : structuredClone(seed);
        } catch {
            return structuredClone(seed);
        }
    }

    let parcels = load();
    let selectedId = null;
    let toastTimer;

    function save() {
        localStorage.setItem(KEY, JSON.stringify(parcels));
    }

    // Save initial sample data for all four pages.
    if (!localStorage.getItem(KEY)) save();

    function safe(value) {
        return String(value ?? "").replace(/[&<>"']/g, (char) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[char]);
    }

    function badge(status) {
        return `<span class="ops-status ${safe(status)}">${safe(statusNames[status])}</span>`;
    }

    function eligible(parcel) {
        return config.statuses.includes(parcel.status);
    }

    function actionLabel(status) {
        if (module === "incoming") return status === "awaiting" ? "Receive" : "Details";
        if (module === "sorting") return status === "received" ? "Sort" : "Details";
        if (module === "assignment") return status === "ready" ? "Assign" : "Details";
        if (module === "monitoring") {
            return ["assigned", "out"].includes(status) ? "Update" : "Details";
        }
        return "Details";
    }

    function setText(id, value) {
        if ($(id)) $(id).textContent = value;
    }

    function configurePage() {
        setText("opsSubtitle", config.subtitle);
        setText("opsTableTitle", config.title);
        setText("opsTableDescription", config.description);

        const select = $("opsStatusFilter");
        select.innerHTML = '<option value="all">All statuses</option>';

        config.statuses.forEach(status => {
            const option = document.createElement("option");
            option.value = status;
            option.textContent = statusNames[status];
            select.appendChild(option);
        });
    }

    function updateStats() {
        const count = status => parcels.filter(p => p.status === status).length;
        const total = parcels.length;

        const values = {
            incoming: [
                ["Total Parcels", total],
                ["Awaiting Receipt", count("awaiting")],
                ["Received", count("received")],
                ["Active Deliveries", count("assigned") + count("out")]
            ],
            sorting: [
                ["Total Parcels", total],
                ["Needs Sorting", count("received")],
                ["Ready for Delivery", count("ready")],
                ["Sorted & Assigned", count("assigned") + count("out")]
            ],
            assignment: [
                ["Ready to Assign", count("ready")],
                ["Assigned", count("assigned")],
                ["Out for Delivery", count("out")],
                ["Delivered", count("delivered")]
            ],
            monitoring: [
                ["Assigned", count("assigned")],
                ["Out for Delivery", count("out")],
                ["Delivered", count("delivered")],
                ["Delivery Failed", count("failed")]
            ]
        }[module];

        values.forEach(([label, value], i) => {
            setText("opsStatLabel" + (i + 1), label);
            setText("opsStat" + (i + 1), value);
        });
    }

    function render() {
        const query = $("opsSearch").value.trim().toLowerCase();
        const filter = $("opsStatusFilter").value;

        const visible = parcels.filter(p => {
            if (!eligible(p)) return false;
            if (filter !== "all" && p.status !== filter) return false;

            const text = [
                p.id, p.seller, p.buyer, p.destination, p.rider
            ].join(" ").toLowerCase();

            return text.includes(query);
        });

        $("opsTableBody").innerHTML = visible.map(p => `
            <tr>
                <td><strong>${safe(p.id)}</strong><small>Standard Parcel</small></td>
                <td>
                    <strong>${safe(p.seller)}</strong>
                    <small>Buyer: ${safe(p.buyer)}</small>
                </td>
                <td><strong>${safe(p.destination)}</strong></td>
                <td><strong>${p.quantity}</strong></td>
                <td>${badge(p.status)}</td>
                <td>${safe(p.rider || "Not assigned")}</td>
                <td>
                    <button type="button" class="ops-row-button"
                            data-parcel="${safe(p.id)}">
                        ${actionLabel(p.status)}
                    </button>
                </td>
            </tr>
        `).join("");

        setText("opsVisibleCount", visible.length);
        $("opsEmpty").classList.toggle("show", visible.length === 0);

        updateStats();
    }

    function openDrawer(id) {
        const p = parcels.find(item => item.id === id);
        if (!p) return;

        selectedId = id;

        setText("opsDrawerTitle", p.id);
        $("opsDrawerStatus").innerHTML = badge(p.status);

        setText("opsDetailId", p.id);
        setText("opsDetailQuantity", p.quantity);
        setText("opsDetailSeller", p.seller);
        setText("opsDetailBuyer", p.buyer);
        setText("opsDetailDestination", p.destination);
        setText("opsDetailZone", p.zone || "Not sorted");
        setText("opsDetailRider", p.rider || "Not assigned");

        $("opsHistory").innerHTML = p.history.map(entry => `
            <div class="ops-history-item">
                <strong>${safe(entry)}</strong>
                <small>Demo activity</small>
            </div>
        `).join("");

        const canAct =
            (module === "incoming" && p.status === "awaiting") ||
            (module === "sorting" && p.status === "received") ||
            (module === "assignment" && p.status === "ready") ||
            (module === "monitoring" && ["assigned", "out"].includes(p.status));

        $("opsActionSection").style.display = canAct ? "" : "none";
        $("opsZoneField").style.display = module === "sorting" ? "" : "none";
        $("opsRiderField").style.display = module === "assignment" ? "" : "none";
        $("opsDeliveryField").style.display = module === "monitoring" ? "" : "none";

        $("opsZone").value = "";
        $("opsRider").value = "";
        $("opsValidation").textContent = "";

        const nextStatus = $("opsDeliveryStatus");
        nextStatus.innerHTML = '<option value="">Select next status</option>';

        if (module === "monitoring") {
            const options = p.status === "assigned"
                ? [["out", "Out for Delivery"]]
                : [["delivered", "Delivered"], ["failed", "Delivery Failed"]];

            options.forEach(([value, label]) => {
                const option = document.createElement("option");
                option.value = value;
                option.textContent = label;
                nextStatus.appendChild(option);
            });
        }

        const descriptions = {
            incoming: "Confirm that the parcel has physically arrived at the sorting center.",
            sorting: "Select the destination zone before marking this parcel ready for delivery.",
            assignment: "Select a rider to handle delivery of this parcel.",
            monitoring: "Update the delivery progress for this parcel."
        };

        setText("opsActionHeading", config.action);
        setText("opsActionHelp", descriptions[module]);
        setText("opsConfirmAction", config.action);

        $("opsOverlay").classList.add("open");
        $("opsDrawer").classList.add("open");
        $("opsDrawer").setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeDrawer() {
        $("opsOverlay").classList.remove("open");
        $("opsDrawer").classList.remove("open");
        $("opsDrawer").setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        selectedId = null;
    }

    function toast(message) {
        const element = $("opsToast");
        element.textContent = message;
        element.classList.add("show");

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => element.classList.remove("show"), 3200);
    }

    function processAction() {
        const p = parcels.find(item => item.id === selectedId);
        if (!p) return;

        let message = "";
        const validation = $("opsValidation");
        validation.textContent = "";

        if (module === "incoming" && p.status === "awaiting") {
            p.status = "received";
            p.history.push("Received at sorting center");
            message = "Parcel received. It is now available for sorting.";
        } else if (module === "sorting" && p.status === "received") {
            const zone = $("opsZone").value;
            if (!zone) {
                validation.textContent = "Please select a sorting zone.";
                return;
            }

            p.zone = zone;
            p.status = "ready";
            p.history.push("Sorted to " + zone);
            p.history.push("Ready for delivery");
            message = "Sorting completed. Parcel is ready for rider assignment.";
        } else if (module === "assignment" && p.status === "ready") {
            const rider = $("opsRider").value;
            if (!rider) {
                validation.textContent = "Please select a rider.";
                return;
            }

            p.rider = rider;
            p.status = "assigned";
            p.history.push("Assigned to " + rider);
            message = "Rider assigned successfully.";
        } else if (module === "monitoring" && ["assigned", "out"].includes(p.status)) {
            const next = $("opsDeliveryStatus").value;

            const allowed = p.status === "assigned"
                ? ["out"]
                : ["delivered", "failed"];

            if (!allowed.includes(next)) {
                validation.textContent = "Please select a valid next status.";
                return;
            }

            p.status = next;
            p.history.push(statusNames[next]);
            message = "Delivery status updated to " + statusNames[next] + ".";
        } else {
            validation.textContent = "This action is not available for the current parcel status.";
            return;
        }

        save();
        closeDrawer();
        render();
        toast(message);
    }

    $("opsTableBody").addEventListener("click", event => {
        const button = event.target.closest("[data-parcel]");
        if (button) openDrawer(button.dataset.parcel);
    });

    $("opsSearch").addEventListener("input", render);
    $("opsStatusFilter").addEventListener("change", render);
    $("opsConfirmAction").addEventListener("click", processAction);
    $("opsClose").addEventListener("click", closeDrawer);
    $("opsOverlay").addEventListener("click", closeDrawer);

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") closeDrawer();
    });

    $("opsReset").addEventListener("click", () => {
        if (!confirm("Reset all demo parcels to their original statuses?")) return;

        parcels = structuredClone(seed);
        save();
        closeDrawer();
        $("opsSearch").value = "";
        $("opsStatusFilter").value = "all";
        render();
        toast("Demo parcel data has been reset.");
    });

    window.addEventListener("storage", event => {
        if (event.key === KEY) {
            parcels = load();
            closeDrawer();
            render();
        }
    });

    configurePage();
    render();
});