/**
 * Records Table Logic
 * Handles rendering, sorting, and filtering of the main records table.
 */

window.BGL = window.BGL || {};

window.BGL.Table = {

    /**
     * Format entity for display (using cache)
     */
    formatEntity(raw, normalized, id, typeLabel, displayOverride = '') {
        const State = BGL.State;
        const Utils = BGL.Utils;

        const hasId = !!id;
        let fromCache = '';

        if (hasId) {
            if (typeLabel === 'bank' && State.bankMap[id]) {
                fromCache = State.bankMap[id].official_name || '';
            } else if (typeLabel === 'supplier' && State.supplierMap[id]) {
                fromCache = State.supplierMap[id].official_name || '';
            }
        }

        const normHit = typeLabel === 'bank' && normalized && State.bankNormMap[normalized]
            ? State.bankNormMap[normalized]
            : '';

        const preferred = displayOverride || fromCache || normHit;
        const display = preferred || normalized || raw || '—';
        const title = raw ? `title="القيمة الأصلية: ${Utils.escapeHtml(raw)}"` : '';

        // Visual cue for resolved vs raw
        const style = hasId ? 'font-weight:600; color:#1f2937;' : 'color:#4b5563;';
        return `<span ${title} style="${style}">${Utils.escapeHtml(display)}</span>`;
    },

    /**
     * Format editable cell for supplier/bank
     * Used in new inline-edit mode
     */
    formatEditableCell(record, field) {
        const State = BGL.State;
        const Utils = BGL.Utils;

        let currentId = null;
        let displayValue = '';
        let rawValue = '';

        if (field === 'supplier') {
            currentId = record.supplier_id;
            rawValue = record.rawSupplierName || '';

            if (currentId && State.supplierMap[currentId]) {
                displayValue = State.supplierMap[currentId].official_name || '';
            } else {
                displayValue = record.normalizedSupplierName || rawValue || '—';
            }
        } else {
            currentId = record.bank_id;
            rawValue = record.rawBankName || '';

            if (currentId && State.bankMap[currentId]) {
                displayValue = State.bankMap[currentId].official_name || '';
            } else {
                displayValue = rawValue || '—';
            }
        }

        const isApproved = record.matchStatus === 'approved';
        const hasValue = !!currentId;
        const safeDisplay = Utils.escapeHtml(displayValue);
        const safeRaw = Utils.escapeHtml(rawValue);

        // CSS classes based on state
        const cellClass = hasValue ? 'has-value' : 'needs-value';
        const valueStyle = hasValue
            ? 'font-weight: 600; color: #1f2937;'
            : 'color: #6b7280; font-style: italic;';

        return `
            <div class="inline-edit-cell ${cellClass}" 
                 data-record-id="${record.id}" 
                 data-field="${field}">
                
                <!-- Display Mode -->
                <div class="display-value flex items-center gap-2 cursor-pointer hover:bg-gray-50 rounded px-2 py-1 -mx-2 transition-colors" 
                     style="${valueStyle}"
                     title="القيمة الأصلية: ${safeRaw}">
                    <span class="flex-1 truncate">${safeDisplay}</span>
                    ${isApproved && hasValue ? `
                        <span class="edit-icon text-gray-400 hover:text-blue-500 transition-colors" title="تعديل">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </span>
                    ` : ''}
                    ${!hasValue ? `
                        <span class="text-orange-500 text-xs">⚠️</span>
                    ` : ''}
                </div>
                
                <!-- Edit Mode (hidden by default) -->
                <div class="edit-mode hidden">
                    <div class="flex items-center gap-1">
                        <input type="text" 
                               class="inline-input w-full border border-blue-300 rounded px-2 py-1.5 text-sm text-right 
                                      focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                               placeholder="${field === 'supplier' ? 'ابحث عن المورد...' : 'ابحث عن البنك...'}"
                               autocomplete="off">
                    </div>
                </div>
                
                <!-- Save Indicator -->
                <span class="save-indicator hidden absolute -top-1 -right-1">
                    <span class="status-saving hidden">
                        <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span class="status-saved hidden text-green-500 text-sm">✓</span>
                    <span class="status-error hidden text-red-500 text-sm">⚠️</span>
                </span>
            </div>
        `;
    },

    /**
     * Sort records array
     */
    sortRecords() {
        const State = BGL.State;
        const Config = BGL.Config;

        State.records.sort((a, b) => {
            let vA = a[Config.sortKey] ?? '';
            let vB = b[Config.sortKey] ?? '';

            // Numeric sort for IDs and Scores
            if (Config.sortKey === 'id' || Config.sortKey === 'score') {
                vA = parseFloat(vA) || 0;
                vB = parseFloat(vB) || 0;
            } else {
                vA = vA.toString().toLowerCase();
                vB = vB.toString().toLowerCase();
            }

            if (vA < vB) return Config.sortDir === 'asc' ? -1 : 1;
            if (vA > vB) return Config.sortDir === 'asc' ? 1 : -1;
            return 0;
        });
    },

    /**
     * Render the table
     */
    render() {
        const State = BGL.State;
        const Config = BGL.Config;
        const Utils = BGL.Utils;
        const DOM = BGL.DOM;

        if (!DOM.tableBody) return;

        const fStatus = DOM.statusFilter ? DOM.statusFilter.value : 'all';

        // Filter
        let visible = State.records.filter(r => {
            if (fStatus === 'completed') return r.matchStatus !== 'pending';
            if (fStatus === 'pending') return r.matchStatus === 'pending';
            return true; // all
        });

        // Update stats
        const total = visible.length;
        const completed = visible.filter(r => r.matchStatus !== 'pending').length;
        if (DOM.paginationInfo) {
            DOM.paginationInfo.textContent = `العدد: ${total} | المكتمل: ${completed}`;
        }

        if (visible.length === 0) {
            DOM.tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-gray-500">لا توجد سجلات مطابقة للبحث</td></tr>';
            return;
        }

        // Sort
        this.sortRecords();

        // Re-filter after sort
        const displayList = State.records.filter(r => {
            if (fStatus === 'completed') return r.matchStatus !== 'pending';
            if (fStatus === 'pending') return r.matchStatus === 'pending';
            return true;
        });

        DOM.tableBody.innerHTML = displayList.map(r => {
            // Check for active panel
            const isActive = State.selectedRecord && State.selectedRecord.id === r.id;
            let rowClass = '';
            if (isActive) rowClass += ' border-l-4 border-l-blue-500';

            const statusLabel = {
                'needs_review': 'يحتاج مراجعة',
                'ready': 'جاهز',
                'completed': 'مكتمل',
                'pending': 'قيد الانتظار',
                'approved': 'معتمد',
                'rejected': 'مرفوض'
            }[r.matchStatus] || r.matchStatus;

            const statusClass = {
                'needs_review': 'bg-orange-100 text-orange-800',
                'ready': 'bg-green-100 text-green-800',
                'completed': 'bg-gray-100 text-gray-800',
                'pending': 'bg-blue-100 text-blue-800',
                'approved': 'bg-green-100 text-green-800',
                'rejected': 'bg-red-100 text-red-800'
            }[r.matchStatus] || 'bg-gray-100';

            // Format Date
            const dateDisplay = (r.date || '').split(' ')[0] || '-';

            // Format Match Score
            const scoreDisplay = (r.maxScore !== undefined && r.maxScore !== null)
                ? `<span class="font-bold ${r.maxScore >= 80 ? 'text-green-600' : 'text-orange-600'}">${Math.round(r.maxScore)}%</span>`
                : '-';

            // Display-only: using formatEntity (decision is on separate page)
            const supplierHtml = this.formatEntity(r.rawSupplierName, r.normalizedSupplierName, r.supplier_id, 'supplier');
            const bankHtml = this.formatEntity(r.rawBankName, null, r.bank_id, 'bank');

            let html = `
                <tr class="hover:bg-gray-50 border-b cursor-pointer transition-colors ${rowClass}" data-id="${r.id}">
                    <td class="p-3 text-sm text-gray-600">${r.id}</td>
                    <td class="p-3 font-medium text-gray-900">
                        ${supplierHtml}
                    </td>
                    <td class="p-3 text-gray-700">
                        ${bankHtml}
                    </td>
                    <td class="p-3 text-gray-700 font-mono text-xs">${Utils.escapeHtml(r.guaranteeNumber || '—')}</td>
                    <td class="p-3 text-gray-600 whitespace-nowrap" dir="ltr" title="تاريخ الانتهاء">${r.expiryDate || r.date || '-'}</td>
                    <td class="p-3 text-right">${scoreDisplay}</td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusClass}">
                            ${statusLabel}
                        </span>
                    </td>
                </tr>
            `;

            return html;
        }).join('');
    },

    /**
     * Setup Header Sorting
     */
    setupSorting() {
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const key = th.dataset.sort;
                const Config = BGL.Config;

                if (Config.sortKey === key) {
                    Config.sortDir = Config.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    Config.sortKey = key;
                    Config.sortDir = 'desc';
                }

                // Update icons
                document.querySelectorAll('th[data-sort] span').forEach(s => s.textContent = '⇅');
                th.querySelector('span').textContent = Config.sortDir === 'asc' ? '↑' : '↓';

                this.render();
            });
        });
    }
};
