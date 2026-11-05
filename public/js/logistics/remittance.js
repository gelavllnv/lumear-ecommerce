document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const reviewButtons = document.querySelectorAll(
        '[data-open-verify]'
    );

    reviewButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const id = button.getAttribute('data-open-verify');

            const panel = document.getElementById(
                'remittance-review-' + id
            );

            if (!panel) {
                return;
            }

            const willOpen = panel.hidden;

            document.querySelectorAll(
                '.remittance-review'
            ).forEach(function (otherPanel) {
                otherPanel.hidden = true;
            });

            panel.hidden = !willOpen;

            button.textContent = willOpen
                ? 'Close'
                : 'Review';
        });
    });

    document.querySelectorAll(
        '[data-confirm-verify]'
    ).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const checkbox = form.querySelector(
                'input[name="funds_confirmed"]'
            );

            if (!checkbox || !checkbox.checked) {
                event.preventDefault();

                window.alert(
                    'You must confirm that the funds actually arrived.'
                );

                return;
            }

            const confirmed = window.confirm(
                'Verify this digital transfer?\n\n' +
                'Only continue if you independently confirmed ' +
                'that the payment reached the correct receiving account.\n\n' +
                'This action will update COD balances.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll(
        '[data-confirm-reject]'
    ).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const reason = form.querySelector(
                'textarea[name="reason"]'
            );

            if (!reason || reason.value.trim().length < 5) {
                event.preventDefault();

                window.alert(
                    'Please enter a valid rejection reason.'
                );

                return;
            }

            const confirmed = window.confirm(
                'Reject this remittance submission?\n\n' +
                'The rider COD balance will remain outstanding.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
});
