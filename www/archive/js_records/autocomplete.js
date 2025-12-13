/**
 * Autocomplete Component
 * Handles the logic for Supplier and Bank comboboxes.
 * Includes ARIA accessibility support and client-side filtering.
 */

window.BGL = window.BGL || {};

window.BGL.Autocomplete = {

    /**
     * Setup a combobox
     */
    setup(input, listEl, candidates, type, record) {
        // Pre-selection Logic
        let currentId = null;
        if (type === 'supplier' && record.supplier_id) currentId = record.supplier_id;
        if (type === 'bank' && record.bank_id) currentId = record.bank_id;

        // Event: Input Typing
        input.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase().trim();

            // Reset ID on manual type
            if (type === 'supplier') BGL.State.selectedSupplierId = null;
            else BGL.State.selectedBankId = null;

            // Filter Function
            const filtered = this.getSuggestions(val, candidates, type);

            // Render
            this.renderList(listEl, filtered, type, val);

            // Show/Hide
            if (filtered.length > 0) listEl.classList.remove('hidden');
            else listEl.classList.add('hidden');
        });

        // Event: Focus
        input.addEventListener('focus', () => {
            const val = input.value.toLowerCase().trim();
            const filtered = this.getSuggestions(val, candidates, type);
            this.renderList(listEl, filtered, type, val);
            listEl.classList.remove('hidden');
        });

        // Event: Blur
        input.addEventListener('blur', () => {
            setTimeout(() => listEl.classList.add('hidden'), 200);
        });

        // Initial Pre-fill
        if (currentId) {
            this.preselect(input, type, currentId);
        } else if (candidates.length > 0) {
            // Auto-fill Enhancement: Use the first candidate if available
            const best = candidates[0];
            input.value = best.name;
            if (type === 'supplier') BGL.State.selectedSupplierId = best.supplier_id;
            // Bank candidates might use 'bank_id' or 'id' depending on source. CandidateService usually sends 'bank_id'.
            if (type === 'bank') BGL.State.selectedBankId = best.bank_id || best.id;
        }

        // Accessibility
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        listEl.setAttribute('role', 'listbox');
    },

    /**
     * Get filtered suggestions
     */
    getSuggestions(query, candidates, type) {
        const State = BGL.State;

        // 1. Smart Candidates
        const smart = candidates.filter(c => (c.name || '').toLowerCase().includes(query));

        // 2. Dictionary Search (Full Cache)
        let dict = [];
        if (type === 'supplier' && State.supplierCache) {
            dict = State.supplierCache
                .filter(s => (s.official_name || '').toLowerCase().includes(query))
                .map(s => ({
                    name: s.official_name,
                    id: s.id,
                    supplier_id: s.id,
                    score: 0,
                    source: 'Search'
                }));
        } else if (type === 'bank' && State.bankMap) {
            dict = Object.values(State.bankMap)
                .filter(b => (b.official_name || '').toLowerCase().includes(query))
                .map(b => ({
                    name: b.official_name,
                    id: b.id,
                    bank_id: b.id,
                    score: 0,
                    source: 'Search'
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

        return merged.slice(0, 50);
    },

    /**
     * Render the dropdown list
     */
    renderList(listEl, items, type, query) {
        const Utils = BGL.Utils;

        if (items.length === 0) {
            listEl.innerHTML = '<li class="p-2 text-gray-400 text-xs text-center">لا توجد نتائج (اكتب لإنشاء جديد)</li>';
            return;
        }

        listEl.innerHTML = items.map(item => {
            const isSmart = (item.score > 0);
            const sourceLabel = isSmart ? `<span class="text-xs bg-blue-100 text-blue-700 px-1 rounded ml-2">${Math.round(item.score * 100)}%</span>` : '';
            const id = item.supplier_id || item.bank_id || item.id;

            return `
           <li class="p-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-100 flex justify-between items-center"
               role="option"
               data-id="${id}"
               data-name="${Utils.escapeHtml(item.name)}">
               <span>
                  ${Utils.escapeHtml(item.name)}
                  ${sourceLabel}
               </span>
               ${isSmart ? '<span class="text-xs text-gray-400">مترح</span>' : ''}
           </li>
         `;
        }).join('');

        // Bind Clicks
        listEl.querySelectorAll('li').forEach(li => {
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                const id = li.dataset.id;
                const name = li.dataset.name;
                this.selectItem(type, id, name);
            });
        });

        const input = type === 'supplier' ? document.getElementById('supplierInput') : document.getElementById('bankInput');
        if (input) input.setAttribute('aria-expanded', 'true');
    },

    /**
     * Select an item
     */
    selectItem(type, id, name) {
        const State = BGL.State;

        if (type === 'supplier') {
            const input = document.getElementById('supplierInput');
            if (input) input.value = name;
            State.selectedSupplierId = id;
            State.selectedSupplierName = name;
            const list = document.getElementById('supplierSuggestions');
            if (list) list.classList.add('hidden');
        } else {
            const input = document.getElementById('bankInput');
            if (input) input.value = name;
            State.selectedBankId = id;
            State.selectedBankName = name;
            const display = document.getElementById('bankDisplayBox');
            if (display) {
                display.textContent = `معتمد: ${name}`;
                display.classList.remove('hidden');
            }
            const list = document.getElementById('bankSuggestions');
            if (list) list.classList.add('hidden');
        }
    },

    /**
     * Pre-select item based on ID
     */
    preselect(input, type, id) {
        const State = BGL.State;
        let name = '';
        if (type === 'supplier' && State.supplierMap[id]) {
            name = State.supplierMap[id].official_name;
            State.selectedSupplierId = id;
            State.selectedSupplierName = name;
        } else if (type === 'bank' && State.bankMap[id]) {
            name = State.bankMap[id].official_name;
            State.selectedBankId = id;
            State.selectedBankName = name;
            const display = document.getElementById('bankDisplayBox');
            if (display) {
                display.textContent = `معتمد: ${name}`;
                display.classList.remove('hidden');
            }
        }

        if (name) input.value = name;
    }
};
