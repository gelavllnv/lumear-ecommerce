(() => {
    const root = document.getElementById('riderTasks');
    if (!root) return;
    const base = root.dataset.base;
    const logistics = root.dataset.logistics === '1';
    const message = document.getElementById('taskError');
    let workspace = {};
    async function request(path, method = 'GET', body) {
        const response = await fetch(base + path, {method, headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf}, ...(body ? {body: JSON.stringify(body)} : {})});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Unable to update the task.');
        return data;
    }
    function options(id, rows, label) {
        const select = document.getElementById(id);
        select.replaceChildren(...rows.map(row => new Option(label(row), row.id)));
    }
    function riders() {
        const job = document.getElementById('taskJob').value;
        options('taskRider', workspace.riders.filter(r => r.job_type === job && r.management_status === 'Available' && r.is_active), r => `${r.name} · ${r.vehicle_type} · ${r.job_type}`);
        document.getElementById('taskHub').disabled = job === 'Seller Pickup';
    }
    async function refresh() {
        message.textContent = '';
        try {
            workspace = await request('/data');
            if (logistics) {
                options('taskShipment', workspace.shipments, s => `${s.tracking_code} · ${s.seller_name} · ${s.status}`);
                options('taskHub', workspace.hubs, h => h.name);
                riders();
            }
            const list = document.getElementById('taskList');
            list.replaceChildren();
            for (const task of workspace.tasks) {
                const card = document.createElement('article');
                card.className = 'rounded border p-4';
                const text = document.createElement('p');
                text.textContent = `${task.tracking_code} · ${task.job_type} · ${task.seller_name} · ${task.status.replaceAll('_', ' ')} · ${task.origin} → ${task.destination}`;
                card.append(text);
                const canAccept = logistics && Number(task.destination_provider_id) === Number(workspace.provider.id) && (task.status === 'seller_confirmed' || (task.job_type === 'Long-distance Handover' && task.status === 'arrived'));
                if (canAccept || (!logistics && task.job_type === 'Seller Pickup' && task.status === 'collected')) {
                    const button = document.createElement('button');
                    button.textContent = logistics ? 'Accept parcels at this hub' : 'Confirm rider collected my parcels';
                    button.onclick = async () => {
                        button.disabled = true;
                        try { await request(`/${task.id}`, 'PATCH', {status: logistics ? 'completed' : 'seller_confirmed'}); await refresh(); }
                        catch (error) { message.textContent = error.message; button.disabled = false; }
                    };
                    card.append(button);
                }
                list.append(card);
            }
            if (!workspace.tasks.length) list.textContent = 'No tasks yet.';
        } catch (error) { message.textContent = error.message; }
    }
    if (logistics) {
        document.getElementById('taskJob').onchange = riders;
        document.getElementById('assignTask').onsubmit = async event => {
            event.preventDefault();
            const button = event.target.querySelector('button');
            button.disabled = true;
            try {
                await request('', 'POST', {shipment_id: Number(document.getElementById('taskShipment').value), rider_id: Number(document.getElementById('taskRider').value), job_type: document.getElementById('taskJob').value, destination_provider_id: document.getElementById('taskJob').value === 'Seller Pickup' ? null : Number(document.getElementById('taskHub').value)});
                await refresh();
            } catch (error) { message.textContent = error.message; }
            finally { button.disabled = false; }
        };
    }
    document.getElementById('refreshTasks').onclick = refresh;
    refresh();
})();
