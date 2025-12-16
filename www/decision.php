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

// Get all records for the session
$allRecords = $records->all($sessionId);

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

// Stats for current session
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
$buildUrl = function($newRecordId = null, $newFilter = null) use ($sessionId, $filter) {
    $params = [];
    if ($sessionId) $params['session_id'] = $sessionId;
    if ($newRecordId) $params['record_id'] = $newRecordId;
    $params['filter'] = $newFilter ?? $filter;
    return '/decision.php?' . http_build_query($params);
};
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

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .chip-strong { background: #dcfce7; color: #166534; border: 1px solid #a7f3d0; }
        .chip-weak { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .chip:hover { transform: scale(1.05); }
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
                            <span class="text-gray-600 font-mono">رقم الجلسة: <strong><?= $currentRecord->sessionId ?? '-' ?></strong></span>
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
                                            placeholder="تحديد المورد..." autocomplete="off"
                                            value="<?= htmlspecialchars($currentRecord->supplierDisplayName ?? '') ?>">
                                        <input type="hidden" id="supplierId" value="<?= $currentRecord->supplierId ?? '' ?>">
                                        <ul class="suggestions-list" id="supplierSuggestions"></ul>

                                        <!-- Candidate Chips -->
                                        <div class="flex flex-wrap items-center gap-2 mt-1 min-h-[20px]">
                                            <div id="supplierChips" class="flex flex-wrap gap-1">
                                                <?php foreach (array_slice($supplierCandidates, 0, 5) as $cand): 
                                                    $isStrong = ($cand['score_raw'] ?? $cand['score'] ?? 0) >= 0.9;
                                                ?>
                                                <span class="chip <?= $isStrong ? 'chip-strong' : 'chip-weak' ?>"
                                                      data-id="<?= $cand['supplier_id'] ?>"
                                                      data-name="<?= htmlspecialchars($cand['name']) ?>">
                                                    <?= htmlspecialchars($cand['name']) ?>
                                                    <span class="text-[10px] opacity-70"><?= round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100) ?>%</span>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
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
                                            placeholder="تحديد البنك..." autocomplete="off"
                                            value="<?= htmlspecialchars($currentRecord->bankDisplay ?? '') ?>">
                                        <input type="hidden" id="bankId" value="<?= $currentRecord->bankId ?? '' ?>">
                                        <ul class="suggestions-list" id="bankSuggestions"></ul>

                                        <!-- Candidate Chips -->
                                        <div class="flex flex-wrap items-center gap-2 mt-1 min-h-[20px]">
                                            <div id="bankChips" class="flex flex-wrap gap-1">
                                                <?php foreach (array_slice($bankCandidates, 0, 5) as $cand): 
                                                    $isStrong = ($cand['score_raw'] ?? $cand['score'] ?? 0) >= 0.9;
                                                ?>
                                                <span class="chip <?= $isStrong ? 'chip-strong' : 'chip-weak' ?>"
                                                      data-id="<?= $cand['bank_id'] ?>"
                                                      data-name="<?= htmlspecialchars($cand['name']) ?>">
                                                    <?= htmlspecialchars($cand['name']) ?>
                                                    <span class="text-[10px] opacity-70"><?= round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100) ?>%</span>
                                                </span>
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
                            <button class="save-btn py-1.5 px-6 text-sm shadow-md hover:shadow-lg" id="btnSaveNext">
                                <span>✓</span>
                                <span>إحفظ (<span id="currentIndex"><?= $currentIndex + 1 ?></span> من <span id="totalCount"><?= $totalRecords ?></span>) وانتقال</span>
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

            <?php if ($currentRecord): ?>
            <!-- Letter Preview Section -->
            <section class="mt-8" id="letterPreviewSection">
                <div id="letterContainer" class="w-full flex justify-center">
                    <div class="letter-paper bg-white shadow-lg p-8 max-w-2xl w-full" style="border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div class="text-center mb-6">
                            <h2 class="text-xl font-bold">خطاب ضمان بنكي</h2>
                        </div>
                        <table class="w-full" style="border-collapse: collapse;">
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; width: 30%; font-weight: 600; background: #f9fafb;">رقم الضمان</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->guaranteeNumber ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; font-weight: 600; background: #f9fafb;">رقم العقد</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->contractNumber ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; font-weight: 600; background: #f9fafb;">المورد</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;" id="letterSupplier"><?= htmlspecialchars($currentRecord->supplierDisplayName ?? $currentRecord->rawSupplierName ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; font-weight: 600; background: #f9fafb;">البنك</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;" id="letterBank"><?= htmlspecialchars($currentRecord->bankDisplay ?? $currentRecord->rawBankName ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; font-weight: 600; background: #f9fafb;">المبلغ</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;"><?= number_format((float)($currentRecord->amount ?? 0), 2) ?> ر.س</td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; font-weight: 600; background: #f9fafb;">تاريخ الانتهاء</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->expiryDate ?? '-') ?></td>
                            </tr>
                            <?php if ($currentRecord->type): ?>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; font-weight: 600; background: #f9fafb;">النوع</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->type) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
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

        // Chip Click Handler
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const type = chip.closest('#supplierChips') ? 'supplier' : 'bank';
                const id = chip.dataset.id;
                const name = chip.dataset.name;
                
                if (type === 'supplier') {
                    document.getElementById('supplierInput').value = name;
                    document.getElementById('supplierId').value = id;
                    document.getElementById('letterSupplier').textContent = name;
                } else {
                    document.getElementById('bankInput').value = name;
                    document.getElementById('bankId').value = id;
                    document.getElementById('letterBank').textContent = name;
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
                    if (json.success && json.session_id) {
                        window.location.href = '/decision.php?session_id=' + json.session_id;
                    } else {
                        alert('خطأ: ' + (json.message || 'فشل الاستيراد'));
                    }
                } catch (err) {
                    alert('خطأ في الاتصال');
                }
            });
        }
    })();
    </script>
</body>
</html>
