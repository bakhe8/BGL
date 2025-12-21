/**
 * BGL Dialog Module
 * SweetAlert2 wrapper functions for consistent UI dialogs
 * 
 * @since v2.0 - Extracted from helpers.js
 * @requires SweetAlert2 to be loaded first
 * @usage showSuccess('تم الحفظ')
 */

window.BGL = window.BGL || {};

// Check SweetAlert2
if (typeof Swal === 'undefined') {
    console.warn('SweetAlert2 is not loaded! Dialog helpers may not work.');
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

// Export to global
window.showSuccess = showSuccess;
window.showError = showError;
window.showInfo = showInfo;
window.showWarning = showWarning;
window.confirmAction = confirmAction;
window.confirmDelete = confirmDelete;
window.showLoading = showLoading;
window.closeLoading = closeLoading;

// Also add to BGL namespace
window.BGL.dialog = {
    showSuccess,
    showError,
    showInfo,
    showWarning,
    confirmAction,
    confirmDelete,
    showLoading,
    closeLoading
};

console.log('✓ BGL.dialog loaded');
