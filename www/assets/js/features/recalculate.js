/**
 * BGL Recalculate Feature
 * Handles recalculating all matches
 * 
 * @since v2.0 - Extracted from decision.js
 * @requires lucide, showSuccess, showError from dialog.js
 */

window.BGL = window.BGL || {};
window.BGL.features = window.BGL.features || {};

/**
 * Initialize Recalculate functionality
 */
window.BGL.features.initRecalculate = function () {
    const btnRecalc = document.getElementById('btnRecalcAll');
    if (!btnRecalc) return;

    btnRecalc.addEventListener('click', async () => {
        if (!btnRecalc.dataset.confirming) {
            // First click - show confirm
            btnRecalc.dataset.confirming = 'true';
            btnRecalc.dataset.originalHtml = btnRecalc.innerHTML;
            btnRecalc.innerHTML = '<i data-lucide="alert-triangle" class="w-4 h-4"></i> تأكيد؟';
            if (window.lucide) lucide.createIcons();
            btnRecalc.classList.add('bg-red-500', 'text-white');

            // Auto-revert after 3 seconds
            btnRecalc._timeout = setTimeout(() => {
                delete btnRecalc.dataset.confirming;
                btnRecalc.innerHTML = btnRecalc.dataset.originalHtml;
                btnRecalc.classList.remove('bg-red-500', 'text-white');
            }, 3000);
            return;
        }

        // Second click - execute
        clearTimeout(btnRecalc._timeout);
        delete btnRecalc.dataset.confirming;
        btnRecalc.classList.remove('bg-red-500', 'text-white');
        btnRecalc.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>...';
        if (window.lucide) lucide.createIcons();
        btnRecalc.disabled = true;

        try {
            const res = await fetch('/api/records/recalculate', { method: 'POST' });
            const json = await res.json();
            if (json.success) {
                if (typeof showSuccess === 'function') {
                    showSuccess('تمت إعادة المطابقة: ' + (json.data?.processed || 0) + ' سجل');
                }
                window.location.href = window.location.href;
            } else {
                if (typeof showError === 'function') {
                    showError('خطأ: ' + (json.message || 'فشلت العملية'));
                }
            }
        } catch (err) {
            if (typeof showError === 'function') {
                showError('خطأ في الاتصال');
            }
        } finally {
            btnRecalc.disabled = false;
            btnRecalc.innerHTML = btnRecalc.dataset.originalHtml || '<i data-lucide="refresh-cw" class="w-4 h-4"></i>';
            if (window.lucide) lucide.createIcons();
        }
    });
};

console.log('✓ BGL.features.initRecalculate loaded');
