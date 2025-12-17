/**
 * Render Module
 * Handles DOM updates for the Decision Interface
 */

import { DOM } from './layout.js';
import * as State from '../state.js';
import * as Preview from './preview.js';

/**
 * Update the full record view
 * @param {Object} record 
 * @param {number} index 
 * @param {number} total 
 */
export function renderRecord(record, index, total) {
    if (!record) {
        clearView();
        return;
    }

    // 1. Meta Data

    // Determine Filter Text Context (Legacy Behavior Restoration)
    const currentFilter = State.getFilter();
    let filterText = 'سجل';
    if (currentFilter === 'approved') {
        filterText = 'سجل جاهز';
    } else if (currentFilter === 'pending') {
        filterText = 'سجل يحتاج قرار';
    }

    DOM.metaRecordId.textContent = `#${record.id}`;

    // Update Button Text
    const btnLabel = DOM.btnSaveNext.querySelector('span:last-child');
    if (btnLabel) {
        // We use innerHTML to inject spans for dynamic updates
        btnLabel.innerHTML = `إحفظ (<span id="currentIndex">${index + 1}</span> من <span id="totalCount">${total}</span>) ${filterText}، وانتقل للتالي`;

        // Re-cache dynamic elements for subsequent fast updates
        if (DOM.currentIndexDisplay) DOM.currentIndexDisplay = document.getElementById('currentIndex');
        if (DOM.totalCountDisplay) DOM.totalCountDisplay = document.getElementById('totalCount');
    }

    DOM.metaSessionId.textContent = record.sessionId;
    DOM.metaGuarantee.textContent = record.guaranteeNumber || '-';
    // Fix: Label says "Expiry", so use expiryDate
    DOM.metaDate.textContent = record.expiryDate || '-';
    DOM.metaAmount.textContent = record.amount ? Number(record.amount).toLocaleString() : '-';
    DOM.metaContract.textContent = record.contractNumber || '-';

    // User Request: Hide "Type" if missing
    if (record.type) {
        DOM.metaType.textContent = record.type;
        DOM.metaType.parentElement.style.display = ''; // Reset to CSS default (flex)
    } else {
        DOM.metaType.parentElement.style.display = 'none';
    }

    // 2. Details (Raw Data)
    if (DOM.detailRawSupplier) DOM.detailRawSupplier.textContent = record.rawSupplierName || '-';
    if (DOM.detailRawBank) DOM.detailRawBank.textContent = record.rawBankName || '-';

    // Legacy: "Update placeholder to show raw name"
    DOM.supplierInput.placeholder = record.rawSupplierName || 'ابحث عن المورد...';
    DOM.bankInput.placeholder = record.rawBankName || 'ابحث عن البنك...';

    // 3. Inputs (Current Decision)
    const dict = State.getDictionaries();
    let supplierName = record.supplierDisplayName || '';
    if (!supplierName && record.supplierId && dict.supplierMap[record.supplierId]) {
        supplierName = dict.supplierMap[record.supplierId].official_name;
    }

    let bankName = record.bankDisplay || '';
    if (!bankName && record.bankId && dict.bankMap[record.bankId]) {
        bankName = dict.bankMap[record.bankId].official_name;
    }

    DOM.supplierInput.value = supplierName;
    DOM.bankInput.value = bankName;

    // Highlight if valid
    // REMOVED GREEN BACKGROUNDS PER USER REQUEST (Legacy behavior preferred cleaner look)
    /*
    if (record.supplierId) {
        DOM.supplierInput.classList.add('has-value', 'bg-green-50', 'border-green-500');
        DOM.supplierInput.classList.remove('border-gray-300');
    } else {
        DOM.supplierInput.classList.remove('has-value', 'bg-green-50', 'border-green-500');
        DOM.supplierInput.classList.add('border-gray-300');
    }

    if (record.bankId) {
        DOM.bankInput.classList.add('has-value', 'bg-green-50', 'border-green-500');
        DOM.bankInput.classList.remove('border-gray-300');
    } else {
        DOM.bankInput.classList.remove('has-value', 'bg-green-50', 'border-green-500');
        DOM.bankInput.classList.add('border-gray-300');
    }
    */

    // Visual reset only (without colors)
    DOM.supplierInput.classList.remove('has-value', 'bg-green-50', 'border-green-500');
    DOM.supplierInput.classList.add('border-gray-300'); // Ensure default border

    DOM.bankInput.classList.remove('has-value', 'bg-green-50', 'border-green-500');
    DOM.bankInput.classList.add('border-gray-300');


    // Reset Chips
    DOM.supplierChips.innerHTML = '';
    DOM.bankChips.innerHTML = '';

    // Update Preview
    Preview.updatePreview();
}

export function clearView() {
    // Clear all inputs
    DOM.metaGuarantee.textContent = '-';
    DOM.metaRecordId.textContent = '-';
    DOM.metaAmount.textContent = '-';
    DOM.metaDate.textContent = '-';
    DOM.metaContract.textContent = '-';
    DOM.supplierInput.value = '';
    DOM.bankInput.value = '';
    DOM.detailRawSupplier.textContent = '-';
    DOM.detailRawBank.textContent = '-';
}

export function updateStats(stats) {
    DOM.countTotal.textContent = stats.total;
    DOM.countApproved.textContent = stats.approved;
    DOM.countPending.textContent = stats.pending;
}

/**
 * Render Suggestions Dropdown
 * @param {HTMLElement} listEl 
 * @param {Array} items 
 * @param {string} type 
 */
export function renderSuggestions(listEl, items, type) {
    if (items.length === 0) {
        // User Request: No text if no suggestions
        listEl.innerHTML = '';
        // Also hide it probably? But caller handles 'open' class based on length.
        // If caller adds 'open' but html is empty, it's just a tiny box.
        // Let's ensure caller doesn't open if empty? 
        // Caller checks `suggestions.length > 0`. So this might not be reached if empty.
        // But if it IS reached with 0, we show nothing.
        return;
    }

    listEl.innerHTML = items.map(item => {
        const scoreHtml = item.score > 0
            ? `<span class="score">${Math.round(item.score * 100)}%</span>`
            : '';
        const id = item.supplier_id || item.bank_id || item.id;
        // Escape helper
        const safeName = (item.name || '').replace(/</g, "&lt;").replace(/>/g, "&gt;");

        return `
            <li class="suggestion-item" data-id="${id}" data-name="${safeName}" data-type="${type}">
                <span>${safeName}</span>
                ${scoreHtml}
            </li>
        `;
    }).join('');
}

/**
 * Show a toast message
 * @param {string} msg 
 * @param {string} type 'success'|'error'|'info'
 */
export function showMessage(msg, type = 'info') {
    const el = DOM.saveMessage;
    el.textContent = msg;
    el.className = `fixed bottom-4 left-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all transform translate-y-0 z-50 ${type === 'error' ? 'bg-red-500' :
        type === 'success' ? 'bg-green-500' : 'bg-gray-700'
        }`;

    el.classList.remove('translate-y-20', 'opacity-0');

    setTimeout(() => {
        el.classList.add('translate-y-20', 'opacity-0');
    }, 3000);
}

/**
 * Render Quick-Select Chips (Smart Suggestions)
 * Ported from legacy decision.js _renderCandidateChips
 * @param {Array} candidates 
 * @param {string} type 'supplier'|'bank'
 */
export function renderCandidateChips(candidates, type) {
    const container = type === 'supplier' ? DOM.supplierChips : DOM.bankChips;
    if (!container) return;

    if (!candidates || candidates.length === 0) {
        // User Request: "If no smart suggestions, no need to write 'No smart suggestions available'"
        container.innerHTML = '';
        return;
    }

    // Take top 3
    const top = candidates.slice(0, 3);

    container.innerHTML = top.map(c => {
        const scoreClass = 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300';
        const id = c.supplier_id || c.bank_id || c.id;

        // Escape helper
        const safeName = (c.name || '').replace(/</g, "&lt;").replace(/>/g, "&gt;");

        return `
            <button type="button" 
                class="chip-btn flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border transition-all hover:scale-105 ${scoreClass}"
                data-action="select-candidate"
                data-type="${type}"
                data-id="${id}"
                data-name="${safeName}"
            >
                <span>${safeName}</span>
                <span class="font-bold opacity-75">${Math.round(c.score * 100)}%</span>
            </button>
        `;
    }).join('');
}

/**
 * Handle Auto-Fill Logic for Supplier
 * 
 * Logic:
 * 1. Always fill the raw name if it exists (User Requirement).
 * 2. This ensures the input is populated for the "Add New" button logic to work.
 * 
 * @param {Array} candidates 
 * @param {string} rawName 
 */
export function handleSupplierAutoFill(candidates, rawName) {
    // User Requirement: "Field contains supplier name from Excel"
    // We ALWAYS fill the raw name if it exists.
    if (rawName) {
        DOM.supplierInput.value = rawName;
    }
}
