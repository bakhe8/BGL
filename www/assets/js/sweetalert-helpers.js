/**
 * SweetAlert2 Helper Functions
 * 
 * مجموعة من الدوال المساعدة لاستبدال alert() و confirm() القديمة
 * بنوافذ SweetAlert2 الحديثة والجميلة
 */

// تأكد من تحميل SweetAlert2
if (typeof Swal === 'undefined') {
    console.error('SweetAlert2 is not loaded! Please include it in your HTML.');
}

/**
 * عرض رسالة نجاح
 * @param {string} message - الرسالة المراد عرضها
 * @param {string} title - العنوان (اختياري)
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
 * @param {string} message - الرسالة المراد عرضها
 * @param {string} title - العنوان (اختياري)
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
 * @param {string} message - الرسالة المراد عرضها
 * @param {string} title - العنوان (اختياري)
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
 * @param {string} message - الرسالة المراد عرضها
 * @param {string} title - العنوان (اختياري)
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
 * @param {string} message - الرسالة المراد عرضها
 * @param {string} title - العنوان (اختياري)
 * @param {object} options - خيارات إضافية
 * @returns {Promise<boolean>} - true إذا وافق المستخدم، false إذا رفض
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
    }).then((result) => {
        return result.isConfirmed;
    });
}

/**
 * طلب تأكيد حذف
 * @param {string} entityType - نوع العنصر (مثل: البنك، المورد، السجل)
 * @param {string} entityName - اسم العنصر (اختياري)
 * @returns {Promise<boolean>}
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
    }).then((result) => {
        return result.isConfirmed;
    });
}

/**
 * عرض رسالة تحميل (Loading)
 * @param {string} message - الرسالة المراد عرضها
 */
function showLoading(message = 'جاري التحميل...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

/**
 * إغلاق نافذة التحميل
 */
function closeLoading() {
    Swal.close();
}

/**
 * استبدال alert() القديم
 * @param {string} message - الرسالة
 */
function alert(message) {
    return showInfo(message, '');
}

/**
 * استبدال confirm() القديم
 * @param {string} message - الرسالة
 * @returns {Promise<boolean>}
 */
function confirm(message) {
    return confirmAction(message, 'تأكيد');
}
