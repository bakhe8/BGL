<?php
/**
 * Decision Page - PHP Version (Exact Match to Original)
 * 
 * صفحة اتخاذ القرار - نسخة طبق الأصل من decision.html
 * PHP يعرض البيانات، JavaScript للـ Autocomplete والتفاعل
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\ImportSessionRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;

$records = new ImportedRecordRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();
$sessions = new ImportSessionRepository();
$candidateService = new CandidateService($suppliers, new SupplierAlternativeNameRepository(), new Normalizer(), $banks);

// Get parameters
$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;
$recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : null;
$filter = $_GET['filter'] ?? 'all'; // all, pending, approved

// Get available sessions for dropdown
$allSessions = $sessions->getAllSessions();

// Default to latest session if none specified
if (!$sessionId && !empty($allSessions)) {
    $sessionId = (int) $allSessions[0]['session_id'];
}

// Get all records for the session
$allRecords = $sessionId ? $records->allBySession($sessionId) : [];

// Apply filter
$filteredRecords = array_filter($allRecords, function($r) use ($filter) {
    if ($filter === 'all') return true;
    $isCompleted = in_array($r->matchStatus, ['ready', 'approved']);
    if ($filter === 'approved') return $isCompleted;
    if ($filter === 'pending') return !$isCompleted;
    return true;
});
$filteredRecords = array_values($filteredRecords);

// Find current record
$currentIndex = 0;
$currentRecord = null;

if ($recordId) {
    foreach ($filteredRecords as $index => $r) {
        if ($r->id === $recordId) {
            $currentIndex = $index;
            $currentRecord = $r;
            break;
        }
    }
}
if (!$currentRecord && !empty($filteredRecords)) {
    $currentRecord = $filteredRecords[0];
}

// Calculate navigation
$totalRecords = count($filteredRecords);
$hasPrev = $currentIndex > 0;
$hasNext = $currentIndex < $totalRecords - 1;
$prevId = $hasPrev ? $filteredRecords[$currentIndex - 1]->id : null;
$nextId = $hasNext ? $filteredRecords[$currentIndex + 1]->id : null;

// Stats for current session (filtered)
$stats = [
    'total' => count($allRecords),
    'approved' => count(array_filter($allRecords, fn($r) => in_array($r->matchStatus, ['ready', 'approved']))),
    'pending' => count(array_filter($allRecords, fn($r) => !in_array($r->matchStatus, ['ready', 'approved']))),
];

// Get candidates for current record
$supplierCandidates = [];
$bankCandidates = [];
if ($currentRecord) {
    $supplierResult = $candidateService->supplierCandidates($currentRecord->rawSupplierName ?? '');
    $supplierCandidates = $supplierResult['candidates'] ?? [];
    $bankResult = $candidateService->bankCandidates($currentRecord->rawBankName ?? '');
    $bankCandidates = $bankResult['candidates'] ?? [];
}

// Get all suppliers and banks for autocomplete
$allSuppliers = $suppliers->allNormalized();
$allBanks = $banks->allNormalized();

// Build query string for navigation
$buildUrl = function($newRecordId = null, $newFilter = null, $newSessionId = null) use ($sessionId, $filter) {
    $params = [];
    $params['session_id'] = $newSessionId ?? $sessionId;
    if ($newRecordId) $params['record_id'] = $newRecordId;
    $params['filter'] = $newFilter ?? $filter;
    return '/decision.php?' . http_build_query($params);
};

// Filter text for save button
$filterText = 'سجل';
if ($filter === 'approved') $filterText = 'سجل جاهز';
elseif ($filter === 'pending') $filterText = 'سجل يحتاج قرار';
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                        mono: ['Inter', 'monospace'],
                    },
                    colors: {
                        primary: '#2563eb',
                        'primary-soft': '#dbeafe',
                    }
                }
            }
        }
    </script>
    <style>
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 10px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.2s;
        }
        .status-badge:hover { transform: scale(1.05); }
        .status-badge.active { box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3); }
        .status-total { background: #f1f5f9; color: #475569; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }

        .field-input { flex: 1; position: relative; }
        .field-input input {
            width: 100%;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.2s;
        }
        .field-input input:focus {
            outline: none !important;
            box-shadow: none !important;
            border-color: #e2e8f0 !important;
        }

        .suggestions-list {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            z-index: 9000;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            max-height: 250px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }
        .suggestions-list.open { display: block; }
        .suggestion-item {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.1s;
        }
        .suggestion-item:hover { background: #f0f9ff; }
        .suggestion-item .score {
            font-size: 0.8em;
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 600;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .nav-btn:hover:not(.disabled) { border-color: #94a3b8; }
        .nav-btn.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

        .save-btn {
            padding: 12px 32px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .save-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        .record-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0;
            color: #64748b;
            font-size: 0.9em;
        }
        .record-meta span { display: flex; align-items: center; gap: 4px; }

        /* Chip styling - matches original gray style */
        .chip-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            background: #f9fafb;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        .chip-btn:hover { 
            transform: scale(1.05); 
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        /* Record selector dropdown */
        .record-selector {
            position: relative;
            display: inline-block;
        }
        .record-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            min-width: 200px;
        }
        .record-dropdown.open { display: block; }
        .record-dropdown a {
            display: block;
            padding: 8px 12px;
            color: #374151;
            text-decoration: none;
            border-bottom: 1px solid #f3f4f6;
        }
        .record-dropdown a:hover { background: #f0f9ff; }
        .record-dropdown a.current { background: #dbeafe; font-weight: 600; }
    </style>
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
                            </div>
                        </div>

                        <!-- ZONE 2: Center Title -->
                        <div class="flex-1 flex justify-center" id="toolbarZoneCenter">
                            <span class="font-bold text-gray-800 text-sm">نظام إدارة خطابات الضمان</span>
                        </div>

                        <!-- ZONE 3: Tools -->
                        <div class="flex items-center gap-1" id="toolbarZoneEnd">
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnToggleImport" title="استيراد ملف">📥</button>
                            <a href="<?= $buildUrl($currentRecord?->id) ?>" class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                title="تحديث البيانات">🔄</a>
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnRecalcAll" title="إعادة المطابقة">🔃</button>

                            <div class="h-4 w-px bg-gray-300 mx-1"></div>

                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnPrintPreview" title="طباعة المعاينة" onclick="window.print()">🖨️</button>
                            <button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
                                id="btnPrintAll" title="طباعة الكل">📑</button>

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
                                        <ul class="suggestions-list" id="supplierSuggestions"></ul>

                                        <!-- Candidate Chips -->
                                        <div class="flex flex-wrap items-center gap-2 mt-1 min-h-[20px]">
                                            <div id="supplierChips" class="flex flex-wrap gap-1">
                                                <?php foreach (array_slice($supplierCandidates, 0, 3) as $cand): ?>
                                                <button type="button" class="chip-btn"
                                                      data-id="<?= $cand['supplier_id'] ?>"
                                                      data-name="<?= htmlspecialchars($cand['name']) ?>"
                                                      data-type="supplier">
                                                    <span><?= htmlspecialchars($cand['name']) ?></span>
                                                    <span class="font-bold opacity-75"><?= round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100) ?>%</span>
                                                </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <!-- Add Supplier Button - only show if no 100% match -->
                                            <?php 
                                            $hasExactMatch = !empty($supplierCandidates) && (($supplierCandidates[0]['score_raw'] ?? $supplierCandidates[0]['score'] ?? 0) >= 1.0);
                                            if (!$hasExactMatch): 
                                            ?>
                                            <button type="button" id="btnAddSupplier"
                                                class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border transition-all bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300 hover:scale-105 whitespace-nowrap"
                                                title="إضافة كمورد جديد">
                                                ➕ إضافة "<span id="supplierNamePreview"><?= htmlspecialchars(mb_substr($currentRecord->rawSupplierName ?? '', 0, 20)) ?></span>" كمورد جديد
                                            </button>
                                            <?php endif; ?>
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
                                                <?php foreach (array_slice($bankCandidates, 0, 3) as $cand): ?>
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

            <?php if ($currentRecord): 
                // Prepare letter data
                $bankName = $currentRecord->bankDisplay ?? $currentRecord->rawBankName ?? 'البنك';
                $supplierName = $currentRecord->supplierDisplayName ?? $currentRecord->rawSupplierName ?? 'المورد';
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
                $isEnglish = !preg_match('/[\x{0600}-\x{06FF}]/u', $supplierName);
                $supplierStyle = $isEnglish ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;" : "";
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

                        <div>
                           <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">قسم الضمانات</div>
                           <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">المقر الرئيسي</div>
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
    <script>
    (function() {
        const suppliers = <?= json_encode($allSuppliers) ?>;
        const banks = <?= json_encode($allBanks) ?>;
        const recordId = <?= $currentRecord?->id ?? 'null' ?>;
        const nextUrl = <?= $hasNext ? '"' . $buildUrl($nextId) . '"' : 'null' ?>;

        // Chip Click Handler (gray buttons)
        document.querySelectorAll('.chip-btn').forEach(chip => {
            chip.addEventListener('click', () => {
                const type = chip.dataset.type;
                const id = chip.dataset.id;
                const name = chip.dataset.name;
                
                if (type === 'supplier') {
                    document.getElementById('supplierInput').value = name;
                    document.getElementById('supplierId').value = id;
                    if (document.getElementById('letterSupplier')) {
                        document.getElementById('letterSupplier').textContent = name;
                    }
                } else {
                    document.getElementById('bankInput').value = name;
                    document.getElementById('bankId').value = id;
                    if (document.getElementById('letterBank')) {
                        document.getElementById('letterBank').textContent = name;
                    }
                }
            });
        });

        // Session Selector Dropdown
        const sessionBtn = document.getElementById('metaSessionId');
        const sessionDropdown = document.getElementById('sessionDropdown');
        const sessionSearch = document.getElementById('sessionSearch');
        if (sessionBtn && sessionDropdown) {
            sessionBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sessionDropdown.classList.toggle('hidden');
                if (!sessionDropdown.classList.contains('hidden') && sessionSearch) {
                    sessionSearch.focus();
                }
            });
            if (sessionSearch) {
                sessionSearch.addEventListener('input', (e) => {
                    const filter = e.target.value.toLowerCase();
                    document.querySelectorAll('#sessionList a').forEach(item => {
                        const sess = item.dataset.session || '';
                        const date = item.dataset.date || '';
                        item.style.display = (sess.includes(filter) || date.includes(filter)) ? '' : 'none';
                    });
                });
                sessionSearch.addEventListener('click', (e) => e.stopPropagation());
            }
            document.addEventListener('click', (e) => {
                if (!sessionDropdown.contains(e.target) && e.target !== sessionBtn) {
                    sessionDropdown.classList.add('hidden');
                }
            });
        }

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
                    if (letter) letter.textContent = name;
                    suggestions.classList.remove('open');
                }
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.classList.remove('open');
                }
            });
        }

        setupAutocomplete('supplierInput', 'supplierSuggestions', 'supplierId', suppliers, 'official_name', 'letterSupplier');
        setupAutocomplete('bankInput', 'bankSuggestions', 'bankId', banks, 'official_name', 'letterBank');

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
                            supplier_id: document.getElementById('supplierId').value || null,
                            bank_id: document.getElementById('bankId').value || null,
                            supplier_name: document.getElementById('supplierInput').value,
                            bank_name: document.getElementById('bankInput').value
                        })
                    });
                    const json = await res.json();
                    
                    if (json.success) {
                        msg.textContent = '✓ تم الحفظ';
                        msg.style.color = '#16a34a';
                        if (nextUrl) {
                            setTimeout(() => window.location.href = nextUrl, 300);
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
                        window.location.href = '/decision.php?session_id=' + json.data.session_id;
                    } else {
                        alert('خطأ: ' + (json.message || 'فشل الاستيراد'));
                    }
                } catch (err) {
                    alert('خطأ في الاتصال');
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
                    btnRecalc.innerHTML = '⚠️ تأكيد؟';
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
                btnRecalc.innerHTML = '⏳...';
                btnRecalc.disabled = true;
                
                try {
                    const res = await fetch('/api/records/recalculate', { method: 'POST' });
                    const json = await res.json();
                    if (json.success) {
                        alert('تمت إعادة المطابقة: ' + (json.data?.processed || 0) + ' سجل');
                        window.location.reload();
                    } else {
                        alert('خطأ: ' + (json.message || 'فشلت العملية'));
                    }
                } catch (err) {
                    alert('خطأ في الاتصال');
                } finally {
                    btnRecalc.disabled = false;
                    btnRecalc.innerHTML = btnRecalc.dataset.originalHtml || '🔃';
                }
            });
        }
    })();
    </script>
</body>
</html>
