<?php
// Decision Page View - HTML only
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اتخاذ القرار</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/letter.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/decision.css">
    <link rel="stylesheet" href="/assets/css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="app-shell">
    <main class="app-main">
        <div class="app-container">

            <!-- Hidden File Input for Direct Upload -->
            <input type="file" id="hiddenFileInput" accept=".xlsx" style="display:none;" />

            <!-- Main Decision Card (Sticky Header) -->
            <section class="card p-0 sticky top-0 z-50 shadow-md rounded-t-none">
                <div class="card-body p-1">

                    <!-- Embedded Toolbar -->
                    <div class="flex items-center gap-1 px-2 py-1 mb-1 border-b border-gray-100">
                        <!-- ZONE 1: Status Badges -->
                        <div class="flex items-center gap-1" id="toolbarZoneStart">
                            <div class="flex items-center gap-1" id="statusBadges">
                                <a href="<?= $buildUrl($currentRecord?->id, 'all') ?>"
                                    class="flex items-center justify-center gap-1 px-2 h-7 rounded text-xs font-bold bg-gray-50 hover:bg-gray-100 border <?= $filter === 'all' ? 'border-gray-400' : 'border-transparent hover:border-gray-200' ?> text-gray-600 transition-all"
                                    title="العدد الكلي">
                                    <span><?= $stats['total'] ?></span> 📋
                                </a>
                                <a href="<?= $buildUrl($currentRecord?->id, 'approved') ?>"
                                    class="flex items-center justify-center gap-1 px-2 h-7 rounded text-xs font-bold bg-green-50 hover:bg-green-100 border <?= $filter === 'approved' ? 'border-green-400' : 'border-transparent hover:border-green-200' ?> text-green-700 transition-all"
                                    title="جاهز للطباعة">
                                    <span><?= $stats['approved'] ?></span> ✓
                                </a>
                                <a href="<?= $buildUrl($currentRecord?->id, 'pending') ?>"
                                    class="flex items-center justify-center gap-1 px-2 h-7 rounded text-xs font-bold bg-orange-50 hover:bg-orange-100 border <?= $filter === 'pending' ? 'border-orange-400' : 'border-transparent hover:border-orange-200' ?> text-orange-700 transition-all"
                                    title="معلق">
                                    <span><?= $stats['pending'] ?></span> !
                                </a>
                                <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-blue-50 text-blue-600 transition-colors"
                                    id="badgeSearch" title="بحث">
                                    🔍
                                </button>
                                <!-- Search Input Wrapper -->
                                <div class="search-input-wrapper" id="searchInputWrapper">
                                    <input type="text" id="guaranteeSearchInput" placeholder="رقم الضمان..." autocomplete="off">
                                    <button id="btnSearchGo">بحث</button>
                                </div>
                            </div>
                        </div>

                        <!-- ZONE 2: Center Title -->
                        <div class="flex-1 flex justify-center" id="toolbarZoneCenter">
                            <span class="font-bold text-gray-800 text-sm">نظام إدارة خطابات الضمان</span>
                        </div>

                        <!-- ZONE 3: Tools -->
                        <div class="flex items-center gap-1" id="toolbarZoneEnd">
                            <!-- Import Group -->
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnToggleImport" title="استيراد ملف Excel">📥</button>
                            <!-- Smart Paste Button Injected Here via JS -->

                            <div class="h-4 w-px bg-gray-300 mx-1"></div>

                            <!-- Data Actions Group -->
                            <a href="<?= $buildUrl($currentRecord?->id) ?>" class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                title="تحديث البيانات">🔄</a>
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnRecalcAll" title="إعادة المطابقة">🔃</button>

                            <div class="h-4 w-px bg-gray-300 mx-1"></div>

                            <!-- Print Group -->
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnPrintPreview" title="طباعة المعاينة (الحالية)" onclick="window.print()">🖨️</button>
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnPrintAll" title="طباعة الكل (Batch Print)">📑</button>

                            <div class="h-4 w-px bg-gray-300 mx-1"></div>

                            <!-- App Links Group -->
                            <a href="/stats.php" class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                title="الإحصائيات">📊</a>
                            <a href="/settings.php" class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                title="الإعدادات">⚙️</a>
                        </div>
                    </div>

                    <?php if ($currentRecord): ?>
                    <!-- Record Meta -->
                    <div class="flex items-center justify-between px-3 pt-2 pb-2 -mx-1 -mt-1 mb-0">
                        <div class="record-meta text-xs flex flex-wrap items-center justify-between w-full gap-y-1">
                            <span class="text-gray-600 font-mono relative" style="position: relative;">
                                رقم الجلسة: 
                                <strong id="metaSessionId" class="cursor-pointer underline" style="text-underline-offset: 2px;" title="انقر لتغيير الجلسة"><?= $sessionId ?? '-' ?></strong>
                                <!-- Session Dropdown -->
                                <div id="sessionDropdown" class="hidden absolute bg-white border border-gray-200 shadow-xl rounded-lg z-50" style="top: 100%; right: 0; width: 240px; max-height: 300px; overflow-y: auto;">
                                    <div class="sticky top-0 bg-white p-2 border-b border-gray-100">
                                        <input type="text" id="sessionSearch" placeholder="بحث (رقم أو تاريخ)..." class="w-full text-xs px-2 py-1 border rounded focus:ring-1 focus:ring-blue-500 outline-none">
                                    </div>
                                    <div id="sessionList">
                                        <?php foreach ($allSessions as $sess): ?>
                                        <a href="<?= $buildUrl(null, null, $sess['session_id']) ?>" 
                                           class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-xs flex justify-between items-center <?= $sess['session_id'] == $sessionId ? 'bg-blue-50 font-bold' : '' ?>" 
                                           data-session="<?= $sess['session_id'] ?>" data-date="<?= $sess['last_date'] ?? '' ?>">
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-700">جلسة #<?= $sess['session_id'] ?></span>
                                                <span class="text-[10px] text-gray-400"><?= $sess['last_date'] ? explode(' ', $sess['last_date'])[0] : '-' ?></span>
                                            </div>
                                            <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-[10px]"><?= $sess['record_count'] ?></span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </span>
                            <span class="text-gray-600 font-mono">السجل: <strong><?= $currentRecord->id ?></strong></span>
                            <span class="text-gray-600">رقم الضمان: <strong><?= htmlspecialchars($currentRecord->guaranteeNumber ?? '-') ?></strong></span>
                            <span class="text-gray-600">رقم العقد: <strong><?= htmlspecialchars($currentRecord->contractNumber ?? '-') ?></strong></span>
                            <span class="text-gray-600">انتهاء الضمان: <strong><?= htmlspecialchars($currentRecord->expiryDate ?? '-') ?></strong></span>
                            <span class="text-gray-600">المبلغ: <strong><?= number_format((float)($currentRecord->amount ?? 0), 2) ?></strong></span>
                            <span class="text-gray-600">النوع: <strong><?= htmlspecialchars($currentRecord->type ?? '-') ?></strong></span>
                        </div>
                    </div>

                    <!-- Supplier & Bank Inputs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1 mb-1">
                        <!-- Supplier Side -->
                        <div class="bg-white rounded p-2 relative">
                            <div class="mt-0 relative z-10">
                                <div class="field-input flex items-start gap-2">
                                    <span class="text-xs font-bold text-gray-700 whitespace-nowrap mt-1.5">المورد:</span>
                                    <div class="relative w-full">
                                        <input type="text" id="supplierInput"
                                            class="w-full border border-gray-300 rounded px-2 py-0 text-xs outline-none transition-all"
                                            placeholder="<?= htmlspecialchars($currentRecord->rawSupplierName ?? 'ابحث عن المورد...') ?>" autocomplete="off"
                                            value="<?= htmlspecialchars($currentRecord->supplierDisplayName ?? $currentRecord->rawSupplierName ?? '') ?>">
                                        <input type="hidden" id="supplierId" value="<?= $currentRecord->supplierId ?? '' ?>">
                                        
                                        <!-- OPTION 1: Show raw Excel name if different from selection -->
                                        <?php if (!empty($currentRecord->rawSupplierName) && 
                                                  !empty($currentRecord->supplierDisplayName) &&
                                                  $currentRecord->rawSupplierName !== $currentRecord->supplierDisplayName): ?>
                                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-1 px-1">
                                            <span>📄</span>
                                            <span class="opacity-75">من الاكسل:</span>
                                            <strong class="text-gray-700">"<?= htmlspecialchars($currentRecord->rawSupplierName) ?>"</strong>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <ul class="suggestions-list" id="supplierSuggestions"></ul>

                                        <!-- Candidate Chips -->
                                        <div class="flex flex-wrap items-center gap-2 mt-1 min-h-[20px]">
                                            <div id="supplierChips" class="flex flex-wrap gap-1">
                                                <?php foreach (array_slice($supplierCandidates, 0, 6) as $cand): 
                                                    $isCurrentSelection = $cand['is_current_selection'] ?? false;
                                                    $isLearning = $cand['is_learning'] ?? false;
                                                    $score = round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100);
                                                    $starRating = $cand['star_rating'] ?? 1;
                                                    $usageCount = $cand['usage_count'] ?? 0;
                                                    $selectionBadge = $cand['selection_badge'] ?? '';
                                                    
                                                    // Determine chip class
                                                    $chipClass = "chip-btn";
                                                    if ($isCurrentSelection) {
                                                        $chipClass .= " chip-selected";
                                                    } elseif ($starRating >= 3) {
                                                        $chipClass .= " chip-3star";
                                                    } elseif ($starRating >= 2) {
                                                        $chipClass .= " chip-2star";
                                                    }
                                                    if ($isLearning) {
                                                        $chipClass .= " chip-learning";
                                                    }
                                                    
                                                    // Generate icon
                                                    $icon = $isCurrentSelection ? '✓' : str_repeat('⭐', $starRating);
                                                    
                                                    // Build tooltip
                                                    $tooltip = "";
                                                    if ($usageCount > 0) {
                                                        $tooltip = "استخدمته {$usageCount} " . ($usageCount == 1 ? 'مرة' : 'مرات');
                                                    }
                                                    
                                                    // Current selection: ALWAYS show (disabled)
                                                    if ($isCurrentSelection) {
                                                        ?>
                                                        <button type="button" class="<?= $chipClass ?>" disabled
                                                              title="<?= htmlspecialchars($tooltip) ?>">
                                                            <span><?= $icon ?> <?= htmlspecialchars($cand['name']) ?></span>
                                                            <?php if ($selectionBadge): ?>
                                                            <span class="selection-badge"><?= $selectionBadge ?></span>
                                                            <?php endif; ?>
                                                        </button>
                                                        <?php
                                                        continue;
                                                    }
                                                    
                                                    // Learning chips: ALWAYS show
                                                    if ($isLearning) {
                                                        ?>
                                                        <button type="button" class="<?= $chipClass ?>"
                                                              data-id="<?= $cand['supplier_id'] ?>"
                                                              data-name="<?= htmlspecialchars($cand['name']) ?>"
                                                              data-type="supplier"
                                                              title="<?= htmlspecialchars($tooltip) ?>">
                                                            <span><?= $icon ?> <?= htmlspecialchars($cand['name']) ?></span>
                                                        </button>
                                                        <?php
                                                        continue;
                                                    }
                                                    
                                                    // Skip if this is current selection (already shown above)
                                                    if ($isCurrentSelection) continue;
                                                    
                                                    // Fuzzy chips: Show only if < 99%
                                                    if ($score >= 99) continue;
                                                ?>
                                                <button type="button" class="<?= $chipClass ?>"
                                                      data-id="<?= $cand['supplier_id'] ?>"
                                                      data-name="<?= htmlspecialchars($cand['name']) ?>"
                                                      data-type="supplier"
                                                      title="<?= htmlspecialchars($tooltip) ?>">
                                                    <span><?= $icon ?> <?= htmlspecialchars($cand['name']) ?></span>
                                                    <!-- Only show % if no usage history -->
                                                    <?php if ($usageCount == 0): ?>
                                                    <span class="font-bold opacity-75"><?= $score ?>%</span>
                                                    <?php endif; ?>
                                                </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <!-- Add Supplier Button - only show if no 100% match -->
                                            <?php 
                                            $hasExactMatch = !empty($supplierCandidates) && (($supplierCandidates[0]['score_raw'] ?? $supplierCandidates[0]['score'] ?? 0) >= 0.99);
                                            ?>
                                            <!-- Add Supplier Button -->
                                            <button type="button" id="btnAddSupplier"
                                                class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border transition-all bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300 hover:scale-105 whitespace-nowrap"
                                                title="إضافة كمورد جديد"
                                                style="<?= $hasExactMatch ? 'display:none;' : '' ?>">
                                                ➕ إضافة "<span id="supplierNamePreview"><?= htmlspecialchars(mb_substr($currentRecord->rawSupplierName ?? '', 0, 20)) ?></span>" كمورد جديد
                                            </button>
                                            <div id="supplierAddError" class="text-red-500 text-[10px] hidden"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Side -->
                        <div class="bg-white rounded p-2 relative">
                            <div class="mt-0 relative z-10">
                                <div class="field-input flex items-start gap-2">
                                    <span class="text-xs font-bold text-gray-700 whitespace-nowrap mt-1.5">البنك:</span>
                                    <div class="relative w-full">
                                        <input type="text" id="bankInput"
                                            class="w-full border border-gray-300 rounded px-2 py-0 text-xs outline-none transition-all"
                                            placeholder="<?= htmlspecialchars($currentRecord->rawBankName ?? 'ابحث عن البنك...') ?>" autocomplete="off"
                                            value="<?= htmlspecialchars($currentRecord->bankDisplay ?? $currentRecord->rawBankName ?? '') ?>">
                                        <input type="hidden" id="bankId" value="<?= $currentRecord->bankId ?? '' ?>">
                                        <ul class="suggestions-list" id="bankSuggestions"></ul>

                                        <!-- Candidate Chips -->
                                        <div class="flex flex-wrap items-center gap-2 mt-1 min-h-[20px]">
                                            <div id="bankChips" class="flex flex-wrap gap-1">
                                                <?php foreach (array_slice($bankCandidates, 0, 5) as $cand): 
                                                    $isLearning = $cand['is_learning'] ?? false;
                                                    $score = round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100);
                                                    
                                                    // Learning chips: ALWAYS show
                                                    if ($isLearning) {
                                                        ?>
                                                        <button type="button" class="chip-btn chip-learning"
                                                              data-id="<?= $cand['bank_id'] ?>"
                                                              data-name="<?= htmlspecialchars($cand['name']) ?>"
                                                              data-type="bank">
                                                            <span>⭐ <?= htmlspecialchars($cand['name']) ?></span>
                                                        </button>
                                                        <?php
                                                        continue;
                                                    }
                                                    
                                                    // Fuzzy chips: Show only if < 99% AND not selected
                                                    if (($currentRecord->bankId ?? null) == $cand['bank_id']) continue;
                                                    if ($score >= 99) continue;

                                                ?>
                                                <button type="button" class="chip-btn"
                                                      data-id="<?= $cand['bank_id'] ?>"
                                                      data-name="<?= htmlspecialchars($cand['name']) ?>"
                                                      data-type="bank">
                                                    <span><?= htmlspecialchars($cand['name']) ?></span>
                                                    <span class="font-bold opacity-75"><?= round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100) ?>%</span>
                                                </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation & Save -->
                    <div class="flex items-center justify-between mt-0 pt-2 px-3 pb-2 -mx-1 -mb-1">
                        <a href="<?= $hasPrev ? $buildUrl($prevId) : '#' ?>" class="nav-btn py-1 px-3 text-sm bg-white shadow-sm hover:shadow <?= !$hasPrev ? 'disabled' : '' ?>">
                            <span>▶</span>
                            <span>السابق</span>
                        </a>

                        <div class="flex items-center gap-4">
                            <span id="saveMessage" class="text-xs font-medium"></span>
                            <?php 
                            $filterText = 'سجل';
                            if ($filter === 'approved') $filterText = 'سجل جاهز';
                            elseif ($filter === 'pending') $filterText = 'سجل يحتاج قرار';
                            ?>
                            <button class="save-btn py-1.5 px-6 text-sm shadow-md hover:shadow-lg" id="btnSaveNext">
                                <span>✓</span>
                                <span>إحفظ <?= $filterText ?> <?= $currentIndex + 1 ?> من <?= $totalRecords ?>، وانتقل للتالي</span>
                            </button>
                        </div>

                        <a href="<?= $hasNext ? $buildUrl($nextId) : '#' ?>" class="nav-btn py-1 px-3 text-sm bg-white shadow-sm hover:shadow <?= !$hasNext ? 'disabled' : '' ?>">
                            <span>التالي</span>
                            <span>◀</span>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-gray-500">
                        لا توجد سجلات. يرجى استيراد ملف Excel أولاً.
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Guarantee History Panel (Hidden by default) -->
            <div id="guaranteeHistoryPanel" class="hidden">
                <div class="panel-header">
                    <h3 id="historyTitle">📜 تاريخ الضمان</h3>
                    <button onclick="document.getElementById('guaranteeHistoryPanel').classList.add('hidden'); document.getElementById('badgeSearch').classList.remove('search-active'); document.getElementById('searchInputWrapper').classList.remove('visible');">✕ إغلاق</button>
                </div>
                <div class="history-timeline" id="historyTimeline">
                    <!-- Timeline content will be inserted here by JavaScript -->
                </div>
            </div>

            <?php if ($currentRecord): 
                // Initialize names from current record or defaults
                $supplierName = $currentRecord->supplierDisplayName ?? $currentRecord->rawSupplierName ?? 'المورد';
                $bankName = $currentRecord->bankDisplay ?? $currentRecord->rawBankName ?? 'البنك';
                
                // Re-ensure names are set (redundant but safe)
                $bankName = $bankName ?? 'البنك';
                $supplierName = $supplierName ?? 'المورد';
                
                // ... rest of data prep ...
                $guaranteeNo = $currentRecord->guaranteeNumber ?? '-';
                $contractNo = $currentRecord->contractNumber ?? '-';
                $amount = number_format((float)($currentRecord->amount ?? 0), 2);
                
                // Convert amount to Hindi numerals
                $hindiDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                $amountHindi = preg_replace_callback('/[0-9]/', fn($m) => $hindiDigits[$m[0]], $amount);
                
                // Calculate renewal date (expiry + 1 year)
                $renewalDate = '-';
                if ($currentRecord->expiryDate) {
                    try {
                        $date = new DateTime($currentRecord->expiryDate);
                        $date->modify('+1 year');
                        $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
                        $day = preg_replace_callback('/[0-9]/', fn($m) => $hindiDigits[$m[0]], $date->format('j'));
                        $month = $months[(int)$date->format('n') - 1];
                        $year = preg_replace_callback('/[0-9]/', fn($m) => $hindiDigits[$m[0]], $date->format('Y'));
                        $renewalDate = $day . ' ' . $month . ' ' . $year . 'م';
                    } catch (Exception $e) {
                        $renewalDate = $currentRecord->expiryDate;
                    }
                }
                
                // Watermark status
                $hasSupplier = !empty($currentRecord->supplierId);
                $hasBank = !empty($currentRecord->bankId);
                $watermarkText = ($hasSupplier && $hasBank) ? 'جاهز' : 'يحتاج قرار';
                $watermarkClass = ($hasSupplier && $hasBank) ? 'status-ready' : 'status-draft';
                
                // Guarantee type description
                $guaranteeDesc = 'الضمان البنكي';
                if ($currentRecord->type) {
                    $t = strtoupper($currentRecord->type);
                    if ($t === 'FINAL') $guaranteeDesc = 'الضمان البنكي النهائي';
                    elseif ($t === 'ADVANCED') $guaranteeDesc = 'ضمان الدفعة المقدمة البنكي';
                }
                
                // Check if supplier name is English for styling
                // Use \p{Arabic} for broader coverage and check === 0 to ensure errors don't force English
                $hasArabic = preg_match('/\p{Arabic}/u', $supplierName);
                $isEnglish = ($hasArabic === 0); 
                $supplierStyle = $isEnglish ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;" : "";

                // Bank Details
                $bankId = $currentRecord->bankId;
                $bankDetails = array_filter($allBanks, fn($b) => $b['id'] == $bankId);
                $bankDetails = !empty($bankDetails) ? reset($bankDetails) : null;
                
                $bankDept = $bankDetails['department'] ?? 'إدارة الضمانات';
                $bankAddress = array_filter([
                    $bankDetails['address_line_1'] ?? 'المقر الرئيسي',
                    $bankDetails['address_line_2'] ?? null,
                ]);
                $bankEmail = $bankDetails['contact_email'] ?? null;
            ?>
            <!-- Letter Preview Section -->
            <section class="mt-8" id="letterPreviewSection">
                <div class="letter-preview">
                    <div class="letter-paper">
                        
                        <!-- Watermark -->
                        <div class="watermark <?= $watermarkClass ?>"><?= $watermarkText ?></div>
                        
                        <div class="header-line">
                          <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">السادة / <span id="letterBank"><?= htmlspecialchars($bankName) ?></span></div>
                          <div class="greeting">المحترمين</div>
                        </div>

                        <div id="letterBankDetails">
                           <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;"><?= htmlspecialchars($bankDept) ?></div>
                           <?php foreach($bankAddress as $line): 
                               // Convert numbers to Hindi in address lines
                               $lineHindi = preg_replace_callback('/[0-9]/', fn($m) => $hindiDigits[$m[0]], $line);
                           ?>
                           <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;"><?= htmlspecialchars($lineHindi) ?></div>
                           <?php endforeach; ?>
                           <?php if($bankEmail): ?>
                           <div><span style="text-shadow: 0 0 1px #333, 0 0 1px #333;">البريد الالكتروني:</span> <?= htmlspecialchars($bankEmail) ?></div>
                           <?php endif; ?>
                        </div>

                        <div style="text-align:right; margin: 5px 0;">السَّلام عليكُم ورحمَة الله وبركاتِه</div>

                        <div class="subject">
                            <span style="flex:0 0 70px;">الموضوع:</span>
                            <span>
                              طلب تمديد الضمان البنكي رقم (<?= htmlspecialchars($guaranteeNo) ?>) 
                              <?php if ($contractNo !== '-'): ?>
                              والعائد للعقد رقم (<?= htmlspecialchars($contractNo) ?>)
                              <?php endif; ?>
                            </span>
                        </div>

                        <div class="first-paragraph">
                            إشارة الى <?= $guaranteeDesc ?> الموضح أعلاه، والصادر منكم لصالحنا على حساب 
                            <span style="<?= $supplierStyle ?>" id="letterSupplier"><?= htmlspecialchars($supplierName) ?></span> 
                            بمبلغ قدره (<strong><?= $amountHindi ?></strong>) ريال، 
                            نأمل منكم <span class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">تمديد فترة سريان الضمان حتى تاريخ <?= $renewalDate ?></span>، 
                            مع بقاء الشروط الأخرى دون تغيير، وإفادتنا بذلك من خلال البريد الالكتروني المخصص للضمانات البنكية لدى مستشفى الملك فيصل التخصصي ومركز الأبحاث بالرياض (bgfinance@kfshrc.edu.sa)، كما نأمل منكم إرسال أصل تمديد الضمان الى:
                        </div>

                        <div style="margin-top: 5px; margin-right: 50px;">
                            <div>مستشفى الملك فيصل التخصصي ومركز الأبحاث – الرياض</div>
                            <div>ص.ب ٣٣٥٤ الرياض ١١٢١١</div>
                            <div>مكتب الخدمات الإدارية</div>
                        </div>

                        <div class="first-paragraph">
                            علمًا بأنه في حال عدم تمكن البنك من تمديد الضمان المذكور قبل انتهاء مدة سريانه، فيجب على البنك دفع قيمة الضمان إلينا حسب النظام.
                        </div>

                        <div style="text-indent:5em; margin-top:5px;">وَتفضَّلوا بِقبُول خَالِص تحيَّاتِي</div>

                        <div class="fw-800-sharp" style="text-align: center; margin-top: 5px; margin-right: 320px;">
                            <div style="margin-bottom: 60px; text-shadow: 0 0 1px #333, 0 0 1px #333;">مُدير الإدارة العامَّة للعمليَّات المحاسبيَّة</div>
                            <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">سَامِي بن عبَّاس الفايز</div>
                        </div>

                        <div style="position:absolute; left:1in; right:1in; bottom:0.7in; display:flex; justify-content:space-between; font-size:9pt;">
                          <span>MBC:09-2</span>
                          <span>BAMZ</span>
                        </div>

                    </div>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <!-- JavaScript for Autocomplete and Save -->
    
    <!-- Smart Paste Modal -->
    <div id="smartPasteModal" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] hidden flex items-center justify-center">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden animate-fade-in-up">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i data-lucide="clipboard-copy" class="w-5 h-5 text-blue-600"></i> استيراد نصي ذكي (Smart Paste)
                </h3>
                <button id="btnCloseSmartPaste" class="text-gray-400 hover:text-gray-600 transition-colors text-2xl leading-none">&times;</button>
            </div>
            
            <div class="p-6">
                <div class="mb-4 bg-blue-50 text-blue-800 p-3 rounded-lg text-sm flex gap-2">
                    <i data-lucide="lightbulb" class="w-5 h-5 text-yellow-500 flex-shrink-0"></i>
                    <div>
                        قم بنسخ نص الإيميل أو الطلب ولصقه هنا. سيقوم النظام باستخراج البيانات تلقائياً.
                    </div>
                </div>

                <textarea id="smartPasteInput" class="w-full h-48 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 focus:bg-white focus:border-blue-500 transition-all font-mono text-sm leading-relaxed" placeholder="مثال: يرجى إصدار ضمان بنكي بمبلغ 50,000 ريال لصالح شركة المراعي..."></textarea>
                
                <div id="smartPasteError" class="mt-3 text-red-600 text-sm hidden font-bold"></div>
            </div>

            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
                <button id="btnCancelSmartPaste" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition-colors">إلغاء</button>
                <button id="btnProcessSmartPaste" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> تحليل وإضافة
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Configuration -->
    <script>
        window.DecisionApp = {
            suppliers: <?= json_encode($allSuppliers) ?>,
            banks: <?= json_encode($allBanks) ?>,
            recordId: <?= $currentRecord?->id ?? 'null' ?>,
            nextUrl: <?= $nextPendingId ? '"' . $buildUrl($nextPendingId) . '"' : ($hasNext ? '"' . $buildUrl($nextId) . '"' : 'null') ?>,
            rawSupplierName: <?= json_encode($currentRecord->rawSupplierName ?? '') ?>,
            sessionId: <?= $sessionId ?? 'null' ?>
        };
    </script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/js/sweetalert-helpers.js"></script>
    
    <!-- Main Logic Script -->
    <script src="/assets/js/decision.js"></script>

    <!-- Guarantee Search Feature -->
    <script src="/assets/js/guarantee-search.js"></script>
    
    <!-- Initialize Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>