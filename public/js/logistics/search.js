/*
|--------------------------------------------------------------------------
| LUMEAR LOGISTICS — GLOBAL SEARCH
|--------------------------------------------------------------------------
| Searches actual MySQL records through the Logistics search API.
| Supports parcels, pickup requests, orders, and riders.
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('logisticsGlobalSearch');
    const input = document.getElementById('logisticsSearchInput');
    const dropdown = document.getElementById('logisticsSearchResults');
    const message = document.getElementById('logisticsSearchMessage');
    const items = document.getElementById('logisticsSearchItems');

    if (!container || !input || !dropdown || !message || !items) {
        return;
    }

    const searchUrl = input.dataset.searchUrl;

    let debounceTimer = null;
    let requestController = null;
    let requestNumber = 0;

    function showDropdown() {
        dropdown.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function hideDropdown() {
        dropdown.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    }

    function showMessage(text) {
        message.textContent = text;
        message.hidden = false;
        items.replaceChildren();
        showDropdown();
    }

    function clearResults() {
        items.replaceChildren();
        message.textContent = '';
        message.hidden = true;
    }

    function makeResult(result) {
        const link = document.createElement('a');

        link.className = 'logistics-search-item';

        // Only allow internal Logistics URLs.
        try {
            const destination = new URL(result.url, window.location.origin);

            if (
                destination.origin !== window.location.origin ||
                !destination.pathname.startsWith('/logistics/')
            ) {
                return null;
            }

            link.href = destination.href;
        } catch (error) {
            return null;
        }

        const type = document.createElement('span');
        type.className = 'logistics-search-type';
        type.textContent = result.type || 'Result';

        const title = document.createElement('strong');
        title.textContent = result.title || 'Untitled';

        const subtitle = document.createElement('span');
        subtitle.textContent = result.subtitle || '';

        link.append(type, title, subtitle);

        return link;
    }

    function renderResults(results) {
        clearResults();

        if (!Array.isArray(results) || results.length === 0) {
            showMessage('No matching parcels, orders, or riders found.');
            return;
        }

        const fragment = document.createDocumentFragment();

        results.forEach(result => {
            const element = makeResult(result);

            if (element) {
                fragment.appendChild(element);
            }
        });

        if (!fragment.childNodes.length) {
            showMessage('No matching results found.');
            return;
        }

        items.appendChild(fragment);
        showDropdown();
    }

    async function performSearch(query) {
        const currentRequest = ++requestNumber;

        if (requestController) {
            requestController.abort();
        }

        requestController = new AbortController();

        showMessage('Searching...');

        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', query);

            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: requestController.signal
            });

            if (currentRequest !== requestNumber) {
                return;
            }

            if (response.status === 401) {
                showMessage('Your session has expired. Please log in again.');
                return;
            }

            if (response.status === 403) {
                showMessage('You do not have access to Logistics search.');
                return;
            }

            if (!response.ok) {
                throw new Error('Search request failed.');
            }

            const data = await response.json();

            if (currentRequest !== requestNumber) {
                return;
            }

            if (!data.success) {
                throw new Error('Search was unsuccessful.');
            }

            // Ignore results if the user has changed the query.
            if (input.value.trim() !== query) {
                return;
            }

            renderResults(data.results);

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            if (currentRequest !== requestNumber) {
                return;
            }

            console.error('Logistics search error:', error);

            showMessage(
                'Unable to search right now. Please try again.'
            );
        }
    }

    input.addEventListener('input', () => {
        const query = input.value.trim();

        clearTimeout(debounceTimer);

        ++requestNumber;

        if (requestController) {
            requestController.abort();
        }

        if (query.length < 2) {
            if (query.length === 0) {
                hideDropdown();
            } else {
                showMessage('Type at least 2 characters to search.');
            }

            return;
        }

        debounceTimer = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2) {
            showDropdown();
        }
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideDropdown();
            input.blur();
            return;
        }

        if (event.key === 'ArrowDown' && !dropdown.hidden) {
            const firstResult = items.querySelector('a');

            if (firstResult) {
                event.preventDefault();
                firstResult.focus();
            }
        }

        if (event.key === 'Enter') {
            const firstResult = items.querySelector('a');

            if (firstResult && !dropdown.hidden) {
                event.preventDefault();
                firstResult.click();
            }
        }
    });

    items.addEventListener('keydown', event => {
        const links = Array.from(items.querySelectorAll('a'));
        const index = links.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            hideDropdown();
            input.focus();
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            links[Math.min(index + 1, links.length - 1)]?.focus();
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();

            if (index <= 0) {
                input.focus();
            } else {
                links[index - 1]?.focus();
            }
        }
    });

    document.addEventListener('click', event => {
        if (!container.contains(event.target)) {
            hideDropdown();
        }
    });

    document.addEventListener('keydown', event => {
        if (
            (event.ctrlKey || event.metaKey) &&
            event.key.toLowerCase() === 'k'
        ) {
            event.preventDefault();
            input.focus();
            input.select();
        }
    });
});
