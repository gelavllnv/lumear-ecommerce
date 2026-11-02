/* =========================================================
   LUMEAR LOGISTICS
   AUTHENTICATION + REGISTRATION

   Laravel 13 / MySQL
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       1. PASSWORD VISIBILITY
       ===================================================== */

    const passwordButtons =
        document.querySelectorAll(".password-button");

    passwordButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            const targetId =
                button.dataset.passwordTarget;

            const passwordInput =
                document.getElementById(targetId);

            if (!passwordInput) {
                return;
            }

            if (passwordInput.type === "password") {

                passwordInput.type = "text";

                button.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.type = "password";

                button.setAttribute(
                    "aria-label",
                    "Show password"
                );

            }

        });

    });


    /* =====================================================
       2. REAL LOGISTICS LOGIN
       ===================================================== */

    const loginForm =
        document.getElementById("logisticsLoginForm");

    if (loginForm) {

        let isSubmitting = false;

        loginForm.addEventListener("submit", function (event) {

            /*
             * Do not prevent normal form submission.
             * Laravel handles authentication and redirects.
             */

            if (!loginForm.checkValidity()) {

                event.preventDefault();

                loginForm.reportValidity();

                return;
            }

            if (isSubmitting) {

                event.preventDefault();

                return;
            }

            isSubmitting = true;

            const button =
                document.getElementById("logisticsLoginButton");

            if (button) {

                button.disabled = true;

                button.innerHTML =
                    "<span>Signing in...</span>";

            }

            /*
             * The browser now sends the POST request to:
             *
             * route('logistics.login.submit')
             *
             * LogisticsAuthController decides whether to:
             *
             * - Redirect approved providers to dashboard
             * - Redirect pending providers to pending page
             * - Reject unauthorized login attempts
             */

        });

    }


    /* =====================================================
       3. AUTOMATIC AGE CALCULATION
       ===================================================== */

    const birthday =
        document.getElementById("birthday");

    const age =
        document.getElementById("age");

    if (birthday && age) {

        const today = new Date();

        const year = today.getFullYear();

        const month =
            String(today.getMonth() + 1).padStart(2, "0");

        const day =
            String(today.getDate()).padStart(2, "0");

        birthday.max = `${year}-${month}-${day}`;

        function calculateAge() {

            if (!birthday.value) {

                age.value = "";

                return;
            }

            const birthDate =
                new Date(birthday.value + "T00:00:00");

            if (Number.isNaN(birthDate.getTime())) {

                age.value = "";

                return;
            }

            const currentDate = new Date();

            let calculatedAge =
                currentDate.getFullYear() -
                birthDate.getFullYear();

            const monthDifference =
                currentDate.getMonth() -
                birthDate.getMonth();

            if (
                monthDifference < 0 ||
                (
                    monthDifference === 0 &&
                    currentDate.getDate() < birthDate.getDate()
                )
            ) {

                calculatedAge--;

            }

            age.value =
                calculatedAge >= 0
                    ? calculatedAge
                    : "";

        }

        birthday.addEventListener(
            "change",
            calculateAge
        );

        calculateAge();

    }


    /* =====================================================
       4. ADDRESS ELEMENTS
       ===================================================== */

    const provinceSelect =
        document.getElementById("province");

    const municipalitySelect =
        document.getElementById("municipality");

    const barangaySelect =
        document.getElementById("barangay");


    /* =====================================================
       5. INITIALIZE ADDRESS SYSTEM
       ===================================================== */

    if (
        provinceSelect &&
        municipalitySelect &&
        barangaySelect
    ) {

        loadProvinces();

    }


    /* =====================================================
       6. LOAD PROVINCES
       ===================================================== */

    async function loadProvinces() {

        provinceSelect.disabled = true;

        municipalitySelect.disabled = true;

        barangaySelect.disabled = true;

        setSelectMessage(
            provinceSelect,
            "Loading provinces..."
        );

        setSelectMessage(
            municipalitySelect,
            "Select province first"
        );

        setSelectMessage(
            barangaySelect,
            "Select municipality first"
        );

        try {

            const provinces =
                await requestAddress(
                    "/logistics/address/provinces"
                );

            fillSelect(
                provinceSelect,
                provinces,
                "Select province"
            );

            provinceSelect.disabled = false;

        } catch (error) {

            console.error(
                "Failed to load provinces:",
                error
            );

            setSelectMessage(
                provinceSelect,
                "Failed to load provinces"
            );

            provinceSelect.disabled = false;

        }

    }


    /* =====================================================
       7. PROVINCE CHANGE
       ===================================================== */

    if (
        provinceSelect &&
        municipalitySelect &&
        barangaySelect
    ) {

        provinceSelect.addEventListener(
            "change",
            async function () {

                const provinceCode =
                    provinceSelect.value;

                setSelectMessage(
                    municipalitySelect,
                    "Select province first"
                );

                setSelectMessage(
                    barangaySelect,
                    "Select municipality first"
                );

                municipalitySelect.disabled = true;

                barangaySelect.disabled = true;

                if (!provinceCode) {
                    return;
                }

                setSelectMessage(
                    municipalitySelect,
                    "Loading cities / municipalities..."
                );

                try {

                    const municipalities =
                        await requestAddress(
                            "/logistics/address/provinces/" +
                            encodeURIComponent(provinceCode) +
                            "/cities-municipalities"
                        );

                    fillSelect(
                        municipalitySelect,
                        municipalities,
                        "Select municipality / city"
                    );

                    municipalitySelect.disabled = false;

                } catch (error) {

                    console.error(
                        "Failed to load municipalities:",
                        error
                    );

                    setSelectMessage(
                        municipalitySelect,
                        "Failed to load locations"
                    );

                    municipalitySelect.disabled = false;

                }

            }
        );

    }


    /* =====================================================
       8. MUNICIPALITY CHANGE
       ===================================================== */

    if (
        municipalitySelect &&
        barangaySelect
    ) {

        municipalitySelect.addEventListener(
            "change",
            async function () {

                const municipalityCode =
                    municipalitySelect.value;

                setSelectMessage(
                    barangaySelect,
                    "Select municipality first"
                );

                barangaySelect.disabled = true;

                if (!municipalityCode) {
                    return;
                }

                setSelectMessage(
                    barangaySelect,
                    "Loading barangays..."
                );

                try {

                    const barangays =
                        await requestAddress(
                            "/logistics/address/cities-municipalities/" +
                            encodeURIComponent(municipalityCode) +
                            "/barangays"
                        );

                    fillSelect(
                        barangaySelect,
                        barangays,
                        "Select barangay"
                    );

                    barangaySelect.disabled = false;

                } catch (error) {

                    console.error(
                        "Failed to load barangays:",
                        error
                    );

                    setSelectMessage(
                        barangaySelect,
                        "Failed to load barangays"
                    );

                    barangaySelect.disabled = false;

                }

            }
        );

    }


    /* =====================================================
       9. ADDRESS API REQUEST
       ===================================================== */

    async function requestAddress(url) {

        console.log(
            "Requesting address:",
            url
        );

        const response = await fetch(url, {

            method: "GET",

            cache: "no-store",

            headers: {

                "Accept": "application/json",

                "X-Requested-With": "XMLHttpRequest"

            }

        });

        if (!response.ok) {

            const errorBody =
                await response.text();

            console.error(
                "Address server error:",
                errorBody
            );

            throw new Error(
                "Address request failed: HTTP " +
                response.status
            );

        }

        const result =
            await response.json();

        if (Array.isArray(result)) {

            return result;

        }

        if (
            result &&
            Array.isArray(result.data)
        ) {

            return result.data;

        }

        console.error(
            "Unknown PSGC response format:",
            result
        );

        throw new Error(
            "The address API returned an unknown format."
        );

    }


    /* =====================================================
       10. POPULATE ADDRESS SELECT
       ===================================================== */

    function fillSelect(
        selectElement,
        items,
        placeholder
    ) {

        if (!selectElement) {
            return;
        }

        selectElement.innerHTML = "";

        const placeholderOption =
            document.createElement("option");

        placeholderOption.value = "";

        placeholderOption.textContent =
            placeholder;

        selectElement.appendChild(
            placeholderOption
        );

        if (!Array.isArray(items)) {

            console.error(
                "Invalid address data:",
                items
            );

            return;

        }

        const sortedItems = [...items].sort(
            function (a, b) {

                const nameA =
                    String(a?.name || "");

                const nameB =
                    String(b?.name || "");

                return nameA.localeCompare(nameB);

            }
        );

        sortedItems.forEach(function (item) {

            if (
                !item ||
                !item.code ||
                !item.name
            ) {
                return;
            }

            const option =
                document.createElement("option");

            option.value =
                item.code;

            option.textContent =
                item.name;

            option.dataset.name =
                item.name;

            if (item.region) {

                option.dataset.region =
                    item.region;

            }

            if (item.type) {

                option.dataset.type =
                    item.type;

            }

            selectElement.appendChild(
                option
            );

        });

        console.log(
            "Loaded",
            sortedItems.length,
            "locations into",
            selectElement.id
        );

    }


    /* =====================================================
       11. ADDRESS SELECT PLACEHOLDER
       ===================================================== */

    function setSelectMessage(
        selectElement,
        message
    ) {

        if (!selectElement) {
            return;
        }

        selectElement.innerHTML = "";

        const option =
            document.createElement("option");

        option.value = "";

        option.textContent =
            message;

        selectElement.appendChild(
            option
        );

    }


    /* =====================================================
       12. FILE UPLOAD NAME DISPLAY
       ===================================================== */

    const fileInputs =
        document.querySelectorAll(
            ".upload-box input[type='file']"
        );

    fileInputs.forEach(function (input) {

        input.addEventListener(
            "change",
            function () {

                const uploadBox =
                    input.closest(".upload-box");

                if (!uploadBox) {
                    return;
                }

                const fileName =
                    uploadBox.querySelector(".file-name");

                if (!fileName) {
                    return;
                }

                if (
                    input.files &&
                    input.files.length > 0
                ) {

                    fileName.textContent =
                        input.files[0].name;

                } else {

                    fileName.textContent =
                        "JPG, PNG or PDF";

                }

            }
        );

    });


    /* =====================================================
       13. REGISTRATION FORM
       ===================================================== */

    const registrationForm =
        document.getElementById(
            "logisticsRegistrationForm"
        );

    if (registrationForm) {

        /*
         * IMPORTANT:
         *
         * The old version prevented the form from
         * submitting and redirected using a demo URL.
         *
         * This version does not intercept registration.
         *
         * Your registration wizard / OTP script can
         * continue handling its own AJAX submission.
         *
         * If no separate handler exists, the form
         * submits normally to its configured action.
         */

    }

});
