/**
 * Reports Plugin Logic
 * Independent module. Fetches from /reports.php
 */

async function init() {
    console.log('[Reports] Initializing...');

    try {
        await Promise.all([
            loadEfficiency(),
            loadBanks(),
            loadSuppliers()
        ]);
        console.log('[Reports] Ready');
    } catch (e) {
        console.error('[Reports] Error:', e);
        alert('Data load error: ' + e.message);
    }
}

async function fetchAPI(endpoint) {
    // We use the standalone entry point with query params (server.php safe)
    const res = await fetch(`/reports.php?api=${endpoint}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Unknown error');
    return json.data;
}

async function loadEfficiency() {
    const data = await fetchAPI('efficiency');

    // Animate numbers? For now just set text
    document.getElementById('valSessions').textContent = data.sessions_count;
    document.getElementById('valRecords').textContent = data.records_count; // .toLocaleString()
    document.getElementById('valCompletion').textContent = data.completion_rate + '%';
    document.getElementById('valPending').textContent = data.pending_count;
}

async function loadBanks() {
    const data = await fetchAPI('banks');

    const ctx = document.getElementById('chartBanks').getContext('2d');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.name),
            datasets: [{
                data: data.map(d => d.count),
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#6366f1', '#ec4899', '#14b8a6', '#f97316', '#64748b'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { font: { family: 'Tajawal' } } }
            }
        }
    });
}

async function loadSuppliers() {
    const data = await fetchAPI('suppliers');

    const ctx = document.getElementById('chartSuppliers').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.name.substring(0, 20) + (d.name.length > 20 ? '...' : '')),
            datasets: [{
                label: 'عدد الخطابات',
                data: data.map(d => d.count),
                backgroundColor: '#3b82f6',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', init);
