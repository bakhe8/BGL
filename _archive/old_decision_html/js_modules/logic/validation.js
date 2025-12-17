/**
 * Validation Logic Module
 */

/**
 * Simulate strict PHP normalization for validation.
 * Matches logic in App\Support\Normalizer::normalizeSupplierName
 * 
 * @param {string} name 
 * @returns {string} normalized string
 */
export function simulateNormalization(name) {
    if (!name) return '';

    let val = name.toLowerCase().trim();

    const stop = [
        'شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مكتب', 'مصنع', 'مقاولات',
        'trading', 'est', 'est.', 'establishment', 'company', 'co', 'co.', 'ltd', 'ltd.',
        'limited', 'llc', 'inc', 'inc.', 'international', 'global'
    ];

    // Replace stop words with space
    stop.forEach(word => {
        const regex = new RegExp(`\\b${word}\\b`, 'gi');
        val = val.replace(regex, ' ');
    });

    // Remove non-alphanumeric (simplified JS version)
    // Removing everything that is NOT a letter, number, or space
    // Using unicode property escapes if supported, else fallback
    try {
        val = val.replace(/[^\p{L}\p{N}\s]/gu, '');
    } catch (e) {
        // Fallback for older browsers
        val = val.replace(/[^a-zA-Z0-9\u0600-\u06FF\s]/g, '');
    }

    // Collapse spaces
    val = val.replace(/\s+/g, ' ').trim();

    return val;
}

/**
 * Check if the supplier name is valid for creation
 * @param {string} normalizedName 
 * @returns {boolean}
 */
export function isValidNewSupplier(normalizedName) {
    return normalizedName.length >= 5;
}
