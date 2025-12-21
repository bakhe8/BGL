/**
 * BGL Decision Page
 * Main logic for the decision page - orchestrates all components and features
 * 
 * @since v2.0 - Refactored from original decision.js
 * @requires core/api.js, core/dialog.js
 * @requires components/autocomplete.js, components/chips.js, components/dropdown.js
 * @requires features/add-supplier.js, features/batch-print.js, features/import-excel.js
 * @requires features/smart-paste.js, features/recalculate.js
 */

(function () {
    'use strict';

    // Read configuration from global object
    const config = window.DecisionApp || {};
    const suppliers = config.suppliers || [];
    const banks = config.banks || [];
    const recordId = config.recordId;
    const nextUrl = config.nextUrl;

    // ═══════════════════════════════════════════════════════════════════
    // LETTER PREVIEW HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Update letter font based on language
     */
    function updateLetterFont(name, elementOrId) {
        const el = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
        if (!el) return;

        el.textContent = name;

        const hasArabic = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/.test(name);
        if (hasArabic) {
            el.style.removeProperty('font-family');
            el.style.removeProperty('direction');
            el.style.removeProperty('display');
        } else {
            el.style.fontFamily = "'Arial', sans-serif";
            el.style.direction = "ltr";
            el.style.display = "inline-block";
        }
    }

    /**
     * Update bank details in letter preview
     */
    function updateBankDetails(bankId, bankName = null) {
        const bank = banks.find(b => b.id == bankId);

        // Update Header Name
        if (bankName || bank) {
            const name = bankName || (bank ? (bank.official_name || bank.name) : '');
            const letterBank = document.getElementById('letterBank');
            if (letterBank) letterBank.textContent = name;
        }

        // Update Details Section
        const detailsContainer = document.getElementById('letterBankDetails');
        if (!detailsContainer) return;

        if (bank) {
            const toHindi = (str) => String(str).replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);

            let html = `<div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${bank.department || 'إدارة الضمانات'}</div>`;
            const addr1 = bank.address_line_1 || 'المقر الرئيسي';
            html += `<div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${toHindi(addr1)}</div>`;
            if (bank.address_line_2) {
                html += `<div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${toHindi(bank.address_line_2)}</div>`;
            }
            if (bank.contact_email) {
                html += `<div><span style="text-shadow: 0 0 1px #333, 0 0 1px #333;">البريد الالكتروني:</span> ${bank.contact_email}</div>`;
            }
            detailsContainer.innerHTML = html;
        } else {
            detailsContainer.innerHTML = `
                <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">إدارة الضمانات</div>
                <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">المقر الرئيسي</div>
            `;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // INITIALIZE COMPONENTS
    // ═══════════════════════════════════════════════════════════════════

    // Chips with letter preview callbacks
    if (window.BGL && window.BGL.setupChips) {
        window.BGL.setupChips('.chip-btn', {
            onSupplierSelect: (id, name) => updateLetterFont(name, 'letterSupplier'),
            onBankSelect: (id, name) => updateBankDetails(id, name)
        });
    }

    // Autocomplete for suppliers
    if (window.BGL && window.BGL.setupAutocomplete) {
        window.BGL.setupAutocomplete('supplierInput', 'supplierSuggestions', 'supplierId', suppliers, 'official_name',
            (id, name) => updateLetterFont(name, 'letterSupplier')
        );

        window.BGL.setupAutocomplete('bankInput', 'bankSuggestions', 'bankId', banks, 'official_name',
            (id, name) => updateBankDetails(id, name)
        );
    }

    // Session dropdown
    if (window.BGL && window.BGL.setupDropdown) {
        window.BGL.setupDropdown('metaSessionId', 'sessionDropdown', 'sessionSearch', 'sessionList');
    }

    // ═══════════════════════════════════════════════════════════════════
    // INITIALIZE FEATURES
    // ═══════════════════════════════════════════════════════════════════

    if (window.BGL && window.BGL.features) {
        // Add Supplier
        if (window.BGL.features.initAddSupplier) {
            window.BGL.features.initAddSupplier(suppliers, config.rawSupplierName || '', (newSupplier) => {
                updateLetterFont(newSupplier.official_name, 'letterSupplier');
            });
        }

        // Batch Print
        if (window.BGL.features.initBatchPrint) {
            window.BGL.features.initBatchPrint(config.sessionId);
        }

        // Import Excel
        if (window.BGL.features.initImportExcel) {
            window.BGL.features.initImportExcel();
        }

        // Smart Paste
        if (window.BGL.features.initSmartPaste) {
            window.BGL.features.initSmartPaste();
        }

        // Recalculate
        if (window.BGL.features.initRecalculate) {
            window.BGL.features.initRecalculate();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SAVE & NEXT (Page-specific logic)
    // ═══════════════════════════════════════════════════════════════════

    const btnSaveNext = document.getElementById('btnSaveNext');
    if (btnSaveNext && recordId) {
        btnSaveNext.addEventListener('click', async () => {
            const msg = document.getElementById('saveMessage');

            try {
                const res = await fetch(`/api/records/${recordId}/decision`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        match_status: 'ready',
                        supplier_id: document.getElementById('supplierId').value || null,
                        bank_id: document.getElementById('bankId').value || null,
                        supplier_name: document.getElementById('supplierInput').value,
                        bank_name: document.getElementById('bankInput').value
                    })
                });
                const json = await res.json();

                if (json.success) {
                    msg.textContent = '';
                    msg.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> تم الحفظ</span>';
                    if (window.lucide) lucide.createIcons();
                    msg.style.color = '#16a34a';

                    if (nextUrl) {
                        setTimeout(() => window.location.href = nextUrl, 300);
                    } else {
                        setTimeout(() => window.location.reload(), 300);
                    }
                } else {
                    msg.textContent = 'خطأ: ' + (json.message || 'فشل الحفظ');
                    msg.style.color = '#dc2626';
                }
            } catch (err) {
                msg.textContent = 'خطأ في الاتصال';
                msg.style.color = '#dc2626';
            }
        });
    }

    console.log('✓ Decision Page initialized');
})();
