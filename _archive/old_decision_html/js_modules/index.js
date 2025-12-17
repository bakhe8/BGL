/**
 * Decision Interface - Main Entry Point
 * Refactored using ES Modules (Phase 11)
 */

import * as Layout from './ui/layout.js';
import * as API from './api.js';
import * as State from './state.js';
import * as Events from './ui/events.js';
import * as Navigation from './ui/navigation.js';
import * as Render from './ui/render.js';
import * as Preview from './ui/preview.js';

async function init() {
    console.log('[Decision] Initializing Modules...');

    // 1. Init DOM Cache
    Layout.initDOM();
    Preview.initLetterPreview(); // Setup print listeners

    try {
        // 2. Load Data (Parallel fetch if possible, but API.fetchRecords usually returns dictionary too?)
        // Implicit assumption from legacy decision.js: Dictionaries were just "there" or fetched.
        // Let's fetch records first.

        // Check URL for session
        const urlParams = new URLSearchParams(window.location.search);
        const sessionId = urlParams.get('session_id');

        // Fetch Data in Parallel
        const [records, dictionaries] = await Promise.all([
            API.fetchRecords(sessionId),
            API.fetchDictionaries()
        ]);

        // Initialize State
        State.initState(records, {
            suppliers: dictionaries.suppliers || [],
            banks: dictionaries.banks || []
        });

        console.log('[Decision] Data Loaded');

        // 3. Setup UI
        Events.setupEventListeners();
        Navigation.initNavigation(); // Dropdown for history

        // 4. Initial Render
        const record = State.getCurrentRecord();
        const stats = State.getStats();

        Render.renderRecord(record, 0, State.getRecords().length);

        // Fetch candidates for initial record
        if (record) {
            API.fetchCandidates(record.id).then(candidates => {
                Render.renderCandidateChips(candidates.suppliers, 'supplier');
                Render.renderCandidateChips(candidates.banks, 'bank');
                // Auto-Fill Logic
                Render.handleSupplierAutoFill(candidates.suppliers, record.rawSupplierName);

                // Force Add Button Update on Init
                Events.updateAddButtonState(Layout.DOM.supplierInput.value);
            });
        }

        Render.updateStats(stats);

        console.log('[Decision] Ready');

    } catch (e) {
        console.error('[Decision] Init Failed:', e);
        Layout.DOM.countTotal.textContent = 'Error';
        Render.showMessage('فشل تحميل البيانات: ' + e.message, 'error');
    }
}

// Auto-run if imported as module
// We export it too just in case.
export { init };

// Bind to window for Legacy HTML calls if needed (though we plan to use type="module")
window.BGL = window.BGL || {};
window.BGL.DecisionModule = { init };

// Start
document.addEventListener('DOMContentLoaded', init);
