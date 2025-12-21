/**
 * BGL Smart Paste Feature
 * Handles text parsing and record creation via Smart Paste modal
 * 
 * @since v2.0 - Extracted from decision.js
 * @requires lucide for icons
 */

window.BGL = window.BGL || {};
window.BGL.features = window.BGL.features || {};

/**
 * Initialize Smart Paste functionality
 */
window.BGL.features.initSmartPaste = function () {
    const modal = document.getElementById('smartPasteModal');
    const btnClose = document.getElementById('btnCloseSmartPaste');
    const btnCancel = document.getElementById('btnCancelSmartPaste');
    const btnProcess = document.getElementById('btnProcessSmartPaste');
    const input = document.getElementById('smartPasteInput');
    const errorDiv = document.getElementById('smartPasteError');

    if (!modal) return;

    // Create trigger button if not exists
    if (!document.getElementById('btnOpenSmartPasteTrigger')) {
        const importBtn = document.getElementById('btnToggleImport');
        if (importBtn && importBtn.parentNode) {
            const btn = document.createElement('button');
            btn.id = 'btnOpenSmartPasteTrigger';
            btn.className = 'flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors';
            btn.title = 'لصق نص (Smart Paste)';
            btn.innerHTML = '<i data-lucide="clipboard-copy" class="w-4 h-4 text-gray-600"></i>';
            if (window.lucide) lucide.createIcons();
            btn.onclick = () => {
                modal.classList.remove('hidden');
                if (input) input.focus();
            };
            importBtn.parentNode.insertBefore(btn, importBtn.nextSibling);
        }
    }

    const closeModal = () => {
        modal.classList.add('hidden');
        if (input) input.value = '';
        if (errorDiv) errorDiv.classList.add('hidden');
        if (btnProcess) {
            btnProcess.disabled = false;
            btnProcess.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="sparkles" class="w-4 h-4"></i> تحليل وإضافة</span>';
            if (window.lucide) lucide.createIcons();
        }
    };

    if (btnClose) btnClose.onclick = closeModal;
    if (btnCancel) btnCancel.onclick = closeModal;

    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    if (btnProcess) {
        btnProcess.onclick = async () => {
            const text = input ? input.value.trim() : '';
            if (!text) {
                if (errorDiv) {
                    errorDiv.textContent = 'يرجى إدخال نص أولاً';
                    errorDiv.classList.remove('hidden');
                }
                return;
            }

            btnProcess.disabled = true;
            btnProcess.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> جارِ التحليل...</span>';
            if (window.lucide) lucide.createIcons();
            if (errorDiv) errorDiv.classList.add('hidden');

            try {
                const relatedToSelect = document.getElementById('smartPasteRelatedTo');
                const relatedTo = relatedToSelect ? relatedToSelect.value || null : null;

                const res = await fetch('/api/import/text', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        text: text,
                        related_to: relatedTo
                    })
                });

                const json = await res.json();

                if (json.success) {
                    window.location.href = `/?session_id=${json.session_id}&record_id=${json.record_id}`;
                } else {
                    throw new Error(json.error || 'فشلت العملية');
                }
            } catch (err) {
                btnProcess.disabled = false;
                btnProcess.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="sparkles" class="w-4 h-4"></i> تحليل وإضافة</span>';
                if (window.lucide) lucide.createIcons();
                if (errorDiv) {
                    errorDiv.textContent = 'خطأ: ' + err.message;
                    errorDiv.classList.remove('hidden');
                }
            }
        };
    }
};

console.log('✓ BGL.features.initSmartPaste loaded');
