/**
 * BGL Import Excel Feature
 * Handles Excel file import via hidden file input
 * 
 * @since v2.0 - Extracted from decision.js
 * @requires showError from dialog.js
 */

window.BGL = window.BGL || {};
window.BGL.features = window.BGL.features || {};

/**
 * Initialize Excel import functionality
 */
window.BGL.features.initImportExcel = function () {
    const btnImport = document.getElementById('btnToggleImport');
    const fileInput = document.getElementById('hiddenFileInput');

    if (!btnImport || !fileInput) return;

    btnImport.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch('/api/import/excel', { method: 'POST', body: formData });
            const json = await res.json();

            if (json.success && json.data && json.data.session_id) {
                if (json.data.first_record_id) {
                    window.location.href = `/?session_id=${json.data.session_id}&record_id=${json.data.first_record_id}`;
                } else {
                    window.location.href = '/?session_id=' + json.data.session_id;
                }
            } else {
                if (typeof showError === 'function') {
                    showError('خطأ: ' + (json.message || 'فشل الاستيراد'));
                } else {
                    alert('خطأ: ' + (json.message || 'فشل الاستيراد'));
                }
            }
        } catch (err) {
            if (typeof showError === 'function') {
                showError('خطأ في الاتصال');
            } else {
                alert('خطأ في الاتصال');
            }
        }

        // Reset file input
        fileInput.value = '';
    });
};

console.log('✓ BGL.features.initImportExcel loaded');
