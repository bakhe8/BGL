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

            // Append Panel Row if Active
            if (isActive) {
                html += `<tr id="panel-row" class="bg-blue-50/30">${Config.PANEL_HTML_TEMPLATE}</tr>`;
            }

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
