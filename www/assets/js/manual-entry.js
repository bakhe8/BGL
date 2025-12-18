/**
 * Manual Entry Module
 * 
 * Handles manual single-record entry from the UI.
 * Integrates with the Decision Page and creates records via /api/import/manual
 */

(function () {
    'use strict';

    // DOM Elements
    const modal = document.getElementById('manualEntryModal');
    const btnOpen = document.getElementById('btnOpenManualEntry');
    const btnClose = document.getElementById('btnCloseManualEntry');
    const btnCancel = document.getElementById('btnCancelManualEntry');
    const btnSave = document.getElementById('btnSaveManualEntry');
    const form = document.getElementById('manualEntryForm');
    const errorDiv = document.getElementById('manualEntryError');

    // Input fields
    const inputs = {
        supplier: document.getElementById('manualSupplier'),
        bank: document.getElementById('manualBank'),
        guarantee: document.getElementById('manualGuarantee'),
        contract: document.getElementById('manualContract'),
        relatedTo: document.getElementById('manualRelatedTo'),
        amount: document.getElementById('manualAmount'),
        expiry: document.getElementById('manualExpiry'),
        issue: document.getElementById('manualIssue'),
        type: document.getElementById('manualType'),
        comment: document.getElementById('manualComment')
    };

    if (!modal || !btnOpen) {
        console.error('Manual Entry: Required elements not found');
        return;
    }

    /**
     * Open Modal
     */
    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        resetForm();
        // Focus first input
        setTimeout(() => inputs.supplier.focus(), 100);
    }

    /**
     * Close Modal
     */
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        resetForm();
    }

    /**
     * Reset Form
     */
    function resetForm() {
        form.reset();
        errorDiv.classList.add('hidden');
        errorDiv.textContent = '';
        // Clear validation states
        Object.values(inputs).forEach(input => {
            if (input) {
                input.classList.remove('border-red-500');
            }
        });
    }

    /**
     * Validate Form
     */
    function validateForm() {
        const errors = [];
        const required = {
            supplier: 'اسم المورد مطلوب',
            bank: 'اسم البنك مطلوب',
            guarantee: 'رقم الضمان مطلوب',
            contract: 'رقم العقد مطلوب',
            relatedTo: 'نوع المستند مطلوب',
            amount: 'المبلغ مطلوب'
        };

        // Reset border colors
        Object.values(inputs).forEach(input => {
            if (input) input.classList.remove('border-red-500');
        });

        // Check required fields
        Object.keys(required).forEach(key => {
            const input = inputs[key];
            if (!input || !input.value.trim()) {
                errors.push(required[key]);
                if (input) input.classList.add('border-red-500');
            }
        });

        // Validate amount is numeric
        if (inputs.amount.value.trim()) {
            const amount = inputs.amount.value.replace(/[,\s]/g, '');
            if (isNaN(amount) || parseFloat(amount) <= 0) {
                errors.push('المبلغ يجب أن يكون رقماً صحيحاً أكبر من صفر');
                inputs.amount.classList.add('border-red-500');
            }
        }

        return errors;
    }

    /**
     * Show Error
     */
    function showError(message) {
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }

    /**
     * Hide Error
     */
    function hideError() {
        errorDiv.classList.add('hidden');
        errorDiv.textContent = '';
    }

    /**
     * Save Manual Entry
     */
    async function saveManualEntry() {
        hideError();

        // Validate
        const errors = validateForm();
        if (errors.length > 0) {
            showError(errors.join('، '));
            return;
        }

        // Prepare data
        const data = {
            supplier: inputs.supplier.value.trim(),
            bank: inputs.bank.value.trim(),
            guarantee_number: inputs.guarantee.value.trim(),
            contract_number: inputs.contract.value.trim(),
            related_to: inputs.relatedTo.value,
            amount: inputs.amount.value.trim(),
            expiry_date: inputs.expiry.value || null,
            issue_date: inputs.issue.value || null,
            type: inputs.type.value || null,
            comment: inputs.comment.value.trim() || 'إدخال يدوي'
        };

        // Show loading state
        btnSave.disabled = true;
        btnSave.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> جاري الحفظ...';

        try {
            const response = await fetch('/api/import/manual', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.error || 'فشل إنشاء السجل');
            }

            // Success!
            if (window.showSuccess) {
                window.showSuccess('✓ تم إنشاء السجل بنجاح');
            }

            // Close modal
            closeModal();

            // Redirect to the new record
            if (result.record_id) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('id', result.record_id);
                if (result.session_id) {
                    currentUrl.searchParams.set('session', result.session_id);
                }
                // Reload page with new record
                setTimeout(() => {
                    window.location.href = currentUrl.toString();
                }, 500);
            } else {
                // Just reload if no record_id
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }

        } catch (error) {
            console.error('Manual Entry Error:', error);
            showError(error.message || 'حدث خطأ أثناء حفظ السجل');
        } finally {
            // Restore button state
            btnSave.disabled = false;
            btnSave.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> حفظ وإضافة';
            // Re-initialize icons
            if (window.lucide) {
                lucide.createIcons();
            }
        }
    }

    // Event Listeners
    btnOpen.addEventListener('click', openModal);
    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    btnSave.addEventListener('click', saveManualEntry);

    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Handle Enter key in form (but not in textarea)
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            saveManualEntry();
        }
    });

    // Escape key to close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    console.log('Manual Entry Module: Initialized');
})();
