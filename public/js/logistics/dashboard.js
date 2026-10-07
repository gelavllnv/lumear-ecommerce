/* =========================================================
   LUMEAR LOGISTICS ERP
   DASHBOARD JAVASCRIPT
   ========================================================= */


document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           ELEMENTS
           ================================================= */

        const sidebar =
            document.getElementById(
                "erpSidebar"
            );

        const sidebarOverlay =
            document.getElementById(
                "sidebarOverlay"
            );

        const mobileMenuButton =
            document.getElementById(
                "mobileMenuButton"
            );

        const sidebarClose =
            document.getElementById(
                "sidebarClose"
            );

        const riderMenuButton =
            document.getElementById(
                "riderMenuButton"
            );

        const riderSubmenu =
            document.getElementById(
                "riderSubmenu"
            );

        const notificationButton =
            document.getElementById(
                "notificationButton"
            );

        const notificationDropdown =
            document.getElementById(
                "notificationDropdown"
            );

        const profileButton =
            document.getElementById(
                "profileButton"
            );

        const profileDropdown =
            document.getElementById(
                "profileDropdown"
            );

        const currentDate =
            document.getElementById(
                "currentDate"
            );

        const themeToggle =
            document.getElementById(
                "themeToggle"
            );



        /* =================================================
           CHART VARIABLE
           ================================================= */

        let parcelActivityChart = null;



        /* =================================================
           GET CURRENT THEME
           ================================================= */

        function getCurrentTheme() {

            return document
                .documentElement
                .getAttribute(
                    "data-theme"
                )
                ||
                "dark";

        }



        /* =================================================
           CSS VARIABLE HELPER
           ================================================= */

        function getCssVariable(name) {

            return getComputedStyle(
                document.documentElement
            )
                .getPropertyValue(name)
                .trim();

        }



        /* =================================================
           THEME BUTTON
           ================================================= */

        function updateThemeButton() {

            if (!themeToggle) {
                return;
            }


            const currentTheme =
                getCurrentTheme();


            if (currentTheme === "dark") {

                themeToggle.setAttribute(
                    "aria-label",
                    "Switch to light mode"
                );

                themeToggle.setAttribute(
                    "title",
                    "Switch to light mode"
                );

            } else {

                themeToggle.setAttribute(
                    "aria-label",
                    "Switch to dark mode"
                );

                themeToggle.setAttribute(
                    "title",
                    "Switch to dark mode"
                );

            }

        }



        /* =================================================
           CHANGE THEME
           ================================================= */

        function changeTheme() {

            const currentTheme =
                getCurrentTheme();


            const newTheme =
                currentTheme === "dark"
                    ? "light"
                    : "dark";


            document
                .documentElement
                .setAttribute(
                    "data-theme",
                    newTheme
                );


            localStorage.setItem(
                "lumear_logistics_theme",
                newTheme
            );


            updateThemeButton();


            /*
             * Update graph colors after
             * CSS variables change.
             */

            setTimeout(
                updateChartTheme,
                20
            );

        }



        if (themeToggle) {

            themeToggle.addEventListener(
                "click",
                changeTheme
            );

        }


        updateThemeButton();



        /* =================================================
           PARCEL ACTIVITY CHART
           ================================================= */

        function createParcelActivityChart() {

            const canvas =
                document.getElementById(
                    "parcelActivityChart"
                );


            if (
                !canvas ||
                typeof Chart === "undefined"
            ) {

                return;

            }


            const incomingColor =
                getCssVariable(
                    "--chart-incoming"
                );


            const deliveredColor =
                getCssVariable(
                    "--chart-delivered"
                );


            const gridColor =
                getCssVariable(
                    "--chart-grid"
                );


            const textColor =
                getCssVariable(
                    "--text-muted"
                );


            const tooltipBackground =
                getCssVariable(
                    "--dropdown-bg"
                );


            const tooltipText =
                getCssVariable(
                    "--text-primary"
                );


            parcelActivityChart =
                new Chart(
                    canvas,
                    {

                        type: "line",


                        data: {

                            labels: [
                                "Mon",
                                "Tue",
                                "Wed",
                                "Thu",
                                "Fri",
                                "Sat",
                                "Sun"
                            ],


                            datasets: [

                                {
                                    label:
                                        "Incoming",

                                    data: [
                                        18,
                                        24,
                                        21,
                                        31,
                                        27,
                                        35,
                                        29
                                    ],

                                    borderColor:
                                        incomingColor,

                                    backgroundColor:
                                        incomingColor,

                                    borderWidth: 2,

                                    pointRadius: 3,

                                    pointHoverRadius: 5,

                                    pointBackgroundColor:
                                        incomingColor,

                                    pointBorderWidth: 0,

                                    tension: 0.38,

                                    fill: false
                                },


                                {
                                    label:
                                        "Delivered",

                                    data: [
                                        14,
                                        20,
                                        19,
                                        25,
                                        24,
                                        30,
                                        26
                                    ],

                                    borderColor:
                                        deliveredColor,

                                    backgroundColor:
                                        deliveredColor,

                                    borderWidth: 2,

                                    pointRadius: 3,

                                    pointHoverRadius: 5,

                                    pointBackgroundColor:
                                        deliveredColor,

                                    pointBorderWidth: 0,

                                    tension: 0.38,

                                    fill: false
                                }

                            ]

                        },


                        options: {

                            responsive: true,

                            maintainAspectRatio: false,


                            interaction: {

                                intersect: false,

                                mode: "index"

                            },


                            plugins: {

                                legend: {
                                    display: false
                                },


                                tooltip: {

                                    backgroundColor:
                                        tooltipBackground,

                                    titleColor:
                                        tooltipText,

                                    bodyColor:
                                        textColor,

                                    borderColor:
                                        getCssVariable(
                                            "--border"
                                        ),

                                    borderWidth: 1,

                                    padding: 12,

                                    displayColors: true,

                                    usePointStyle: true,

                                    cornerRadius: 8

                                }

                            },


                            scales: {

                                x: {

                                    border: {
                                        display: false
                                    },

                                    grid: {
                                        display: false
                                    },

                                    ticks: {

                                        color:
                                            textColor,

                                        font: {
                                            size: 10
                                        }

                                    }

                                },


                                y: {

                                    beginAtZero: true,


                                    suggestedMax: 40,


                                    border: {
                                        display: false
                                    },


                                    grid: {

                                        color:
                                            gridColor,

                                        drawTicks:
                                            false

                                    },


                                    ticks: {

                                        color:
                                            textColor,

                                        padding: 10,

                                        stepSize: 10,

                                        font: {
                                            size: 10
                                        }

                                    }

                                }

                            }

                        }

                    }
                );

        }



        /* =================================================
           UPDATE CHART WHEN THEME CHANGES
           ================================================= */

        function updateChartTheme() {

            if (!parcelActivityChart) {
                return;
            }


            const incomingColor =
                getCssVariable(
                    "--chart-incoming"
                );


            const deliveredColor =
                getCssVariable(
                    "--chart-delivered"
                );


            const gridColor =
                getCssVariable(
                    "--chart-grid"
                );


            const textColor =
                getCssVariable(
                    "--text-muted"
                );


            const tooltipBackground =
                getCssVariable(
                    "--dropdown-bg"
                );


            const tooltipText =
                getCssVariable(
                    "--text-primary"
                );



            /*
             * Incoming dataset
             */

            parcelActivityChart
                .data
                .datasets[0]
                .borderColor =
                    incomingColor;


            parcelActivityChart
                .data
                .datasets[0]
                .backgroundColor =
                    incomingColor;


            parcelActivityChart
                .data
                .datasets[0]
                .pointBackgroundColor =
                    incomingColor;



            /*
             * Delivered dataset
             */

            parcelActivityChart
                .data
                .datasets[1]
                .borderColor =
                    deliveredColor;


            parcelActivityChart
                .data
                .datasets[1]
                .backgroundColor =
                    deliveredColor;


            parcelActivityChart
                .data
                .datasets[1]
                .pointBackgroundColor =
                    deliveredColor;



            /*
             * Axis colors
             */

            parcelActivityChart
                .options
                .scales
                .x
                .ticks
                .color =
                    textColor;


            parcelActivityChart
                .options
                .scales
                .y
                .ticks
                .color =
                    textColor;


            parcelActivityChart
                .options
                .scales
                .y
                .grid
                .color =
                    gridColor;



            /*
             * Tooltip
             */

            parcelActivityChart
                .options
                .plugins
                .tooltip
                .backgroundColor =
                    tooltipBackground;


            parcelActivityChart
                .options
                .plugins
                .tooltip
                .titleColor =
                    tooltipText;


            parcelActivityChart
                .options
                .plugins
                .tooltip
                .bodyColor =
                    textColor;


            parcelActivityChart
                .options
                .plugins
                .tooltip
                .borderColor =
                    getCssVariable(
                        "--border"
                    );


            parcelActivityChart.update();

        }



        /*
         * Create chart.
         */

        createParcelActivityChart();



        /* =================================================
           MOBILE SIDEBAR
           ================================================= */

        function openSidebar() {

            if (sidebar) {

                sidebar.classList.add(
                    "open"
                );

            }


            if (sidebarOverlay) {

                sidebarOverlay.classList.add(
                    "active"
                );

            }


            document.body.style.overflow =
                "hidden";

        }



        function closeSidebar() {

            if (sidebar) {

                sidebar.classList.remove(
                    "open"
                );

            }


            if (sidebarOverlay) {

                sidebarOverlay.classList.remove(
                    "active"
                );

            }


            document.body.style.overflow =
                "";

        }



        if (mobileMenuButton) {

            mobileMenuButton.addEventListener(
                "click",
                openSidebar
            );

        }


        if (sidebarClose) {

            sidebarClose.addEventListener(
                "click",
                closeSidebar
            );

        }


        if (sidebarOverlay) {

            sidebarOverlay.addEventListener(
                "click",
                closeSidebar
            );

        }



        /* =================================================
           RIDER SUBMENU
           ================================================= */

        if (
            riderMenuButton &&
            riderSubmenu
        ) {

            riderMenuButton.addEventListener(
                "click",
                function () {

                    riderMenuButton
                        .classList
                        .toggle(
                            "open"
                        );


                    riderSubmenu
                        .classList
                        .toggle(
                            "open"
                        );

                }
            );

        }



        /* =================================================
           NOTIFICATIONS
           ================================================= */

        if (
            notificationButton &&
            notificationDropdown
        ) {

            notificationButton
                .addEventListener(
                    "click",
                    function (event) {

                        event.stopPropagation();


                        if (profileDropdown) {

                            profileDropdown
                                .classList
                                .remove(
                                    "open"
                                );

                        }


                        notificationDropdown
                            .classList
                            .toggle(
                                "open"
                            );

                    }
                );

        }



        /* =================================================
           PROFILE
           ================================================= */

        if (
            profileButton &&
            profileDropdown
        ) {

            profileButton.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();


                    if (
                        notificationDropdown
                    ) {

                        notificationDropdown
                            .classList
                            .remove(
                                "open"
                            );

                    }


                    profileDropdown
                        .classList
                        .toggle(
                            "open"
                        );

                }
            );

        }



        /* =================================================
           CLICK OUTSIDE DROPDOWNS
           ================================================= */

        document.addEventListener(
            "click",
            function (event) {


                if (
                    notificationDropdown &&
                    notificationButton
                ) {

                    const wrapper =
                        notificationButton
                            .closest(
                                ".topbar-dropdown-wrapper"
                            );


                    if (
                        wrapper &&
                        !wrapper.contains(
                            event.target
                        )
                    ) {

                        notificationDropdown
                            .classList
                            .remove(
                                "open"
                            );

                    }

                }


                if (
                    profileDropdown &&
                    profileButton
                ) {

                    const wrapper =
                        profileButton
                            .closest(
                                ".topbar-dropdown-wrapper"
                            );


                    if (
                        wrapper &&
                        !wrapper.contains(
                            event.target
                        )
                    ) {

                        profileDropdown
                            .classList
                            .remove(
                                "open"
                            );

                    }

                }


            }
        );



        /* =================================================
           CURRENT DATE
           ================================================= */

        function displayCurrentDate() {

            if (!currentDate) {
                return;
            }


            const today =
                new Date();


            currentDate.textContent =
                today.toLocaleDateString(
                    "en-PH",
                    {
                        weekday:
                            "short",

                        month:
                            "short",

                        day:
                            "numeric",

                        year:
                            "numeric"
                    }
                );

        }


        displayCurrentDate();



        /* =================================================
           ESCAPE KEY
           ================================================= */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key !==
                    "Escape"
                ) {
                    return;
                }


                closeSidebar();


                if (
                    notificationDropdown
                ) {

                    notificationDropdown
                        .classList
                        .remove(
                            "open"
                        );

                }


                if (
                    profileDropdown
                ) {

                    profileDropdown
                        .classList
                        .remove(
                            "open"
                        );

                }

            }
        );



        /* =================================================
           WINDOW RESIZE
           ================================================= */

        window.addEventListener(
            "resize",
            function () {

                if (
                    window.innerWidth > 900
                ) {

                    closeSidebar();

                }

            }
        );


    }
);