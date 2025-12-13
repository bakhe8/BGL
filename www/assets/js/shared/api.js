/**
 * Shared API Wrapper
 * Handles fetching with timeouts and basic error parsing.
 */
window.escapeHtml = (str = '') => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const api = {
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

            // Check HTTP status
            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
            }

            // Parse JSON
            const text = await response.text();
            try {
                const json = JSON.parse(text);
                return json;
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
