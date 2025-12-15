/**
 * Matching Logic Module
 * Contains Algorithms for "Smart Suggestions" and Search
 */

import { getDictionaries } from '../state.js';

// Stop Words (Synced with Backend App\Support\Normalizer.php)
const STOP_WORDS = [
    'شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مكتب', 'مصنع', 'مقاولات',
    'trading', 'est', 'est.', 'establishment', 'company', 'co', 'co.', 'ltd', 'ltd.',
    'limited', 'llc', 'inc', 'inc.', 'international', 'global'
];

/**
 * Generate list of supplier suggestions based on user query.
 * Logic:
 * 1. Checks Stop Words
 * 2. Enforces Min Length (3 chars)
 * 3. Filters Dictionary
 * 
 * @param {string} query 
 * @returns {Array} List of suggestions
 */
export function getSupplierSuggestions(query) {
    if (!query) return [];

    let cleanQuery = query.toLowerCase().trim();

    // 1. Exact Stop Word Match -> Return Empty
    if (STOP_WORDS.includes(cleanQuery)) {
        return [];
    }

    // 2. Strict Length Check
    if (cleanQuery.length < 3) {
        return [];
    }

    // 3. Dictionary Search
    const suppliers = getDictionaries().suppliers || [];

    return suppliers
        .filter(s => {
            const name = (s.official_name || '').toLowerCase();
            return name.includes(cleanQuery);
        })
        .map(s => ({
            name: s.official_name,
            id: s.id,
            supplier_id: s.id,
            score: 0
        }))
        .slice(0, 20);
}

/**
 * Generate list of bank suggestions
 * Logic:
 * 1. Empty Query -> Returns Top 50 (Select Menu behavior)
 * 2. Typed Query -> Filters by Name (Ar/En) or Short Code
 * 
 * @param {string} query 
 * @returns {Array}
 */
export function getBankSuggestions(query) {
    const banks = Object.values(getDictionaries().bankMap || {});

    // Empty query behavior
    if (!query || query.length === 0) {
        return banks.map(b => ({
            name: b.official_name,
            id: b.id,
            bank_id: b.id,
            score: 0
        })).slice(0, 50);
    }

    // Search behavior
    const q = query.toLowerCase();

    return banks
        .filter(b => {
            const nameVal = (b.official_name || '').toLowerCase();
            const nameEn = (b.official_name_en || '').toLowerCase();
            const nameAr = (b.official_name_ar || '').toLowerCase();
            const short = (b.short_code || '').toLowerCase();
            return nameVal.includes(q) || nameEn.includes(q) || nameAr.includes(q) || short.includes(q);
        })
        .map(b => ({
            name: b.official_name,
            id: b.id,
            bank_id: b.id,
            score: 0
        }))
        .slice(0, 20);
}
