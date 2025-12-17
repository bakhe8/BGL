/**
 * Layout Module
 * Caches DOM Elements for quick access
 */

export const DOM = {};

export function initDOM() {
    Object.assign(DOM, {
        // Inputs
        bankInput: document.getElementById('bankInput'),
        supplierInput: document.getElementById('supplierInput'),
        bankSuggestions: document.getElementById('bankSuggestions'),
        supplierSuggestions: document.getElementById('supplierSuggestions'),

        // Meta display
        metaSessionId: document.getElementById('metaSessionId'),
        metaRecordId: document.getElementById('metaRecordId'),
        metaGuarantee: document.getElementById('metaGuarantee'),
        metaDate: document.getElementById('metaDate'),
        metaAmount: document.getElementById('metaAmount'),
        metaContract: document.getElementById('metaContract'),
        metaType: document.getElementById('metaType'),
        detailRawSupplier: document.getElementById('detailRawSupplier'),
        detailRawBank: document.getElementById('detailRawBank'),
        detailRawBank: document.getElementById('detailRawBank'),
        // metaRecordIndex: document.getElementById('metaRecordIndex'), // Removed

        // Filter Badges
        badgeTotal: document.getElementById('badgeTotal'),
        badgeApproved: document.getElementById('badgeApproved'),
        badgePending: document.getElementById('badgePending'),

        // Chips
        supplierChips: document.getElementById('supplierChips'),
        bankChips: document.getElementById('bankChips'),

        // Counters
        countTotal: document.getElementById('countTotal'),
        countApproved: document.getElementById('countApproved'),
        countPending: document.getElementById('countPending'),
        currentIndexDisplay: document.getElementById('currentIndex'),
        totalCountDisplay: document.getElementById('totalCount'),

        // Buttons
        btnPrev: document.getElementById('btnPrev'),
        btnNext: document.getElementById('btnNext'),
        btnSaveNext: document.getElementById('btnSaveNext'),
        btnAddSupplier: document.getElementById('btnAddSupplier'),
        supplierAddError: document.getElementById('supplierAddError'),
        toggleDetails: document.getElementById('toggleDetails'),
        expandedDetails: document.getElementById('expandedDetails'),
        refreshBtn: document.getElementById('refreshBtn'),
        btnRecalcAll: document.getElementById('btnRecalcAll'),
        btnPrintAll: document.getElementById('btnPrintAll'),
        btnToggleImport: document.getElementById('btnToggleImport'),
        hiddenFileInput: document.getElementById('hiddenFileInput'),

        // Messages
        saveMessage: document.getElementById('saveMessage'),

        // Custom Modal
        confirmModal: document.getElementById('confirmModal'),
        confirmModalMessage: document.getElementById('confirmModalMessage'),
        btnConfirmOk: document.getElementById('btnConfirmOk'),
        btnConfirmCancel: document.getElementById('btnConfirmCancel'),
    });

    console.log('[Layout] DOM Initialized');
}
