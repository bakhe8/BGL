/**
 * Initialization Script
 * Binds DOM elements, sets up event listeners, and triggers initial data load.
 */

// Define global loading functions 
window.loadSuppliersCache = async function (force = false) {
    try {
        const res = await api.get('/api/dictionary/suppliers');
        if (res.success) {
            BGL.State.supplierCache = res.data || [];
            BGL.State.supplierMap = {};
            BGL.State.supplierCache.forEach(s => {
                BGL.State.supplierMap[s.id] = s;
            });
        }
    } catch (e) {
        console.error('Failed to load suppliers', e);
    }
};

window.ensureBanksCache = async function (force = false) {
    if (!force && Object.keys(BGL.State.bankMap).length) return;
    try {
        const res = await api.get('/api/dictionary/banks');
        if (res.success && Array.isArray(res.data)) {
            BGL.State.bankMap = {};
            BGL.State.bankNormMap = {};
            res.data.forEach(b => {
                BGL.State.bankMap[b.id] = b;
                if (b.normalized_key) {
                    const disp = b.official_name || '';
                    if (disp && !BGL.State.bankNormMap[b.normalized_key]) {
                        BGL.State.bankNormMap[b.normalized_key] = disp;
                    }
                }
            });
        }
    } catch (_) { /* ignore */ }
};

window.loadRecords = async function () {
    const DOM = BGL.DOM;
    if (DOM.tableBody) DOM.tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-gray-500">جارٍ التحميل...</td></tr>';

    try {
        const params = new URLSearchParams(location.search);

        if (DOM.sessionFilter && DOM.sessionFilter.value) {
            params.set('session_id', DOM.sessionFilter.value);
        }

        const url = params.toString() ? `/api/records?${params.toString()}` : '/api/records';
        const json = await api.get(url);

        if (!json.success) throw new Error(json.message || 'فشل تحميل السجلات');

        BGL.State.records = json.data || [];
        console.log('DEBUG RECORDS Loaded:', BGL.State.records.length);

        await ensureBanksCache();
        await loadSuppliersCache();

        BGL.Table.render();

    } catch (e) {
        console.error(e);
        if (DOM.tableBody) DOM.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-red-500">خطأ: ${e.message}</td></tr>`;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize DOM References in BGL.DOM
    BGL.DOM.tableBody = document.getElementById('tableBody');
    BGL.DOM.sessionFilter = document.getElementById('sessionFilter');
    BGL.DOM.statusFilter = document.getElementById('statusFilter');
    BGL.DOM.refreshBtn = document.getElementById('refreshBtn');
    BGL.DOM.paginationInfo = document.getElementById('paginationInfo');

    const DOM = BGL.DOM;

    // 2. Setup Event Listeners
    if (DOM.statusFilter) {
        DOM.statusFilter.addEventListener('change', () => {
            BGL.Table.render();
        });
    }

    if (DOM.refreshBtn) {
        DOM.refreshBtn.addEventListener('click', () => {
            loadRecords();
        });
    }

    // Row Click (Delegated)
    if (DOM.tableBody) {
        DOM.tableBody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (tr && tr.dataset.id && tr.id !== 'panel-row') {
                const id = parseInt(tr.dataset.id);
                // BGL.State.records
                const record = BGL.State.records.find(r => r.id === id);
                if (record) {
                    BGL.Panel.openPanel(record);
                }
            }
        });
    }

    // Initialize Sorting
    BGL.Table.setupSorting();

    // Recalc Button
    const btnRecalcAll = document.getElementById('btnRecalcAll');
    if (btnRecalcAll) {
        btnRecalcAll.addEventListener('click', async () => {
            if (!confirm('هل أنت متأكد من إعادة المطابقة لجميع السجلات؟')) return;

            btnRecalcAll.disabled = true;
            btnRecalcAll.textContent = '...';
            try {
                const res = await api.post('/api/records/recalculate');
                if (res.success) {
                    alert(`تمت العملية: ${res.data?.processed} سجل`);
                    loadRecords();
                } else {
                    alert(res.message);
                }
            } catch (e) {
                alert(e.message);
            } finally {
                btnRecalcAll.disabled = false;
                btnRecalcAll.textContent = 'إعادة مطابقة الكل';
            }
        });
    }

    // Import Toggle and Form (Simulated logic, keeping simple for now)
    // ... (Keep existing import logic if needed, or assume it works independently)
    // For brevity in this refactor, I'm verifying the core BGL namespace logic first.
    // Copying strict import logic:
    const btnToggleImport = document.getElementById('btnToggleImport');
    const importCard = document.getElementById('importCard');
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const uploadMsg = document.getElementById('uploadMsg');
    const uploadError = document.getElementById('uploadError');

    if (btnToggleImport && importCard) {
        btnToggleImport.addEventListener('click', () => {
            importCard.style.display = (importCard.style.display === 'none') ? 'block' : 'none';
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (uploadError) uploadError.style.display = 'none';
            if (uploadMsg) uploadMsg.textContent = 'جارٍ الرفع...';

            if (!fileInput.files.length) {
                if (uploadError) {
                    uploadError.textContent = 'اختر ملفاً';
                    uploadError.style.display = 'block';
                }
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            try {
                const res = await fetch('/api/import/excel', { method: 'POST', body: formData });
                const json = await res.json();

                if (!json.success) throw new Error(json.message);

                const result = json.data || {};
                let msg = `تم استيراد: ${result.records_count} سجل بنجاح (جلسة ${result.session_id}).`;
                let className = 'text-green-600 font-bold';

                if (result.skipped && result.skipped.length > 0) {
                    msg += `<br><span class="text-red-600">تم تخطي ${result.skipped.length} سجل:</span><br>`;
                    // Start list
                    const listItems = result.skipped.slice(0, 5).map(s => `<li>${BGL.Utils.escapeHtml(s)}</li>`).join('');
                    const remaining = result.skipped.length > 5 ? `<li>... و ${result.skipped.length - 5} آخرين</li>` : '';

                    msg += `<ul class="list-disc list-inside text-sm text-red-700 mt-2 bg-red-50 p-2 rounded">${listItems}${remaining}</ul>`;

                    if (result.records_count === 0) {
                        className = 'text-red-700 font-bold';
                    }
                }

                if (uploadMsg) {
                    uploadMsg.innerHTML = msg;
                    uploadMsg.className = className;
                    uploadMsg.style.display = 'block';
                }
                fileInput.value = '';
                loadRecords();
            } catch (err) {
                if (uploadError) {
                    uploadError.textContent = err.message;
                    uploadError.style.display = 'block';
                }
                if (uploadMsg) uploadMsg.textContent = '';
            }
        });
    }

    // 3. Initial Load
    // Initialize Overlay if exists
    if (typeof BGL.Overlay !== 'undefined') BGL.Overlay.init();

    // Load Data
    loadRecords();
});
