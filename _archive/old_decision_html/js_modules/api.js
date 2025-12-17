/**
 * API Service Module
 * Handles network interactions (Fetch/Post)
 * Depends on global 'window.api' helper.
 */

// Helper to construct cache-busted URL
function getUrlWithParams(endpoint, params = {}) {
    const url = new URL(endpoint, window.location.origin);
    Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined) {
            url.searchParams.append(key, params[key]);
        }
    });
    url.searchParams.append('_t', Date.now()); // Cache buster
    return url.toString();
}

/**
 * Load Records (Filtered by Session ID if provided)
 * @param {string|number|null} sessionId 
 * @returns {Promise<Object>} { records: [], dictionaries: {} } (Derived)
 * 
 * Note: The backend endpoint /api/records returns everything needed (candidates, records)
 * BUT for dictionaries (all banks/suppliers), we might rely on the same payload or separate?
 * 
 * decision.js _loadData calls /api/records with session_id.
 * It expects { success: true, data: [...] }
 * 
 * It also relies on BGL.State.supplierMap being invalid? 
 * No, the original code initialized dictionaries somewhere?
 * 
 * Wait, `_loadData` in original decision.js loads `/api/records`. 
 * WHERE are dictionaries loaded? 
 * Ah, in original `_loadData` (legacy code checked):
 * It fetches records.
 * But `_getSupplierSuggestions` uses `BGL.State.supplierCache`.
 * WHERE is `BGL.State.supplierCache` populated?
 * 
 * Looking at `documentation/legacy/decision.js` (implicit knowledge):
 * Usually filtered from matched records OR a separate call.
 * 
 * Let's assumed dictionaries are loaded or derived.
 * Re-reading decision.js snippet: 
 * It doesn't show explicit Dictionary load in `_loadData`.
 * 
 * However, `window.BGL.State` defined `supplierCache`.
 * 
 * I will implement `fetchRecords` and we might need `fetchDictionaries` if it was done separately.
 * If the User said "dictionaries are loaded from API", I will check `server.php` or `docs`.
 * 
 * For now, I will assume `/api/records` returns what we need.
 */
export async function fetchRecords(sessionId = null) {
    const params = {};
    if (sessionId) params.session_id = sessionId;

    const res = await window.api.get(getUrlWithParams('/api/records', params));
    if (!res.success) throw new Error(res.message);
    return res.data || [];
}

/**
 * Fetch available sessions for history
 */
export async function fetchSessions() {
    const res = await window.api.get('/api/sessions');
    if (!res.success) throw new Error(res.message);
    return res.data;
}

/**
 * Fetch Dictionaries (Suppliers and Banks)
 */
export async function fetchDictionaries() {
    const [suppliersRes, banksRes] = await Promise.all([
        window.api.get('/api/dictionary/suppliers'),
        window.api.get('/api/dictionary/banks')
    ]);

    if (!suppliersRes.success) console.warn('Failed to load suppliers', suppliersRes.message);
    if (!banksRes.success) console.warn('Failed to load banks', banksRes.message);

    return {
        suppliers: suppliersRes.success ? suppliersRes.data : [],
        banks: banksRes.success ? banksRes.data : []
    };
}

/**
 * Save decision and move next
 * @param {Object} payload 
 */
export async function saveDecision(payload) {
    const res = await window.api.post('/api/records/decision', payload);
    if (!res.success) throw new Error(res.message);
    return res.data;
}

/**
 * Add New Supplier
 * @param {string} name 
 */
export async function createSupplier(name) {
    const res = await window.api.post('/api/suppliers', {
        official_name: name,
        is_active: 1
    });
    if (!res.success) throw new Error(res.message);
    return res.data; // Should return new Supplier object
}

/**
 * Trigger Recalulation
 */
export async function recalculateAll() {
    const res = await window.api.post('/api/records/recalculate');
    if (!res.success) throw new Error(res.message);
    return res.data;
}

/**
 * Import Excel File
 * @param {FormData} formData 
 */
export async function importExcel(formData) {
    // using fetch directly for FormData usually
    const res = await fetch('/api/import/excel', {
        method: 'POST',
        body: formData
    });
    const result = await res.json();
    if (!result.success) throw new Error(result.message);
    return result.data;
}

/**
 * Fetch Suggestion Candidates for a Record
 * @param {number} recordId 
 */
export async function fetchCandidates(recordId) {
    const res = await window.api.get(`/api/records/${recordId}/candidates`);
    // Legacy response structure: { data: { supplier: { candidates: [] }, bank: { candidates: [] } } }
    if (res.success && res.data) {
        return {
            suppliers: res.data.supplier?.candidates || [],
            banks: res.data.bank?.candidates || []
        };
    }
    return { suppliers: [], banks: [] };
}
