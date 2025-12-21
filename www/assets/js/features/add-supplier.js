/**
 * BGL Add Supplier Feature
 * Handles adding new supplier from decision page
 * 
 * @since v2.0 - Extracted from decision.js
 * @requires BGL.api, lucide
 */

window.BGL = window.BGL || {};
window.BGL.features = window.BGL.features || {};

/**
 * Initialize Add Supplier functionality
 * 
 * @param {Array} suppliers - Current suppliers list
 * @param {string} rawSupplierContext - Raw supplier name for learning context
 * @param {Function} onAdd - Callback when supplier added (receives newSupplier)
 */
window.BGL.features.initAddSupplier = function (suppliers, rawSupplierContext = '', onAdd = null) {
    const btnAddSupplier = document.getElementById('btnAddSupplier');
    const supplierInput = document.getElementById('supplierInput');
    const supplierNamePreview = document.getElementById('supplierNamePreview');
    const errorDiv = document.getElementById('supplierAddError');

    if (!btnAddSupplier || !supplierInput) return;

    // ⚠️ SYNC WARNING: This function is duplicated from PHP!
    // @see app/Support/Normalizer.php - makeSupplierKey() method
    const makeSupplierKey = (val) => {
        if (!val) return '';
        let s = val.toLowerCase().trim();

        // Unify Arabic chars
        s = s.replace(/[أإآ]/g, 'ا')
            .replace(/ة/g, 'ه')
            .replace(/[ىئ]/g, 'ي')
            .replace(/ؤ/g, 'و');

        // Remove non-alphanumeric
        s = s.replace(/[^a-z0-9\u0600-\u06FF\s]/g, '');

        // Stop words
        const stop = [
            'شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مكتب', 'مصنع', 'مقاولات',
            'trading', 'est', 'establishment', 'company', 'co', 'ltd',
            'limited', 'llc', 'inc', 'international', 'global'
        ];

        const parts = s.split(/\s+/).filter(p => p && !stop.includes(p));
        return parts.join('');
    };

    const checkMatch = (val) => {
        const inputKey = makeSupplierKey(val);
        if (!inputKey) {
            btnAddSupplier.style.display = 'none';
            return;
        }

        const exists = suppliers.some(s => {
            const sKey = makeSupplierKey(s.official_name);
            return sKey === inputKey;
        });

        if (exists) {
            btnAddSupplier.style.display = 'none';
        } else {
            btnAddSupplier.style.display = 'flex';
            if (supplierNamePreview) {
                supplierNamePreview.textContent = val;
            }
        }
    };

    // Input listener
    supplierInput.addEventListener('input', (e) => {
        checkMatch(e.target.value);
    });

    // Add button click
    btnAddSupplier.addEventListener('click', async () => {
        const name = supplierInput.value.trim();
        if (!name) return;

        const originalText = btnAddSupplier.innerHTML;
        btnAddSupplier.disabled = true;
        btnAddSupplier.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> جاري الإضافة...';
        if (window.lucide) lucide.createIcons();

        try {
            const res = await fetch('/api/dictionary/suppliers', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    official_name: name,
                    raw_name_context: rawSupplierContext
                })
            });
            const json = await res.json();

            if (json.success) {
                const newSupplier = {
                    id: json.data.id,
                    official_name: json.data.officialName,
                    normalized_name: json.data.normalizedName
                };
                suppliers.push(newSupplier);

                // Update form
                document.getElementById('supplierId').value = newSupplier.id;
                supplierInput.value = newSupplier.official_name;
                btnAddSupplier.style.display = 'none';

                // Show success
                if (errorDiv) {
                    errorDiv.classList.remove('hidden', 'text-red-500');
                    errorDiv.classList.add('text-green-600', 'font-bold');
                    errorDiv.innerHTML = '<div class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> تمت الإضافة بنجاح</div>';
                    if (window.lucide) lucide.createIcons();
                    errorDiv.style.display = 'block';
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                    }, 3000);
                }

                // Callback
                if (typeof onAdd === 'function') {
                    onAdd(newSupplier);
                }
            } else {
                if (errorDiv) {
                    errorDiv.classList.remove('hidden', 'text-green-600');
                    errorDiv.classList.add('text-red-500');
                    errorDiv.textContent = 'خطأ: ' + (json.message || 'فشل إضافة المورد');
                    errorDiv.style.display = 'block';
                }
                btnAddSupplier.innerHTML = originalText;
            }
        } catch (e) {
            if (errorDiv) {
                errorDiv.classList.remove('hidden', 'text-green-600');
                errorDiv.classList.add('text-red-500');
                errorDiv.textContent = 'خطأ في الاتصال';
                errorDiv.style.display = 'block';
            }
            btnAddSupplier.innerHTML = originalText;
        } finally {
            btnAddSupplier.disabled = false;
        }
    });
};

console.log('✓ BGL.features.initAddSupplier loaded');
