/* =========================================================
   LUMEAR LOGISTICS
   AUTHENTICATION + REGISTRATION
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
       2. DEMO LOGISTICS LOGIN
       ===================================================== */

    const loginForm =
        document.getElementById("logisticsLoginForm");


    if (loginForm) {

        loginForm.addEventListener(
            "submit",
            function (event) {

                event.preventDefault();


                const email =
                    document.getElementById(
                        "logisticsEmail"
                    );

                const password =
                    document.getElementById(
                        "logisticsPassword"
                    );

                const button =
                    document.getElementById(
                        "logisticsLoginButton"
                    );


                if (
                    !email.value.trim() ||
                    !password.value.trim()
                ) {
                    return;
                }


                localStorage.setItem(
                    "lumear_logistics_email",
                    email.value.trim()
                );


                if (button) {

                    button.disabled = true;

                    button.innerHTML =
                        "<span>Signing in...</span>";

                }


                setTimeout(function () {

                    window.location.href =
                        loginForm.dataset.dashboardUrl;

                }, 550);

            }
        );

    }



    /* =====================================================
       3. AUTOMATIC AGE
       ===================================================== */

    const birthday =
        document.getElementById("birthday");

    const age =
        document.getElementById("age");


    if (birthday && age) {

        const today =
            new Date();


        const year =
            today.getFullYear();

        const month =
            String(
                today.getMonth() + 1
            ).padStart(2, "0");

        const day =
            String(
                today.getDate()
            ).padStart(2, "0");


        birthday.max =
            `${year}-${month}-${day}`;


        birthday.addEventListener(
            "change",
            function () {

                if (!birthday.value) {

                    age.value = "";

                    return;

                }


                const birthDate =
                    new Date(
                        birthday.value +
                        "T00:00:00"
                    );


                const currentDate =
                    new Date();


                let calculatedAge =
                    currentDate.getFullYear()
                    -
                    birthDate.getFullYear();


                const monthDifference =
                    currentDate.getMonth()
                    -
                    birthDate.getMonth();


                if (
                    monthDifference < 0 ||
                    (
                        monthDifference === 0 &&
                        currentDate.getDate()
                        <
                        birthDate.getDate()
                    )
                ) {

                    calculatedAge--;

                }


                age.value =
                    calculatedAge >= 0
                        ? calculatedAge
                        : "";

            }
        );

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
       5. START ADDRESS SYSTEM
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

    if (provinceSelect) {

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
                            "/logistics/address/provinces/"
                            +
                            encodeURIComponent(
                                provinceCode
                            )
                            +
                            "/cities-municipalities"
                        );


                    fillSelect(
                        municipalitySelect,
                        municipalities,
                        "Select municipality / city"
                    );


                    municipalitySelect.disabled =
                        false;


                } catch (error) {

                    console.error(
                        "Failed to load municipalities:",
                        error
                    );


                    setSelectMessage(
                        municipalitySelect,
                        "Failed to load locations"
                    );


                    municipalitySelect.disabled =
                        false;

                }

            }
        );

    }



    /* =====================================================
       8. MUNICIPALITY / CITY CHANGE
       ===================================================== */

    if (municipalitySelect) {

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
                            "/logistics/address/cities-municipalities/"
                            +
                            encodeURIComponent(
                                municipalityCode
                            )
                            +
                            "/barangays"
                        );


                    fillSelect(
                        barangaySelect,
                        barangays,
                        "Select barangay"
                    );


                    barangaySelect.disabled =
                        false;


                } catch (error) {

                    console.error(
                        "Failed to load barangays:",
                        error
                    );


                    setSelectMessage(
                        barangaySelect,
                        "Failed to load barangays"
                    );


                    barangaySelect.disabled =
                        false;

                }

            }
        );

    }



    /* =====================================================
       9. ADDRESS REQUEST

       IMPORTANT:

       Your Laravel endpoint returns:

       {
           "data": [
               {...},
               {...}
           ]
       }

       This function supports BOTH:

       {
           "data": [...]
       }

       and:

       [...]
       ===================================================== */

    async function requestAddress(url) {

        console.log(
            "Requesting address:",
            url
        );


        const response =
            await fetch(
                url,
                {
                    method: "GET",

                    cache: "no-store",

                    headers: {

                        "Accept":
                            "application/json",

                        "X-Requested-With":
                            "XMLHttpRequest"

                    }

                }
            );


        console.log(
            "Address HTTP status:",
            response.status
        );


        if (!response.ok) {

            const errorBody =
                await response.text();


            console.error(
                "Address server error:",
                errorBody
            );


            throw new Error(
                "HTTP error "
                +
                response.status
            );

        }


        const result =
            await response.json();


        console.log(
            "Address response:",
            result
        );



        /* ---------------------------------------------
           FORMAT 1

           [
               {...},
               {...}
           ]
           --------------------------------------------- */

        if (Array.isArray(result)) {

            return result;

        }



        /* ---------------------------------------------
           FORMAT 2

           {
               "data": [
                   {...},
                   {...}
               ]
           }

           This is the format shown in your screenshot.
           --------------------------------------------- */

        if (
            result &&
            Array.isArray(result.data)
        ) {

            return result.data;

        }



        /* ---------------------------------------------
           Invalid format
           --------------------------------------------- */

        console.error(
            "Unknown PSGC response format:",
            result
        );


        throw new Error(
            "The address API returned an unknown format."
        );

    }



    /* =====================================================
       10. FILL SELECT
       ===================================================== */

    function fillSelect(
        selectElement,
        items,
        placeholder
    ) {

        selectElement.innerHTML = "";


        const placeholderOption =
            document.createElement(
                "option"
            );


        placeholderOption.value = "";

        placeholderOption.textContent =
            placeholder;


        selectElement.appendChild(
            placeholderOption
        );



        /*
         * Make sure we really received
         * an array.
         */

        if (!Array.isArray(items)) {

            console.error(
                "fillSelect received invalid data:",
                items
            );

            return;

        }



        /*
         * Sort alphabetically.
         */

        items.sort(
            function (a, b) {

                const nameA =
                    a.name || "";

                const nameB =
                    b.name || "";


                return nameA.localeCompare(
                    nameB
                );

            }
        );



        /*
         * Add every location.
         */

        items.forEach(
            function (item) {

                if (
                    !item ||
                    !item.code ||
                    !item.name
                ) {
                    return;
                }


                const option =
                    document.createElement(
                        "option"
                    );


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

            }
        );


        console.log(
            "Loaded",
            items.length,
            "items into",
            selectElement.id
        );

    }



    /* =====================================================
       11. SELECT MESSAGE
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
            document.createElement(
                "option"
            );


        option.value = "";

        option.textContent =
            message;


        selectElement.appendChild(
            option
        );

    }



    /* =====================================================
       12. FILE UPLOAD NAME
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
                    input.closest(
                        ".upload-box"
                    );


                if (!uploadBox) {
                    return;
                }


                const fileName =
                    uploadBox.querySelector(
                        ".file-name"
                    );


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
       13. REGISTRATION SUBMISSION
       ===================================================== */

    const registrationForm =
        document.getElementById(
            "logisticsRegistrationForm"
        );


    if (registrationForm) {

        registrationForm.addEventListener(
            "submit",
            function (event) {

                event.preventDefault();


                if (
                    !registrationForm.checkValidity()
                ) {

                    registrationForm.reportValidity();

                    return;

                }



                /* -----------------------------------------
                   Get readable address names
                   ----------------------------------------- */

                const provinceName =
                    provinceSelect
                        ?.selectedOptions[0]
                        ?.dataset.name
                    ||
                    "";


                const municipalityName =
                    municipalitySelect
                        ?.selectedOptions[0]
                        ?.dataset.name
                    ||
                    "";


                const barangayName =
                    barangaySelect
                        ?.selectedOptions[0]
                        ?.dataset.name
                    ||
                    "";



                /* -----------------------------------------
                   Temporary storage
                   ----------------------------------------- */

                localStorage.setItem(
                    "lumear_logistics_province",
                    provinceName
                );


                localStorage.setItem(
                    "lumear_logistics_municipality",
                    municipalityName
                );


                localStorage.setItem(
                    "lumear_logistics_barangay",
                    barangayName
                );



                const button =
                    document.getElementById(
                        "registrationSubmitButton"
                    );


                if (button) {

                    button.disabled = true;


                    button.innerHTML =
                        "<span>Submitting...</span>";

                }



                setTimeout(
                    function () {

                        window.location.href =
                            registrationForm
                                .dataset
                                .pendingUrl;

                    },
                    650
                );

            }
        );

    }


});