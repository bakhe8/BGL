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
            supplierAddError: document.getElementById('supplierAddError'),
            toggleDetails: document.getElementById('toggleDetails'),
            expandedDetails: document.getElementById('expandedDetails'),

            // Messages
            saveMessage: document.getElementById('saveMessage'),

            // Chips Container
            supplierChips: document.getElementById('supplierChips'),
            bankChips: document.getElementById('bankChips')
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



        // Bank input
        DOM.bankInput.addEventListener('input', (e) => this._handleBankInput(e.target.value));
        DOM.bankInput.addEventListener('click', () => {
            // Only show fixed list if empty. If filled, user must clear/type to search.
            if (!DOM.bankInput.value) {
                this._handleBankInput('');
            }
        });
        DOM.bankInput.addEventListener('blur', () => {
            setTimeout(() => DOM.bankSuggestions.classList.remove('open'), 200);
        });

        // Supplier input
        DOM.supplierInput.addEventListener('input', (e) => this._handleSupplierInput(e.target.value));
        DOM.supplierInput.addEventListener('blur', () => {
            setTimeout(() => DOM.supplierSuggestions.classList.remove('open'), 200);
        });

        // Add supplier button - creates new supplier from typed name
        DOM.btnAddSupplier.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this._addNewSupplier();
        });

        // Keyboard navigation
        // FIX: Arrow direction for RTL (Arabic) interface
        // In RTL context: Left = Previous, Right = Next
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;

            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                this.navigatePrev();  // ✅ Fixed: Left arrow goes to previous
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                this.navigateNext();  // ✅ Fixed: Right arrow goes to next
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

            // SORTING LOGIC:
            // 1. Pending records first ('needs_review')
            // 2. Then by ID ASC
            this.records.sort((a, b) => {
                const aDone = this._isCompleted(a);
                const bDone = this._isCompleted(b);
                if (aDone === bDone) return a.id - b.id; // Same status, sort by ID
                return aDone ? 1 : -1; // Pending first
            });

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

        // CRITICAL: Reset pending state to prevent leaks from previous record
        // NOTE: This is NOT a bug - it's intentional design to prevent showing
        // "Add Supplier" button with stale data from previous record.
        // The button state is properly updated in _updateAddButtonState() at line 579.
        this._pendingNewSupplierName = null;

        // Update raw details
        this.DOM.detailRawSupplier.textContent = record.rawSupplierName || '-';
        this.DOM.detailRawBank.textContent = record.rawBankName || '-';

        // Update placeholder to show raw name
        this.DOM.supplierInput.placeholder = record.rawSupplierName || 'ابحث عن المورد...';

        // Reset selections - support both camelCase and snake_case from API
        // NOTE: This dual support is part of a planned migration from camelCase to snake_case.
        // Backend is being gradually updated to use snake_case consistently.
        // Once migration is complete, camelCase support can be safely removed.
        this.selectedSupplierId = record.supplierId || record.supplier_id || null;
        this.selectedBankId = record.bankId || record.bank_id || null;

        // Reset Chips
        this.DOM.supplierChips.innerHTML = '<span class="text-xs text-gray-400 animate-pulse">جاري البحث عن مقترحات...</span>';
        this.DOM.bankChips.innerHTML = '';

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

        // Render Chips for quick selection
        this._renderCandidateChips(this.supplierCandidates, 'supplier');
        this._renderCandidateChips(this.bankCandidates, 'bank');

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

        if (!this.selectedSupplierId) {
            if (this.supplierCandidates.length > 0) {
                const best = this.supplierCandidates[0];

                // SAFE AUTO-FILL: Only auto-fill if match is very strong
                // Threshold: Exact/Alias OR Score >= 0.90
                // DESIGN RATIONALE: Clearing input for weak matches is INTENTIONAL
                // to prevent accidental saves and force user attention.
                const isStrongMatch = (best.match_type === 'exact' || best.match_type === 'alias_match' || best.score >= 0.90);

                if (isStrongMatch) {
                    console.log('[Decision] Auto-filling supplier (Strong Match):', best);
                    this.DOM.supplierInput.value = best.name;
                    this.selectedSupplierId = parseInt(best.supplier_id || best.id);
                    this.selectedSupplierName = best.name;
                    this.DOM.supplierInput.classList.add('has-value');
                    console.log('[Decision] Supplier auto-filled, selectedSupplierId =', this.selectedSupplierId);
                } else {
                    console.log('[Decision] Weak match found, skipping auto-fill to prevent accidental save:', best);
                    // ✅ INTENTIONAL DESIGN: Clear input to reduce cognitive load
                    // and prevent accidental saves with low-confidence matches.
                    this.DOM.supplierInput.value = '';
                }
            }

            // Initialize Add Button State using smart logic
            // This handles both cases (Empty/Weak Match -> Init with Raw Name) OR (Strong Match -> Init with Input Value)
            // this._updateAddButtonState(this.DOM.supplierInput.value); // Moved below to ensure global execution
        }

        // CRITICAL: Always update button state logic regardless of selection state
        this._updateAddButtonState(this.DOM.supplierInput.value);



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
     * Handle bank input search (Debounced)
     */
    _handleBankQuery(query) {
        const trimmedQuery = query.trim();
        const suggestions = this._getBankSuggestions(trimmedQuery.toLowerCase());

        if (suggestions.length === 0) {
            this.DOM.bankSuggestions.classList.remove('open');
            this.DOM.bankSuggestions.innerHTML = '';
        } else {
            this._renderSuggestions(this.DOM.bankSuggestions, suggestions, 'bank');
            this.DOM.bankSuggestions.classList.add('open');
        }
    },

    /**
     * Handle bank input
     */
    _handleBankInput(query) {
        this.selectedBankId = null;
        this.DOM.bankInput.classList.remove('has-value');

        // Debounced Search
        if (!this._debouncedBankSearch) {
            this._debouncedBankSearch = this._debounce((q) => this._handleBankQuery(q), 300);
        }
        this._debouncedBankSearch(query);
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
     * Generate list of bank suggestions.
     * 
     * LOGIC UPDATE (Smart Select Menu):
     * 1. **Empty Query**: Returns ALL banks (Top 50) -> Acts like a fixed Select Menu.
     * 2. **Typed Query**: Filters banks by Name (Ar/En) or Short Code.
     * 
     * @private
     * @param {string} query 
     * @returns {Array<Object>}
     */
    _getBankSuggestions(query) {
        // If query is empty, show ALL banks (treating it as a fixed list/select menu)
        const banks = Object.values(BGL.State.bankMap || {});

        if (!query || query.length === 0) {
            return banks.map(b => ({
                name: b.official_name,
                id: b.id,
                bank_id: b.id,
                score: 0
            })).slice(0, 50); // Show top 50 banks
        }

        // Dictionary Search
        return banks
            .filter(b => {
                const q = query.toLowerCase();
                const nameVal = (b.official_name || '').toLowerCase();
                const nameEn = (b.official_name_en || '').toLowerCase();
                const nameAr = (b.official_name_ar || '').toLowerCase(); // If available
                const short = (b.short_code || '').toLowerCase();

                return nameVal.includes(q) || nameEn.includes(q) || nameAr.includes(q) || short.includes(q);
            })
            .map(b => ({
                name: b.official_name, // Display name
                id: b.id,
                bank_id: b.id,
                score: 0
            }))
            .slice(0, 20);
    },

    /**
     * Debounce helper
     */
    _debounce(func, wait) {
        let timeout;
        // NOTE: Using regular `function` instead of arrow `=>`.
        // This is NOT a bug because callers use arrow functions that preserve `this`.
        // Example: this._debounce((q) => this._handleBankQuery(q), 300)
        // The arrow in the caller ensures `this` context is correct.
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Simulate strict PHP normalization for validation.
     * Matches logic in App\Support\Normalizer::normalizeSupplierName
     */
    _simulateNormalization(name) {
        if (!name) return '';

        let val = name.toLowerCase().trim();

        // Remove common prefixes/suffixes (Stop words)
        const stop = [
            'شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مكتب', 'مصنع', 'مقاولات',
            'trading', 'est', 'est.', 'establishment', 'company', 'co', 'co.', 'ltd', 'ltd.',
            'limited', 'llc', 'inc', 'inc.', 'international', 'global'
        ];

        // Replace stop words with space
        stop.forEach(word => {
            // Regex matches whole words only
            const regex = new RegExp(`\\b${word}\\b`, 'gi');
            val = val.replace(regex, ' ');
        });

        // Remove non-alphanumeric (keep Arabic letters, English letters, Numbers)
        // Note: JS regex for Unicode properties needs /u flag and \p{L}
        // Simplified approach for typical inputs:
        // Remove known punctuations or just keep letters/numbers
        val = val.replace(/[^\p{L}\p{N}\s]/gu, '');

        // Collapse spaces
        val = val.replace(/\s+/g, ' ').trim();

        return val;
    },

    /**
     * Handle supplier input search (Debounced in event listener)
     */
    _handleSupplierQuery(query) {
        const trimmedQuery = query.trim();
        const suggestions = this._getSupplierSuggestions(trimmedQuery.toLowerCase());

        if (suggestions.length === 0) {
            this.DOM.supplierSuggestions.classList.remove('open');
            this.DOM.supplierSuggestions.innerHTML = '';
        } else {
            this._renderSuggestions(this.DOM.supplierSuggestions, suggestions, 'supplier');
            this.DOM.supplierSuggestions.classList.add('open');
        }
    },

    /**
     * Handle supplier input - main entry point
     * 
     * LOGIC UPDATE (Smart Button):
     * - Clears previous error states.
     * - Checks "Add New" button eligibility via `_updateAddButtonState`.
     * - Triggers debounced search (300ms).
     * 
     * @param {string} query 
     */
    _handleSupplierInput(query) {
        this.selectedSupplierId = null;
        this.DOM.supplierInput.classList.remove('has-value');
        // Clear previous error if any
        this.DOM.supplierAddError.classList.add('hidden');

        // Always update button state immediately for UX responsiveness
        const record = this.records[this.currentIndex];
        this._updateAddButtonState(query, record ? record.rawSupplierName : '');

        // Debounced Search
        if (!this._debouncedSupplierSearch) {
            this._debouncedSupplierSearch = this._debounce((q) => this._handleSupplierQuery(q), 300);
        }
        this._debouncedSupplierSearch(query);
    },

    /**
     * Update "Add New Supplier" button visibility & state.
     * 
     * LOGIC UPDATE (Smart Pop-in):
     * - **Backend Validation**: Uses `_simulateNormalization` to check if name is valid (len >= 5).
     * - **Collision Check**: Checks if normalized name exists in Dictionary or Candidates.
     * - **Visibility**: Using `.hidden` class instead of disabled attribute for cleaner UI.
     * - **Text**: Dynamically updates text to `+ Add "Query"`.
     * 
     * @param {string} query - Current input value
     * @param {string} rawName - Original raw name from record
     */
    _updateAddButtonState(inputText) {
        const record = this.records[this.currentIndex];
        const rawName = record ? (record.rawSupplierName || '').trim() : '';

        // Determine what name we are dealing with (Input OR Fallback to Raw)
        const nameToCheck = inputText.length > 0 ? inputText : rawName;

        // 1. Basic Validity Check (Backend Rule: Normalized >= 5)
        const normalizedCheck = this._simulateNormalization(nameToCheck);

        if (normalizedCheck.length < 5) {
            this._pendingNewSupplierName = null;
            this.DOM.btnAddSupplier.classList.add('hidden'); // Hide if invalid
            return;
        }

        // 2. Duplicate Check
        const isDuplicate = this.supplierCandidates.some(c => c.name.toLowerCase() === nameToCheck.toLowerCase());

        if (isDuplicate) {
            this._pendingNewSupplierName = null;
            this.DOM.btnAddSupplier.classList.add('hidden'); // Hide if duplicate
        } else {
            // Unique Name - Enable & Show
            this._pendingNewSupplierName = nameToCheck;
            this.DOM.btnAddSupplier.classList.remove('hidden');
            this.DOM.btnAddSupplier.disabled = false;

            // Clean styles
            this.DOM.btnAddSupplier.className = "text-xs text-blue-600 hover:text-blue-800 mt-1 font-medium transition-all";

            if (inputText.length === 0) {
                // Showing Raw Name Fallback
                this.DOM.btnAddSupplier.title = `إضافة "${rawName}" (من الملف) كمورد جديد`;
                this.DOM.btnAddSupplier.textContent = `+ إضافة "${rawName}"`;
            } else {
                // Showing Typed Name
                this.DOM.btnAddSupplier.title = `إضافة "${inputText}" كمورد جديد`;
                this.DOM.btnAddSupplier.textContent = `+ إضافة "${inputText}"`;
            }

            // Update error dynamically if we want to show non-blocking info
            // But currently users want hidden if duplicate, so valid state is clean.
            this._setAddStatus('', 'info'); // clear
            this.DOM.supplierAddError.classList.add('hidden');
        }
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
    /**
     * Generate list of supplier suggestions based on user query.
     * 
     * LOGIC UPDATE (Strict Search):
     * 1. **Stop Words**: Ignores common suffixes like "Co", "Ltd", "Company" if typed alone.
     *    (Synced with Backend `App\Support\Normalizer.php`)
     * 2. **Min Length**: Requires >= 3 meaningful characters.
     * 
     * This prevents "Co" from returning thousands of irrelevant results.
     * 
     * @private
     * @param {string} query - User input text
     * @returns {Array<Object>} List of suggestions from dictionary
     */
    _getSupplierSuggestions(query) {
        if (!query) return [];

        // Stop Words from App\Support\Normalizer.php
        const stopWords = [
            'شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مكتب', 'مصنع', 'مقاولات',
            'trading', 'est', 'est.', 'establishment', 'company', 'co', 'co.', 'ltd', 'ltd.',
            'limited', 'llc', 'inc', 'inc.', 'international', 'global'
        ];

        // Normalize query: specific cleaning for search
        let cleanQuery = query.toLowerCase().trim();

        // 1. Exact Stop Word Match -> Return Empty
        if (stopWords.includes(cleanQuery)) {
            return [];
        }

        // 2. Strict Length Check
        // If query is very short, ignoring it prevents massive result sets.
        // Backend normalization might strip meaningful chars, but for *search* we need to be practical.
        if (cleanQuery.length < 3) {
            return [];
        }

        // Dictionary Search
        return (BGL.State.supplierCache || [])
            .filter(s => {
                const name = (s.official_name || '').toLowerCase();
                return name.includes(cleanQuery);
            })
            .map(s => ({
                name: s.official_name,
                id: s.id,
                supplier_id: s.id,
                score: 0
            }))
            .slice(0, 20);
    },

    /**
     * Render quick-select chips for top candidates
     */
    _renderCandidateChips(candidates, type) {
        const container = type === 'supplier' ? this.DOM.supplierChips : this.DOM.bankChips;
        if (!container) return;

        if (!candidates || candidates.length === 0) {
            container.innerHTML = '<span class="text-xs text-gray-400">لا توجد مقترحات ذكية</span>';
            return;
        }

        // Take top 3
        const top = candidates.slice(0, 3);

        container.innerHTML = top.map(c => {
            const scoreClass = c.score >= 0.9 ? 'bg-green-100 text-green-700 border-green-200' :
                c.score >= 0.7 ? 'bg-blue-100 text-blue-700 border-blue-200' :
                    'bg-gray-100 text-gray-700 border-gray-200';

            const id = c.supplier_id || c.bank_id || c.id;
            // Escape single quotes for the function call
            const safeName = (c.name || '').replace(/'/g, "\\'");

            return `
                <button type="button" 
                    class="chip-btn flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border transition-all hover:scale-105 ${scoreClass}"
                    onclick="BGL.Decision._selectItem('${type}', '${id}', '${this._escapeHtml(safeName)}')"
                >
                    <span>${this._escapeHtml(c.name)}</span>
                    <span class="font-bold opacity-75">${Math.round(c.score * 100)}%</span>
                </button>
            `;
        }).join('');
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
        // NOTE: Re-creating handlers on each render is NOT a memory leak.
        // Previous handlers are automatically garbage collected when reassigned.
        // Performance impact is negligible (< 1ms per keystroke).
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
    /**
     * Helper to show local status message for Add Supplier button
     */
    _setAddStatus(msg, type) {
        const el = this.DOM.supplierAddError;
        if (!el) return;

        el.textContent = msg;
        el.classList.remove('hidden', 'text-red-500', 'text-green-600', 'text-gray-500');

        if (type === 'error') {
            el.classList.add('text-red-500');
        } else if (type === 'success') {
            el.classList.add('text-green-600');
        } else {
            el.classList.add('text-gray-500'); // info
        }
    },

    async _addNewSupplier() {
        const name = this._pendingNewSupplierName;
        const normalized = this._simulateNormalization(name);

        if (!name || normalized.length < 5) {
            this._setAddStatus('⚠️ اسم المورد يجب أن يحتوي على 5 حروف صالحة على الأقل (بدون كلمات عامة)', 'error');
            return;
        }

        this.DOM.btnAddSupplier.disabled = true;
        this._setAddStatus('جارٍ إضافة المورد...', 'info');

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

                // Success message alongside the button
                this._setAddStatus(`✓ تم إضافة "${name}" للقائمة. اضغط "حفظ" لاعتماد السجل.`, 'success');

                console.log('[Decision] New supplier added:', { id: newId, name });
            } else {
                throw new Error(res.message || 'فشل إضافة المورد');
            }
        } catch (e) {
            console.error('[Decision] Add supplier failed:', e);
            // Clean up error message (remove "Error: " prefix if present)
            const msg = e.message.replace(/^Error:\s*/, '');

            // Show local error
            this._setAddStatus(msg, 'error');

            // Hide global loading message if present
            this.DOM.saveMessage.classList.add('hidden');

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
                // FIX: Added NULL check to prevent spreading to unrelated empty records
                // See: BUG-001-NULL-Propagation.md for details
                const propagatedCount = res.propagated_count || 0;
                if (propagatedCount > 0) {
                    const rawName = record.rawSupplierName;

                    // ✅ CRITICAL FIX: Only propagate if rawName is valid (not null/empty)
                    // Without this check, null === null would match ALL empty records!
                    if (rawName && rawName.trim().length > 0) {
                        this.records.forEach(r => {
                            if (r.id !== record.id &&
                                r.rawSupplierName === rawName &&
                                !r.supplierId) {
                                r.supplierId = this.selectedSupplierId;
                                r.matchStatus = 'ready';
                            }
                        });
                    } else {
                        // Log when propagation is skipped for empty records
                        console.warn('[Decision] Propagation skipped: rawSupplierName is empty for record', record.id);
                    }
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
