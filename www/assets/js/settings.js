/**
 * Settings Management Module
 * Handles Tabs, Data Loading, and CRUD operations for Dictionaries.
 */

const Settings = {
    state: {
        activeTab: 'general',
        suppliers: [],
        banks: [],
    },

    init() {
        this.setupTabs();
        this.loadSettings(); // General Settings

    },

    setupTabs() {
        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(btn => {
            btn.addEventListener('click', (e) => {
                // UI Toggle
                tabs.forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

                e.target.classList.add('active');
                const targetId = e.target.dataset.target;
                document.getElementById(targetId).style.display = 'block';

                this.state.activeTab = targetId;

                // Lazy Load
                if (targetId === 'suppliers' && this.state.suppliers.length === 0) {
                    this.Dictionaries.loadSuppliers();
                }
                if (targetId === 'banks' && this.state.banks.length === 0) {
                    this.Dictionaries.loadBanks();
                }
            });
        });
    },

    // --- General Settings Logic ---
    async loadSettings() {
        try {
            const res = await fetch('/api/settings');
            const json = await res.json();
            if (!json.success) return;
            const d = json.data;
            const setVal = (id, val, def) => {
                const el = document.getElementById(id);
                if (el) el.value = val !== undefined && val !== null ? val : def;
            };

            // Match Settings
            setVal('autoTh', d.MATCH_AUTO_THRESHOLD, 0.90);
            setVal('revTh', d.MATCH_REVIEW_THRESHOLD, 0.70);
            setVal('confDelta', d.CONFLICT_DELTA, 0.1);
            setVal('weakTh', d.MATCH_WEAK_THRESHOLD, 0.80);
            setVal('candLimit', d.CANDIDATES_LIMIT, 20);

            // Weights
            setVal('wOfficial', d.WEIGHT_OFFICIAL, 1.0);
            setVal('wAltConf', d.WEIGHT_ALT_CONFIRMED, 0.85);
            setVal('wAltLearn', d.WEIGHT_ALT_LEARNING, 0.75);
            setVal('wFuzzy', d.WEIGHT_FUZZY, 0.60);

        } catch (e) { console.error('Failed to load settings', e); }
    },

    async saveSettings(e) {
        e.preventDefault();
        const payload = {
            MATCH_AUTO_THRESHOLD: parseFloat(document.getElementById('autoTh').value),
            MATCH_REVIEW_THRESHOLD: parseFloat(document.getElementById('revTh').value),
            CONFLICT_DELTA: parseFloat(document.getElementById('confDelta').value),

            MATCH_WEAK_THRESHOLD: parseFloat(document.getElementById('weakTh').value),
            CANDIDATES_LIMIT: parseInt(document.getElementById('candLimit').value),
            WEIGHT_OFFICIAL: parseFloat(document.getElementById('wOfficial').value),
            WEIGHT_ALT_CONFIRMED: parseFloat(document.getElementById('wAltConf').value),
            WEIGHT_ALT_LEARNING: parseFloat(document.getElementById('wAltLearn').value),
            WEIGHT_FUZZY: parseFloat(document.getElementById('wFuzzy').value),
        };

        const res = await fetch('/api/settings', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const json = await res.json();
        this.UI.showToast(json.success ? 'تم حفظ الإعدادات بنجاح' : 'حدث خطأ', json.success ? 'success' : 'error');
    },

    // --- Dictionaries Sub-Module ---
    Dictionaries: {
        async loadSuppliers() {
            Settings.UI.setLoading('suppliersTableBody', true);
            const res = await fetch('/api/dictionary/suppliers');
            const json = await res.json();
            Settings.state.suppliers = json.data || [];
            this.renderSuppliers(Settings.state.suppliers);
            Settings.UI.setLoading('suppliersTableBody', false);
        },

        async loadBanks() {
            Settings.UI.setLoading('banksTableBody', true);
            const res = await fetch('/api/dictionary/banks');
            const json = await res.json();
            Settings.state.banks = json.data || [];
            this.renderBanks(Settings.state.banks);
            Settings.UI.setLoading('banksTableBody', false);
        },

        renderSuppliers(list) {
            const tbody = document.getElementById('suppliersTableBody');
            tbody.innerHTML = '';
            list.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-medium">${s.official_name}</td>
                    <td class="text-sm text-muted">${s.normalized_name}</td>
                    <td class="text-center">${s.alternatives_count || 0}</td>
                    <td class="text-left">
                        <button class="btn-xs btn-outline-primary" onclick="Settings.Dictionaries.editSupplier(${s.id})">تعديل</button>
                        <button class="btn-xs btn-outline-danger" onclick="Settings.Dictionaries.deleteSupplier(${s.id})">حذف</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        },

        renderBanks(list) {
            const tbody = document.getElementById('banksTableBody');
            tbody.innerHTML = '';
            list.forEach(b => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-medium">${b.official_name}</td>
                    <td class="text-sm text-muted">${b.official_name_en || '-'}</td>
                    <td class="text-sm">${b.short_code || '-'}</td>
                    <td class="text-left">
                        <button class="btn-xs btn-outline-primary" onclick="Settings.Dictionaries.editBank(${b.id})">تعديل</button>
                        <button class="btn-xs btn-outline-danger" onclick="Settings.Dictionaries.deleteBank(${b.id})">حذف</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        },

        // Search Handlers
        filterSuppliers(q) {
            const normalized = q.toLowerCase();
            const filtered = Settings.state.suppliers.filter(s =>
                s.official_name.toLowerCase().includes(normalized) ||
                s.normalized_name.includes(normalized)
            );
            this.renderSuppliers(filtered);
        },

        filterBanks(q) {
            const normalized = q.toLowerCase();
            const filtered = Settings.state.banks.filter(b =>
                b.official_name.toLowerCase().includes(normalized) ||
                (b.official_name_en && b.official_name_en.toLowerCase().includes(normalized))
            );
            this.renderBanks(filtered);
        },

        // CRUD
        async deleteSupplier(id) {
            if (!await Settings.UI.showConfirm('هل أنت متأكد من حذف هذا المورد؟')) return;
            const res = await fetch(`/api/dictionary/suppliers/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                Settings.UI.showToast('تم الحذف بنجاح', 'success');
                this.loadSuppliers(); // Reload
            } else {
                alert(json.message);
            }
        },

        async deleteBank(id) {
            if (!await Settings.UI.showConfirm('هل أنت متأكد من حذف هذا البنك؟')) return;
            const res = await fetch(`/api/dictionary/banks/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                Settings.UI.showToast('تم الحذف بنجاح', 'success');
                this.loadBanks(); // Reload
            } else {
                alert(json.message);
            }
        },

        async deleteAlias(aliasId, supplierId) {
            if (!await Settings.UI.showConfirm('حذف هذا الاسم البديل؟')) return;
            const res = await fetch(`/api/dictionary/aliases/${aliasId}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                // Refresh specific supplier data to update list in modal
                // Currently I have to reload the whole list to be safe, then reopen/update modal
                // Or better: just fetch supplier again.
                // For simplicity, I will reload suppliers then find the supplier and re-render modal
                await this.loadSuppliers();
                const s = Settings.state.suppliers.find(x => x.id === supplierId);
                if (s) Settings.UI.openSupplierModal(s);
            }
        },

        // Edit/Create Logic (Simplified using Prompts for now, or Modal if needed)
        // For 'Same Style', a Modal is better. I'll interact with the Modal in HTML.
        editSupplier(id) {
            const s = Settings.state.suppliers.find(x => x.id === id);
            if (!s) return;
            Settings.UI.openSupplierModal(s);
        },

        createSupplier() {
            Settings.UI.openSupplierModal(null);
        },

        editBank(id) {
            const b = Settings.state.banks.find(x => x.id === id);
            if (!b) return;
            Settings.UI.openBankModal(b);
        },

        createBank() {
            Settings.UI.openBankModal(null);
        }
    },

    // --- UI Helpers ---
    UI: {
        setLoading(id, isLoading) {
            const el = document.getElementById(id);
            if (isLoading) el.innerHTML = '<tr><td colspan="4" class="text-center py-4">جاري التحميل...</td></tr>';
        },

        showToast(msg, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 left-4 p-4 rounded shadow-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
            toast.textContent = msg;
            toast.style.zIndex = 9999;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },

        // Modals
        openSupplierModal(data) {
            const modal = document.getElementById('supplierModal');
            const form = document.getElementById('supplierForm');
            form.reset();
            document.getElementById('supId').value = data ? data.id : '';
            document.getElementById('supName').value = data ? data.official_name : '';

            // Render Alternatives
            const divAlts = document.getElementById('divSupAlts');
            const ulAlts = document.getElementById('supAltsList');
            if (divAlts && ulAlts) {
                ulAlts.innerHTML = '';
                if (data && data.alternatives && data.alternatives.length > 0) {
                    divAlts.style.display = 'block';
                    data.alternatives.forEach(alt => {
                        const li = document.createElement('li');
                        li.className = 'flex justify-between items-center mb-1';
                        li.innerHTML = `
                            <span>${alt.raw_name}</span>
                            <button type="button" class="btn-xs text-red-600 hover:text-red-800 delete-alias-btn" data-alias-id="${alt.id}" data-supplier-id="${data.id}">×</button>
                        `;
                        ulAlts.appendChild(li);
                    });
                } else {
                    divAlts.style.display = 'none';
                }
            }

            modal.style.display = 'flex';
        },

        openBankModal(data) {
            const modal = document.getElementById('bankModal');
            const form = document.getElementById('bankForm');
            form.reset();
            document.getElementById('bankId').value = data ? data.id : '';
            document.getElementById('bankName').value = data ? data.official_name : '';
            document.getElementById('bankNameEn').value = data ? data.official_name_en : '';
            document.getElementById('bankCode').value = data ? data.short_code : '';

            // Address
            document.getElementById('bankDept').value = data ? (data.department || '') : '';
            document.getElementById('bankEmail').value = data ? (data.contact_email || '') : '';
            document.getElementById('bankAddr1').value = data ? (data.address_line_1 || '') : '';
            document.getElementById('bankAddr2').value = data ? (data.address_line_2 || '') : '';

            modal.style.display = 'flex';
        },

        closeModal(id) {
            document.getElementById(id).style.display = 'none';
        },

        // Custom Confirm
        showConfirm(msg) {
            return new Promise((resolve) => {
                const modal = document.getElementById('confirmModal');
                const msgEl = document.getElementById('confirmMsg');
                const btnYes = document.getElementById('btnConfirmYes');
                const btnNo = document.getElementById('btnConfirmNo');

                msgEl.textContent = msg;
                modal.style.display = 'flex';

                // One-time handlers
                const handleYes = () => {
                    cleanup();
                    resolve(true);
                };
                const handleNo = () => {
                    cleanup();
                    resolve(false);
                };

                const cleanup = () => {
                    modal.style.display = 'none';
                    btnYes.removeEventListener('click', handleYes);
                    btnNo.removeEventListener('click', handleNo);
                };

                btnYes.addEventListener('click', handleYes, { once: true });
                btnNo.addEventListener('click', handleNo, { once: true });
            });
        }
    }
};

// Global Exposure for OnClick
window.Settings = Settings;

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    Settings.init();

    document.getElementById('settingsForm').addEventListener('submit', (e) => Settings.saveSettings(e));

    // Search Inputs
    document.getElementById('searchSupplier').addEventListener('input', (e) => Settings.Dictionaries.filterSuppliers(e.target.value));
    document.getElementById('searchBank').addEventListener('input', (e) => Settings.Dictionaries.filterBanks(e.target.value));

    // Modals
    document.addEventListener('click', (e) => {
        // Delegate 'close-modal' click
        if (e.target.matches('.close-modal') || e.target.closest('.close-modal')) {
            const btn = e.target.matches('.close-modal') ? e.target : e.target.closest('.close-modal');
            const targetId = btn.dataset.target;
            if (targetId) {
                Settings.UI.closeModal(targetId);
            }
        }

        // Delegate 'delete-alias-btn'
        if (e.target.matches('.delete-alias-btn') || e.target.closest('.delete-alias-btn')) {
            const btn = e.target.matches('.delete-alias-btn') ? e.target : e.target.closest('.delete-alias-btn');
            const aliasId = parseInt(btn.dataset.aliasId);
            const supplierId = parseInt(btn.dataset.supplierId);
            if (aliasId && supplierId) {
                Settings.Dictionaries.deleteAlias(aliasId, supplierId);
            }
        }
    });

    // Forms
    document.getElementById('supplierForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('supId').value;
        const payload = { official_name: document.getElementById('supName').value };
        const method = id ? 'POST' : 'POST'; // Update is POST to /id, Create is POST to /
        const url = id ? `/api/dictionary/suppliers/${id}` : '/api/dictionary/suppliers';

        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const json = await res.json();

        if (json.success) {
            Settings.UI.closeModal('supplierModal');
            Settings.UI.showToast('تم الحفظ', 'success');
            Settings.Dictionaries.loadSuppliers();
        } else {
            alert(json.message);
        }
    });

    document.getElementById('bankForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('bankId').value;
        const payload = {
            official_name: document.getElementById('bankName').value,
            official_name_en: document.getElementById('bankNameEn').value,
            short_code: document.getElementById('bankCode').value,
            department: document.getElementById('bankDept').value,
            contact_email: document.getElementById('bankEmail').value,
            address_line_1: document.getElementById('bankAddr1').value,
            address_line_2: document.getElementById('bankAddr2').value,
        };
        const url = id ? `/api/dictionary/banks/${id}` : '/api/dictionary/banks';

        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const json = await res.json();

        if (json.success) {
            Settings.UI.closeModal('bankModal');
            Settings.UI.showToast('تم الحفظ', 'success');
            Settings.Dictionaries.loadBanks();
        } else {
            alert(json.message);
        }
    });
});
