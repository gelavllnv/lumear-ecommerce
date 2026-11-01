document.querySelectorAll('[data-auth-role-tabs]').forEach((container) => {
    const buttons = [...container.querySelectorAll('[data-auth-role-tab]')];
    const roleInput = container.querySelector('[data-auth-role-input]');
    const googleLinks = [...container.querySelectorAll('[data-auth-google-role]')];
    const logisticsRegistration = container.querySelector('[data-auth-logistics-register]');
    const standardRegistration = container.querySelector('[data-auth-standard-register]');

    const select = (role) => {
        roleInput.value = role;
        buttons.forEach((button) => {
            const active = button.dataset.authRoleTab === role;
            button.setAttribute('aria-selected', String(active));
            button.classList.toggle('auth-role-tab-active', active);
        });
        googleLinks.forEach((link) => link.hidden = link.dataset.authGoogleRole !== role);
        if (logisticsRegistration) logisticsRegistration.hidden = role !== 'logistics';
        if (standardRegistration) standardRegistration.hidden = role === 'logistics';
    };

    buttons.forEach((button) => button.addEventListener('click', () => select(button.dataset.authRoleTab)));
    select(container.dataset.defaultRole || 'buyer');
});
