/**
 * =============================================================================
 * Inline Edit Module
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Handles inline editing of supplier and bank fields directly within the table.
 * Replaces the Decision Panel with in-cell editing for a streamlined UX.
 * 
 * DESIGN DECISIONS:
 * -----------------
 * 1. Uses Event Delegation for performance (100+ rows)
 * 2. Uses Portal Pattern - single suggestions list that moves to active cell
 * 3. Auto-save with 500ms debounce after selection
 * 4. Approved records show as static text with edit icon
 * 
 * =============================================================================
 */

window.BGL = window.BGL || {};

window.BGL.InlineEdit = {

    // Debounce timer reference
    _saveTimeout: null,

    // Reference to the global suggestions portal
    _suggestionsPortal: null,

    /**
     * Initialize the inline edit system
     * Called once on DOMContentLoaded
     */
    init() {
        this._createSuggestionsPortal();
        this._setupEventDelegation();
        console.log('[InlineEdit] Initialized');
    },

    /**
     * Create the global suggestions portal (single <ul> that moves around)
     */
    _createSuggestionsPortal() {
        // Check if already exists
        if (document.getElementById('inline-suggestions-portal')) {
            this._suggestionsPortal = document.getElementById('inline-suggestions-portal');
            return;
        }

        const portal = document.createElement('ul');
        portal.id = 'inline-suggestions-portal';
        portal.className = 'fixed z-[9999] bg-white border border-gray-200 rounded-lg shadow-xl max-h-60 overflow-y-auto hidden';
        portal.setAttribute('role', 'listbox');
        portal.style.minWidth = '250px';
        document.body.appendChild(portal);
        this._suggestionsPortal = portal;
    },

    /**
     * Setup event delegation on table body
     */
    _setupEventDelegation() {
        const tableBody = BGL.DOM.tableBody;
        if (!tableBody) return;

        // Click on editable cell
        tableBody.addEventListener('click', (e) => {
            const cell = e.target.closest('.inline-edit-cell');
            if (cell) {
                e.stopPropagation();
                this._handleCellClick(cell);
            }
        });

        // Input events for search
        tableBody.addEventListener('input', (e) => {
            if (e.target.classList.contains('inline-input')) {
                this._handleInput(e.target);
            }
        });

        // Focus events
        tableBody.addEventListener('focusin', (e) => {
            if (e.target.classList.contains('inline-input')) {
                this._handleFocus(e.target);
            }
        });

        // Blur events
        tableBody.addEventListener('focusout', (e) => {
            if (e.target.classList.contains('inline-input')) {
                // Delay to allow click on suggestion
                setTimeout(() => this._handleBlur(e.target), 200);
            }
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.inline-edit-cell') &&
                !e.target.closest('#inline-suggestions-portal')) {
                this.deactivate();
            }
        });

        // Keyboard navigation
        tableBody.addEventListener('keydown', (e) => {
            if (e.target.classList.contains('inline-input')) {
                this._handleKeydown(e);
            }
        });
    },

    /**
     * Handle cell click - activate edit mode
     */
    async _handleCellClick(cell) {
        const recordId = parseInt(cell.dataset.recordId);
        const field = cell.dataset.field;
        const record = BGL.State.records.find(r => r.id === recordId);

        if (!record) return;

        // Check if already approved and not in edit mode
        const isApproved = record.matchStatus === 'approved';
        const hasValue = field === 'supplier' ? record.supplier_id : record.bank_id;

        // If clicking edit icon on approved record
        if (isApproved && hasValue && !cell.classList.contains('editing')) {
            // Show edit icon is clickable
            const editIcon = cell.querySelector('.edit-icon');
            if (editIcon && !cell.contains(document.activeElement)) {
                // First click shows the input
                this._activateCell(cell, record, field);
                return;
            }
        }

        // Activate if not already editing this cell
        if (BGL.State.editing.recordId !== recordId || BGL.State.editing.field !== field) {
            this._activateCell(cell, record, field);
        }
    },

    /**
     * Activate a cell for editing
     */
    async _activateCell(cell, record, field) {
        // Deactivate any previous cell
        this.deactivate();

        const recordId = record.id;

        // Get current value
        let currentValue = '';
        let currentId = null;

        if (field === 'supplier') {
            currentId = record.supplier_id;
            if (currentId && BGL.State.supplierMap[currentId]) {
                currentValue = BGL.State.supplierMap[currentId].official_name || '';
            } else {
                currentValue = record.normalizedSupplierName || record.rawSupplierName || '';
            }
        } else {
            currentId = record.bank_id;
            if (currentId && BGL.State.bankMap[currentId]) {
                currentValue = BGL.State.bankMap[currentId].official_name || '';
            } else {
                currentValue = record.rawBankName || '';
            }
        }

        // Update state
        BGL.State.editing = {
            recordId,
            field,
            originalValue: { id: currentId, name: currentValue },
            pendingValue: null,
            isSaving: false,
            candidates: []
        };

        // Show edit mode
        cell.classList.add('editing');
        const displayEl = cell.querySelector('.display-value');
        const editEl = cell.querySelector('.edit-mode');
        const input = cell.querySelector('.inline-input');

        if (displayEl) displayEl.classList.add('hidden');
        if (editEl) editEl.classList.remove('hidden');

        if (input) {
            input.value = currentValue;
            input.focus();
            input.select();
        }

        // Load candidates from API
        await this._loadCandidates(recordId, field);
    },

    /**
     * Load candidates from API
     */
    async _loadCandidates(recordId, field) {
        try {
            // Check if we have pending decisions with candidates already
            const pending = BGL.State.pendingDecisions[recordId];
            if (pending && pending.candidates && pending.candidates[field]) {
                BGL.State.editing.candidates = pending.candidates[field];
                return;
            }

            // Fetch from API
            const json = await api.get(`/api/records/${recordId}/candidates`);
            if (!json.success) throw new Error(json.message);

            const data = json.data;
            if (field === 'supplier') {
                BGL.State.editing.candidates = data.supplier?.candidates || [];
            } else {
                BGL.State.editing.candidates = data.bank?.candidates || [];
            }

            // Cache for later
            if (!BGL.State.pendingDecisions[recordId]) {
                BGL.State.pendingDecisions[recordId] = { candidates: {} };
            }
            BGL.State.pendingDecisions[recordId].candidates =
                BGL.State.pendingDecisions[recordId].candidates || {};
            BGL.State.pendingDecisions[recordId].candidates[field] = BGL.State.editing.candidates;

        } catch (e) {
            console.error('[InlineEdit] Failed to load candidates:', e);
            BGL.State.editing.candidates = [];
        }
    },

    /**
     * Handle input typing
     */
    _handleInput(input) {
        const query = input.value.toLowerCase().trim();
        const field = BGL.State.editing.field;

        // Get suggestions
        const suggestions = this._getSuggestions(query, field);

        // Render portal
        this._renderSuggestions(suggestions, input);
    },

    /**
     * Handle focus on input
     */
    _handleFocus(input) {
        const query = input.value.toLowerCase().trim();
        const field = BGL.State.editing.field;
        const suggestions = this._getSuggestions(query, field);
        this._renderSuggestions(suggestions, input);
    },

    /**
     * Handle blur
     */
    _handleBlur(input) {
        // If no selection was made, keep the portal hidden but don't deactivate
        if (this._suggestionsPortal) {
            this._suggestionsPortal.classList.add('hidden');
        }
    },

    /**
     * Handle keyboard navigation
     */
    _handleKeydown(e) {
        const portal = this._suggestionsPortal;
        if (!portal || portal.classList.contains('hidden')) return;

        const items = portal.querySelectorAll('li[data-id]');
        const activeItem = portal.querySelector('li.bg-blue-100');
        let activeIndex = Array.from(items).indexOf(activeItem);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (activeIndex < items.length - 1) {
                    if (activeItem) activeItem.classList.remove('bg-blue-100');
                    items[activeIndex + 1].classList.add('bg-blue-100');
                    items[activeIndex + 1].scrollIntoView({ block: 'nearest' });
                } else if (activeIndex === -1 && items.length > 0) {
                    items[0].classList.add('bg-blue-100');
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (activeIndex > 0) {
                    if (activeItem) activeItem.classList.remove('bg-blue-100');
                    items[activeIndex - 1].classList.add('bg-blue-100');
                    items[activeIndex - 1].scrollIntoView({ block: 'nearest' });
                }
                break;

            case 'Enter':
                e.preventDefault();
                if (activeItem) {
                    const id = activeItem.dataset.id;
                    const name = activeItem.dataset.name;
                    this._selectItem(id, name);
                }
                break;

            case 'Escape':
                e.preventDefault();
                this.deactivate();
                break;
        }
    },

    /**
     * Get filtered suggestions
     */
    _getSuggestions(query, field) {
        const State = BGL.State;
        const candidates = State.editing.candidates || [];

        // 1. Smart candidates from API
        const smart = candidates.filter(c =>
            (c.name || '').toLowerCase().includes(query)
        );

        // 2. Dictionary search
        let dict = [];
        if (field === 'supplier' && State.supplierCache) {
            dict = State.supplierCache
                .filter(s => (s.official_name || '').toLowerCase().includes(query))
                .map(s => ({
                    name: s.official_name,
                    id: s.id,
                    supplier_id: s.id,
                    score: 0,
                    source: 'dictionary'
                }));
        } else if (field === 'bank' && State.bankMap) {
            dict = Object.values(State.bankMap)
                .filter(b => (b.official_name || '').toLowerCase().includes(query))
                .map(b => ({
                    name: b.official_name,
                    id: b.id,
                    bank_id: b.id,
                    score: 0,
                    source: 'dictionary'
                }));
        }

        // Deduplicate
        const seen = new Set(smart.map(c => c.name));
        const merged = [...smart];
        dict.forEach(d => {
            if (!seen.has(d.name)) {
                merged.push(d);
                seen.add(d.name);
            }
        });

        return merged.slice(0, 30);
    },

    /**
     * Render suggestions in portal
     */
    _renderSuggestions(items, input) {
        const portal = this._suggestionsPortal;
        if (!portal) return;

        const field = BGL.State.editing.field;

        // Position portal near input
        const rect = input.getBoundingClientRect();
        portal.style.top = `${rect.bottom + window.scrollY + 4}px`;
        portal.style.left = `${rect.left + window.scrollX}px`;
        portal.style.minWidth = `${rect.width}px`;

        if (items.length === 0) {
            portal.innerHTML = `
                <li class="p-3 text-gray-400 text-sm text-center">
                    لا توجد نتائج
                </li>
                ${field === 'supplier' ? `
                    <li class="p-2 border-t text-center">
                        <button class="add-new-btn text-blue-600 hover:text-blue-800 text-sm font-medium" 
                                data-action="add-new">
                            + إضافة مورد جديد
                        </button>
                    </li>
                ` : ''}
            `;
        } else {
            portal.innerHTML = items.map(item => {
                const isSmart = item.score > 0;
                const scoreLabel = isSmart
                    ? `<span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded ml-2">${Math.round(item.score * 100)}%</span>`
                    : '';
                const id = item.supplier_id || item.bank_id || item.id;

                return `
                    <li class="p-2.5 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-100 flex justify-between items-center transition-colors"
                        role="option"
                        data-id="${id}"
                        data-name="${BGL.Utils.escapeHtml(item.name)}">
                        <span class="flex items-center gap-2">
                            ${BGL.Utils.escapeHtml(item.name)}
                            ${scoreLabel}
                        </span>
                        ${isSmart ? '<span class="text-xs text-gray-400">مقترح</span>' : ''}
                    </li>
                `;
            }).join('');

            // Add "Add New" option for suppliers
            if (field === 'supplier') {
                portal.innerHTML += `
                    <li class="p-2 border-t text-center sticky bottom-0 bg-white">
                        <button class="add-new-btn text-blue-600 hover:text-blue-800 text-sm font-medium" 
                                data-action="add-new">
                            + إضافة مورد جديد
                        </button>
                    </li>
                `;
            }
        }

        // Bind click handlers
        portal.querySelectorAll('li[data-id]').forEach(li => {
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                const id = li.dataset.id;
                const name = li.dataset.name;
                this._selectItem(id, name);
            });
        });

        // Bind add-new button
        const addNewBtn = portal.querySelector('.add-new-btn');
        if (addNewBtn) {
            addNewBtn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                this._handleAddNew();
            });
        }

        portal.classList.remove('hidden');
    },

    /**
     * Select an item from suggestions
     */
    _selectItem(id, name) {
        const State = BGL.State;
        const { recordId, field } = State.editing;

        // Update pending decision
        if (!State.pendingDecisions[recordId]) {
            State.pendingDecisions[recordId] = {};
        }

        if (field === 'supplier') {
            State.pendingDecisions[recordId].supplierId = parseInt(id);
            State.pendingDecisions[recordId].supplierName = name;
        } else {
            State.pendingDecisions[recordId].bankId = parseInt(id);
            State.pendingDecisions[recordId].bankName = name;
        }

        // Update input
        const cell = document.querySelector(
            `.inline-edit-cell[data-record-id="${recordId}"][data-field="${field}"]`
        );
        if (cell) {
            const input = cell.querySelector('.inline-input');
            if (input) input.value = name;
        }

        // Hide portal
        this._suggestionsPortal.classList.add('hidden');

        // Auto-save with debounce
        this._debouncedSave(recordId);
    },

    /**
     * Handle add new supplier
     */
    _handleAddNew() {
        const State = BGL.State;
        const record = State.records.find(r => r.id === State.editing.recordId);

        if (record && typeof BGL.Overlay !== 'undefined') {
            BGL.Overlay.open(record.rawSupplierName);
        }

        this.deactivate();
    },

    /**
     * Debounced save to API
     */
    _debouncedSave(recordId) {
        if (this._saveTimeout) {
            clearTimeout(this._saveTimeout);
        }

        this._saveTimeout = setTimeout(() => {
            this._saveDecision(recordId);
        }, 500);
    },

    /**
     * Save decision to API
     */
    async _saveDecision(recordId) {
        const State = BGL.State;
        const pending = State.pendingDecisions[recordId];

        if (!pending) return;

        // Check if we have both values
        const record = State.records.find(r => r.id === recordId);
        if (!record) return;

        const supplierId = pending.supplierId || record.supplier_id;
        const bankId = pending.bankId || record.bank_id;

        // Need both to save
        if (!supplierId || !bankId) {
            console.log('[InlineEdit] Waiting for both supplier and bank selection');
            return;
        }

        // Show saving indicator
        this._showSaveIndicator(recordId, 'saving');
        State.editing.isSaving = true;

        try {
            const payload = {
                matchStatus: 'approved',
                supplier_id: supplierId,
                bank_id: bankId,
                decisionResult: 'manual'
            };

            const res = await api.post(`/api/records/${recordId}/decision`, payload);

            if (res.success) {
                // Update local record
                const idx = State.records.findIndex(r => r.id === recordId);
                if (idx !== -1) {
                    State.records[idx].matchStatus = 'approved';
                    State.records[idx].match_status = 'approved';
                    State.records[idx].supplier_id = supplierId;
                    State.records[idx].bank_id = bankId;
                    State.records[idx].decisionResult = 'manual';
                }

                // Show success
                this._showSaveIndicator(recordId, 'saved');

                // Clear pending
                delete State.pendingDecisions[recordId];

                // Deactivate after brief delay
                setTimeout(() => {
                    this.deactivate();
                    BGL.Table.render();
                }, 800);

            } else {
                throw new Error(res.message);
            }

        } catch (e) {
            console.error('[InlineEdit] Save failed:', e);
            this._showSaveIndicator(recordId, 'error');
            State.editing.isSaving = false;
        }
    },

    /**
     * Show save indicator
     */
    _showSaveIndicator(recordId, status) {
        const cells = document.querySelectorAll(
            `.inline-edit-cell[data-record-id="${recordId}"]`
        );

        cells.forEach(cell => {
            const indicator = cell.querySelector('.save-indicator');
            if (!indicator) return;

            indicator.classList.remove('hidden');
            indicator.querySelectorAll('span').forEach(s => s.classList.add('hidden'));

            const statusEl = indicator.querySelector(`.status-${status}`);
            if (statusEl) statusEl.classList.remove('hidden');
        });
    },

    /**
     * Deactivate editing mode
     */
    deactivate() {
        const State = BGL.State;

        // Hide portal
        if (this._suggestionsPortal) {
            this._suggestionsPortal.classList.add('hidden');
        }

        // Reset cell UI
        const activeCell = document.querySelector('.inline-edit-cell.editing');
        if (activeCell) {
            activeCell.classList.remove('editing');
            const displayEl = activeCell.querySelector('.display-value');
            const editEl = activeCell.querySelector('.edit-mode');

            if (displayEl) displayEl.classList.remove('hidden');
            if (editEl) editEl.classList.add('hidden');
        }

        // Reset state
        State.editing = {
            recordId: null,
            field: null,
            originalValue: null,
            pendingValue: null,
            isSaving: false,
            candidates: []
        };
    },

    /**
     * Check if a record needs both supplier and bank
     */
    needsBothSelections(recordId) {
        const record = BGL.State.records.find(r => r.id === recordId);
        if (!record) return false;

        const pending = BGL.State.pendingDecisions[recordId] || {};
        const hasSupplierId = pending.supplierId || record.supplier_id;
        const hasBankId = pending.bankId || record.bank_id;

        return !hasSupplierId || !hasBankId;
    }
};
