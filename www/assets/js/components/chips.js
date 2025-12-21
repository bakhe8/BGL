/**
 * BGL Chips Component
 * Handles chip button clicks for supplier/bank selection
 * 
 * @since v2.0 - Extracted from decision.js
 * @usage BGL.setupChips('.chip-btn', callbacks)
 */

window.BGL = window.BGL || {};

/**
 * Setup chip button click handlers
 * 
 * @param {string} selector - CSS selector for chip buttons (default: '.chip-btn')
 * @param {Object} callbacks - Object with onSupplierSelect and onBankSelect functions
 */
window.BGL.setupChips = function (selector = '.chip-btn', callbacks = {}) {
    document.querySelectorAll(selector).forEach(chip => {
        chip.addEventListener('click', () => {
            const type = chip.dataset.type;
            const id = chip.dataset.id;
            const name = chip.dataset.name;

            if (type === 'supplier') {
                // Update input fields
                const supplierInput = document.getElementById('supplierInput');
                const supplierId = document.getElementById('supplierId');
                if (supplierInput) supplierInput.value = name;
                if (supplierId) supplierId.value = id;

                // Call callback if provided
                if (typeof callbacks.onSupplierSelect === 'function') {
                    callbacks.onSupplierSelect(id, name);
                }
            } else if (type === 'bank') {
                // Update input fields
                const bankInput = document.getElementById('bankInput');
                const bankId = document.getElementById('bankId');
                if (bankInput) bankInput.value = name;
                if (bankId) bankId.value = id;

                // Call callback if provided
                if (typeof callbacks.onBankSelect === 'function') {
                    callbacks.onBankSelect(id, name);
                }
            }
        });
    });
};

console.log('✓ BGL.setupChips loaded');
