/* =====================================================
   LUMEAR LOGISTICS — MY ACCOUNT
   Live Laravel + MySQL Integration
   No demo profiles or localStorage
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    if (!document.getElementById("logisticsAccountApp")) return;

    const $ = id => document.getElementById(id);

    const API = "/logistics/api/account";

    let profile = null;
    let toastTimer = null;

    const fields = {
        laFirstName: "firstName",
        laLastName: "lastName",
        laMiddleInitial: "middleInitial",
        laSex: "sex",
        laBirthday: "birthday",
        laEmail: "email",
        laPhone: "phone",
        laBusinessName: "businessName",
        laProvince: "province",
        laMunicipality: "municipality",
        laBarangay: "barangay",
        laPostalCode: "postalCode",
        laHouseNumber: "houseNumber",
        laStreet: "street",
        laSEC: "secNumber",
        laTIN: "tin"
    };

    function toast(message, error = false) {
        const element = $("laToast");

        if (!element) return;

        element.textContent = message;
        element.classList.toggle("error", error);
        element.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 3500);
    }

    function create(tag, className = "", content = "") {
        const element = document.createElement(tag);

        if (className) element.className = className;
        if (content !== "") element.textContent = String(content);

        return element;
    }

    function getCsrfToken() {
        return document.querySelector(
            'meta[name="csrf-token"]'
        )?.content || "";
    }

    async function request(path = "", method = "GET", data = null) {
        const headers = {
            Accept: "application/json"
        };

        if (method !== "GET") {
            const token = getCsrfToken();

            if (!token) {
                throw new Error(
                    "CSRF token missing. Check your Logistics layout."
                );
            }

            headers["Content-Type"] = "application/json";
            headers["X-CSRF-TOKEN"] = token;
        }

        const response = await fetch(`${API}${path}`, {
            method,
            credentials: "same-origin",
            headers,
            ...(data !== null
                ? { body: JSON.stringify(data) }
                : {})
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const details = result.errors
                ? Object.values(result.errors).flat().join(" ")
                : null;

            throw new Error(
                details ||
                result.message ||
                `Request failed (${response.status}).`
            );
        }

        return result;
    }

    function ageFromBirthday(birthday) {
        if (!birthday) return "";

        const birth = new Date(`${birthday}T12:00:00`);

        if (Number.isNaN(birth.getTime())) return "";

        const today = new Date();

        let age = today.getFullYear() - birth.getFullYear();

        const monthDifference =
            today.getMonth() - birth.getMonth();

        if (
            monthDifference < 0 ||
            (
                monthDifference === 0 &&
                today.getDate() < birth.getDate()
            )
        ) {
            age--;
        }

        return age >= 0 && age <= 120 ? age : "";
    }

    function updateAge() {
        $("laAge").value = ageFromBirthday(
            $("laBirthday").value
        );
    }

    function renderBanner() {
        if (!profile) return;

        $("laProfileName").textContent =
            profile.businessName || "Logistics Provider";

        $("laProfileEmail").textContent =
            profile.email || "—";

        $("laProfileArea").textContent = [
            profile.municipality,
            profile.province
        ].filter(Boolean).join(", ") || "—";

        $("laAccountId").textContent =
            profile.accountId || "—";

        const status = profile.status || "unknown";

        $("laAccountStatus").textContent =
            status.charAt(0).toUpperCase() +
            status.slice(1) +
            " Account";

        $("laHeaderStatus").textContent =
            status.toUpperCase();

        const words = String(
            profile.businessName || "LUMEAR"
        ).trim().split(/\s+/);

        $("laAvatar").textContent = words
            .slice(0, 2)
            .map(word => word.charAt(0).toUpperCase())
            .join("");
    }

    function renderDocuments() {
        const documents = profile?.documents || {};

        const items = [
            {
                data: documents.validId,
                status: $("laValidIdStatus"),
                link: $("laValidIdLink")
            },
            {
                data: documents.businessPermit,
                status: $("laPermitStatus"),
                link: $("laPermitLink")
            }
        ];

        items.forEach(item => {
            const available = Boolean(item.data?.available);

            item.status.textContent = available
                ? "Registration document available"
                : "No document on record";

            item.link.hidden = !available;

            if (available) {
                item.link.href = item.data.url;
            } else {
                item.link.removeAttribute("href");
            }
        });
    }

    function renderEmailVerification() {
        const verified = Boolean(profile?.emailVerified);

        $("laEmailVerification").textContent = verified
            ? "Your registered email address has been verified."
            : "Your email address is not verified. Contact the administrator for verification assistance.";

        $("laEmailVerificationTag").textContent = verified
            ? "Verified Email"
            : "Verification Required";
    }

    function populateForms() {
        if (!profile) return;

        Object.entries(fields).forEach(([id, key]) => {
            const element = $(id);

            if (element) {
                element.value = profile[key] ?? "";
            }
        });

        updateAge();
        renderBanner();
        renderDocuments();
        renderEmailVerification();
    }

    async function loadAccount() {
        try {
            const result = await request();

            profile = result.profile;

            populateForms();
        } catch (error) {
            toast(error.message, true);

            $("laProfileName").textContent =
                "Unable to load account";
        }
    }

    function switchTab(tab) {
        document.querySelectorAll("[data-la-tab]")
            .forEach(button => {
                button.classList.toggle(
                    "active",
                    button.dataset.laTab === tab
                );
            });

        document.querySelectorAll(".la-section")
            .forEach(section => {
                section.classList.toggle(
                    "active",
                    section.id === `la-tab-${tab}`
                );
            });

        if (tab === "activity") {
            loadActivity();
        }
    }

    document.querySelectorAll("[data-la-tab]")
        .forEach(button => {
            button.addEventListener("click", () => {
                switchTab(button.dataset.laTab);
            });
        });

    // Set birthday maximum to today's local date.
    function localToday() {
        const now = new Date();

        return [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, "0"),
            String(now.getDate()).padStart(2, "0")
        ].join("-");
    }

    $("laBirthday").max = localToday();

    $("laBirthday").addEventListener(
        "change",
        updateAge
    );

    async function submitForm(form, path, data) {
        const button = form.querySelector(
            'button[type="submit"]'
        );

        const previousText = button.textContent;

        button.disabled = true;
        button.textContent = "Saving...";

        try {
            const result = await request(
                path,
                "PUT",
                data
            );

            await loadAccount();
            await loadActivity();

            toast(result.message || "Changes saved successfully.");

            return true;
        } catch (error) {
            toast(error.message, true);
            return false;
        } finally {
            button.disabled = false;
            button.textContent = previousText;
        }
    }

    /* PERSONAL INFORMATION */

    $("laPersonalForm").addEventListener(
        "submit",
        async event => {
            event.preventDefault();

            const form = event.currentTarget;

            if (!form.reportValidity()) return;

            const age = ageFromBirthday(
                $("laBirthday").value
            );

            if (age === "" || age < 18) {
                toast(
                    "Account holder must be at least 18 years old.",
                    true
                );
                return;
            }

            const phone = $("laPhone").value.trim();

            if (!/^(09[0-9]{9}|\+639[0-9]{9})$/.test(phone)) {
                toast(
                    "Enter 09XXXXXXXXX or +639XXXXXXXXX.",
                    true
                );
                return;
            }

            await submitForm(
                form,
                "/personal",
                {
                    first_name: $("laFirstName").value.trim(),
                    last_name: $("laLastName").value.trim(),
                    middle_initial:
                        $("laMiddleInitial").value.trim(),
                    sex: $("laSex").value,
                    birthday: $("laBirthday").value,
                    phone
                }
            );
        }
    );

    /* BUSINESS INFORMATION */

    $("laBusinessForm").addEventListener(
        "submit",
        async event => {
            event.preventDefault();

            const form = event.currentTarget;

            if (!form.reportValidity()) return;

            await submitForm(
                form,
                "/business",
                {
                    business_name:
                        $("laBusinessName").value.trim(),
                    barangay:
                        $("laBarangay").value.trim(),
                    postal_code:
                        $("laPostalCode").value.trim(),
                    house_number:
                        $("laHouseNumber").value.trim(),
                    street:
                        $("laStreet").value.trim()
                }
            );
        }
    );

    /* PASSWORD REQUIREMENTS */

    const requirements = [
        {
            id: "laReqLength",
            text: "At least 8 characters",
            test: password => password.length >= 8
        },
        {
            id: "laReqUpper",
            text: "At least one uppercase letter",
            test: password => /[A-Z]/.test(password)
        },
        {
            id: "laReqLower",
            text: "At least one lowercase letter",
            test: password => /[a-z]/.test(password)
        },
        {
            id: "laReqNumber",
            text: "At least one number",
            test: password => /[0-9]/.test(password)
        },
        {
            id: "laReqSpecial",
            text: "At least one special character",
            test: password => /[^A-Za-z0-9]/.test(password)
        },
        {
            id: "laReqMatch",
            text: "Passwords match",
            test: password =>
                password.length > 0 &&
                password === $("laConfirmPassword").value
        }
    ];

    function updatePasswordRequirements() {
        const password = $("laNewPassword").value;

        const results = requirements.map(requirement => {
            const valid = requirement.test(password);
            const element = $(requirement.id);

            if (element) {
                element.classList.toggle("valid", valid);
                element.textContent =
                    `${valid ? "✓" : "○"} ${requirement.text}`;
            }

            return valid;
        });

        return results.every(Boolean);
    }

    $("laNewPassword").addEventListener(
        "input",
        updatePasswordRequirements
    );

    $("laConfirmPassword").addEventListener(
        "input",
        updatePasswordRequirements
    );

    /* CHANGE REAL LARAVEL PASSWORD */

    $("laPasswordForm").addEventListener(
        "submit",
        async event => {
            event.preventDefault();

            const form = event.currentTarget;

            if (!form.reportValidity()) return;

            if (!updatePasswordRequirements()) {
                toast(
                    "Please meet all password requirements.",
                    true
                );
                return;
            }

            const currentPassword =
                $("laCurrentPassword").value;

            const newPassword =
                $("laNewPassword").value;

            const confirmation =
                $("laConfirmPassword").value;

            const saved = await submitForm(
                form,
                "/password",
                {
                    current_password: currentPassword,
                    password: newPassword,
                    password_confirmation: confirmation
                }
            );

            if (saved) {
                form.reset();
                updatePasswordRequirements();
            }
        }
    );

    /* ACCOUNT ACTIVITY */

    async function loadActivity() {
        const container = $("laActivityList");

        if (!container) return;

        try {
            const result = await request("/activity");

            const activities = Array.isArray(result.activities)
                ? result.activities
                : [];

            container.replaceChildren();

            if (!activities.length) {
                container.appendChild(
                    create(
                        "div",
                        "la-empty",
                        "No account changes have been recorded yet."
                    )
                );
                return;
            }

            const titles = {
                personal_updated: "Personal Information Updated",
                business_updated: "Business Information Updated",
                password_changed: "Password Changed"
            };

            activities.forEach(activity => {
                const item = create(
                    "div",
                    "la-activity-item"
                );

                const icon = create(
                    "div",
                    "la-activity-icon",
                    "◷"
                );

                const content = create("div");

                content.appendChild(
                    create(
                        "strong",
                        "",
                        titles[activity.action] ||
                            "Account Activity"
                    )
                );

                content.appendChild(
                    create(
                        "p",
                        "",
                        activity.description
                    )
                );

                const time = create("time");

                const timestamp = new Date(
                    String(activity.created_at)
                        .replace(" ", "T")
                );

                time.textContent = Number.isNaN(
                    timestamp.getTime()
                )
                    ? String(activity.created_at)
                    : timestamp.toLocaleString(
                        "en-PH",
                        {
                            dateStyle: "medium",
                            timeStyle: "short"
                        }
                    );

                content.appendChild(time);

                item.append(icon, content);
                container.appendChild(item);
            });

        } catch (error) {
            container.replaceChildren(
                create(
                    "div",
                    "la-empty",
                    "Unable to load account activity."
                )
            );

            toast(error.message, true);
        }
    }

    /* INITIAL LOAD */

    updatePasswordRequirements();
    loadAccount();
});
