const formatAge = (birthDate) => {
    const birth = new Date(`${birthDate}T00:00:00`);
    if (Number.isNaN(birth.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const beforeBirthday = today.getMonth() < birth.getMonth()
        || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate());

    return beforeBirthday ? age - 1 : age;
};

document.querySelectorAll('[data-age-gate]').forEach((gate) => {
    const input = gate.querySelector('[data-date-of-birth]');
    const status = gate.querySelector('[data-age-status]');
    if (!input || !status) return;

    const update = () => {
        const age = formatAge(input.value);
        const isUnderAge = age !== null && age < 18;
        const isFutureDate = age !== null && age < 0;

        input.setCustomValidity(isUnderAge || isFutureDate ? 'You must be at least 18 years old to create a Lumear account.' : '');
        status.classList.toggle('text-red-700', isUnderAge || isFutureDate);
        status.classList.toggle('text-emerald-700', age !== null && age >= 18);
        status.classList.toggle('text-maroon-900/55', age === null);

        if (age === null) {
            status.textContent = 'You must be 18 or older to create a Lumear account.';
        } else if (isUnderAge || isFutureDate) {
            status.textContent = `You are ${Math.max(age, 0)}. You must be at least 18 to register.`;
        } else {
            status.textContent = `You are ${age}. You meet Lumear’s 18+ account requirement.`;
        }
    };

    input.max = new Date().toISOString().slice(0, 10);
    input.addEventListener('input', update);
    input.addEventListener('change', update);
    update();
});