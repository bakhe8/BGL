/**
 * Stats Dashboard Logic
 * Fetches data from /api/stats and updates the UI
 */

const Stats = {
    init() {
        this.loadStats();
    },

    async loadStats() {
        try {
            const res = await fetch('/api/stats');
            const json = await res.json();

            if (json.success) {
                this.updateUI(json.data);
                this.renderCharts(json.data);
            }
        } catch (e) {
            console.error('Failed to load stats', e);
        }
    },

    updateUI(data) {
        // Safe set helper
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        set('valTotal', data.total_records);
        set('valCompleted', data.completed);
        set('valPending', data.pending);
        set('valSuppliers', data.suppliers_count);
    },

    renderCharts(data) {
        // 1. Pie Chart: Status
        const ctxStatus = document.getElementById('chartStatus');
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['مكتمل', 'معلق'],
                    datasets: [{
                        data: [data.completed, data.pending],
                        backgroundColor: ['#16a34a', '#ea580c'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // 2. Bar Chart: Top Banks
        const ctxBanks = document.getElementById('chartBanks');
        if (data.top_banks && ctxBanks) {
            const labels = data.top_banks.map(x => x.raw_bank_name);
            const counts = data.top_banks.map(x => x.count);

            new Chart(ctxBanks, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'عدد الخطابات',
                        data: counts,
                        backgroundColor: '#2563eb',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    }
};

// Auto-init
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Stats.init());
} else {
    Stats.init();
}
