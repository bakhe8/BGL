/**
 * Events Module
 * Controller for all User Interactions
 */

import { DOM } from './layout.js';
import * as State from '../state.js';
import * as API from '../api.js';
import * as Matching from '../logic/matching.js';
import * as Validation from '../logic/validation.js';
import * as Render from './render.js';

// Internal Debounce Helper
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Controller Actions
const Actions = {
    navigateNext: async () => {
        if (State.nextRecord()) {
            const nav = State.getNavigationInfo();
            const record = State.getCurrentRecord();
            Render.renderRecord(record, nav.index, nav.total);

            const candidates = await API.fetchCandidates(record.id);
            Render.renderCandidateChips(candidates.suppliers, 'supplier');
            Render.renderCandidateChips(candidates.banks, 'bank');

            // Auto-Fill Logic (Learning Flow)
            Render.handleSupplierAutoFill(candidates.suppliers, record.rawSupplierName);

            // FIX: Update Add Button State immediately after navigation
            updateAddButtonState(record.rawSupplierName);
        }
    },
    navigatePrev: async () => {
        if (State.prevRecord()) {
            const nav = State.getNavigationInfo();
            const record = State.getCurrentRecord();
            Render.renderRecord(record, nav.index, nav.total);

            // Async Fetch Candidates
            const candidates = await API.fetchCandidates(record.id);
            Render.renderCandidateChips(candidates.suppliers, 'supplier');
            Render.renderCandidateChips(candidates.banks, 'bank');

            // Auto-Fill Logic (Learning Flow)
            Render.handleSupplierAutoFill(candidates.suppliers, record.rawSupplierName);

            // FIX: Update Add Button State immediately after navigation
            updateAddButtonState(record.rawSupplierName);
        }
    },
    saveAndNext: async () => {
        if (DOM.btnSaveNext.disabled) return;

        try {
            DOM.btnSaveNext.disabled = true;
            DOM.btnSaveNext.textContent = '...';

            const record = State.getCurrentRecord();
            const payload = {
                record_id: record.id,
                supplier_id: DOM.supplierInput.classList.contains('has-value') ? State.getCurrentSelection().supplierId || record.supplierId : null,
                bank_id: DOM.bankInput.classList.contains('has-value') ? State.getCurrentSelection().bankId || record.bankId : null,
            };

            if (State.getCurrentSelection().supplierId) {
                payload.supplier_id = State.getCurrentSelection().supplierId;
            }
            if (State.getCurrentSelection().bankId) {
                payload.bank_id = State.getCurrentSelection().bankId;
            }

            await API.saveDecision(payload);

            // Update local record to reflect ready status (optimistic)?
            State.updateRecord(record.id, { matchStatus: 'approved' }); // or ready

            // Move next
            Render.showMessage('تم الحفظ', 'success');
            // Navigate next handles rendering
            Actions.navigateNext();

        } catch (e) {
            Render.showMessage('خطأ في الحفظ: ' + e.message, 'error');
        } finally {
            DOM.btnSaveNext.disabled = false;
            DOM.btnSaveNext.textContent = 'حفظ وانتقال (Ctrl + Enter)';
        }
    }
};

export function setupEventListeners() {

    // --- Navigation ---
    DOM.btnPrev.addEventListener('click', Actions.navigatePrev);
    DOM.btnNext.addEventListener('click', Actions.navigateNext);
    DOM.btnSaveNext.addEventListener('click', Actions.saveAndNext);

    // --- Tools ---
    DOM.refreshBtn?.addEventListener('click', () => {
        window.location.reload();
    });

    // Custom Modal Logic
    let pendingConfirmAction = null;

    const hideModal = () => {
        DOM.confirmModal.classList.add('hidden');
        pendingConfirmAction = null;
    };

    const showModal = (message, action) => {
        DOM.confirmModalMessage.textContent = message;
        DOM.confirmModal.classList.remove('hidden');
        pendingConfirmAction = action;
    };

    // Bind Modal Buttons (Once)
    DOM.btnConfirmCancel?.addEventListener('click', hideModal);
    DOM.btnConfirmOk?.addEventListener('click', () => {
        if (pendingConfirmAction) pendingConfirmAction();
        hideModal();
    });

    const btnRecalc = DOM.btnRecalcAll || document.getElementById('btnRecalcAll');
    if (btnRecalc) {
        btnRecalc.addEventListener('click', () => {
            showModal('هل أنت متأكد من إعادة المطابقة لجميع السجلات؟ قد يستغرق هذا وقتاً.', async () => {
                try {
                    Render.showMessage('جاري إعادة المطابقة...', 'info');
                    await API.recalculateAll();
                    Render.showMessage('تمت إعادة المطابقة', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } catch (e) {
                    Render.showMessage('خطأ: ' + e.message, 'error');
                }
            });
        });
        console.log('[Events] Recalc Listener Attached (Custom Modal)');
    } else {
        console.error('[Events] Recalc Button Not Found');
    }

    // --- Filters ---
    const updateFilter = async (filter) => {
        State.setFilter(filter);
        const nav = State.getNavigationInfo();
        const record = State.getCurrentRecord();

        Render.renderRecord(record, nav.index, nav.total);
        Render.updateStats(State.getStats());

        // Manual UI update for active badge
        [DOM.badgeTotal, DOM.badgeApproved, DOM.badgePending].forEach(el => {
            if (el) el.classList.remove('ring-2', 'ring-offset-1', 'ring-blue-400');
        });

        if (filter === 'all' && DOM.badgeTotal) DOM.badgeTotal.classList.add('ring-2', 'ring-offset-1', 'ring-blue-400');
        if (filter === 'approved' && DOM.badgeApproved) DOM.badgeApproved.classList.add('ring-2', 'ring-offset-1', 'ring-blue-400');
        if (filter === 'pending' && DOM.badgePending) DOM.badgePending.classList.add('ring-2', 'ring-offset-1', 'ring-blue-400');

        // FIX: Re-fetch candidates and update UI state for the new current record
        if (record) {
            const candidates = await API.fetchCandidates(record.id);
            Render.renderCandidateChips(candidates.suppliers, 'supplier');
            Render.renderCandidateChips(candidates.banks, 'bank');
            Render.handleSupplierAutoFill(candidates.suppliers, record.rawSupplierName);
            updateAddButtonState(record.rawSupplierName);
        }
    };

    DOM.badgeTotal?.addEventListener('click', () => updateFilter('all'));
    DOM.badgeApproved?.addEventListener('click', () => updateFilter('approved'));
    DOM.badgePending?.addEventListener('click', () => updateFilter('pending'));

    // --- Chip Selection (Delegation) ---
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="select-candidate"]');
        if (btn) {
            const type = btn.dataset.type;
            const id = btn.dataset.id;
            const name = btn.dataset.name;

            if (type === 'bank') {
                State.getCurrentSelection().bankId = parseInt(id);
                DOM.bankInput.value = name;
                DOM.bankInput.classList.add('has-value');
            } else {
                State.getCurrentSelection().supplierId = parseInt(id);
                DOM.supplierInput.value = name;
                DOM.supplierInput.classList.add('has-value');

                // Disable Add New (legacy logic)
                if (DOM.btnAddSupplier) DOM.btnAddSupplier.disabled = true;
            }
        }
    });

    // --- Inputs ---

    // Bank Input
    const debouncedBankSearch = debounce((q) => {
        const suggestions = Matching.getBankSuggestions(q);
        if (suggestions.length > 0) {
            Render.renderSuggestions(DOM.bankSuggestions, suggestions, 'bank');
            DOM.bankSuggestions.classList.add('open');
        } else {
            DOM.bankSuggestions.classList.remove('open');
        }
    }, 300);

    DOM.bankInput.addEventListener('input', (e) => {
        DOM.bankInput.classList.remove('has-value');
        State.getCurrentSelection().bankId = null; // Clear selection
        debouncedBankSearch(e.target.value);
    });

    DOM.bankInput.addEventListener('blur', () => {
        setTimeout(() => DOM.bankSuggestions.classList.remove('open'), 200);
    });

    // Supplier Input
    const debouncedSupplierSearch = debounce((q) => {
        const suggestions = Matching.getSupplierSuggestions(q);
        if (suggestions.length > 0) {
            Render.renderSuggestions(DOM.supplierSuggestions, suggestions, 'supplier');
            DOM.supplierSuggestions.classList.add('open');
        } else {
            DOM.supplierSuggestions.classList.remove('open');
        }
    }, 300);

    DOM.supplierInput.addEventListener('input', (e) => {
        const val = e.target.value;
        DOM.supplierInput.classList.remove('has-value');
        State.getCurrentSelection().supplierId = null;

        // Update "Add New" Button State
        updateAddButtonState(val);

        debouncedSupplierSearch(val);
    });

    DOM.supplierInput.addEventListener('blur', () => {
        setTimeout(() => DOM.supplierSuggestions.classList.remove('open'), 200);
    });

    // Add New Supplier Button
    DOM.btnAddSupplier.addEventListener('click', async (e) => {
        e.preventDefault();
        const rawName = State.getCurrentRecord()?.rawSupplierName || '';
        const inputName = DOM.supplierInput.value.trim() || rawName;

        if (!inputName) return;

        try {
            DOM.btnAddSupplier.disabled = true;
            DOM.btnAddSupplier.textContent = '...';

            const newSupplier = await API.createSupplier(inputName);

            // Update State with new supplier
            State.getDictionaries().suppliers.push(newSupplier);
            State.getDictionaries().supplierMap[newSupplier.id] = newSupplier;

            // Select it
            State.getCurrentSelection().supplierId = newSupplier.id;
            State.getCurrentSelection().supplierName = newSupplier.official_name;

            DOM.supplierInput.value = newSupplier.official_name;
            DOM.supplierInput.classList.add('has-value');

            Render.showMessage('تم إضافة المورد بنجاح', 'success');
            DOM.btnAddSupplier.classList.add('hidden'); // Hide button

        } catch (err) {
            Render.showMessage('فشل الإضافة: ' + err.message, 'error');
        } finally {
            DOM.btnAddSupplier.disabled = false;
        }
    });

    // --- Delegation for Suggestions Clicks ---
    const handleSelection = (e) => {
        const li = e.target.closest('.suggestion-item');
        if (!li) return;

        const type = li.dataset.type;
        const id = li.dataset.id;
        const name = li.dataset.name;

        if (type === 'bank') {
            State.getCurrentSelection().bankId = parseInt(id);
            DOM.bankInput.value = name;
            DOM.bankInput.classList.add('has-value');
            DOM.bankSuggestions.classList.remove('open');
        } else if (type === 'supplier') {
            State.getCurrentSelection().supplierId = parseInt(id);
            DOM.supplierInput.value = name;
            DOM.supplierInput.classList.add('has-value');
            DOM.supplierSuggestions.classList.remove('open');
            DOM.btnAddSupplier.classList.add('hidden');
        }
    };

    DOM.bankSuggestions.addEventListener('mousedown', handleSelection);
    DOM.supplierSuggestions.addEventListener('mousedown', handleSelection);

    // --- Keyboard Shortcuts ---
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT') return;

        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            Actions.navigatePrev();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            Actions.navigateNext();
        } else if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            Actions.saveAndNext();
        }
    });

    // --- File Import ---
    DOM.btnToggleImport?.addEventListener('click', () => {
        DOM.hiddenFileInput.value = '';
        DOM.hiddenFileInput.click();
    });

    DOM.hiddenFileInput?.addEventListener('change', async (e) => {
        if (!e.target.files.length) return;
        try {
            Render.showMessage('jar al-raf...', 'info');
            const fd = new FormData();
            fd.append('file', e.target.files[0]);
            await API.importExcel(fd);
            Render.showMessage('Import Successful', 'success');
            // User Request: Redirect to root to load the NEW session automatically
            window.location.href = '/';
        } catch (err) {
            Render.showMessage(err.message, 'error');
        }
    });
}

export function updateAddButtonState(query) {
    const rawName = State.getCurrentRecord()?.rawSupplierName || '';
    const nameToCheck = (query && query.length > 0) ? query : rawName;

    if (!nameToCheck || nameToCheck.trim().length === 0) {
        DOM.btnAddSupplier.classList.add('hidden');
        return;
    }

    // 2. Duplicate Check (Strict)
    const isDuplicate = State.getDictionaries().suppliers.some(s =>
        s.official_name.toLowerCase() === nameToCheck.toLowerCase()
    );

    if (isDuplicate) {
        DOM.btnAddSupplier.classList.add('hidden');
    } else {
        DOM.btnAddSupplier.classList.remove('hidden');
        DOM.btnAddSupplier.disabled = false;

        // Ensure styling logic is consistent with legacy
        DOM.btnAddSupplier.className = "flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border transition-all bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300 hover:scale-105 whitespace-nowrap";

        // Text Logic
        if (!query || query.length === 0) {
            // Raw Name Case
            DOM.btnAddSupplier.textContent = `+ إضافة "${rawName}"`;
            DOM.btnAddSupplier.title = `إضافة "${rawName}" (من الملف) كمورد جديد`;
        } else {
            // Typed Name Case
            DOM.btnAddSupplier.textContent = `+ إضافة "${query}"`;
            DOM.btnAddSupplier.title = `إضافة "${query}" كمورد جديد`;
        }
    }
}
