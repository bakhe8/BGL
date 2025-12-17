/**
 * Guarantee Search and History Feature
 * Adds interactive search for guarantees and displays their history across sessions
 */

(function () {
    const searchBtn = document.getElementById('badgeSearch');
    const searchWrapper = document.getElementById('searchInputWrapper');
    const searchInput = document.getElementById('guaranteeSearchInput');
    const searchGoBtn = document.getElementById('btnSearchGo');
    const historyPanel = document.getElementById('guaranteeHistoryPanel');
    const historyTimeline = document.getElementById('historyTimeline');
    const historyTitle = document.getElementById('historyTitle');

    if (!searchBtn) return; // Exit if elements not found

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

    // Search function
    async function searchGuarantee() {
        if (!searchInput || !historyPanel || !historyTimeline) return;

        const guaranteeNumber = searchInput.value.trim();

        if (!guaranteeNumber) {
            alert('الرجاء إدخال رقم ضمان');
            return;
        }

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

        if (!data.history || data.history.length === 0) {
            historyTimeline.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;">لا توجد سجلات</div>';
            return;
        }

        let html = '';

        data.history.forEach((item, index) => {
            const isFirst = item.is_first;
            const statusClass = item.status === 'جاهز' ? 'ready' : 'pending';
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
                                <span class="status-badge-timeline ${statusClass}">${item.status}</span>
                            </div>
                        </div>
                        <div class="timeline-date">${formattedDate}</div>
                        
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
                        
                        <a href="/?id=${item.record_id}&session=${item.session_id}" class="view-record-btn">
                            عرض السجل ←
                        </a>
                    </div>
                </div>
            `;
        });

        historyTimeline.innerHTML = html;
    }

    // Event listeners
    if (searchGoBtn) {
        searchGoBtn.addEventListener('click', searchGuarantee);
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchGuarantee();
            }
        });
    }
})();
