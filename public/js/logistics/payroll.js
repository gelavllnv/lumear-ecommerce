document.addEventListener("DOMContentLoaded", () => {
    const forms = document.querySelectorAll(".rp-generate-form");

    forms.forEach((form) => {
        form.addEventListener("submit", (event) => {
            const confirmed = window.confirm(
                "Generate a draft payroll for this rider and selected period? This does not send a payment."
            );

            if (!confirmed) {
                event.preventDefault();
                return;
            }

            const button = form.querySelector('button[type="submit"]');

            if (button) {
                button.disabled = true;
                button.textContent = "Generating...";
            }
        });
    });

    const from = document.getElementById("rpFrom");
    const to = document.getElementById("rpTo");

    if (from && to) {
        from.addEventListener("change", () => {
            to.min = from.value;

            if (to.value && to.value < from.value) {
                to.value = from.value;
            }
        });

        to.min = from.value;
    }
});
