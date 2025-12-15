/**
 * State Management Module
 * Single Source of Truth for the Decision Interface.
 */

export const state = {
    // Data
    records: [],
    dictionaries: {
        suppliers: [],
        banks: [],
        supplierMap: {},
        bankMap: {}
    },

    // Navigation State
    currentIndex: 0,
    currentSessionId: null,

    // UI State
    filter: 'all', // 'all', 'pending', 'approved'

    // Selection State (Transient)
    currentSelection: {
        supplierId: null,
        supplierName: null,
        bankId: null,
        bankName: null
    }
};

/**
 * Initialize State with fetched data
 * @param {Array} records 
 * @param {Object} dictionaries { suppliers, banks }
 */
export function initState(records, dictionaries) {
    state.records = records || [];
    state.dictionaries.suppliers = dictionaries.suppliers || [];
    state.dictionaries.banks = dictionaries.banks || [];

    // Build Maps for O(1) Access
    state.dictionaries.supplierMap = (dictionaries.suppliers || []).reduce((acc, s) => {
        acc[s.id] = s;
        return acc;
    }, {});

    state.dictionaries.bankMap = (dictionaries.banks || []).reduce((acc, b) => {
        acc[b.id] = b;
        return acc;
    }, {});

    // Reset index on fresh load
    state.currentIndex = 0;

    // Detect Session ID from first record if present
    if (state.records.length > 0) {
        state.currentSessionId = state.records[0].sessionId;
    }

    console.log('[State] Initialized', {
        records: state.records.length,
        session: state.currentSessionId
    });
}

// --- Getters ---

export function getFilter() {
    return state.filter;
}

export function getRecords() {
    return state.records;
}

export function getCurrentRecord() {
    return state.records[state.currentIndex];
}

export function getDictionaries() {
    return state.dictionaries;
}

export function getCurrentIndex() {
    return state.currentIndex;
}

export function getCurrentSelection() {
    return state.currentSelection;
}

export function getStats() {
    return {
        total: state.records.length,
        approved: state.records.filter(r => r.matchStatus === 'ready' || r.matchStatus === 'approved').length,
        pending: state.records.filter(r => r.matchStatus !== 'ready' && r.matchStatus !== 'approved').length
    };
}

export function getNavigationInfo() {
    if (state.filter === 'all') {
        return {
            index: state.currentIndex, // 0-based
            total: state.records.length
        };
    }

    // Filtered
    const filtered = state.records.filter(matchesFilter);
    const currentId = state.records[state.currentIndex]?.id;
    const indexInFiltered = filtered.findIndex(r => r.id === currentId);

    return {
        index: indexInFiltered !== -1 ? indexInFiltered : 0, // 0-based
        total: filtered.length
    };
}

// --- Helper ---
function matchesFilter(record) {
    if (state.filter === 'all') return true;
    const isCompleted = record.matchStatus === 'ready' || record.matchStatus === 'approved';
    if (state.filter === 'approved') return isCompleted;
    if (state.filter === 'pending') return !isCompleted;
    return true;
}

// --- Mutators ---

export function setFilter(filter) {
    if (['all', 'pending', 'approved'].includes(filter)) {
        state.filter = filter;
        // Find first matching record
        const firstMatch = state.records.findIndex(r => matchesFilter(r));
        if (firstMatch !== -1) {
            state.currentIndex = firstMatch;
        } else {
            state.currentIndex = 0; // Fallback
        }
        return true;
    }
    return false;
}

export function setIndex(index) {
    if (index >= 0 && index < state.records.length) {
        state.currentIndex = index;
        return true;
    }
    return false;
}

export function nextRecord() {
    // Find next index that matches filter
    let nextIdx = -1;
    for (let i = state.currentIndex + 1; i < state.records.length; i++) {
        if (matchesFilter(state.records[i])) {
            nextIdx = i;
            break;
        }
    }
    if (nextIdx !== -1) return setIndex(nextIdx);
    return false;
}

export function prevRecord() {
    // Find prev index that matches filter
    let prevIdx = -1;
    for (let i = state.currentIndex - 1; i >= 0; i--) {
        if (matchesFilter(state.records[i])) {
            prevIdx = i;
            break;
        }
    }
    if (prevIdx !== -1) return setIndex(prevIdx);
    return false;
}

/**
 * Update a specific record in the state (e.g. after save)
 * @param {number} id Record ID
 * @param {Object} updates Partial updates
 */
export function updateRecord(id, updates) {
    const idx = state.records.findIndex(r => r.id === id);
    if (idx !== -1) {
        state.records[idx] = { ...state.records[idx], ...updates };
        return true;
    }
    return false;
}

export default state;
