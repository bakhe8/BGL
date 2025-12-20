/**
 * Guarantee History Feature
 * Interactive display of guarantee history with search, extension, and release actions
 */

(function () {
    const searchBtn = document.getElementById('badgeSearch');
    const searchWrapper = document.getElementById('searchInputWrapper');
    const searchInput = document.getElementById('guaranteeSearchInput');
    const searchGoBtn = document.getElementById('btnSearchGo');
    const historyPanel = document.getElementById('guaranteeHistoryPanel');
    const historyTimeline = document.getElementById('historyTimeline');
    const historyTitle = document.getElementById('historyTitle');
    const quickSearchBtn = document.getElementById('btnQuickSearch'); // Quick search from meta bar
    const issueReleaseBtn = document.getElementById('btnIssueRelease'); // Release Button
    const issueExtensionBtn = document.getElementById('btnIssueExtension'); // Extension Button

    if (!searchBtn) return; // Exit if elements not found

    // Quick Search Listener (Meta Bar Click)
    if (quickSearchBtn) {
        quickSearchBtn.addEventListener('click', () => {
            const guaranteeNum = quickSearchBtn.innerText.trim();
            if (guaranteeNum && guaranteeNum !== '-') {
                // Populate search input
                if (searchInput) {
                    searchInput.value = guaranteeNum;
                    // Show the search wrapper for better UX
                    if (searchWrapper) searchWrapper.classList.add('visible');
                    // Load history
                    loadGuaranteeHistory();
                }
            }
        });
    }


    // Issue Release Letter Listener
    if (issueReleaseBtn) {
        issueReleaseBtn.addEventListener('click', async () => {
            const guaranteeNum = issueReleaseBtn.dataset.guarantee;
            if (!guaranteeNum) return;

            // Disable button during request
            issueReleaseBtn.disabled = true;
            issueReleaseBtn.textContent = 'جاري الإصدار...';

            try {
                const response = await fetch('/api/issue-release.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `guarantee_no=${encodeURIComponent(guaranteeNum)}`
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('تم إصدار خطاب الإفراج بنجاح');
                    // Refresh history to show new release record
                    loadGuaranteeHistory(guaranteeNum);
                } else {
                    showWarning(data.error || 'فشل إصدار الخطاب');
                }
            } catch (error) {
                showWarning('خطأ في الاتصال بالخادم');
                console.error('Release error:', error);
            } finally {
                issueReleaseBtn.disabled = false;
                issueReleaseBtn.innerHTML = '<i data-lucide="file-check"></i> إصدار خطاب إفراج';
                lucide.createIcons();
            }
        });
    }

    // Issue Extension Letter Listener
    if (issueExtensionBtn) {
        issueExtensionBtn.addEventListener('click', async () => {
            const guaranteeNum = issueExtensionBtn.dataset.guarantee;
            if (!guaranteeNum) return;

            // Disable button during request
            issueExtensionBtn.disabled = true;
            issueExtensionBtn.textContent = 'جاري الإنشاء...';

            try {
                const response = await fetch('/api/issue-extension.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `guarantee_no=${encodeURIComponent(guaranteeNum)}`
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message || 'تم إنشاء سجل التمديد بنجاح');
                    // Refresh history to show new extension record
                    loadGuaranteeHistory(guaranteeNum);
                } else {
                    showWarning(data.error || 'فشل إنشاء التمديد');
                }
            } catch (error) {
                showWarning('خطأ في الاتصال بالخادم');
                console.error('Extension error:', error);
            } finally {
                issueExtensionBtn.disabled = false;
                issueExtensionBtn.innerHTML = '<i data-lucide="refresh-cw"></i> إنشاء تمديد';
                lucide.createIcons();
            }
        });
    }


    // Toggle search input visibility
    if (searchWrapper && searchBtn) {
        searchBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = searchWrapper.classList.toggle('visible');
            searchBtn.classList.toggle('search-active');

            if (isVisible && searchInput) {
                setTimeout(() => searchInput.focus(), 300);
            }
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!searchWrapper.contains(e.target) && e.target !== searchBtn) {
                searchWrapper.classList.remove('visible');
                searchBtn.classList.remove('search-active');
            }
        });
    }

    // Load and display guarantee history
    async function loadGuaranteeHistory(guaranteeNum = null) {
        if (!historyPanel || !historyTimeline) return;

        // CRITICAL FIX: Prevent Event object from being used as guarantee number
        if (guaranteeNum && typeof guaranteeNum !== 'string') {
            guaranteeNum = null;
        }

        // Use provided number or get from searchInput
        const guaranteeNumber = guaranteeNum || (searchInput ? searchInput.value.trim() : '');

        if (!guaranteeNumber) {
            showWarning('الرجاء إدخال رقم ضمان');
            return;
        }

        // Hide release button initially
        if (issueReleaseBtn) issueReleaseBtn.classList.add('hidden');

        // Show loading
        historyPanel.classList.remove('hidden');
        historyPanel.classList.remove('hidden');
        historyTimeline.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;"><div class="flex justify-center mb-2"><i data-lucide="loader-2" class="w-8 h-8 animate-spin"></i></div> جاري البحث...</div>';
        historyTitle.innerHTML = `<span class="flex items-center gap-2"><i data-lucide="file-text" class="w-5 h-5"></i> جاري البحث عن الضمان رقم: ${guaranteeNumber}</span>`;
        lucide.createIcons();

        try {
            const response = await fetch(`/api/guarantee-history.php?number=${encodeURIComponent(guaranteeNumber)}`);
            const data = await response.json();

            if (data.success) {
                displayHistory(data);
            } else {
                historyTimeline.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <div class="flex justify-center mb-4"><i data-lucide="search-x" class="w-12 h-12 text-red-500 opacity-50"></i></div>
                        <div style="color: #ef4444; font-weight: 600; font-size: 16px;">${data.error || 'لم يتم العثور على نتائج'}</div>
                    </div>
                `;
                lucide.createIcons();
            }
        } catch (error) {
            historyTimeline.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="flex justify-center mb-4"><i data-lucide="wifi-off" class="w-12 h-12 text-red-500 opacity-50"></i></div>
                    <div style="color: #ef4444; font-weight: 600;">خطأ في الاتصال بالخادم</div>
                </div>
            `;
            lucide.createIcons();
            console.error('Search error:', error);
        }
    }

    // Display history timeline
    function displayHistory(data) {
        if (!historyPanel || !historyTimeline || !historyTitle) return;

        historyTitle.textContent = '';
        historyTitle.innerHTML = `<span class="flex items-center gap-2"><i data-lucide="history" class="w-5 h-5"></i> تاريخ الضمان رقم: ${data.guarantee_number} (${data.total_records} سجل)</span>`;
        lucide.createIcons();

        // Show and setup Release Button
        if (issueReleaseBtn) {
            issueReleaseBtn.classList.remove('hidden');
            issueReleaseBtn.dataset.guarantee = data.guarantee_number;
        }

        // Show and setup Extension Button
        if (issueExtensionBtn) {
            issueExtensionBtn.classList.remove('hidden');
            issueExtensionBtn.dataset.guarantee = data.guarantee_number;
        }

        if (!data.history || data.history.length === 0) {
            historyTimeline.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;">لا توجد سجلات</div>';
            return;
        }

        let html = '';

        // Helper function to generate source link
        const getSourceLink = (item) => {
            // Action (extension/release) → Session link
            if (item.record_type && item.record_type !== 'import') {
                const date = new Date(item.date);
                const dateStr = date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' });
                return `<div class="timeline-source">📋 <a href="/?session_id=${item.session_id}">إجراءات ${dateStr}</a></div>`;
            }
            
            // Import → Batch link (if available)
            if (item.import_batch_id) {
                return `<div class="timeline-source">📦 <a href="/?batch_id=${item.import_batch_id}">مجموعة #${item.import_batch_id}</a></div>`;
            }
            
            return ''; // No source link
        };
        
        data.history.forEach((item, index) => {
            const isFirst = item.is_first;
            const isRelease = item.record_type === 'release_action';
            const isExtension = item.record_type === 'extension_action';

            // Generate action type badge (if applicable)
            let actionBadge = '';
            if (isRelease) {
                actionBadge = '<span class="status-badge-timeline release">إفراج</span>';
            } else if (isExtension) {
                actionBadge = '<span class="status-badge-timeline extension">تمديد</span>';
            }

            // Generate status badge
            let statusBadge = '';
            const statusClass = item.status === 'جاهز' ? 'ready' : 'pending';
            const statusLabel = item.status;
            statusBadge = `<span class="status-badge-timeline ${statusClass}">${statusLabel}</span>`;

            const date = new Date(item.date);
            const formattedDate = date.toLocaleDateString('ar-SA', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            html += `
                <div class="timeline-item ${isFirst ? 'first-record' : ''}">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div>
                                <span class="session-badge flex items-center gap-1"><i data-lucide="file-digit" class="w-3 h-3"></i> جلسة #${item.session_id}</span>
                                ${actionBadge}
                                ${statusBadge}
                            </div>
                        </div>
                        <div class="timeline-date">${formattedDate}</div>
                        
                        ${getSourceLink(item)}
                        
                        <div class="timeline-info">
                            <div class="info-row">
                                <span class="info-label">المورد:</span>
                                <span class="info-value">${item.supplier || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">البنك:</span>
                                <span class="info-value">${item.bank || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">المبلغ:</span>
                                <span class="info-value">${parseFloat(item.amount || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 })} ريال</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">تاريخ الانتهاء:</span>
                                <span class="info-value">${item.expiry_date || '-'}</span>
                            </div>
                        </div>
                        
                        ${item.changes && item.changes.length > 0 ? `
                            <div class="changes-list">
                                <div style="font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 8px; display: flex; items-center; gap: 4px;">
                                    <i data-lucide="edit-3" class="w-3 h-3"></i> التغييرات:
                                </div>
                                ${item.changes.map(change => `
                                    <div class="change-item">
                                        <span style="font-weight: 600;">${change.field}:</span>
                                        <span>
                                            <span class="change-from">${change.from || '-'}</span>
                                            ←
                                            <span class="change-to">${change.to || '-'}</span>
                                        </span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                        
                        
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <a href="/?record_id=${item.record_id}&session_id=${item.session_id}" class="view-record-btn" style="width: auto; padding: 6px 20px; text-align: center; justify-content: center;">
                                عرض السجل
                            </a>
                            <button onclick="
                                var recId = ${item.record_id || 0};
                                if (!recId) { alert('خطأ: معرف السجل مفقود'); return; }
                                window.open('/letters/print-letter.php?id=' + recId, '_blank', 'width=900,height=800');
                            " 
                                    class="view-record-btn" 
                                    style="width: auto; padding: 6px 20px; display: flex; align-items: center; justify-content: center; gap: 5px; cursor: pointer; text-decoration: none;"
                                    title="طباعة">
                                <i data-lucide="printer" class="w-4 h-4"></i> طباعة
                            </button>
                        </div>
                    </div>
                </div>
            `;

        });

        historyTimeline.innerHTML = html;
        lucide.createIcons(); // Re-render icons
    }

    // Event listeners
    if (searchGoBtn) {
        // CRITICAL FIX: Wrap in arrow function to prevent passing Event object
        searchGoBtn.addEventListener('click', () => loadGuaranteeHistory());
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                loadGuaranteeHistory();
            }
        });
    }
})();
