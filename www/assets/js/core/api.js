/**
 * BGL API Module
 * Centralized fetch wrapper with timeout and JSON parsing
 * 
 * @since v2.0 - Extracted from helpers.js
 * @usage window.BGL.api.get('/api/endpoint')
 */

window.BGL = window.BGL || {};

window.BGL.api = {
    /**
     * Fetch with timeout and JSON parsing
     * @param {string} url - The URL to fetch
     * @param {object} options - Fetch options (method, body, etc.)
     * @param {number} timeoutMs - Timeout in milliseconds (default 10s)
     * @returns {Promise<any>} - Parsed JSON response
     */
    async fetchJson(url, options = {}, timeoutMs = 10000) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

        const config = {
            ...options,
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(options.headers || {})
            }
        };

        try {
            const response = await fetch(url, config);
            clearTimeout(timeoutId);

            if (!response.ok) {
                let errorMsg = `HTTP Error ${response.status}: ${response.statusText}`;
                try {
                    const errJson = await response.json();
                    if (errJson && errJson.message) {
                        errorMsg = errJson.message;
                    }
                } catch (e) { /* ignore */ }
                throw new Error(errorMsg);
            }

            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (parseErr) {
                console.error('API Parse Error. Response:', text);
                throw new Error('Invalid JSON response from server');
            }

        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                throw new Error('Timeout: Server did not respond in time.');
            }
            throw error;
        }
    },

    /**
     * Helper for GET requests
     */
    get(url) {
        const separator = url.includes('?') ? '&' : '?';
        return this.fetchJson(url + separator + '_t=' + Date.now());
    },

    /**
     * Helper for POST requests
     */
    post(url, data) {
        return this.fetchJson(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
};

// Legacy support: expose as window.api
window.api = window.BGL.api;

console.log('✓ BGL.api loaded');
