document.querySelectorAll('[data-address-picker]').forEach((picker) => {
    const locations = JSON.parse(picker.dataset.locations || '{}');
    const regions = JSON.parse(picker.dataset.regions || '{}');
    const region = picker.querySelector('[data-region]');
    const province = picker.querySelector('[data-province]');
    const city = picker.querySelector('[data-city]');
    const initialProvince = picker.dataset.initialProvince || '';
    const initialCity = picker.dataset.initialCity || '';

    const populateCities = (selected = '') => {
        const places = locations[province.value] || [];
        city.replaceChildren(new Option(province.value ? 'Choose a city / municipality' : 'Choose a province first', ''));
        places.forEach((place) => city.add(new Option(place, place, false, place === selected)));
        city.disabled = places.length === 0;
    };

    const populateProvinces = (selected = '') => {
        if (!region) return;
        const provinces = regions[region.value] || [];
        province.replaceChildren(new Option(region.value ? 'Choose a province' : 'Choose a region first', ''));
        provinces.forEach((place) => province.add(new Option(place, place, false, place === selected)));
        province.disabled = provinces.length === 0;
        populateCities();
    };

    if (region) {
        region.addEventListener('change', () => populateProvinces());
        const matchingRegion = Object.entries(regions).find(([, provinces]) => provinces.includes(initialProvince));
        if (!region.value && matchingRegion) region.value = matchingRegion[0];
        populateProvinces(initialProvince);
    }
    province.addEventListener('change', () => populateCities());
    populateCities(initialCity);
});
