/**
 * Shared Helpers Module
 * 
 * يجمع كل الدوال المساعدة المشتركة في ملف واحد:
 * - API wrapper (fetch with timeout)
 * - SweetAlert2 helpers (alerts, confirms, etc.)
 * - Utility functions (escapeHtml, etc.)
 * 
 * @since v1.4.1 - Merged from api.js + sweetalert-helpers.js
 */

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Escape HTML to prevent XSS
 */
window.escapeHtml = (str = '') => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');


// ============================================
// API WRAPPER
// ============================================

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

window.api = api;


// ============================================
// SWEETALERT2 HELPERS
// ============================================

// تأكد من تحميل SweetAlert2
if (typeof Swal === 'undefined') {
    console.warn('SweetAlert2 is not loaded! Alert helpers may not work.');
}

/**
 * عرض رسالة نجاح
 */
function showSuccess(message, title = 'نجح!') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        confirmButtonText: 'حسناً',
        confirmButtonColor: '#10b981'
    });
}

/**
 * عرض رسالة خطأ
 */
function showError(message, title = 'خطأ!') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        confirmButtonText: 'حسناً',
        confirmButtonColor: '#dc2626'
    });
}

/**
 * عرض رسالة معلومات
 */
function showInfo(message, title = 'معلومة') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'info',
        confirmButtonText: 'حسناً',
        confirmButtonColor: '#3b82f6'
    });
}

/**
 * عرض رسالة تحذير
 */
function showWarning(message, title = 'تحذير') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        confirmButtonText: 'حسناً',
        confirmButtonColor: '#f59e0b'
    });
}

/**
 * طلب تأكيد من المستخدم
 */
function confirmAction(message, title = 'هل أنت متأكد؟', options = {}) {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: options.confirmColor || '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: options.confirmText || 'نعم',
        cancelButtonText: options.cancelText || 'إلغاء',
        reverseButtons: true,
        focusCancel: true
    }).then((result) => result.isConfirmed);
}

/**
 * طلب تأكيد حذف
 */
function confirmDelete(entityType, entityName = null) {
    const message = entityName
        ? `هل أنت متأكد من حذف ${entityType} "${entityName}"؟`
        : `هل أنت متأكد من حذف هذا ${entityType}؟`;

    return Swal.fire({
        title: `حذف ${entityType}`,
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        reverseButtons: true,
        focusCancel: true,
        footer: '<small>هذا الإجراء لا يمكن التراجع عنه</small>'
    }).then((result) => result.isConfirmed);
}

/**
 * عرض رسالة تحميل (Loading)
 */
function showLoading(message = 'جاري التحميل...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });
}

/**
 * إغلاق نافذة التحميل
 */
function closeLoading() {
    Swal.close();
}

// Export functions globally
window.showSuccess = showSuccess;
window.showError = showError;
window.showInfo = showInfo;
window.showWarning = showWarning;
window.confirmAction = confirmAction;
window.confirmDelete = confirmDelete;
window.showLoading = showLoading;
window.closeLoading = closeLoading;

console.log('✓ Helpers module loaded (API + Dialogs)');
