/* =========================================================
   LUMEAR LOGISTICS
   PICKUP REQUEST MANAGEMENT
   ========================================================= */


document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           ELEMENTS
           ================================================= */

        const rows =
            Array.from(
                document.querySelectorAll(
                    ".pickup-request-row"
                )
            );


        const searchInput =
            document.getElementById(
                "pickupSearch"
            );


        const statusFilter =
            document.getElementById(
                "pickupStatusFilter"
            );


        const areaFilter =
            document.getElementById(
                "pickupAreaFilter"
            );


        const visibleRequestCount =
            document.getElementById(
                "visibleRequestCount"
            );


        const emptyState =
            document.getElementById(
                "pickupEmptyState"
            );


        const drawer =
            document.getElementById(
                "pickupDrawer"
            );


        const drawerOverlay =
            document.getElementById(
                "pickupDrawerOverlay"
            );


        const drawerCloseButton =
            document.getElementById(
                "drawerCloseButton"
            );


        const drawerRequestId =
            document.getElementById(
                "drawerRequestId"
            );


        const drawerSeller =
            document.getElementById(
                "drawerSeller"
            );


        const drawerAddress =
            document.getElementById(
                "drawerAddress"
            );


        const drawerBarangay =
            document.getElementById(
                "drawerBarangay"
            );


        const drawerParcelCount =
            document.getElementById(
                "drawerParcelCount"
            );


        const drawerStatus =
            document.getElementById(
                "drawerStatus"
            );


        const drawerActions =
            document.getElementById(
                "drawerActions"
            );


        const approveButton =
            document.getElementById(
                "approveRequestButton"
            );


        const rejectButton =
            document.getElementById(
                "rejectRequestButton"
            );


        const riderAssignmentSection =
            document.getElementById(
                "riderAssignmentSection"
            );


        const riderSelect =
            document.getElementById(
                "pickupRiderSelect"
            );


        const assignRiderButton =
            document.getElementById(
                "assignRiderButton"
            );


        const rejectionModal =
            document.getElementById(
                "rejectionModal"
            );


        const rejectionReason =
            document.getElementById(
                "rejectionReason"
            );


        const cancelRejectionButton =
            document.getElementById(
                "cancelRejectionButton"
            );


        const confirmRejectionButton =
            document.getElementById(
                "confirmRejectionButton"
            );


        const refreshButton =
            document.getElementById(
                "refreshPickupButton"
            );


        const toast =
            document.getElementById(
                "pickupToast"
            );


        const toastTitle =
            document.getElementById(
                "toastTitle"
            );


        const toastMessage =
            document.getElementById(
                "toastMessage"
            );


        const reviewTimelineEntry =
            document.getElementById(
                "reviewTimelineEntry"
            );


        const reviewTimelineTitle =
            document.getElementById(
                "reviewTimelineTitle"
            );


        const reviewTimelineDescription =
            document.getElementById(
                "reviewTimelineDescription"
            );


        let activeRow = null;

        let toastTimeout = null;



        /* =================================================
           FILTER TABLE
           ================================================= */

        function filterRequests() {

            const searchValue =
                searchInput
                    ? searchInput
                        .value
                        .trim()
                        .toLowerCase()
                    : "";


            const statusValue =
                statusFilter
                    ? statusFilter.value
                    : "all";


            const areaValue =
                areaFilter
                    ? areaFilter.value
                    : "all";


            let visibleRows = 0;


            rows.forEach(
                function (row) {

                    const id =
                        (
                            row.dataset.id || ""
                        ).toLowerCase();


                    const seller =
                        (
                            row.dataset.seller || ""
                        ).toLowerCase();


                    const area =
                        (
                            row.dataset.area || ""
                        ).toLowerCase();


                    const status =
                        (
                            row.dataset.status || ""
                        ).toLowerCase();


                    const matchesSearch =
                        !searchValue
                        ||
                        id.includes(
                            searchValue
                        )
                        ||
                        seller.includes(
                            searchValue
                        )
                        ||
                        area.includes(
                            searchValue
                        );


                    const matchesStatus =
                        statusValue === "all"
                        ||
                        status === statusValue;


                    const matchesArea =
                        areaValue === "all"
                        ||
                        area === areaValue;


                    const shouldShow =
                        matchesSearch
                        &&
                        matchesStatus
                        &&
                        matchesArea;


                    row.classList.toggle(
                        "hidden-request",
                        !shouldShow
                    );


                    if (shouldShow) {

                        visibleRows++;

                    }

                }
            );


            if (visibleRequestCount) {

                visibleRequestCount.textContent =
                    visibleRows;

            }


            if (emptyState) {

                emptyState.classList.toggle(
                    "show",
                    visibleRows === 0
                );

            }

        }



        if (searchInput) {

            searchInput.addEventListener(
                "input",
                filterRequests
            );

        }


        if (statusFilter) {

            statusFilter.addEventListener(
                "change",
                filterRequests
            );

        }


        if (areaFilter) {

            areaFilter.addEventListener(
                "change",
                filterRequests
            );

        }



        /* =================================================
           OPEN DRAWER
           ================================================= */

        function openRequestDrawer(row) {

            if (!row) {
                return;
            }


            activeRow = row;


            const requestId =
                row.dataset.id;


            const seller =
                row.dataset.seller;


            const area =
                row.dataset.area;


            const status =
                row.dataset.status;


            const barangayElement =
                row.querySelector(
                    ".address-secondary"
                );


            const parcelCell =
                row.children[3];


            const parcelCount =
                parcelCell
                    ? parcelCell.textContent.trim()
                    : "0";


            if (drawerRequestId) {

                drawerRequestId.textContent =
                    requestId;

            }


            if (drawerSeller) {

                drawerSeller.textContent =
                    seller;

            }


            if (drawerAddress) {

                drawerAddress.textContent =
                    area + ", Laguna";

            }


            if (drawerBarangay) {

                drawerBarangay.textContent =
                    barangayElement
                        ? barangayElement.textContent.trim()
                        : "";

            }


            if (drawerParcelCount) {

                drawerParcelCount.textContent =
                    parcelCount;

            }


            updateDrawerStatus(
                status
            );


            if (drawer) {

                drawer.classList.add(
                    "open"
                );


                drawer.setAttribute(
                    "aria-hidden",
                    "false"
                );

            }


            if (drawerOverlay) {

                drawerOverlay.classList.add(
                    "open"
                );

            }


            document.body.style.overflow =
                "hidden";

        }



        /* =================================================
           CLOSE DRAWER
           ================================================= */

        function closeRequestDrawer() {

            if (drawer) {

                drawer.classList.remove(
                    "open"
                );


                drawer.setAttribute(
                    "aria-hidden",
                    "true"
                );

            }


            if (drawerOverlay) {

                drawerOverlay.classList.remove(
                    "open"
                );

            }


            document.body.style.overflow =
                "";


            if (riderSelect) {

                riderSelect.value = "";

            }

        }



        document
            .querySelectorAll(
                ".review-request-button"
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        "click",
                        function () {

                            const row =
                                button.closest(
                                    ".pickup-request-row"
                                );


                            openRequestDrawer(
                                row
                            );

                        }
                    );

                }
            );


        if (drawerCloseButton) {

            drawerCloseButton.addEventListener(
                "click",
                closeRequestDrawer
            );

        }


        if (drawerOverlay) {

            drawerOverlay.addEventListener(
                "click",
                closeRequestDrawer
            );

        }



        /* =================================================
           DRAWER STATUS
           ================================================= */

        function updateDrawerStatus(status) {

            if (!drawerStatus) {
                return;
            }


            drawerStatus.className =
                "pickup-status "
                +
                status;


            const statusText = {

                pending:
                    "Pending",

                approved:
                    "Approved",

                assigned:
                    "Assigned",

                rejected:
                    "Rejected"

            };


            drawerStatus.textContent =
                statusText[status]
                ||
                status;


            /*
             * Pending:
             * Logistics can approve/reject.
             */

            if (status === "pending") {

                if (drawerActions) {

                    drawerActions.classList.remove(
                        "hidden"
                    );

                }


                if (riderAssignmentSection) {

                    riderAssignmentSection
                        .classList
                        .remove(
                            "show"
                        );

                }


                if (reviewTimelineEntry) {

                    reviewTimelineEntry
                        .classList
                        .remove(
                            "completed"
                        );

                }


                if (reviewTimelineTitle) {

                    reviewTimelineTitle.textContent =
                        "Waiting for logistics review";

                }


                if (
                    reviewTimelineDescription
                ) {

                    reviewTimelineDescription
                        .textContent =
                        "Approve or reject this pickup request.";

                }

            }


            /*
             * Approved:
             * Logistics can assign rider.
             */

            if (status === "approved") {

                if (drawerActions) {

                    drawerActions.classList.add(
                        "hidden"
                    );

                }


                if (riderAssignmentSection) {

                    riderAssignmentSection
                        .classList
                        .add(
                            "show"
                        );

                }


                if (reviewTimelineEntry) {

                    reviewTimelineEntry
                        .classList
                        .add(
                            "completed"
                        );

                }


                if (reviewTimelineTitle) {

                    reviewTimelineTitle.textContent =
                        "Pickup request approved";

                }


                if (
                    reviewTimelineDescription
                ) {

                    reviewTimelineDescription
                        .textContent =
                        "Select an available rider for parcel collection.";

                }

            }


            /*
             * Assigned / rejected:
             * No action controls.
             */

            if (
                status === "assigned"
                ||
                status === "rejected"
            ) {

                if (drawerActions) {

                    drawerActions.classList.add(
                        "hidden"
                    );

                }


                if (riderAssignmentSection) {

                    riderAssignmentSection
                        .classList
                        .remove(
                            "show"
                        );

                }


                if (reviewTimelineEntry) {

                    reviewTimelineEntry
                        .classList
                        .add(
                            "completed"
                        );

                }


                if (reviewTimelineTitle) {

                    reviewTimelineTitle.textContent =
                        status === "assigned"
                            ? "Pickup rider assigned"
                            : "Pickup request rejected";

                }


                if (
                    reviewTimelineDescription
                ) {

                    reviewTimelineDescription
                        .textContent =
                        status === "assigned"
                            ? "The pickup task is ready for the rider."
                            : "The seller pickup request was rejected.";

                }

            }

        }



        /* =================================================
           APPROVE
           ================================================= */

        if (approveButton) {

            approveButton.addEventListener(
                "click",
                function () {

                    if (!activeRow) {
                        return;
                    }


                    activeRow.dataset.status =
                        "approved";


                    const badge =
                        activeRow.querySelector(
                            ".pickup-status"
                        );


                    if (badge) {

                        badge.className =
                            "pickup-status approved";


                        badge.textContent =
                            "Approved";

                    }


                    const actionButton =
                        activeRow.querySelector(
                            ".review-request-button"
                        );


                    if (actionButton) {

                        actionButton.textContent =
                            "Assign";

                    }


                    updateDrawerStatus(
                        "approved"
                    );


                    updateStatistics();


                    showToast(
                        "Pickup approved",
                        activeRow.dataset.id
                        +
                        " is ready for rider assignment."
                    );

                }
            );

        }



        /* =================================================
           OPEN REJECTION MODAL
           ================================================= */

        if (rejectButton) {

            rejectButton.addEventListener(
                "click",
                function () {

                    if (rejectionModal) {

                        rejectionModal
                            .classList
                            .add(
                                "open"
                            );

                    }

                }
            );

        }



        function closeRejectionModal() {

            if (rejectionModal) {

                rejectionModal
                    .classList
                    .remove(
                        "open"
                    );

            }


            if (rejectionReason) {

                rejectionReason.value =
                    "";

            }

        }



        if (cancelRejectionButton) {

            cancelRejectionButton
                .addEventListener(
                    "click",
                    closeRejectionModal
                );

        }



        /* =================================================
           CONFIRM REJECTION
           ================================================= */

        if (confirmRejectionButton) {

            confirmRejectionButton
                .addEventListener(
                    "click",
                    function () {

                        if (!activeRow) {
                            return;
                        }


                        const reason =
                            rejectionReason
                                ? rejectionReason
                                    .value
                                    .trim()
                                : "";


                        if (!reason) {

                            if (rejectionReason) {

                                rejectionReason.focus();

                            }

                            return;

                        }


                        activeRow.dataset.status =
                            "rejected";


                        const badge =
                            activeRow.querySelector(
                                ".pickup-status"
                            );


                        if (badge) {

                            badge.className =
                                "pickup-status rejected";


                            badge.textContent =
                                "Rejected";

                        }


                        const actionButton =
                            activeRow.querySelector(
                                ".review-request-button"
                            );


                        if (actionButton) {

                            actionButton.textContent =
                                "Details";

                        }


                        updateDrawerStatus(
                            "rejected"
                        );


                        updateStatistics();


                        closeRejectionModal();


                        showToast(
                            "Request rejected",
                            activeRow.dataset.id
                            +
                            " has been marked as rejected."
                        );

                    }
                );

        }



        /* =================================================
           ASSIGN RIDER
           ================================================= */

        if (assignRiderButton) {

            assignRiderButton.addEventListener(
                "click",
                function () {

                    if (
                        !activeRow
                        ||
                        !riderSelect
                    ) {
                        return;
                    }


                    const rider =
                        riderSelect.value;


                    if (!rider) {

                        riderSelect.focus();

                        return;

                    }


                    activeRow.dataset.status =
                        "assigned";


                    const badge =
                        activeRow.querySelector(
                            ".pickup-status"
                        );


                    if (badge) {

                        badge.className =
                            "pickup-status assigned";


                        badge.textContent =
                            "Assigned";

                    }


                    const riderCell =
                        activeRow.children[6];


                    if (riderCell) {

                        const initials =
                            rider
                                .split(" ")
                                .map(
                                    function (name) {

                                        return name[0];

                                    }
                                )
                                .join("")
                                .substring(0, 2)
                                .toUpperCase();


                        riderCell.innerHTML =
                            `
                            <div class="assigned-rider">

                                <span>
                                    ${initials}
                                </span>

                                <strong>
                                    ${rider}
                                </strong>

                            </div>
                            `;

                    }


                    const actionButton =
                        activeRow.querySelector(
                            ".review-request-button"
                        );


                    if (actionButton) {

                        actionButton.textContent =
                            "Details";

                    }


                    updateDrawerStatus(
                        "assigned"
                    );


                    updateStatistics();


                    showToast(
                        "Rider assigned",
                        rider
                        +
                        " has been assigned to "
                        +
                        activeRow.dataset.id
                        +
                        "."
                    );

                }
            );

        }



        /* =================================================
           STATISTICS
           ================================================= */

        function updateStatistics() {

            let pending = 0;
            let approved = 0;
            let assigned = 0;


            rows.forEach(
                function (row) {

                    if (
                        row.dataset.status
                        ===
                        "pending"
                    ) {

                        pending++;

                    }


                    if (
                        row.dataset.status
                        ===
                        "approved"
                    ) {

                        approved++;

                    }


                    if (
                        row.dataset.status
                        ===
                        "assigned"
                    ) {

                        assigned++;

                    }

                }
            );


            const pendingCount =
                document.getElementById(
                    "pendingCount"
                );


            const approvedCount =
                document.getElementById(
                    "approvedCount"
                );


            const assignedCount =
                document.getElementById(
                    "assignedCount"
                );


            if (pendingCount) {

                pendingCount.textContent =
                    pending;

            }


            if (approvedCount) {

                approvedCount.textContent =
                    approved;

            }


            if (assignedCount) {

                assignedCount.textContent =
                    assigned;

            }


            filterRequests();

        }



        /* =================================================
           TOAST
           ================================================= */

        function showToast(
            title,
            message
        ) {

            if (
                !toast
                ||
                !toastTitle
                ||
                !toastMessage
            ) {
                return;
            }


            toastTitle.textContent =
                title;


            toastMessage.textContent =
                message;


            toast.classList.add(
                "show"
            );


            if (toastTimeout) {

                clearTimeout(
                    toastTimeout
                );

            }


            toastTimeout =
                setTimeout(
                    function () {

                        toast.classList.remove(
                            "show"
                        );

                    },
                    3000
                );

        }



        /* =================================================
           REFRESH
           ================================================= */

        if (refreshButton) {

            refreshButton.addEventListener(
                "click",
                function () {

                    refreshButton.disabled =
                        true;


                    const oldContent =
                        refreshButton.innerHTML;


                    refreshButton.textContent =
                        "Refreshing...";


                    setTimeout(
                        function () {

                            refreshButton.innerHTML =
                                oldContent;


                            refreshButton.disabled =
                                false;


                            filterRequests();


                            showToast(
                                "Pickup queue refreshed",
                                "The latest pickup requests are displayed."
                            );

                        },
                        550
                    );

                }
            );

        }



        /* =================================================
           ESCAPE KEY
           ================================================= */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key !== "Escape"
                ) {
                    return;
                }


                if (
                    rejectionModal
                    &&
                    rejectionModal
                        .classList
                        .contains(
                            "open"
                        )
                ) {

                    closeRejectionModal();

                    return;

                }


                closeRequestDrawer();

            }
        );



        /* =================================================
           INITIAL
           ================================================= */

        updateStatistics();


    }
);