/**
 * Decision Page Logic
 * Extracted from index.php
 */
(function () {
    // Read configuration from global object
    const config = window.DecisionApp || {};
    const suppliers = config.suppliers || [];
    const banks = config.banks || [];
    const recordId = config.recordId;
    const nextUrl = config.nextUrl;

    // Unified function to update bank details
    function updateBankDetails(bankId, bankName = null) {
        const bank = banks.find(b => b.id == bankId);

        // Update Header Name
        if (bankName || bank) {
            const name = bankName || (bank ? (bank.official_name || bank.name) : '');
            if (document.getElementById('letterBank')) {
                document.getElementById('letterBank').textContent = name;
            }
        }

        // Update Details Section
        const detailsContainer = document.getElementById('letterBankDetails');
        if (detailsContainer) {
            if (bank) {
                // Helper to convert numbers to Hindi
                const toHindi = (str) => String(str).replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);

                let html = `<div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${bank.department || 'إدارة الضمانات'}</div>`;
                const addr1 = bank.address_line_1 || 'المقر الرئيسي';
                html += `<div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${toHindi(addr1)}</div>`;
                if (bank.address_line_2) {
                    html += `<div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${toHindi(bank.address_line_2)}</div>`;
                }
                if (bank.contact_email) {
                    html += `<div><span style="text-shadow: 0 0 1px #333, 0 0 1px #333;">البريد الالكتروني:</span> ${bank.contact_email}</div>`;
                }
                detailsContainer.innerHTML = html;
            } else {
                // Reset to default
                detailsContainer.innerHTML = `
                        <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">إدارة الضمانات</div>
                        <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">المقر الرئيسي</div>
                    `;
            }
        }
    }

    // Font update helper
    function updateLetterFont(name, elementOrId) {
        const el = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
        if (!el) return;

        el.textContent = name;

        // Font Logic: If Arabic present, remove inline styles (revert to Al-Mohanad).
        // If pure English, force Arial.
        const hasArabic = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/.test(name);
        if (hasArabic) {
            el.style.removeProperty('font-family');
            el.style.removeProperty('direction');
            el.style.removeProperty('display');
        } else {
            el.style.fontFamily = "'Arial', sans-serif";
            el.style.direction = "ltr";
            el.style.display = "inline-block";
        }
    }

    // Chip Click Handler (gray buttons)
    document.querySelectorAll('.chip-btn').forEach(chip => {
        chip.addEventListener('click', () => {
            const type = chip.dataset.type;
            const id = chip.dataset.id;
            const name = chip.dataset.name;

            if (type === 'supplier') {
                document.getElementById('supplierInput').value = name;
                document.getElementById('supplierId').value = id;
                updateLetterFont(name, 'letterSupplier');
            } else {
                document.getElementById('bankInput').value = name;
                document.getElementById('bankId').value = id;
                updateBankDetails(id, name);
            }
        });
    });

    // Autocomplete Setup
    function setupAutocomplete(inputId, suggestionsId, hiddenId, data, nameKey, letterId) {
        const input = document.getElementById(inputId);
        const suggestions = document.getElementById(suggestionsId);
        const hidden = document.getElementById(hiddenId);
        const letter = document.getElementById(letterId);

        if (!input || !suggestions) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            if (query.length < 1) {
                suggestions.classList.remove('open');
                return;
            }

            const matches = data.filter(item => {
                const name = (item[nameKey] || '').toLowerCase();
                return name.includes(query);
            }).slice(0, 10);

            if (matches.length === 0) {
                suggestions.classList.remove('open');
                return;
            }

            suggestions.innerHTML = matches.map(item =>
                `<li class="suggestion-item" data-id="${item.id}" data-name="${item[nameKey]}">
                    <span>${item[nameKey]}</span>
                </li>`
            ).join('');
            suggestions.classList.add('open');
        });

        suggestions.addEventListener('click', (e) => {
            const item = e.target.closest('.suggestion-item');
            if (item) {
                const id = item.dataset.id;
                const name = item.dataset.name;
                input.value = name;
                hidden.value = id;

                if (inputId === 'supplierInput') {
                    if (letter) updateLetterFont(name, letter);
                } else if (inputId === 'bankInput') {
                    updateBankDetails(id, name);
                }

                suggestions.classList.remove('open');
            }
        });

        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.classList.remove('open');
            }
        });
    }

    // Add Supplier Button Logic
    const btnAddSupplier = document.getElementById('btnAddSupplier');
    const supplierInput = document.getElementById('supplierInput');
    const supplierNamePreview = document.getElementById('supplierNamePreview');

    if (btnAddSupplier && supplierInput && supplierNamePreview) {
        // 1. Dynamic Text Update & Visibility
        // ⚠️ SYNC WARNING: This function is duplicated from PHP!
        // @see app/Support/Normalizer.php - makeSupplierKey() method
        // 
        // السبب: نحتاج التحقق الفوري قبل إرسال الطلب للخادم (كل حرف يكتبه المستخدم).
        // إذا عدّلت هذه الدالة، يجب تحديث نسخة PHP أيضاً!
        const makeSupplierKey = (val) => {
            if (!val) return '';
            let s = val.toLowerCase().trim();

            // Unify Arabic chars
            s = s.replace(/[أإآ]/g, 'ا')
                .replace(/ة/g, 'ه')
                .replace(/[ىئ]/g, 'ي')
                .replace(/ؤ/g, 'و');

            // Remove non-alphanumeric (keep spaces for splitting)
            // Regex matches: NOT (LTR chars, Arabic chars, digits, whitespace)
            s = s.replace(/[^a-z0-9\u0600-\u06FF\s]/g, '');

            // Stop words (same as PHP)
            const stop = [
                'شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مكتب', 'مصنع', 'مقاولات',
                'trading', 'est', 'establishment', 'company', 'co', 'ltd',
                'limited', 'llc', 'inc', 'international', 'global'
            ];

            const parts = s.split(/\s+/).filter(p => p && !stop.includes(p));
            return parts.join(''); // Join without spaces for key
        };

        const checkMatch = (val) => {
            const inputKey = makeSupplierKey(val);
            if (!inputKey) {
                btnAddSupplier.style.display = 'none';
                return;
            }

            // Check against existing suppliers using the normalized key
            // Note: We use the same normalization logic on the list just to be safe, 
            // though server likely sends good data.
            const exists = suppliers.some(s => {
                const sKey = makeSupplierKey(s.official_name); // Calculate key on fly to be safe
                return sKey === inputKey;
            });

            if (exists) {
                btnAddSupplier.style.display = 'none';
            } else {
                btnAddSupplier.style.display = 'flex'; // Restore flex display
                supplierNamePreview.textContent = val;
            }
        };

        supplierInput.addEventListener('input', (e) => {
            const val = e.target.value; // Don't trim here to allow typing spaces
            checkMatch(val);
        });

        // 2. Add Action
        btnAddSupplier.addEventListener('click', async () => {
            const name = supplierInput.value.trim();
            if (!name) return;

            // Disable button
            const OriginalText = btnAddSupplier.innerHTML;
            btnAddSupplier.disabled = true;
            btnAddSupplier.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> جاري الإضافة...';
            lucide.createIcons();

            try {
                const res = await fetch('/api/dictionary/suppliers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        official_name: name,
                        // WHY: We send the 'raw_name_context' of the current record.
                        // This tells the backend: "This NEW supplier is being created specifically for THIS raw name."
                        // The backend uses this to immediately link them and pre-calculate match scores,
                        // so when the user returns to the list, this supplier appears as a high-score suggestion.
                        raw_name_context: config.rawSupplierName || ''
                    })
                });
                const json = await res.json();
                if (json.success) {
                    // Update suppliers list locally so checkMatch knows about it
                    // Note: The API returns a Supplier object with camelCase properties (officialName), but our local 
                    // suppliers array (from PHP) uses snake_case (official_name). We need to adapt it.
                    const newSupplier = {
                        id: json.data.id,
                        official_name: json.data.officialName, // Map API camelCase to local snake_case
                        normalized_name: json.data.normalizedName
                    };
                    suppliers.push(newSupplier);

                    // Select the new supplier
                    document.getElementById('supplierId').value = newSupplier.id;
                    document.getElementById('supplierInput').value = newSupplier.official_name;
                    if (document.getElementById('letterSupplier')) {
                        updateLetterFont(newSupplier.official_name, 'letterSupplier');
                    }

                    // Hide the add button immediately
                    btnAddSupplier.style.display = 'none';

                    // Show success feedback
                    const errorDiv = document.getElementById('supplierAddError');
                    errorDiv.classList.remove('hidden', 'text-red-500');
                    errorDiv.classList.add('text-green-600', 'font-bold');
                    errorDiv.innerHTML = '<div class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> تمت الإضافة بنجاح</div>';
                    lucide.createIcons();
                    errorDiv.style.display = 'block';

                    // Hide feedback after 3 seconds
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                        errorDiv.innerHTML = '';
                    }, 3000);

                } else {
                    const errorDiv = document.getElementById('supplierAddError');
                    errorDiv.classList.remove('hidden', 'text-green-600');
                    errorDiv.classList.add('text-red-500');
                    errorDiv.textContent = 'خطأ: ' + (json.message || 'فشل إضافة المورد');
                    errorDiv.style.display = 'block';
                    btnAddSupplier.innerHTML = OriginalText;
                }
            } catch (e) {
                const errorDiv = document.getElementById('supplierAddError');
                errorDiv.classList.remove('hidden', 'text-green-600');
                errorDiv.classList.add('text-red-500');
                errorDiv.textContent = 'خطأ في الاتصال';
                errorDiv.style.display = 'block';
                btnAddSupplier.innerHTML = OriginalText;
            } finally {
                btnAddSupplier.disabled = false;
            }
        });
    }

    setupAutocomplete('supplierInput', 'supplierSuggestions', 'supplierId', suppliers, 'official_name', 'letterSupplier');
    setupAutocomplete('bankInput', 'bankSuggestions', 'bankId', banks, 'official_name', 'letterBank');

    // Session Dropdown Logic
    const metaSessionId = document.getElementById('metaSessionId');
    const sessionDropdown = document.getElementById('sessionDropdown');
    const sessionSearch = document.getElementById('sessionSearch');
    const sessionList = document.getElementById('sessionList');

    if (metaSessionId && sessionDropdown) {
        // Toggle
        metaSessionId.addEventListener('click', (e) => {
            e.stopPropagation();
            sessionDropdown.classList.toggle('hidden');
            if (!sessionDropdown.classList.contains('hidden')) {
                sessionSearch.focus();
            }
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!sessionDropdown.contains(e.target) && e.target !== metaSessionId) {
                sessionDropdown.classList.add('hidden');
            }
        });

        // Prevent closing when clicking inside
        sessionDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Search Filter
        if (sessionSearch && sessionList) {
            sessionSearch.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                const items = sessionList.querySelectorAll('a');
                items.forEach(item => {
                    const txt = item.innerText.toLowerCase();
                    if (txt.includes(term)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    }

    // Save & Next
    const btnSaveNext = document.getElementById('btnSaveNext');
    if (btnSaveNext && recordId) {
        btnSaveNext.addEventListener('click', async () => {
            const msg = document.getElementById('saveMessage');

            try {
                const res = await fetch(`/api/records/${recordId}/decision`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        match_status: 'ready', // Required by backend
                        supplier_id: document.getElementById('supplierId').value || null,
                        bank_id: document.getElementById('bankId').value || null,
                        supplier_name: document.getElementById('supplierInput').value,
                        bank_name: document.getElementById('bankInput').value
                    })
                });
                const json = await res.json();

                if (json.success) {
                    msg.textContent = '';
                    msg.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> تم الحفظ</span>';
                    lucide.createIcons();
                    msg.style.color = '#16a34a';
                    
                    // Navigate to next record or reload current page
                    if (nextUrl) {
                        setTimeout(() => window.location.href = nextUrl, 300);
                    } else {
                        // No next URL, reload current page to show updated data
                        setTimeout(() => window.location.reload(), 300);
                    }
                } else {
                    msg.textContent = 'خطأ: ' + (json.message || 'فشل الحفظ');
                    msg.style.color = '#dc2626';
                }
            } catch (err) {
                msg.textContent = 'خطأ في الاتصال';
                msg.style.color = '#dc2626';
            }
        });
    }

    // Print All Button
    const btnPrintAll = document.getElementById('btnPrintAll');
    if (btnPrintAll) {
        btnPrintAll.addEventListener('click', () => {
            // Try to get session_id from URL params first, then from config
            const urlParams = new URLSearchParams(window.location.search);
            let sid = urlParams.get('session_id');

            // If not in URL, try to get from config (which PHP sets)
            if (!sid && config.sessionId) {
                sid = config.sessionId;
            }

            // If still no session_id, try to read from meta display
            if (!sid) {
                const metaElem = document.getElementById('metaSessionId');
                if (metaElem) {
                    sid = metaElem.textContent.trim();
                }
            }

            if (sid && sid !== '-') {
                // Create hidden iframe for printing without leaving page
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                iframe.src = '/?session_id=' + sid + '&print_batch=1';

                // Helper to clean up
                iframe.onload = function () {
                    // The iframe has its own window.print() on load. 
                    // We can remove it after a delay or just leave it.
                    setTimeout(() => document.body.removeChild(iframe), 60000);
                };

                document.body.appendChild(iframe);
            } else {
                showWarning('لا يوجد رقم جلسة محدد. الرجاء اختيار جلسة أولاً أو استيراد ملف.');
            }
        });
    }

    // File Import
    const btnImport = document.getElementById('btnToggleImport');
    const fileInput = document.getElementById('hiddenFileInput');
    if (btnImport && fileInput) {
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
                    // Navigate to first record if available, otherwise just session
                    if (json.data.first_record_id) {
                        window.location.href = `/?session_id=${json.data.session_id}&record_id=${json.data.first_record_id}`;
                    } else {
                        window.location.href = '/?session_id=' + json.data.session_id;
                    }
                } else {
                    showError('خطأ: ' + (json.message || 'فشل الاستيراد'));
                }
            } catch (err) {
                showError('خطأ في الاتصال');
            }
        });
    }

    // Recalculate All button
    const btnRecalc = document.getElementById('btnRecalcAll');
    if (btnRecalc) {
        btnRecalc.addEventListener('click', async () => {
            if (!btnRecalc.dataset.confirming) {
                // First click - show confirm
                btnRecalc.dataset.confirming = 'true';
                btnRecalc.dataset.originalHtml = btnRecalc.innerHTML;
                btnRecalc.innerHTML = '<i data-lucide="alert-triangle" class="w-4 h-4"></i> تأكيد؟';
                lucide.createIcons();
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
            lucide.createIcons();
            btnRecalc.disabled = true;

            try {
                const res = await fetch('/api/records/recalculate', { method: 'POST' });
                const json = await res.json();
                if (json.success) {
                    showSuccess('تمت إعادة المطابقة: ' + (json.data?.processed || 0) + ' سجل');
                    window.location.href = window.location.href; // Force reload with params
                } else {
                    showError('خطأ: ' + (json.message || 'فشلت العملية'));
                }
            } catch (err) {
                showError('خطأ في الاتصال');
            } finally {
                btnRecalc.disabled = false;
                btnRecalc.innerHTML = btnRecalc.dataset.originalHtml || '<i data-lucide="refresh-cw" class="w-4 h-4"></i>';
                lucide.createIcons();
            }
        });
    }
})();

/**
 * Smart Paste Logic
 */
(function () {
    const modal = document.getElementById('smartPasteModal');
    const btnOpen = document.getElementById('btnOpenSmartPaste'); // Need to add this button to header
    const btnClose = document.getElementById('btnCloseSmartPaste');
    const btnCancel = document.getElementById('btnCancelSmartPaste');
    const btnProcess = document.getElementById('btnProcessSmartPaste');
    const input = document.getElementById('smartPasteInput');
    const errorDiv = document.getElementById('smartPasteError');

    // Note: Creating the trigger button dynamically if it doesn't exist yet
    // Ideally should be in the main toolbar HTML, but for now we inject it via JS to avoid messing up complex HTML structure blindly
    if (!document.getElementById('btnOpenSmartPasteTrigger')) {
        const toolbar = document.querySelector('.nav-btn')?.parentElement;
        // Or find a distinct header area. Let's try to append near the existing 'Import Excel' button logic
        const importBtn = document.getElementById('btnToggleImport');
        if (importBtn && importBtn.parentNode) {
            const btn = document.createElement('button');
            btn.id = 'btnOpenSmartPasteTrigger';
            // Match existing toolbar icon buttons exactly
            btn.className = 'flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors';
            btn.title = 'لصق نص (Smart Paste)';
            btn.innerHTML = '<i data-lucide="clipboard-copy" class="w-4 h-4 text-gray-600"></i>';
            lucide.createIcons();
            btn.onclick = () => {
                modal.classList.remove('hidden');
                input.focus();
            };
            importBtn.parentNode.insertBefore(btn, importBtn.nextSibling); // Add after import button

            // Removed margin hack as flex gap handles spacing
        }
    }

    const closeModal = () => {
        modal.classList.add('hidden');
        input.value = '';
        errorDiv.classList.add('hidden');
        btnProcess.disabled = false;
        btnProcess.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="sparkles" class="w-4 h-4"></i> تحليل وإضافة</span>';
        lucide.createIcons();
    };

    if (btnClose) btnClose.onclick = closeModal;
    if (btnCancel) btnCancel.onclick = closeModal;

    // Close on outside click
    // Close on outside click
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    if (btnProcess) {
        btnProcess.onclick = async () => {
            const text = input.value.trim();
            if (!text) {
                errorDiv.textContent = 'يرجى إدخال نص أولاً';
                errorDiv.classList.remove('hidden');
                return;
            }

            btnProcess.disabled = true;
            btnProcess.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> جارِ التحليل...</span>';
            lucide.createIcons();
            errorDiv.classList.add('hidden');

            try {
                // Get optional document type from selector
                const relatedToSelect = document.getElementById('smartPasteRelatedTo');
                const relatedTo = relatedToSelect ? relatedToSelect.value || null : null;

                const res = await fetch('/api/import/text', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        text: text,
                        related_to: relatedTo  // Optional: auto-detection fallback if null
                    })
                });

                const json = await res.json();

                if (json.success) {
                    // Redirect to the new session/record
                    window.location.href = `/?session_id=${json.session_id}&record_id=${json.record_id}`;
                } else {
                    throw new Error(json.error || 'فشلت العملية');
                }
            } catch (err) {
                btnProcess.disabled = false;
                btnProcess.innerHTML = '<span class="flex items-center gap-2"><i data-lucide="sparkles" class="w-4 h-4"></i> تحليل وإضافة</span>';
                lucide.createIcons();
                errorDiv.textContent = 'خطأ: ' + err.message;
                errorDiv.classList.remove('hidden');
            }
        };
    }
})();
