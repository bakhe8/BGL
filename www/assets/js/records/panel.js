/**
 * =============================================================================
 * Records Panel Logic
 * =============================================================================
 * 
 * 📚 DOCUMENTATION: docs/matching-system-guide.md
 * 
 * PURPOSE:
 * --------
 * This module handles the "Inline Panel" that opens when clicking a record.
 * It fetches supplier/bank candidates from the API and displays them for
 * user selection.
 * 
 * KEY BUSINESS RULES:
 * -------------------
 * 1. EMPTY FIELDS ARE VALID: If the API returns no candidates (score < 70%),
 *    the input field is intentionally left empty. This is NOT a bug.
 * 
 * 2. API RESPONSE STRUCTURE (CRITICAL):
 *    The API returns: data.supplier.candidates and data.bank.candidates
 *    NOT: data.suppliers or data.banks
 *    See: docs/api-response-structure.md
 * 
 * 3. AUTO-FILL BEHAVIOR:
 *    - If candidates exist → first candidate is pre-filled
 *    - If candidates empty → field stays empty, user must add manually
 * 
 * COMMON DEBUGGING SCENARIOS:
 * ---------------------------
 * Q: Why is the supplier field empty?
 * A: Run `php debug_supplier_match.php` to see if any supplier scores >= 70%
 * 
 * Q: Why does the bank show but supplier doesn't?
 * A: Different matching rules. Banks use short codes, suppliers use fuzzy match.
 * 
 * Q: The API returns data but fields are empty?
 * A: Check if you're accessing data.supplier.candidates (not data.suppliers)
 * 
 * @see docs/matching-system-guide.md for full documentation
 * @see docs/api-response-structure.md for API structure
 * =============================================================================
 */

window.BGL = window.BGL || {};

window.BGL.Panel = {

    /**
     * Close any open panel
     */
    closePanel() {
        const State = BGL.State;

        State.selectedRecord = null;
        BGL.Table.render();
    },

    /**
     * Open panel for a record
     */
    async openPanel(record) {
        const State = BGL.State;

        if (State.selectedRecord && State.selectedRecord.id === record.id) {
            this.closePanel();
            return;
        }

        State.selectedRecord = record;
        BGL.Table.render(); // This renders the panel row (empty container)

        // Identify container (it exists now)
        const container = document.getElementById('panel-content');
        if (!container) return;

        try {
            const json = await api.get(`/api/records/${record.id}/candidates`);

            if (!json.success) throw new Error(json.message);

            const data = json.data;
            const conflicts = json.conflicts || [];

            /**
             * ⚠️ API RESPONSE STRUCTURE - CRITICAL DOCUMENTATION
             * ===================================================
             * 
             * The /api/records/{id}/candidates endpoint returns:
             * 
             * {
             *   "success": true,
             *   "data": {
             *     "supplier": { "normalized": "...", "candidates": [...] },  // ← NOT "suppliers"
             *     "bank": { "normalized": "...", "candidates": [...] },      // ← NOT "banks"
             *     "conflicts": [...]
             *   }
             * }
             * 
             * PREVIOUS BUG (Fixed 2025-12-13):
             * --------------------------------
             * The code was incorrectly accessing:
             *   - data.suppliers (WRONG - undefined)
             *   - data.banks (WRONG - undefined)
             * 
             * This caused all suggestions to appear empty even when the API
             * returned valid candidates (e.g., "بنك الرياض" with score=1.0).
             * 
             * CORRECT ACCESS PATTERN:
             *   - data.supplier.candidates (for supplier suggestions)
             *   - data.bank.candidates (for bank suggestions)
             * 
             * See: docs/api-response-structure.md for full API documentation.
             */
            const candidates = data.supplier?.candidates || [];
            const bankCandidates = data.bank?.candidates || [];

            this.renderPanelContent(container, record, candidates, bankCandidates, conflicts);

        } catch (e) {
            console.error(e);
            container.innerHTML = `<div class="col-span-12 text-center text-red-500 py-4">فشل تحميل التفاصيل: ${e.message}</div>`;
        }
    },

    /**
     * Render the internal content of the panel
     */
    renderPanelContent(container, record, candidates, bankCandidates, conflicts) {
        // Safe logger
        console.log('Rendering panel for record', record.id, { candidates, bankCandidates });

        const State = BGL.State;
        // Local safe escape to avoid dependency issues during render
        const escapeHtml = (unsafe) => {
            if (typeof unsafe !== 'string') return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        };

        try {
            // Logic for Block Button
            let blockBtnHTML = '';
            const topCandidate = candidates && candidates.length > 0 ? candidates[0] : null;

            if (topCandidate) {
                const safeCandName = escapeHtml(topCandidate.name);
                blockBtnHTML = `
                    <div class="pt-4 border-t border-gray-100 flex justify-between items-center mt-auto">
                        <span class="text-xs text-gray-400">اقتراح خاطئ؟ (Blocking):</span>
                        <button id="btnRejectSupplier" class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded transition-colors" 
                            data-blocked-id="${topCandidate.supplier_id}" 
                            data-blocked-name="${safeCandName}"
                            title="لن يتم اقتراح هذا المورد لهذا النص مرة أخرى">
                            حظر الاقتراح: "${safeCandName}"
                        </button>
                    </div>`;
            } else {
                blockBtnHTML = `<div class="pt-4 border-t border-gray-100 hidden"></div>`;
            }

            // Auto-fill Calculation (Pre-HTML Injection)
            let initSupplierVal = '';
            if (record.supplier_id) {
                // If ID exists, Autocomplete.setup handles it via preselect
            } else if (topCandidate) {
                initSupplierVal = escapeHtml(topCandidate.name);
                State.selectedSupplierId = topCandidate.supplier_id;
            }

            let initBankVal = '';
            const topBank = bankCandidates && bankCandidates.length > 0 ? bankCandidates[0] : null;
            if (!record.bank_id && topBank) {
                initBankVal = escapeHtml(topBank.name);
                State.selectedBankId = topBank.bank_id || topBank.id;
            }

            // Prepare conflict warnings
            let alerts = '';
            if (conflicts && conflicts.length > 0) {
                alerts = `<div class="bg-yellow-50 border-r-4 border-yellow-400 p-3 mb-4 rounded shadow-sm">
             <div class="flex">
               <div class="flex-shrink-0"><span class="text-yellow-400 text-xl">⚠️</span></div>
               <div class="mr-3">
                 <h3 class="text-sm font-medium text-yellow-800">تنبيهات المطابقة</h3>
                 <ul class="mt-1 text-sm text-yellow-700 list-disc list-inside">
                   ${conflicts.map(c => `<li>${c}</li>`).join('')}
                 </ul>
               </div>
             </div>
           </div>`;
            }

            // HTML Generation
            container.innerHTML = `
            <!-- Right: Supplier Selection -->
            <div class="col-span-12 md:col-span-6 bg-white p-6 rounded-xl shadow-md border border-gray-100">
               ${alerts}
               
               <div class="flex items-center justify-between border-b pb-3 mb-5">
                 <h4 class="text-lg font-bold text-gray-800">1. اختيار المورد</h4>
                 <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">مطلوب</span>
               </div>

               <div class="mb-5">
                 <label class="block text-sm font-medium text-gray-500 mb-2">المورد المقترح (من الفاتورة)</label>
                 <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 font-mono text-sm text-gray-700 select-all" title="النص كما ورد في الملف">
                   ${escapeHtml(record.rawSupplierName)}
                 </div>
               </div>

               <!-- Combobox Supplier -->
               <div class="relative mb-5" id="supplier-combobox-wrapper">
                 <label class="block text-sm font-medium text-gray-700 mb-2">ابحث عن المورد في القاموس</label>
                 <div class="flex gap-2">
                    <div class="relative flex-1">
                       <input type="text" id="supplierInput" 
                         class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-right focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                         placeholder="اكتب اسم المورد للبحث..." value="${initSupplierVal}" autocomplete="off">
                       <!-- Spinner -->
                       <div id="supplierSpinner" class="hidden absolute left-3 top-3.5">
                          <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                       </div>
                       <!-- Suggestions List -->
                       <ul id="supplierSuggestions" class="absolute z-50 w-full bg-white border border-gray-200 rounded-b-lg shadow-xl max-h-60 overflow-y-auto hidden mt-1"></ul>
                    </div>
                    <button id="btnCreateSupplier" class="bg-gray-50 hover:bg-gray-100 text-gray-700 px-4 py-2 rounded-lg border border-gray-300 transition-colors font-medium text-sm whitespace-nowrap" title="إضافة مورد جديد للقواميس">
                       + جديد
                    </button>
                 </div>
               </div>
               
               <!-- Reject Button (Blacklist) -->
                ${blockBtnHTML}

            </div>

            <!-- Middle: Bank Selection -->
            <div class="col-span-12 md:col-span-6 bg-white p-6 rounded-xl shadow-md border border-gray-100 flex flex-col">
               <div class="flex items-center justify-between border-b pb-3 mb-5">
                 <h4 class="text-lg font-bold text-gray-800">2. اختيار البنك</h4>
                 <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">مطلوب</span>
               </div>

               <div class="mb-5">
                 <label class="block text-sm font-medium text-gray-500 mb-2">البنك (من الفاتورة)</label>
                 <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 font-mono text-sm text-gray-700 select-all">
                   ${escapeHtml(record.rawBankName)}
                 </div>
               </div>

               <!-- Combobox Bank -->
               <div class="relative mb-5 flex-1">
                 <label class="block text-sm font-medium text-gray-700 mb-2">البنك المعتمد</label>
                 <div class="relative">
                    <input type="text" id="bankInput" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-right focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all shadow-sm"
                       placeholder="اكتب اسم البنك..." value="${initBankVal}" autocomplete="off">
                    <ul id="bankSuggestions" class="absolute z-50 w-full bg-white border border-gray-200 rounded-b-lg shadow-xl max-h-60 overflow-y-auto hidden mt-1"></ul>
                 </div>
                 <!-- Read-only Display Used for submitting -->
                 <div id="bankDisplayBox" class="mt-3 text-sm text-purple-700 font-semibold bg-purple-50 border border-purple-100 px-3 py-2 rounded-lg hidden flex items-center gap-2">
                    <span>🏦</span>
                    <span class="font-bold"></span>
                 </div>
               </div>
            </div>

            <!-- Footer: Action Buttons -->
            <div class="col-span-12 flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-200 mt-2">
               <div class="flex items-center gap-4">
                  <span id="decisionMsg" class="font-bold text-sm transition-all duration-300"></span>
               </div>
               
               <div class="flex gap-3">
                  <button onclick="BGL.Panel.closePanel()" class="px-5 py-2.5 text-gray-600 hover:bg-white hover:shadow-sm hover:text-gray-800 rounded-lg border border-transparent hover:border-gray-200 transition-all">إلغاء</button>
                  
                  <div class="h-6 w-px bg-gray-300 self-center mx-1"></div>


                  <button onclick="BGL.Panel.saveDecision('approved')" id="btnApprove" class="px-8 py-2.5 bg-blue-600 text-white hover:bg-blue-700 rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-95 flex items-center gap-2 font-bold">
                     <span>اعتماد وحفظ</span>
                     <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  </button>
               </div>
            </div>
            `;

            // Initialize References
            const supplierInput = container.querySelector('#supplierInput');
            const supplierSuggestions = container.querySelector('#supplierSuggestions');
            const createSupplierBtn = container.querySelector('#btnCreateSupplier');
            const btnRejectSupplier = container.querySelector('#btnRejectSupplier');

            const bankInput = container.querySelector('#bankInput');
            const bankSuggestions = container.querySelector('#bankSuggestions');
            const bankDisplayBox = container.querySelector('#bankDisplayBox');

            State.decisionMsg = container.querySelector('#decisionMsg');

            // Initialize Autocomplete (from autocomplete.js)
            if (typeof BGL.Autocomplete !== 'undefined') {
                BGL.Autocomplete.setup(supplierInput, supplierSuggestions, candidates, 'supplier', record);
                BGL.Autocomplete.setup(bankInput, bankSuggestions, bankCandidates, 'bank', record);
            }

            // Bind Create Supplier
            createSupplierBtn.addEventListener('click', () => {
                if (typeof BGL.Overlay !== 'undefined') {
                    BGL.Overlay.open(record.rawSupplierName);
                }
            });

            // Bind Reject Supplier
            if (btnRejectSupplier) {
                btnRejectSupplier.addEventListener('click', (e) => {
                    const blockedId = e.target.closest('button').dataset.blockedId;
                    const blockedName = e.target.closest('button').dataset.blockedName;

                    if (blockedId) {
                        State.selectedSupplierBlockedId = parseInt(blockedId);
                        alert(`سيتم منع اقتراح "${blockedName}" لهذا النص ("${record.rawSupplierName}") مستقبلاً.`);
                    }
                });
            }

            // Scroll to panel
            setTimeout(() => {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);

        } catch (err) {
            console.error('Critical Error in RenderPanelContent', err);
            container.innerHTML = `<div class="col-span-12 p-8 text-center text-red-500">حدث خطأ في عرض الواجهة: ${err.message}</div>`;
        }
    },

    /**
     * Save the decision (Approve/Reject)
     */
    async saveDecision(status = 'approved') {
        const State = BGL.State;

        if (!State.selectedRecord) return;

        const feedback = State.decisionMsg; // Use cached ref from render
        const btn = document.getElementById('btnApprove');

        // Validation
        if (status === 'approved') {
            if (!State.selectedSupplierId) {
                if (feedback) {
                    feedback.textContent = '⚠️ يجب اختيار مورد من القائمة (أو إنشاء جديد)';
                    feedback.className = 'text-red-600 animate-pulse';
                }
                return;
            }
            if (!State.selectedBankId) {
                if (feedback) {
                    feedback.textContent = '⚠️ يجب اختيار بنك من القائمة';
                    feedback.className = 'text-red-600 animate-pulse';
                }
                return;
            }
        }

        // UI Loading
        if (feedback) {
            feedback.textContent = 'جارٍ الحفظ...';
            feedback.className = 'text-blue-600';
        }
        if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }

        // Payload
        const payload = {
            matchStatus: status,
            supplier_id: State.selectedSupplierId,
            bank_id: State.selectedBankId,
            decisionResult: 'manual'
        };

        try {
            const res = await api.post(`/api/records/${State.selectedRecord.id}/decision`, payload);

            if (res.success) {
                if (feedback) {
                    feedback.textContent = 'تم الحفظ بنجاح ✓';
                    feedback.className = 'text-green-600 font-bold';
                }

                // Update Local Data
                const idx = State.records.findIndex(r => r.id === State.selectedRecord.id);
                if (idx !== -1) {
                    State.records[idx].matchStatus = status;
                    State.records[idx].match_status = status; // Legacy compat

                    if (status === 'approved') {
                        State.records[idx].supplier_id = State.selectedSupplierId;
                        State.records[idx].bank_id = State.selectedBankId;
                        State.records[idx].decisionResult = 'manual';
                    }
                }

                // Close after delay
                setTimeout(() => {
                    this.closePanel();
                }, 800);
            } else {
                throw new Error(res.message);
            }
        } catch (e) {
            console.error(e);
            if (feedback) {
                feedback.textContent = 'خطأ في الحفظ: ' + e.message;
                feedback.className = 'text-red-600 font-bold';
            }
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        }
    }

};
