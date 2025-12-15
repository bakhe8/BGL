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

    // UI State
    currentFilter: 'all', // 'all', 'pending', 'approved'

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
            metaSessionId: document.getElementById('metaSessionId'),
            metaRecordId: document.getElementById('metaRecordId'),
            metaGuarantee: document.getElementById('metaGuarantee'),
            metaDate: document.getElementById('metaDate'),
            metaAmount: document.getElementById('metaAmount'),
            // New Meta Fields
            metaContract: document.getElementById('metaContract'),
            metaType: document.getElementById('metaType'),
            detailRawSupplier: document.getElementById('detailRawSupplier'),
            detailRawBank: document.getElementById('detailRawBank'),

            // Counters
            countTotal: document.getElementById('countTotal'),
            countApproved: document.getElementById('countApproved'),
            countPending: document.getElementById('countPending'),
            currentIndex: document.getElementById('currentIndex'),
            metaRecordIndex: document.getElementById('metaRecordIndex'), // Added element
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

        // Init Letter Preview (Assets only)
        this._initLetterPreview();

        console.log('[Decision] Ready. Records:', this.records.length);

        // Fetch available sessions for navigation
        this._loadSessions();
    },

    /**
     * Load available import sessions for the dropdown navigation
     */
    async _loadSessions() {
        try {
            const res = await api.get('/api/sessions');
            if (res.success && res.data) {
                this.availableSessions = res.data;
                this._setupSessionDropdown();
            }
        } catch (e) {
            console.error('[Decision] Failed to load sessions:', e);
        }
    },

    /**
     * Setup the session navigation dropdown
     */
    _setupSessionDropdown() {
        const sessionMeta = this.DOM.metaSessionId;
        if (!sessionMeta || !this.availableSessions || this.availableSessions.length === 0) return;

        // Make it look clickable
        sessionMeta.style.cursor = 'pointer';
        sessionMeta.style.textDecoration = 'underline';
        sessionMeta.style.textUnderlineOffset = '2px';
        sessionMeta.title = 'انقر لتغيير الجلسة';

        // Create Dropdown Element (hidden by default)
        const dropdown = document.createElement('div');
        dropdown.className = 'absolute bg-white border border-gray-200 shadow-xl rounded-lg z-50 hidden';
        dropdown.style.top = '100%';
        dropdown.style.right = '0';
        dropdown.style.width = '240px';
        dropdown.style.maxHeight = '300px';
        dropdown.style.overflowY = 'auto'; // ensure scroll

        // Search Input inside dropdown
        const searchContainer = document.createElement('div');
        searchContainer.className = 'sticky top-0 bg-white p-2 border-b border-gray-100';
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'بحث (رقم أو تاريخ)...';
        searchInput.className = 'w-full text-xs px-2 py-1 border rounded focus:ring-1 focus:ring-blue-500 outline-none';
        searchContainer.appendChild(searchInput);
        dropdown.appendChild(searchContainer);

        const listContainer = document.createElement('div');
        dropdown.appendChild(listContainer);

        // Position it relative to the parent
        sessionMeta.parentElement.style.position = 'relative';
        sessionMeta.parentElement.appendChild(dropdown);

        // Render function
        const renderList = (filter = '') => {
            listContainer.innerHTML = '';
            const filtered = this.availableSessions.filter(s =>
                s.session_id.toString().includes(filter) ||
                (s.last_date && s.last_date.includes(filter))
            );

            if (filtered.length === 0) {
                listContainer.innerHTML = '<div class="p-2 text-xs text-gray-400 text-center">لا توجد نتائج</div>';
                return;
            }

            filtered.forEach(s => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-xs flex justify-between items-center';

                // Highlight current session
                // We need to know current session ID. It's in the first record usually.
                const currentSessionId = this.records[0]?.sessionId || 0;
                if (s.session_id == currentSessionId) {
                    item.classList.add('bg-blue-50', 'font-bold');
                }

                // Format Date safely
                const dateStr = s.last_date ? s.last_date.split(' ')[0] : '-';

                item.innerHTML = `
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-700">جلسة #${s.session_id}</span>
                        <span class="text-[10px] text-gray-400">${dateStr}</span>
                    </div>
                    <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-[10px]">${s.record_count}</span>
                `;

                item.addEventListener('click', () => {
                    window.location.href = `/?session_id=${s.session_id}`;
                });

                listContainer.appendChild(item);
            });
        };

        // Event Listeners
        sessionMeta.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = dropdown.classList.contains('hidden');
            // Close others if any (not implemented, but good practice)
            document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.add('hidden'));

            if (isHidden) {
                renderList(); // Initial render
                dropdown.classList.remove('hidden');
                searchInput.focus();
            } else {
                dropdown.classList.add('hidden');
            }
        });

        // Search
        searchInput.addEventListener('input', (e) => {
            renderList(e.target.value);
        });

        searchInput.addEventListener('click', (e) => e.stopPropagation());

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== sessionMeta) {
                dropdown.classList.add('hidden');
            }
        });
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

        // Direct Import Button
        document.getElementById('btnToggleImport')?.addEventListener('click', () => {
            const fileInput = document.getElementById('hiddenFileInput');
            if (fileInput) {
                fileInput.value = ''; // Reset to allow re-selecting same file
                fileInput.click();
            }
        });

        // Recalculate All button
        // Recalculate All button (Non-blocking Confirm)
        document.getElementById('btnRecalcAll')?.addEventListener('click', async (e) => {
            const btn = e.target.closest('button'); // Ensure we get the button
            if (!btn) return;

            // Step 1: Check if already in 'confirm' mode
            if (!btn.dataset.confirming) {
                // Activate confirm mode
                btn.dataset.confirming = 'true';
                const originalText = btn.textContent;
                btn.dataset.originalText = originalText;

                btn.textContent = 'تأكيد؟';
                btn.classList.add('bg-red-500', 'text-white', 'border-red-600');

                // Auto-revert after 3s
                btn._confirmTimeout = setTimeout(() => {
                    delete btn.dataset.confirming;
                    btn.textContent = originalText;
                    btn.classList.remove('bg-red-500', 'text-white', 'border-red-600');
                }, 3000);
                return;
            }

            // Step 2: Second click (Action)
            clearTimeout(btn._confirmTimeout); // Cancel timeout

            // Cleanup reset visual state partially but keep disabled state during loading
            btn.classList.remove('bg-red-500', 'text-white', 'border-red-600');
            delete btn.dataset.confirming;

            btn.disabled = true;
            btn.textContent = '...';

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
                btn.disabled = false;
                btn.textContent = btn.dataset.originalText || '🔃 إعادة مطابقة';
            }
        });

        // Print All Button
        document.getElementById('btnPrintAll')?.addEventListener('click', async () => {
            const approvedRecords = this.records.filter(r => this._isCompleted(r));
            if (approvedRecords.length === 0) {
                this._showMessage('لا يوجد سجلات جاهزة للطباعة', 'error');
                return;
            }

            // Create hidden iframe
            let iframe = document.getElementById('printFrame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'printFrame';
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                document.body.appendChild(iframe);
            }

            // Generate HTML for all
            let content = '';
            approvedRecords.forEach((record, index) => {
                // Determine page break class
                const pageBreak = index < approvedRecords.length - 1 ? 'page-break' : '';
                content += `<div class="print-page ${pageBreak}">
                    ${this._generateLetterHtmlForRecord(record)}
                </div>`;
            });

            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(`
                <!DOCTYPE html>
                <html lang="ar" dir="rtl">
                <head>
                    <title>طباعة الكل (${approvedRecords.length})</title>
                    <meta charset="UTF-8">
                    <link rel="stylesheet" href="/assets/css/letter.css">
                    <style>
                        body { margin: 0; padding: 0; background: #fff; }
                        .print-page { margin: 0; padding: 0; }
                        @media print {
                            body, .print-page, .letter-preview {
                                width: 100% !important;
                                position: static !important;
                                visibility: visible !important;
                                overflow: visible !important;
                            }
                            .letter-paper {
                                position: relative !important;
                                box-shadow: none !important; 
                                margin: 0 auto !important; 
                                min-height: 296mm !important; 
                                overflow: hidden !important; 
                            }
                            .page-break { 
                                page-break-after: always !important; 
                                height: 0; 
                                display: block;
                            }
                            @page {
                                margin: 0;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${content}
                    <script>
                        window.addEventListener('load', () => {
                            document.fonts.ready.then(() => {
                                setTimeout(() => {
                                    window.print();
                                }, 500); 
                            });
                        });
                    </script>
                </body>
                </html>
            `);
            doc.close();
        });

        // Hidden Input Change (Auto-Upload)
        document.getElementById('hiddenFileInput')?.addEventListener('change', async (e) => {
            const fileInput = e.target;

            if (!fileInput.files.length) return;

            const file = fileInput.files[0];
            const formData = new FormData();
            formData.append('file', file);

            this._showMessage('جارٍ رفع الملف ومعالجته...', 'info');

            try {
                const res = await fetch('/api/import/excel', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.success) {
                    this._showMessage(`✓ تم استيراد ${result.data?.imported || 0} سجل بنجاح`, 'success');

                    // UX Improvement: Reset URL to root to ensure we load the NEW latest session
                    // instead of sticking to the old session ID if present in URL.
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('session_id')) {
                        // Push new history state without reload
                        window.history.pushState({}, '', '/');
                    }

                    await this._loadData();
                    // Refresh sessions dropdown
                    this._loadSessions();
                } else {
                    throw new Error(result.message);
                }
            } catch (e) {
                this._showMessage('فشل الاستيراد: ' + e.message, 'error');
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
            // FIX: Pass current query params (session_id) to the API
            // AND add cache buster to prevent stale data
            const queryString = window.location.search;
            const separator = queryString.includes('?') ? '&' : '?';
            const cacheBuster = `${separator}_t=${Date.now()}`;

            const recordsRes = await api.get(`/api/records${queryString}${cacheBuster}`);
            if (!recordsRes.success) throw new Error(recordsRes.message);

            const allRecords = recordsRes.data || [];

            // Backend now handles session filtering if session_id is provided.
            // If no session_id is provided, it returns all.
            // But we want to defaulting behavior? 
            // The controller: "data = sessionId ? records->allBySession : records->all"

            // Client-side Logic Update:
            // If URL has session_id -> Use returned data as is.
            // If URL has NO session_id -> Filter for LATEST session logic (Keep existing "latest" logic for clean landing)

            const urlParams = new URLSearchParams(window.location.search);
            const requestedSessionId = urlParams.get('session_id');

            if (requestedSessionId) {
                // User specifically requested a session, show what API returned
                this.records = allRecords;
                console.log(`[Decision] Showing requested session ${requestedSessionId}: ${this.records.length} records`);
            } else {
                // Default view: Show latest session only
                if (allRecords.length > 0) {
                    const latestSessionId = Math.max(...allRecords.map(r => r.sessionId || 0));
                    this.records = allRecords.filter(r => r.sessionId === latestSessionId);
                    console.log(`[Decision] Defaulting to latest session ${latestSessionId}: ${this.records.length}/${allRecords.length} records`);
                } else {
                    this.records = allRecords;
                }
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
        this.currentFilter = status;

        // Find first record matching the new filter
        const targetIndex = this.records.findIndex(r => this._matchesFilter(r));

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
        // Update index display with filter context
        const currentFilter = this.currentFilter || 'all';
        let filterText = 'سجل';
        let filteredTotal = this.records.length;
        let filteredIndex = this.currentIndex + 1;

        if (currentFilter === 'approved') {
            filterText = 'سجل جاهز';
            // Calculate index within filtered set
            const approvedRecords = this.records.filter(r => this._isCompleted(r));
            filteredTotal = approvedRecords.length;
            filteredIndex = approvedRecords.findIndex(r => r.id === record.id) + 1;
        } else if (currentFilter === 'pending') {
            filterText = 'سجل يحتاج قرار';
            const pendingRecords = this.records.filter(r => !this._isCompleted(r));
            filteredTotal = pendingRecords.length;
            filteredIndex = pendingRecords.findIndex(r => r.id === record.id) + 1;
        }

        // Update Button Text
        // "حفظ سجل رقم XX من اجمالي XX [نوع]، وانتقل للتالي"
        this.DOM.currentIndex.textContent = filteredIndex;
        this.DOM.totalCount.textContent = filteredTotal;

        const btnText = `إحفظ (${filteredIndex} من ${filteredTotal}) ${filterText}، وانتقل للتالي`;
        // We replace the text node but keep the icon if possible, or just replace innerHTML
        // Existing: <span>✓</span> <span>حفظ (<span id="currentIndex">1</span> من <span id="totalCount">22</span>) وانتقال</span>

        // Let's update the span text specifically to preserve structure if needed, 
        // or just update the text parts.
        // Actually, user wants full dynamic text.

        const btnLabel = this.DOM.btnSaveNext.querySelector('span:last-child');
        if (btnLabel) {
            btnLabel.innerHTML = `إحفظ (<span id="currentIndex">${filteredIndex}</span> من <span id="totalCount">${filteredTotal}</span>) ${filterText}، وانتقل للتالي`;
            // Re-bind DOM references because we just wiped them
            this.DOM.currentIndex = document.getElementById('currentIndex');
            this.DOM.totalCount = document.getElementById('totalCount');
        }

        if (this.DOM.metaRecordIndex) {
            this.DOM.metaRecordIndex.textContent = `${filteredIndex} من ${filteredTotal} (${filterText})`;
        }

        // Update meta
        if (this.DOM.metaSessionId) this.DOM.metaSessionId.textContent = record.sessionId || '-';
        this.DOM.metaRecordId.textContent = record.id;
        this.DOM.metaGuarantee.textContent = record.guaranteeNumber || '-';
        this.DOM.metaDate.textContent = record.expiryDate || record.date || '-';
        this.DOM.metaAmount.textContent = record.amount ? `${record.amount} ريال` : '-';

        // Populate new meta fields
        if (this.DOM.metaContract) this.DOM.metaContract.textContent = record.contractNumber || '-';
        if (this.DOM.metaContract) this.DOM.metaContract.textContent = record.contractNumber || '-';

        // Hide Type field if empty
        if (this.DOM.metaType) {
            const hasType = record.type && record.type !== '-';
            this.DOM.metaType.textContent = record.type || '-';
            // Toggle visibility of the parent span (label + value)
            if (this.DOM.metaType.parentElement) {
                this.DOM.metaType.parentElement.style.display = hasType ? 'inline' : 'none';
            }
        }

        // CRITICAL: Reset pending state to prevent leaks from previous record
        // NOTE: This is NOT a bug - it's intentional design to prevent showing
        // "Add Supplier" button with stale data from previous record.
        // The button state is properly updated in _updateAddButtonState() at line 579.
        this._pendingNewSupplierName = null;

        // Update raw details
        // Update raw details
        if (this.DOM.detailRawSupplier) this.DOM.detailRawSupplier.textContent = record.rawSupplierName || '-';
        if (this.DOM.detailRawBank) this.DOM.detailRawBank.textContent = record.rawBankName || '-';

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
            this.selectedBankName = null; // FIX: Clear stale name
            this.DOM.bankInput.classList.remove('has-value');
        }

        if (this.selectedSupplierId && BGL.State.supplierMap[this.selectedSupplierId]) {
            const supplierName = BGL.State.supplierMap[this.selectedSupplierId].official_name;
            this.DOM.supplierInput.value = supplierName;
            this.selectedSupplierName = supplierName;
            this.DOM.supplierInput.classList.add('has-value');
        } else {
            this.DOM.supplierInput.value = '';
            this.selectedSupplierName = null; // FIX: Clear stale name
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

        }

        // CRITICAL: Always update button state logic regardless of selection state
        this._updateAddButtonState(this.DOM.supplierInput.value);



        // Update navigation buttons
        this.DOM.btnPrev.disabled = this.currentIndex === 0;
        this.DOM.btnNext.disabled = this.currentIndex >= this.records.length - 1;

        // Update Letter Preview
        this._updateLetterPreview(record);

        // Render Letter Preview
        this._updateLetterPreview(record);

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

            // Clean styles (Match Chip Style)
            this.DOM.btnAddSupplier.className = "flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border transition-all bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300 hover:scale-105 whitespace-nowrap";

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
            const scoreClass = 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300';

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
    /**
     * Helper to check if record matches current filter
     */
    _matchesFilter(record) {
        if (this.currentFilter === 'all') return true;

        const isCompleted = this._isCompleted(record);
        if (this.currentFilter === 'approved') return isCompleted;
        if (this.currentFilter === 'pending') return !isCompleted;

        return true;
    },

    /**
     * Navigate to previous record matching current filter
     */
    navigatePrev() {
        // Find previous matching record
        let newIndex = -1;
        for (let i = this.currentIndex - 1; i >= 0; i--) {
            if (this._matchesFilter(this.records[i])) {
                newIndex = i;
                break;
            }
        }

        // If found, go to it
        if (newIndex >= 0) {
            this.currentIndex = newIndex;
            this._displayCurrentRecord();
        } else {
            // Optional: User feedback for "No previous records"
        }
    },

    /**
     * Navigate to next record matching current filter
     */
    navigateNext() {
        // Find next matching record
        let newIndex = -1;
        for (let i = this.currentIndex + 1; i < this.records.length; i++) {
            if (this._matchesFilter(this.records[i])) {
                newIndex = i;
                break;
            }
        }

        // If found, go to it
        if (newIndex >= 0) {
            this.currentIndex = newIndex;
            this._displayCurrentRecord();
        } else {
            // Optional: User feedback for "End of list"
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

    // --- Letter Preview Extension ---

    /**
     * Initialize letter preview by loading the template one time
     */
    // --- Letter Preview Extension (Legacy Logic Port) ---

    /**
     * Initialize letter preview
     */
    _initLetterPreview() {
        this.DOM.btnPrintPreview = document.getElementById('btnPrintPreview');
        this.DOM.letterContainer = document.getElementById('letterContainer');

        if (this.DOM.btnPrintPreview) {
            this.DOM.btnPrintPreview.addEventListener('click', () => {
                this._printLetter();
            });
        }
    },

    /**
     * Update the letter preview using Client-Side Template Generation
     * Logic ported from DecisionView.jsx
     */
    _updateLetterPreview(record) {
        if (!this.DOM.letterContainer) return;

        const html = this._generateLetterHtml(record);

        // Inject directly into container (Shadow DOM not needed as styles are scoped via class or unique rules)
        // However, to ensure total isolation like the legacy system apparently did via iframe or careful CSS:
        // The legacy system used <style> inside the string. Let's do the same.
        // We will put it in a friendly wrapper.
        this.DOM.letterContainer.innerHTML = html;
    },

    /**
     * Generate the HTML string for the letter using the record data.
     * Uses external letter.css for styling (no inline CSS)
     */
    _generateLetterHtml(record) {
        // Wrapper that uses current UI selection if matching record, or falls back to record data
        // For preview of CURRENT record, we prefer current UI selection (if user changed dropdown but didn't save)
        // For Print All, we must strictly use record data.

        // If this is the current record being edited, use UI state
        if (record.id === this.records[this.currentIndex]?.id) {
            return this._generateLetterHtmlFromState(record);
        }
        return this._generateLetterHtmlForRecord(record);
    },

    // Refactored to use UI state (Current behavior)
    _generateLetterHtmlFromState(record) {
        const bankName = this.selectedBankName || record.rawBankName || "البنك الرسمي";
        const bankId = this.selectedBankId;
        const supplierName = this.selectedSupplierName || record.rawSupplierName || "المورد";
        const supplierId = this.selectedSupplierId;

        return this._buildLetterHtml(record, bankId, bankName, supplierId, supplierName);
    },

    // Refactored to use Record data purely (For Print All)
    _generateLetterHtmlForRecord(record) {
        // We need to look up bank name from ID if possible, or use raw if valid, or fallback
        // Since record.bankId is stored, get details from State.bankMap
        const bankId = record.bankId;
        let bankName = record.rawBankName || "البنك الرسمي";
        if (bankId && BGL.State.bankMap[bankId]) {
            // In map, it might be 'official_name' or similar. 
            // Let's check map structure usage in original code: BGL.State.bankMap[this.selectedBankId]
            const b = BGL.State.bankMap[bankId];
            bankName = b.official_name || bankName;
        }

        const supplierId = record.supplierId;
        let supplierName = record.rawSupplierName || "المورد";
        if (supplierId && BGL.State.supplierMap[supplierId]) {
            const s = BGL.State.supplierMap[supplierId];
            supplierName = s.official_name || supplierName;
        }

        return this._buildLetterHtml(record, bankId, bankName, supplierId, supplierName);
    },

    // Core generator
    _buildLetterHtml(record, bankId, bankName, supplierId, supplierName) {
        // Get bank address data from dictionary (dynamic)
        const bankData = bankId ? BGL.State.bankMap[bankId] : null;

        const bankContact = {
            department: bankData?.department || "إدارة الضمانات",
            addressLines: [
                bankData?.address_line_1 || "المقر الرئيسي",
                bankData?.address_line_2,
                bankData?.contact_email ? `البريد الالكتروني: ${bankData.contact_email}` : null // Added email with label
            ].filter(Boolean), // Remove null/empty values
            email: bankData?.contact_email || ""
        };


        const guaranteeNo = record.guaranteeNumber || "-";
        const contractNo = record.contractNumber || "-";
        let amount = record.amount ? Number(record.amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) : "-";
        // Convert to Hindi Numerals
        if (amount !== "-") {
            amount = amount.replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);
        }

        // Calculate Renewal Date (Expiry + 1 Year) as requested
        let renewalDate = "-";
        if (record.expiryDate) {
            try {
                const dateObj = new Date(record.expiryDate);
                if (!isNaN(dateObj.getTime())) {
                    // Calculate next year date
                    const nextYearDate = new Date(dateObj);
                    nextYearDate.setFullYear(dateObj.getFullYear() + 1);

                    // Format: Day MonthName Year (Arabic)
                    const formatter = new Intl.DateTimeFormat('ar-EG', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });

                    // "10 أكتوبر 2026"
                    let dateStr = formatter.format(nextYearDate);

                    // Convert to Hindi Numerals
                    dateStr = dateStr.replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);

                    // Append 'م'
                    renewalDate = dateStr + 'م';
                } else {
                    // For manually entered dates, try to format them if possible, otherwise use as is
                    const d = new Date(record.expiryDate);
                    if (!isNaN(d.getTime())) {
                        const formatter = new Intl.DateTimeFormat('ar-EG', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        });
                        renewalDate = formatter.format(d).replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]) + 'م';
                    } else {
                        renewalDate = record.expiryDate;
                    }
                }
            } catch (e) {
                console.error("Date parse error", e);
                renewalDate = record.expiryDate;
            }
        }

        // Determine watermark status
        const hasSupplier = this.selectedSupplierId !== null;
        const hasBank = this.selectedBankId !== null;

        let watermarkText = '';
        let watermarkClass = '';

        if (hasSupplier && hasBank) {
            watermarkText = 'جاهز';
            watermarkClass = 'status-ready';
        } else if (hasSupplier || hasBank) {
            watermarkText = 'يحتاج قرار';
            watermarkClass = 'status-partial';
        } else {
            watermarkText = 'يحتاج قرار';
            watermarkClass = 'status-draft';
        }

        let guaranteeDesc = "الضمان البنكي";
        if (record.type) {
            const t = record.type.toUpperCase();
            if (t === 'FINAL') guaranteeDesc = "الضمان البنكي النهائي";
            else if (t === 'ADVANCED') guaranteeDesc = "ضمان الدفعة المقدمة البنكي";
        }


        // Determine font style based on language
        // If it contains Arabic characters, use default (Arabic). Otherwise (English), use Inter/Sans.
        const hasArabic = /[\u0600-\u06FF]/.test(supplierName);
        const supplierStyle = !hasArabic
            ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;"
            : "";

        // Clean HTML template (styling handled by letter.css)
        return `
        <div class="letter-preview">
            <div class="letter-paper">
                
                <!-- Watermark -->
                <div class="watermark ${watermarkClass}">${watermarkText}</div>
                
                <div class="header-line">
                  <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">السادة / ${this._escapeHtml(bankName)}</div>
                  <div class="greeting">المحترمين</div>
                </div>

                <div>
                   <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${bankContact.department}</div>
                   ${bankContact.addressLines.map(line => {
            if (line.includes('البريد الالكتروني:')) {
                const parts = line.split('البريد الالكتروني:');
                return `<div><span style="text-shadow: 0 0 1px #333, 0 0 1px #333;">البريد الالكتروني:</span>${parts[1]}</div>`;
            }
            return `<div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${line.replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d])}</div>`;
        }).join('')}
                </div>

                <div style="text-align:right; margin: 5px 0;">السَّلام عليكُم ورحمَة الله وبركاتِه</div>

                <div class="subject">
                    <span style="flex:0 0 70px;">الموضوع:</span>
                    <span>
                      طلب تمديد الضمان البنكي رقم (${this._escapeHtml(guaranteeNo)}) 
                      ${(() => {
                if (contractNo === '-') return '';
                let displayNo = contractNo;
                // If it's a PO, convert to Hindi numerals as requested
                if (record.contractSource === 'po') {
                    displayNo = String(displayNo).replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);
                }
                return `والعائد ${record.contractSource === 'po' ? 'لأمر الشراء' : 'للعقد'} رقم (${displayNo})`;
            })()}
                    </span>
                </div>

                <div class="first-paragraph">
                    إشارة الى ${guaranteeDesc} الموضح أعلاه، والصادر منكم لصالحنا على حساب 
                    <span style="${supplierStyle}">${this._escapeHtml(supplierName)}</span> 
                    بمبلغ قدره (<strong>${amount}</strong>) ريال، 
                    نأمل منكم <span class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">تمديد فترة سريان الضمان حتى تاريخ ${renewalDate}</span>، 
                    مع بقاء الشروط الأخرى دون تغيير، وإفادتنا بذلك من خلال البريد الالكتروني المخصص للضمانات البنكية لدى مستشفى الملك فيصل التخصصي ومركز الأبحاث بالرياض (bgfinance@kfshrc.edu.sa)، كما نأمل منكم إرسال أصل تمديد الضمان الى:
                </div>

                <div style="margin-top: 5px; margin-right: 50px;">
                    <div>مستشفى الملك فيصل التخصصي ومركز الأبحاث – الرياض</div>
                    <div>ص.ب ٣٣٥٤ الرياض ١١٢١١</div>
                    <div>مكتب الخدمات الإدارية</div>
                </div>

                <div class="first-paragraph">
                    علمًا بأنه في حال عدم تمكن البنك من تمديد الضمان المذكور قبل انتهاء مدة سريانه، فيجب على البنك دفع قيمة الضمان إلينا حسب النظام.
                </div>

                <div style="text-indent:5em; margin-top:5px;">وَتفضَّلوا بِقبُول خَالِص تحيَّاتِي</div>

                <div class="fw-800-sharp" style="text-align: center; margin-top: 5px; margin-right: 320px;">
                    <div style="margin-bottom: 60px; text-shadow: 0 0 1px #333, 0 0 1px #333;">مُدير الإدارة العامَّة للعمليَّات المحاسبيَّة</div>
                    <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">سَامِي بن عبَّاس الفايز</div>
                </div>

                <div style="position:absolute; left:1in; right:1in; bottom:0.7in; display:flex; justify-content:space-between; font-size:9pt;">
                  <span>MBC:09-2</span>
                  <span>BAMZ</span>
                </div>

            </div>
        </div>
        `;
    },

    /**
     * Handle printing logic via hidden iframe or window.print styles
     */
    _printLetter() {
        // Since we are now injecting into main DOM, we can use window.print() 
        // relying on @media print styles to hide everything else.
        window.print();
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
