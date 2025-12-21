/**
 * BGL Dropdown Component
 * Reusable dropdown with search functionality
 * 
 * @since v2.0 - Extracted from decision.js (session dropdown)
 * @usage BGL.setupDropdown('triggerId', 'dropdownId', 'searchId', 'listId')
 */

window.BGL = window.BGL || {};

/**
 * Setup a searchable dropdown
 * 
 * @param {string} triggerId - ID of the trigger element
 * @param {string} dropdownId - ID of the dropdown container
 * @param {string} searchId - ID of the search input (optional)
 * @param {string} listId - ID of the list container (optional)
 */
window.BGL.setupDropdown = function (triggerId, dropdownId, searchId = null, listId = null) {
    const trigger = document.getElementById(triggerId);
    const dropdown = document.getElementById(dropdownId);
    const searchInput = searchId ? document.getElementById(searchId) : null;
    const list = listId ? document.getElementById(listId) : null;

    if (!trigger || !dropdown) {
        console.warn('Dropdown: Missing elements', triggerId, dropdownId);
        return;
    }

    // Toggle on trigger click
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden') && searchInput) {
            searchInput.focus();
        }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== trigger) {
            dropdown.classList.add('hidden');
        }
    });

    // Prevent closing when clicking inside
    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Search filter
    if (searchInput && list) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const items = list.querySelectorAll('a, li');
            items.forEach(item => {
                const txt = item.innerText.toLowerCase();
                item.style.display = txt.includes(term) ? '' : 'none';
            });
        });
    }
};

console.log('✓ BGL.setupDropdown loaded');
