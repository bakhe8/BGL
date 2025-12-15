/**
 * Navigation Module
 * Handles History (Sessions) and URL State
 */

import { fetchSessions } from '../api.js';
import * as State from '../state.js';
import { DOM } from './layout.js';

export async function initNavigation() {
    const sessions = await fetchSessions();
    if (sessions && sessions.length > 0) {
        setupSessionDropdown(sessions);
    }
}

function setupSessionDropdown(availableSessions) {
    const sessionMeta = DOM.metaSessionId;
    if (!sessionMeta) return;

    // Styling
    sessionMeta.style.cursor = 'pointer';
    sessionMeta.style.textDecoration = 'underline';
    sessionMeta.style.textUnderlineOffset = '2px';
    sessionMeta.title = 'انقر لتغيير الجلسة';

    // Dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'absolute bg-white border border-gray-200 shadow-xl rounded-lg z-50 hidden';
    dropdown.style.top = '100%';
    dropdown.style.right = '0';
    dropdown.style.width = '240px';
    dropdown.style.maxHeight = '300px';
    dropdown.style.overflowY = 'auto';

    // Search
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

    sessionMeta.parentElement.style.position = 'relative';
    sessionMeta.parentElement.appendChild(dropdown);

    // Filter Logic
    const renderList = (filter = '') => {
        listContainer.innerHTML = '';
        const filtered = availableSessions.filter(s =>
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

            // Highlight current
            if (s.session_id == State.currentSessionId) {
                item.classList.add('bg-blue-50', 'font-bold');
            }

            const dateStr = s.last_date ? s.last_date.split(' ')[0] : '-';

            item.innerHTML = `
                <div class="flex flex-col">
                    <span class="font-medium text-gray-700">جلسة #${s.session_id}</span>
                    <span class="text-[10px] text-gray-400">${dateStr}</span>
                </div>
                <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-[10px]">${s.record_count}</span>
            `;

            item.addEventListener('click', () => {
                // Navigate via URL
                window.location.href = `/?session_id=${s.session_id}`;
            });

            listContainer.appendChild(item);
        });
    };

    // Events
    sessionMeta.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = dropdown.classList.contains('hidden');
        document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.add('hidden'));

        if (isHidden) {
            renderList();
            dropdown.classList.remove('hidden');
            searchInput.focus();
        } else {
            dropdown.classList.add('hidden');
        }
    });

    searchInput.addEventListener('input', (e) => renderList(e.target.value));
    searchInput.addEventListener('click', (e) => e.stopPropagation());

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== sessionMeta) {
            dropdown.classList.add('hidden');
        }
    });
}
