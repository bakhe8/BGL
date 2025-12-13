/**
 * =============================================================================
 * Decision Page Logic
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Single-record decision view for approving supplier and bank selections.
 * Shows one record at a time with Previous/Next navigation.
 * 
 * FEATURES:
 * ---------
 * 1. Single record display with all details
 * 2. Bank and Supplier autocomplete
 * 3. Previous/Next navigation
 * 4. Auto-save and move to next record
 * 5. Status tracking (approved, pending counts)
 * 
 * =============================================================================
 */

/**
 * Window Namespace for BGL (Bank Guarantee Letters) System
 * @namespace BGL
 */
window.BGL = window.BGL || {};

/**
 * State Management for decision.html
 * Since state.js was archived, we initialize the required state here.
 * @namespace BGL.State
 */
window.BGL.State = {
    supplierCache: [],
    supplierMap: {},
    bankMap: {}
};

/**
 * Decision Module
 * 
 * Manages the core decision-making interface where users review records,
 * select suppliers and banks, and approve matches.
 * 
 * Key Responsibilities:
 * - Fetching records and candidates from API
 * - Managing local state for current record and navigation
 * - Handling autocomplete logic for Banks and Suppliers
 * - Submitting decisions and handling auto-propagation
 * - Managing the new "Import" and "Recalculate" features
 * 
 * @namespace BGL.Decision
 */
window.BGL.Decision = {

    // Current state
    records: [],
    currentIndex: 0,
    selectedSupplierId: null,
    selectedSupplierName: null,
    selectedBankId: null,
    selectedBankName: null,

    // Candidates for current record
    supplierCandidates: [],
    bankCandidates: [],

    // DOM References
    DOM: {},

    /**
     * Initialize the decision page layout and functionality.
     * 
     * Actions:
     * 1. Caches DOM elements for performance
     * 2. Sets up all event listeners (buttons, inputs, shortcuts)
     * 3. Loads initial data from API
     * 
     * @async
     * @returns {Promise<void>}
     */
    async init() {
        console.log('[Decision] Initializing...');

        // Cache DOM elements
        this.DOM = {
            // Inputs
            bankInput: document.getElementById('bankInput'),
            supplierInput: document.getElementById('supplierInput'),
            bankSuggestions: document.getElementById('bankSuggestions'),
            supplierSuggestions: document.getElementById('supplierSuggestions'),

            // Meta display
            metaRecordId: document.getElementById('metaRecordId'),
            metaGuarantee: document.getElementById('metaGuarantee'),
            metaDate: document.getElementById('metaDate'),
            metaAmount: document.getElementById('metaAmount'),
            detailRawSupplier: document.getElementById('detailRawSupplier'),
            detailRawBank: document.getElementById('detailRawBank'),

            // Counters
            countTotal: document.getElementById('countTotal'),
            countApproved: document.getElementById('countApproved'),
            countPending: document.getElementById('countPending'),
            currentIndex: document.getElementById('currentIndex'),
            totalCount: document.getElementById('totalCount'),

            // Buttons
            btnPrev: document.getElementById('btnPrev'),
            btnNext: document.getElementById('btnNext'),
            btnSaveNext: document.getElementById('btnSaveNext'),
            btnAddSupplier: document.getElementById('btnAddSupplier'),
            toggleDetails: document.getElementById('toggleDetails'),
            expandedDetails: document.getElementById('expandedDetails'),

            // Messages
            saveMessage: document.getElementById('saveMessage')
        };

        // Disable add supplier button initially
        this.DOM.btnAddSupplier.disabled = true;
        this._pendingNewSupplierName = null;

        // Setup event listeners
        this._setupEventListeners();

        // Load data
        await this._loadData();

        console.log('[Decision] Ready. Records:', this.records.length);
    },

    /**
     * Setup all DOM event listeners.
     * 
     * Handlers attached:
     * - Navigation (Prev/Next buttons, Keyboard arrows)
     * - Actions (Save & Next, Refresh, Recalculate, Import Toggle)
     * - Input logic (Autocomplete triggering on input/focus)
     * - Filtering (Status badges clicks)
     * - File Upload (Import form submission)
     * 
     * @private
     */
    _setupEventListeners() {
        const { DOM } = this;

        // Navigation
        DOM.btnPrev.addEventListener('click', () => this.navigatePrev());
        DOM.btnNext.addEventListener('click', () => this.navigateNext());
        DOM.btnSaveNext.addEventListener('click', () => this.saveAndNext());

        // Toggle details
        DOM.toggleDetails.addEventListener('click', () => {
            DOM.expandedDetails.classList.toggle('hidden');
            DOM.toggleDetails.classList.toggle('open');
        });

        // Bank input
        DOM.bankInput.addEventListener('input', (e) => this._handleBankInput(e.target.value));
        DOM.bankInput.addEventListener('focus', () => this._showBankSuggestions());
        DOM.bankInput.addEventListener('blur', () => {
            setTimeout(() => DOM.bankSuggestions.classList.remove('open'), 200);
        });

        // Supplier input
        DOM.supplierInput.addEventListener('input', (e) => this._handleSupplierInput(e.target.value));
        DOM.supplierInput.addEventListener('focus', () => this._showSupplierSuggestions());
        DOM.supplierInput.addEventListener('blur', () => {
            setTimeout(() => DOM.supplierSuggestions.classList.remove('open'), 200);
        });

        // Add supplier button - creates new supplier from typed name
        DOM.btnAddSupplier.addEventListener('click', () => this._addNewSupplier());

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;

            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                this.navigateNext();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                this.navigatePrev();
            } else if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                this.saveAndNext();
            }
        });

        // Status badge clicks for filtering
        document.getElementById('badgePending')?.addEventListener('click', () => {
            this._filterRecords('pending');
        });
        document.getElementById('badgeApproved')?.addEventListener('click', () => {
            this._filterRecords('approved');
        });
        document.getElementById('badgeTotal')?.addEventListener('click', () => {
            this._filterRecords('all');
        });

        // Refresh button - reload all data
        document.getElementById('refreshBtn')?.addEventListener('click', () => {
            this._showMessage('جارٍ التحديث...', 'info');
            this._loadData();
        });

        // Toggle Import Card
        document.getElementById('btnToggleImport')?.addEventListener('click', () => {
            const card = document.getElementById('importCard');
            if (card) {
                card.style.display = card.style.display === 'none' ? 'block' : 'none';
            }
        });

        // Recalculate All button
        document.getElementById('btnRecalcAll')?.addEventListener('click', async () => {
            if (!confirm('هل أنت متأكد من إعادة المطابقة لجميع السجلات؟')) return;

            const btn = document.getElementById('btnRecalcAll');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '...';
            }

            try {
                const res = await api.post('/api/records/recalculate');
                if (res.success) {
                    this._showMessage(`تمت العملية: ${res.data?.processed || 0} سجل`, 'success');
                    await this._loadData();
                } else {
                    throw new Error(res.message);
                }
            } catch (e) {
                this._showMessage('خطأ: ' + e.message, 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '🔃 إعادة مطابقة';
                }
            }
        });

        // Upload Form
        document.getElementById('uploadForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fileInput = document.getElementById('fileInput');
            const uploadMsg = document.getElementById('uploadMsg');
            const uploadError = document.getElementById('uploadError');

            if (!fileInput?.files?.length) {
                if (uploadError) {
                    uploadError.textContent = 'اختر ملف أولاً';
                    uploadError.style.display = 'block';
                }
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            if (uploadMsg) uploadMsg.textContent = 'جارٍ الرفع...';
            if (uploadError) uploadError.style.display = 'none';

            try {
                const res = await fetch('/api/import/excel', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.success) {
                    if (uploadMsg) uploadMsg.textContent = `✓ تم استيراد ${result.data?.imported || 0} سجل`;
                    document.getElementById('importCard').style.display = 'none';
                    await this._loadData();
                } else {
                    throw new Error(result.message);
                }
            } catch (e) {
                if (uploadError) {
                    uploadError.textContent = e.message;
                    uploadError.style.display = 'block';
                }
                if (uploadMsg) uploadMsg.textContent = '';
            }
        });
    },

    /**
     * Load records and dictionary data from the server.
     * 
     * Logic:
     * 1. Fetches all records from `/api/records`
     * 2. Filters records to show ONLY the latest session (by `sessionId`)
     *    to focus the user on the most recently imported batch.
     * 3. Loads Supplier and Bank dictionaries for autocomplete.
     * 4. Updates status counts (Total, ready, pending).
     * 5. Navigates to the first "Pending" record automatically.
     * 
     * @async
     * @returns {Promise<void>}
     */
    async _loadData() {
        try {
            // Load records
            const recordsRes = await api.get('/api/records');
            if (!recordsRes.success) throw new Error(recordsRes.message);

            const allRecords = recordsRes.data || [];

            // Filter to latest session only (for focused review)
            if (allRecords.length > 0) {
                // Find the latest sessionId
                const latestSessionId = Math.max(...allRecords.map(r => r.sessionId || 0));
                this.records = allRecords.filter(r => r.sessionId === latestSessionId);
                console.log(`[Decision] Filtered to session ${latestSessionId}: ${this.records.length}/${allRecords.length} records`);
            } else {
                this.records = allRecords;
            }

            // Load dictionaries
            await this._loadDictionaries();

            // Update counts
            this._updateCounts();

            // Show first pending record
            this._goToFirstPending();

            this._showMessage(`تم تحميل ${this.records.length} سجل`, 'success');

        } catch (e) {
            console.error('[Decision] Load failed:', e);
            this._showMessage('خطأ في تحميل البيانات: ' + e.message, 'error');
        }
    },

    /**
     * Load supplier and bank dictionaries
     */
    async _loadDictionaries() {
        try {
            // Suppliers
            const suppRes = await api.get('/api/dictionary/suppliers');
            if (suppRes.success) {
                BGL.State.supplierCache = suppRes.data || [];
                BGL.State.supplierMap = {};
                BGL.State.supplierCache.forEach(s => {
                    BGL.State.supplierMap[s.id] = s;
                });
            }

            // Banks
            const bankRes = await api.get('/api/dictionary/banks');
            if (bankRes.success && Array.isArray(bankRes.data)) {
                BGL.State.bankMap = {};
                bankRes.data.forEach(b => {
                    BGL.State.bankMap[b.id] = b;
                });
            }
        } catch (e) {
            console.error('[Decision] Dictionary load failed:', e);
        }
    },

    /**
     * Update the status counters in the header.
     * 
     * Calculates:
     * - Approved: Records with status 'ready' or 'approved'
     * - Pending: Everything else
     * 
     * Updates the DOM elements directly.
     * @private
     */
    _updateCounts() {
        const total = this.records.length;
        // API uses 'ready' for approved records, but some may still have 'approved'
        const completedStatuses = ['ready', 'approved'];
        const approved = this.records.filter(r => completedStatuses.includes(r.matchStatus)).length;
        const pending = total - approved;

        this.DOM.countTotal.textContent = total;
        this.DOM.countApproved.textContent = approved;
        this.DOM.countPending.textContent = pending;
        this.DOM.totalCount.textContent = total;
    },

    /**
     * Check if a specific record is considered "completed".
     * 
     * @private
     * @param {Object} record - The record object to check
     * @returns {boolean} True if status is 'ready' or 'approved'
     */
    _isCompleted(record) {
        return ['ready', 'approved'].includes(record.matchStatus);
    },

    /**
     * Go to first pending record
     */
    _goToFirstPending() {
        const pendingIndex = this.records.findIndex(r => !this._isCompleted(r));
        this.currentIndex = pendingIndex >= 0 ? pendingIndex : 0;
        this._displayCurrentRecord();
    },

    /**
     * Filter the view to show a specific category of records.
     * 
     * Currently implemented by navigating to the first record matching
     * the requested status, rather than hiding others.
     * 
     * @private
     * @param {string} status - 'pending', 'approved', or 'all'
     */
    _filterRecords(status) {
        // For now, just navigate to first matching record
        let targetIndex = -1;

        if (status === 'pending') {
            targetIndex = this.records.findIndex(r => !this._isCompleted(r));
        } else if (status === 'approved') {
            targetIndex = this.records.findIndex(r => this._isCompleted(r));
        } else {
            targetIndex = 0;
        }

        if (targetIndex >= 0) {
            this.currentIndex = targetIndex;
            this._displayCurrentRecord();
        }
    },

    /**
     * Render the current record details into the UI.
     * 
     * Actions:
     * - Updates Meta info (ID, Amount, Date, Guarantee #)
     * - Updates Input fields with current selections
     * - Fetches and displays Candidates (Suggestions) from API
     * - Auto-fills inputs if high-confidence candidates exist and no selection is made
     * - Updates Navigation button states (disabled/enabled)
     * 
     * @async
     * @returns {Promise<void>}
     */
    async _displayCurrentRecord() {
        const record = this.records[this.currentIndex];
        if (!record) {
            this._showMessage('لا توجد سجلات', 'error');
            return;
        }

        // Update index display
        this.DOM.currentIndex.textContent = this.currentIndex + 1;

        // Update meta
        this.DOM.metaRecordId.textContent = record.id;
        this.DOM.metaGuarantee.textContent = record.guaranteeNumber || '-';
        this.DOM.metaDate.textContent = record.expiryDate || record.date || '-';
        this.DOM.metaAmount.textContent = record.amount ? `${record.amount} ريال` : '-';

        // Update raw details
        this.DOM.detailRawSupplier.textContent = record.rawSupplierName || '-';
        this.DOM.detailRawBank.textContent = record.rawBankName || '-';

        // Reset selections - support both camelCase and snake_case from API
        this.selectedSupplierId = record.supplierId || record.supplier_id || null;
        this.selectedBankId = record.bankId || record.bank_id || null;

        // Set input values
        if (this.selectedBankId && BGL.State.bankMap[this.selectedBankId]) {
            const bankName = BGL.State.bankMap[this.selectedBankId].official_name;
            this.DOM.bankInput.value = bankName;
            this.selectedBankName = bankName;
            this.DOM.bankInput.classList.add('has-value');
        } else {
            this.DOM.bankInput.value = '';
            this.DOM.bankInput.classList.remove('has-value');
        }

        if (this.selectedSupplierId && BGL.State.supplierMap[this.selectedSupplierId]) {
            const supplierName = BGL.State.supplierMap[this.selectedSupplierId].official_name;
            this.DOM.supplierInput.value = supplierName;
            this.selectedSupplierName = supplierName;
            this.DOM.supplierInput.classList.add('has-value');
        } else {
            this.DOM.supplierInput.value = '';
            this.DOM.supplierInput.classList.remove('has-value');
        }

        // Load candidates from API
        await this._loadCandidates(record.id);

        console.log('[Decision] Candidates loaded:', {
            bankCandidates: this.bankCandidates,
            supplierCandidates: this.supplierCandidates,
            currentBankId: this.selectedBankId,
            currentSupplierId: this.selectedSupplierId
        });

        // Auto-fill if candidates available and no selection yet
        if (!this.selectedBankId && this.bankCandidates.length > 0) {
            const best = this.bankCandidates[0];
            console.log('[Decision] Auto-filling bank:', best);
            this.DOM.bankInput.value = best.name;
            this.selectedBankId = parseInt(best.bank_id || best.id);
            this.selectedBankName = best.name;
            this.DOM.bankInput.classList.add('has-value');
            console.log('[Decision] Bank auto-filled, selectedBankId =', this.selectedBankId);
        }

        if (!this.selectedSupplierId && this.supplierCandidates.length > 0) {
            const best = this.supplierCandidates[0];
            console.log('[Decision] Auto-filling supplier:', best);
            this.DOM.supplierInput.value = best.name;
            this.selectedSupplierId = parseInt(best.supplier_id || best.id);
            this.selectedSupplierName = best.name;
            this.DOM.supplierInput.classList.add('has-value');
            console.log('[Decision] Supplier auto-filled, selectedSupplierId =', this.selectedSupplierId);
        }

        // Update navigation buttons
        this.DOM.btnPrev.disabled = this.currentIndex === 0;
        this.DOM.btnNext.disabled = this.currentIndex >= this.records.length - 1;

        // Clear message
        this._showMessage('');
    },

    /**
     * Fetch suggestion candidates from the backend for a specific record.
     * 
     * The backend uses fuzzy matching to find the best Supplier and Bank matches.
     * 
     * @private
     * @async
     * @param {number} recordId - ID of the record to fetch candidates for
     * @returns {Promise<void>} Updates `this.supplierCandidates` and `this.bankCandidates`
     */
    async _loadCandidates(recordId) {
        try {
            const res = await api.get(`/api/records/${recordId}/candidates`);
            if (res.success && res.data) {
                this.supplierCandidates = res.data.supplier?.candidates || [];
                this.bankCandidates = res.data.bank?.candidates || [];
            }
        } catch (e) {
            console.error('[Decision] Failed to load candidates:', e);
            this.supplierCandidates = [];
            this.bankCandidates = [];
        }
    },

    /**
     * Handle bank input
     */
    _handleBankInput(query) {
        this.selectedBankId = null;
        this.DOM.bankInput.classList.remove('has-value');
        this._showBankSuggestions(query.toLowerCase().trim());
    },

    /**
     * Show bank suggestions
     */
    _showBankSuggestions(query = '') {
        const suggestions = this._getBankSuggestions(query);
        this._renderSuggestions(this.DOM.bankSuggestions, suggestions, 'bank');
        this.DOM.bankSuggestions.classList.add('open');
    },

    /**
     * Generate list of bank suggestions based on user query.
     * 
     * combining:
     * 1. Smart Candidates (from API) - appear first
     * 2. Dictionary Search (client-side filtering of all banks)
     * 
     * @private
     * @param {string} query - User input text
     * @returns {Array<Object>} Merged and distinct list of suggestions
     */
    _getBankSuggestions(query) {
        // Candidates first
        const smart = this.bankCandidates.filter(c =>
            (c.name || '').toLowerCase().includes(query)
        );

        // Dictionary
        const dict = Object.values(BGL.State.bankMap || {})
            .filter(b => (b.official_name || '').toLowerCase().includes(query))
            .map(b => ({
                name: b.official_name,
                id: b.id,
                bank_id: b.id,
                score: 0
            }));

        // Merge
        const seen = new Set(smart.map(c => c.name));
        const merged = [...smart];
        dict.forEach(d => {
            if (!seen.has(d.name)) {
                merged.push(d);
                seen.add(d.name);
            }
        });

        return merged.slice(0, 20);
    },

    /**
     * Handle supplier input
     */
    _handleSupplierInput(query) {
        this.selectedSupplierId = null;
        this.DOM.supplierInput.classList.remove('has-value');

        const trimmedQuery = query.trim();
        const suggestions = this._getSupplierSuggestions(trimmedQuery.toLowerCase());

        // Enable "Add New" button if user typed something and no exact match found
        if (trimmedQuery.length >= 2 && suggestions.length === 0) {
            this._pendingNewSupplierName = trimmedQuery;
            this.DOM.btnAddSupplier.disabled = false;
            this.DOM.btnAddSupplier.title = `إضافة "${trimmedQuery}" كمورد جديد`;
        } else {
            this._pendingNewSupplierName = null;
            this.DOM.btnAddSupplier.disabled = true;
            this.DOM.btnAddSupplier.title = '';
        }

        this._renderSuggestions(this.DOM.supplierSuggestions, suggestions, 'supplier');
        this.DOM.supplierSuggestions.classList.add('open');
    },

    /**
     * Show supplier suggestions
     */
    _showSupplierSuggestions(query = '') {
        const suggestions = this._getSupplierSuggestions(query);
        this._renderSuggestions(this.DOM.supplierSuggestions, suggestions, 'supplier');
        this.DOM.supplierSuggestions.classList.add('open');

        // Disable add button when showing empty search
        if (!query) {
            this._pendingNewSupplierName = null;
            this.DOM.btnAddSupplier.disabled = true;
        }
    },

    /**
     * Generate list of supplier suggestions based on user query.
     * 
     * combining:
     * 1. Smart Candidates (from API)
     * 2. Dictionary Search (client-side filtering)
     * 
     * Note: "Add New" logic is handled in `_handleSupplierInput`.
     * 
     * @private
     * @param {string} query - User input text
     * @returns {Array<Object>} Merged and distinct list of suggestions
     */
    _getSupplierSuggestions(query) {
        // Candidates first
        const smart = this.supplierCandidates.filter(c =>
            (c.name || '').toLowerCase().includes(query)
        );

        // Dictionary
        const dict = (BGL.State.supplierCache || [])
            .filter(s => (s.official_name || '').toLowerCase().includes(query))
            .map(s => ({
                name: s.official_name,
                id: s.id,
                supplier_id: s.id,
                score: 0
            }));

        // Merge
        const seen = new Set(smart.map(c => c.name));
        const merged = [...smart];
        dict.forEach(d => {
            if (!seen.has(d.name)) {
                merged.push(d);
                seen.add(d.name);
            }
        });

        return merged.slice(0, 20);
    },

    /**
     * Render suggestions list
     */
    _renderSuggestions(listEl, items, type) {
        if (items.length === 0) {
            listEl.innerHTML = `
                <li class="suggestion-item text-gray-400 text-center">
                    لا توجد نتائج
                </li>
            `;
            return;
        }

        listEl.innerHTML = items.map(item => {
            const scoreHtml = item.score > 0
                ? `<span class="score">${Math.round(item.score * 100)}%</span>`
                : '';
            const id = item.supplier_id || item.bank_id || item.id;

            return `
                <li class="suggestion-item" data-id="${id}" data-name="${this._escapeHtml(item.name)}" data-type="${type}">
                    <span>${this._escapeHtml(item.name)}</span>
                    ${scoreHtml}
                </li>
            `;
        }).join('');

        // Bind clicks using event delegation on the list itself
        const self = this;
        listEl.onclick = function (e) {
            const li = e.target.closest('.suggestion-item[data-id]');
            if (li) {
                e.preventDefault();
                e.stopPropagation();
                const id = li.dataset.id;
                const name = li.dataset.name;
                const itemType = li.dataset.type;
                console.log('[Decision] Selection clicked:', { type: itemType, id, name });
                self._selectItem(itemType, id, name);
            }
        };

        // Also bind mousedown for cases where blur happens first
        listEl.onmousedown = function (e) {
            const li = e.target.closest('.suggestion-item[data-id]');
            if (li) {
                e.preventDefault();
            }
        };
    },

    /**
     * Handle user selection from the suggestion dropdown.
     * 
     * @private
     * @param {string} type - 'bank' or 'supplier'
     * @param {string|number} id - ID of the selected entity
     * @param {string} name - Official name to display in input
     */
    _selectItem(type, id, name) {
        if (type === 'bank') {
            this.selectedBankId = parseInt(id);
            this.selectedBankName = name;
            this.DOM.bankInput.value = name;
            this.DOM.bankInput.classList.add('has-value');
            this.DOM.bankSuggestions.classList.remove('open');
        } else {
            this.selectedSupplierId = parseInt(id);
            this.selectedSupplierName = name;
            this.DOM.supplierInput.value = name;
            this.DOM.supplierInput.classList.add('has-value');
            this.DOM.supplierSuggestions.classList.remove('open');

            // Disable add button since user selected existing supplier
            this._pendingNewSupplierName = null;
            this.DOM.btnAddSupplier.disabled = true;
        }
    },

    /**
     * Create a NEW Supplier in the database.
     * 
     * Triggered when user clicks "Add New Supplier" button.
     * Uses `_pendingNewSupplierName` which captures the typed input.
     * 
     * On success:
     * - Adds new supplier to backend
     * - Updates local cache
     * - Auto-selects the new supplier for the current record
     * 
     * @private
     * @async
     * @returns {Promise<void>}
     */
    async _addNewSupplier() {
        const name = this._pendingNewSupplierName;
        if (!name || name.length < 2) {
            this._showMessage('⚠️ اسم المورد قصير جداً', 'error');
            return;
        }

        this.DOM.btnAddSupplier.disabled = true;
        this._showMessage('جارٍ إضافة المورد...', 'info');

        try {
            const res = await api.post('/api/dictionary/suppliers', {
                official_name: name
            });

            if (res.success && res.data && res.data.id) {
                const newId = res.data.id;

                // Add to local cache
                const newSupplier = {
                    id: newId,
                    official_name: name,
                    normalized_name: name.toLowerCase()
                };
                BGL.State.supplierCache = BGL.State.supplierCache || [];
                BGL.State.supplierCache.push(newSupplier);
                BGL.State.supplierMap = BGL.State.supplierMap || {};
                BGL.State.supplierMap[newId] = newSupplier;

                // Select the new supplier
                this._selectItem('supplier', newId, name);

                this._showMessage(`✓ تم إضافة المورد: ${name}`, 'success');
                console.log('[Decision] New supplier added:', { id: newId, name });
            } else {
                throw new Error(res.message || 'فشل إضافة المورد');
            }
        } catch (e) {
            console.error('[Decision] Add supplier failed:', e);
            this._showMessage('خطأ: ' + e.message, 'error');
            this.DOM.btnAddSupplier.disabled = false;
        }
    },

    /**
     * Navigate to previous record
     */
    navigatePrev() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this._displayCurrentRecord();
        }
    },

    /**
     * Navigate to next record
     */
    navigateNext() {
        if (this.currentIndex < this.records.length - 1) {
            this.currentIndex++;
            this._displayCurrentRecord();
        }
    },

    /**
     * Submit the decision to the backend and advance to next record.
     * 
     * Payload sent:
     * - match_status: 'ready'
     * - supplier_id & bank_id
     * - raw_supplier_name & raw_bank_name (Vital for Learning System)
     * 
     * Features:
     * - Validates inputs (must select Bank and Supplier)
     * - Updates current record status locally
     * - Propagates decision to other identical records in the current batch
     * - Auto-navigates to the next record
     * 
     * @async
     * @returns {Promise<void>}
     */
    async saveAndNext() {
        console.log('[Decision] Attempting save with:', {
            supplierId: this.selectedSupplierId,
            supplierName: this.selectedSupplierName,
            bankId: this.selectedBankId,
            bankName: this.selectedBankName
        });

        // Validate
        if (!this.selectedSupplierId) {
            this._showMessage('⚠️ يجب اختيار المورد', 'error');
            this.DOM.supplierInput.focus();
            return;
        }
        if (!this.selectedBankId) {
            this._showMessage('⚠️ يجب اختيار البنك', 'error');
            this.DOM.bankInput.focus();
            return;
        }

        const record = this.records[this.currentIndex];
        if (!record) return;

        // Disable button
        this.DOM.btnSaveNext.disabled = true;
        this._showMessage('جارٍ الحفظ...', 'info');

        try {
            // API expects 'match_status' (snake_case) with values 'ready' or 'needs_review'
            // Also needs raw names for learning
            const payload = {
                match_status: 'ready',
                supplier_id: this.selectedSupplierId,
                bank_id: this.selectedBankId,
                raw_supplier_name: record.rawSupplierName || '',
                raw_bank_name: record.rawBankName || ''
            };

            console.log('[Decision] Sending payload:', payload);
            const res = await api.post(`/api/records/${record.id}/decision`, payload);

            if (res.success) {
                // Update local record
                record.matchStatus = 'ready';
                record.supplierId = this.selectedSupplierId;
                record.bankId = this.selectedBankId;

                // Update other records with same raw name (propagation)
                const propagatedCount = res.propagated_count || 0;
                if (propagatedCount > 0) {
                    const rawName = record.rawSupplierName;
                    this.records.forEach(r => {
                        if (r.id !== record.id &&
                            r.rawSupplierName === rawName &&
                            !r.supplierId) {
                            r.supplierId = this.selectedSupplierId;
                            r.matchStatus = 'ready';
                        }
                    });
                }

                // Update counts
                this._updateCounts();

                // Show success with propagation info
                if (propagatedCount > 0) {
                    this._showMessage(`✓ تم الحفظ وتحديث ${propagatedCount} سجل مشابه`, 'success');
                } else {
                    this._showMessage('✓ تم الحفظ بنجاح', 'success');
                }

                // Move to next pending after delay
                setTimeout(() => {
                    this._goToNextPending();
                }, 500);

            } else {
                throw new Error(res.message);
            }

        } catch (e) {
            console.error('[Decision] Save failed:', e);
            this._showMessage('خطأ: ' + e.message, 'error');
        } finally {
            this.DOM.btnSaveNext.disabled = false;
        }
    },

    /**
     * Go to next pending record
     */
    _goToNextPending() {
        // Find next pending after current
        for (let i = this.currentIndex + 1; i < this.records.length; i++) {
            if (!this._isCompleted(this.records[i])) {
                this.currentIndex = i;
                this._displayCurrentRecord();
                return;
            }
        }

        // Wrap around to beginning
        for (let i = 0; i < this.currentIndex; i++) {
            if (!this._isCompleted(this.records[i])) {
                this.currentIndex = i;
                this._displayCurrentRecord();
                return;
            }
        }

        // All done!
        this._showMessage('🎉 تم اعتماد جميع السجلات!', 'success');
    },

    /**
     * Show message
     */
    _showMessage(text, type = 'info') {
        const colors = {
            info: 'text-blue-600',
            success: 'text-green-600',
            error: 'text-red-600'
        };

        this.DOM.saveMessage.textContent = text;
        this.DOM.saveMessage.className = `text-sm font-medium ${colors[type] || colors.info}`;
    },

    /**
     * Escape HTML - uses global window.escapeHtml from api.js
     */
    _escapeHtml(str) {
        return window.escapeHtml ? window.escapeHtml(str) : String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    BGL.Decision.init();
});
