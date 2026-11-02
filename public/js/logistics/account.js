
/* =====================================================
   LUMEAR LOGISTICS — MY ACCOUNT
   Frontend demo: profile data stored in localStorage
   Passwords are NEVER stored.
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    if (!document.getElementById("logisticsAccountApp")) return;

    const $ = id => document.getElementById(id);
    const STORAGE_KEY = "lumear_logistics_account_demo_v1";
    const ACTIVITY_KEY = "lumear_logistics_account_activity_v1";

    const defaultProfile = {
        firstName: "Maria",
        lastName: "Santos",
        middleInitial: "L",
        sex: "Female",
        birthday: "1998-05-15",
        email: "logistics@example.test",
        phone: "09170000000",
        businessName: "Santa Cruz Sorting Center",
        province: "Laguna",
        municipality: "Santa Cruz",
        barangay: "Poblacion I",
        postalCode: "4009",
        street: "Demo Building, Main Street"
    };

    function readStorage(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (error) {
            return fallback;
        }
    }

    let profile = {
        ...defaultProfile,
        ...readStorage(STORAGE_KEY, {})
    };

    let activities = readStorage(ACTIVITY_KEY, []);

    if (!Array.isArray(activities)) activities = [];

    let toastTimer = null;

    function saveProfile() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
            return true;
        } catch (error) {
            toast("Unable to save account information.", true);
            return false;
        }
    }

    function saveActivities() {
        try {
            localStorage.setItem(ACTIVITY_KEY, JSON.stringify(activities));
        } catch (error) {
            console.warn("Unable to save demo activity.", error);
        }
    }

    function toast(message, error = false) {
        const node = $("laToast");

        node.textContent = message;
        node.classList.toggle("error", error);
        node.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            node.classList.remove("show");
        }, 3200);
    }

    function create(tag, className = "", text = "") {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== "") node.textContent = String(text);
        return node;
    }

    function addActivity(title, description) {
        activities.unshift({
            id: `activity-${Date.now()}`,
            title,
            description,
            time: new Date().toISOString()
        });

        activities = activities.slice(0, 30);
        saveActivities();
        renderActivity();
    }

    function ageFromBirthday(birthday) {
        if (!birthday) return "";

        const birth = new Date(`${birthday}T12:00:00`);
        if (Number.isNaN(birth.getTime())) return "";

        const now = new Date();
        let age = now.getFullYear() - birth.getFullYear();

        const monthDiff = now.getMonth() - birth.getMonth();

        if (monthDiff < 0 ||
            (monthDiff === 0 && now.getDate() < birth.getDate())) {
            age--;
        }

        return age >= 0 && age <= 120 ? age : "";
    }

    function updateAge() {
        $("laAge").value = ageFromBirthday($("laBirthday").value);
    }

    function renderBanner() {
        $("laProfileName").textContent = profile.businessName;
        $("laProfileEmail").textContent = profile.email;
        $("laProfileArea").textContent =
            `${profile.municipality}, ${profile.province}`;

        $("laAvatar").textContent = profile.businessName
            .split(/\s+/)
            .slice(0, 2)
            .map(word => word[0]?.toUpperCase() || "")
            .join("");
    }

    function populateForms() {
        const mapping = {
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
            laStreet: "street"
        };

        Object.entries(mapping).forEach(([id, key]) => {
            $(id).value = profile[key] || "";
        });

        updateAge();
        renderBanner();
    }

    function switchTab(tab) {
        document.querySelectorAll("[data-la-tab]").forEach(button => {
            button.classList.toggle(
                "active",
                button.dataset.laTab === tab
            );
        });

        document.querySelectorAll(".la-section").forEach(section => {
            section.classList.toggle(
                "active",
                section.id === `la-tab-${tab}`
            );
        });
    }

    document.querySelectorAll("[data-la-tab]").forEach(button => {
        button.addEventListener("click", () => {
            switchTab(button.dataset.laTab);
        });
    });

    $("laBirthday").max = new Date().toLocaleDateString("en-CA");

    $("laBirthday").addEventListener("change", updateAge);

    $("laPersonalForm").addEventListener("submit", event => {
        event.preventDefault();

        if (!$("laPersonalForm").reportValidity()) return;

        if (ageFromBirthday($("laBirthday").value) === "") {
            toast("Please enter a valid birthday.", true);
            return;
        }

        const updated = {
            ...profile,
            firstName: $("laFirstName").value.trim(),
            lastName: $("laLastName").value.trim(),
            middleInitial: $("laMiddleInitial").value.trim(),
            sex: $("laSex").value,
            birthday: $("laBirthday").value,
            email: $("laEmail").value.trim(),
            phone: $("laPhone").value.trim()
        };

        profile = updated;

        if (!saveProfile()) return;

        renderBanner();
        addActivity(
            "Personal Information Updated",
            "The account holder's personal information was updated."
        );

        toast("Personal information saved successfully.");
    });

    $("laBusinessForm").addEventListener("submit", event => {
        event.preventDefault();

        if (!$("laBusinessForm").reportValidity()) return;

        profile = {
            ...profile,
            businessName: $("laBusinessName").value.trim(),
            barangay: $("laBarangay").value.trim(),
            postalCode: $("laPostalCode").value.trim(),
            street: $("laStreet").value.trim()
        };

        if (!saveProfile()) return;

        renderBanner();
        addActivity(
            "Business Information Updated",
            "The sorting center's business details were updated."
        );

        toast("Business information saved successfully.");
    });

    function updatePasswordRequirements() {
        const password = $("laNewPassword").value;
        const confirm = $("laConfirmPassword").value;

        const checks = {
            laReqLength: password.length >= 8,
            laReqUpper: /[A-Z]/.test(password),
            laReqLower: /[a-z]/.test(password),
            laReqNumber: /[0-9]/.test(password),
            laReqMatch: password.length > 0 && password === confirm
        };

        Object.entries(checks).forEach(([id, valid]) => {
            const node = $(id);
            node.classList.toggle("valid", valid);
            node.textContent =
                `${valid ? "✓" : "○"} ${node.textContent.slice(2)}`;
        });

        return Object.values(checks).every(Boolean);
    }

    $("laNewPassword").addEventListener(
        "input", updatePasswordRequirements
    );

    $("laConfirmPassword").addEventListener(
        "input", updatePasswordRequirements
    );

    $("laPasswordForm").addEventListener("submit", event => {
        event.preventDefault();

        if (!$("laPasswordForm").reportValidity()) return;

        if (!updatePasswordRequirements()) {
            toast("Please meet all password requirements.", true);
            return;
        }

        // No password is saved or changed.
        $("laPasswordForm").reset();
        updatePasswordRequirements();

        toast("Password format validated. No real password was changed.");
    });

    function renderActivity() {
        const container = $("laActivityList");
        container.replaceChildren();

        if (!activities.length) {
            container.appendChild(
                create(
                    "div",
                    "la-empty",
                    "No account activity recorded in this browser yet."
                )
            );
            return;
        }

        activities.forEach(activity => {
            const item = create("div", "la-activity-item");
            const icon = create("div", "la-activity-icon", "◷");
            const content = create("div");

            content.appendChild(
                create("strong", "", activity.title)
            );

            content.appendChild(
                create("p", "", activity.description)
            );

            const time = create(
                "time",
                "",
                new Date(activity.time).toLocaleString("en-PH", {
                    dateStyle: "medium",
                    timeStyle: "short"
                })
            );

            content.appendChild(time);
            item.append(icon, content);
            container.appendChild(item);
        });
    }

    $("laClearActivity").addEventListener("click", () => {
        if (!activities.length) {
            toast("There is no demo activity to clear.");
            return;
        }

        if (!confirm("Clear all demo account activity?")) return;

        activities = [];
        saveActivities();
        renderActivity();
        toast("Demo account activity cleared.");
    });

    window.addEventListener("storage", event => {
        if (event.key === STORAGE_KEY) {
            profile = {
                ...defaultProfile,
                ...readStorage(STORAGE_KEY, {})
            };
            populateForms();
        }

        if (event.key === ACTIVITY_KEY) {
            activities = readStorage(ACTIVITY_KEY, []);
            if (!Array.isArray(activities)) activities = [];
            renderActivity();
        }
    });

    populateForms();
    renderActivity();
    updatePasswordRequirements();
});
