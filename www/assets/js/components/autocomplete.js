/**
 * BGL Autocomplete Component
 * Reusable autocomplete dropdown for text inputs
 * 
 * @since v2.0 - Extracted from decision.js
 * @usage BGL.setupAutocomplete('inputId', 'suggestionsId', 'hiddenId', data, 'nameKey', callback)
 */

window.BGL = window.BGL || {};

/**
 * Setup autocomplete on an input field
 * 
 * @param {string} inputId - ID of the text input
 * @param {string} suggestionsId - ID of the suggestions UL element
 * @param {string} hiddenId - ID of the hidden input for selected ID
 * @param {Array} data - Array of objects to search/suggest
 * @param {string} nameKey - Key in data objects to display (e.g., 'official_name')
 * @param {Function} onSelect - Callback when item selected (receives id, name, item)
 */
window.BGL.setupAutocomplete = function (inputId, suggestionsId, hiddenId, data, nameKey, onSelect) {
    const input = document.getElementById(inputId);
    const suggestions = document.getElementById(suggestionsId);
    const hidden = document.getElementById(hiddenId);

    if (!input || !suggestions) {
        console.warn('Autocomplete: Missing elements', inputId, suggestionsId);
        return;
    }

    input.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        if (query.length < 1) {
            suggestions.classList.remove('open');
            return;
        }

        const matches = data.filter(item => {
            const name = (item[nameKey] || '').toLowerCase();
            return name.includes(query);
        }).slice(0, 10);

        if (matches.length === 0) {
            suggestions.classList.remove('open');
            return;
        }

        suggestions.innerHTML = matches.map(item =>
            `<li class="suggestion-item" data-id="${item.id}" data-name="${item[nameKey]}">
                <span>${item[nameKey]}</span>
            </li>`
        ).join('');
        suggestions.classList.add('open');
    });

    suggestions.addEventListener('click', (e) => {
        const item = e.target.closest('.suggestion-item');
        if (item) {
            const id = item.dataset.id;
            const name = item.dataset.name;
            input.value = name;
            if (hidden) hidden.value = id;

            // Call the onSelect callback if provided
            if (typeof onSelect === 'function') {
                const fullItem = data.find(d => d.id == id);
                onSelect(id, name, fullItem);
            }

            suggestions.classList.remove('open');
        }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.classList.remove('open');
        }
    });
};

console.log('✓ BGL.setupAutocomplete loaded');
