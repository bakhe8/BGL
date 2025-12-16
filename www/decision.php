<?php
/**
 * Decision Page - PHP Version (Hybrid)
 * 
 * صفحة اتخاذ القرار - النسخة المبسطة
 * PHP يعرض البيانات، JavaScript للـ Autocomplete فقط
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\ImportSessionRepository;

$records = new ImportedRecordRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();
$sessions = new ImportSessionRepository();

// Get parameters
$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;
$recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : null;
$filter = $_GET['filter'] ?? 'all'; // all, pending, approved

// Get all records (optionally filtered by session)
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
} elseif (!empty($filteredRecords)) {
    $currentRecord = $filteredRecords[0];
}

// Calculate navigation
$totalRecords = count($filteredRecords);
$hasPrev = $currentIndex > 0;
$hasNext = $currentIndex < $totalRecords - 1;
$prevId = $hasPrev ? $filteredRecords[$currentIndex - 1]->id : null;
$nextId = $hasNext ? $filteredRecords[$currentIndex + 1]->id : null;

// Stats
$stats = [
    'total' => count($allRecords),
    'approved' => count(array_filter($allRecords, fn($r) => in_array($r->matchStatus, ['ready', 'approved']))),
    'pending' => count(array_filter($allRecords, fn($r) => !in_array($r->matchStatus, ['ready', 'approved']))),
];

// Get suppliers and banks for autocomplete (as JSON)
$suppliersJson = json_encode($suppliers->allNormalized());
$banksJson = json_encode($banks->allNormalized());

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
    <title>اتخاذ القرار - نظام خطابات الضمان</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/letter.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .decision-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 1.5rem; }
        .field-row { display: flex; align-items: center; gap: 1rem; padding: 0.75rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.75rem; }
        .field-label { min-width: 80px; font-weight: 600; color: #6b7280; }
        .field-value { flex: 1; font-weight: 500; }
        .field-input { flex: 1; position: relative; }
        .field-input input { width: 100%; padding: 0.5rem 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; }
        .field-input input:focus { outline: none; border-color: #3b82f6; }
        .suggestions-list { position: absolute; top: 100%; right: 0; left: 0; background: white; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; display: none; z-index: 100; }
        .suggestions-list.open { display: block; }
        .suggestion-item { padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
        .suggestion-item:hover { background: #f0f9ff; }
        .nav-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
        .nav-btn { padding: 0.5rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; background: white; font-weight: 600; cursor: pointer; text-decoration: none; color: #374151; }
        .nav-btn:hover:not(.disabled) { border-color: #9ca3af; }
        .nav-btn.disabled { opacity: 0.5; pointer-events: none; }
        .save-btn { padding: 0.75rem 2rem; background: #000; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .save-btn:hover { background: #1f2937; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 99px; font-size: 0.875rem; font-weight: 600; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .stats-bar { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .stat-item { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; }
        .stat-item.active { box-shadow: 0 0 0 2px #3b82f6; }
        .stat-all { background: #f1f5f9; color: #475569; }
        .stat-approved { background: #dcfce7; color: #166534; }
        .stat-pending { background: #fef3c7; color: #92400e; }
        .record-meta { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem; }
        .record-meta strong { color: #111827; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-info { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body class="app-shell">
    <header class="app-header">
        <div class="app-header-inner">
            <div>
                <span class="app-logo">BL</span>
                <span class="app-title">نظام إدارة خطابات الضمان</span>
            </div>
            <nav class="app-nav">
                <a href="/decision.php" class="app-nav-link is-active">الرئيسية</a>
                <a href="/stats.php" class="app-nav-link">الإحصائيات</a>
                <a href="/settings.php" class="app-nav-link">الإعدادات</a>
            </nav>
        </div>
    </header>

    <main class="app-main">
        <div class="app-container">
            
            <!-- Stats Bar -->
            <div class="stats-bar">
                <a href="<?= $buildUrl($currentRecord?->id, 'all') ?>" class="stat-item stat-all <?= $filter === 'all' ? 'active' : '' ?>">
                    📋 الكل: <?= $stats['total'] ?>
                </a>
                <a href="<?= $buildUrl($currentRecord?->id, 'approved') ?>" class="stat-item stat-approved <?= $filter === 'approved' ? 'active' : '' ?>">
                    ✓ جاهز: <?= $stats['approved'] ?>
                </a>
                <a href="<?= $buildUrl($currentRecord?->id, 'pending') ?>" class="stat-item stat-pending <?= $filter === 'pending' ? 'active' : '' ?>">
                    ! معلق: <?= $stats['pending'] ?>
                </a>
            </div>

            <?php if (!$currentRecord): ?>
                <div class="alert alert-info">
                    لا توجد سجلات. يرجى <a href="/settings.php">استيراد ملف Excel</a> أولاً.
                </div>
            <?php else: ?>
                
                <!-- Navigation Bar -->
                <div class="nav-bar">
                    <a href="<?= $hasPrev ? $buildUrl($prevId) : '#' ?>" class="nav-btn <?= !$hasPrev ? 'disabled' : '' ?>">
                        ▶ السابق
                    </a>
                    <span style="font-weight: 600;">
                        السجل <?= $currentIndex + 1 ?> من <?= $totalRecords ?>
                    </span>
                    <a href="<?= $hasNext ? $buildUrl($nextId) : '#' ?>" class="nav-btn <?= !$hasNext ? 'disabled' : '' ?>">
                        التالي ◀
                    </a>
                </div>

                <!-- Record Meta -->
                <div class="record-meta">
                    <span>رقم السجل: <strong>#<?= $currentRecord->id ?></strong></span>
                    <span>رقم الضمان: <strong><?= htmlspecialchars($currentRecord->guaranteeNumber ?? '-') ?></strong></span>
                    <span>رقم العقد: <strong><?= htmlspecialchars($currentRecord->contractNumber ?? '-') ?></strong></span>
                    <span>المبلغ: <strong><?= number_format((float)($currentRecord->amount ?? 0), 2) ?> ر.س</strong></span>
                    <span>تاريخ الانتهاء: <strong><?= htmlspecialchars($currentRecord->expiryDate ?? '-') ?></strong></span>
                    <span>الحالة: 
                        <span class="status-badge <?= in_array($currentRecord->matchStatus, ['ready', 'approved']) ? 'status-approved' : 'status-pending' ?>">
                            <?= in_array($currentRecord->matchStatus, ['ready', 'approved']) ? 'جاهز' : 'معلق' ?>
                        </span>
                    </span>
                </div>

                <!-- Decision Form -->
                <form id="decisionForm" class="decision-card">
                    <input type="hidden" name="record_id" value="<?= $currentRecord->id ?>">
                    
                    <!-- Supplier -->
                    <div class="field-row">
                        <span class="field-label">المورد الأصلي:</span>
                        <span class="field-value" style="color: #9ca3af;"><?= htmlspecialchars($currentRecord->rawSupplierName ?? '') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">المورد:</span>
                        <div class="field-input">
                            <input type="text" id="supplierInput" name="supplier_name" 
                                   value="<?= htmlspecialchars($currentRecord->supplierDisplayName ?? $currentRecord->rawSupplierName ?? '') ?>"
                                   placeholder="ابحث عن المورد..." autocomplete="off">
                            <input type="hidden" id="supplierId" name="supplier_id" value="<?= $currentRecord->supplierId ?? '' ?>">
                            <ul class="suggestions-list" id="supplierSuggestions"></ul>
                        </div>
                    </div>

                    <!-- Bank -->
                    <div class="field-row">
                        <span class="field-label">البنك الأصلي:</span>
                        <span class="field-value" style="color: #9ca3af;"><?= htmlspecialchars($currentRecord->rawBankName ?? '') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">البنك:</span>
                        <div class="field-input">
                            <input type="text" id="bankInput" name="bank_name" 
                                   value="<?= htmlspecialchars($currentRecord->bankDisplay ?? $currentRecord->rawBankName ?? '') ?>"
                                   placeholder="ابحث عن البنك..." autocomplete="off">
                            <input type="hidden" id="bankId" name="bank_id" value="<?= $currentRecord->bankId ?? '' ?>">
                            <ul class="suggestions-list" id="bankSuggestions"></ul>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                        <span id="saveMessage" style="font-size: 0.875rem;"></span>
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="save-btn" id="btnSave">
                                ✓ حفظ
                            </button>
                            <?php if ($hasNext): ?>
                            <a href="<?= $buildUrl($nextId) ?>" class="save-btn" style="background: #3b82f6; text-decoration: none;">
                                حفظ والتالي ←
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <!-- Letter Preview -->
                <div class="decision-card" style="background: #f9fafb;">
                    <h3 style="font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">معاينة الخطاب</h3>
                    <div style="background: white; padding: 2rem; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div style="text-align: center; margin-bottom: 1.5rem;">
                            <h2 style="font-size: 1.25rem; font-weight: 700;">خطاب ضمان بنكي</h2>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb; width: 30%; font-weight: 600;">رقم الضمان</td>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->guaranteeNumber ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb; font-weight: 600;">رقم العقد</td>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->contractNumber ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb; font-weight: 600;">المورد</td>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb;" id="previewSupplier"><?= htmlspecialchars($currentRecord->supplierDisplayName ?? $currentRecord->rawSupplierName ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb; font-weight: 600;">البنك</td>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb;" id="previewBank"><?= htmlspecialchars($currentRecord->bankDisplay ?? $currentRecord->rawBankName ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb; font-weight: 600;">المبلغ</td>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb;"><?= number_format((float)($currentRecord->amount ?? 0), 2) ?> ر.س</td>
                            </tr>
                            <tr>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb; font-weight: 600;">تاريخ الانتهاء</td>
                                <td style="padding: 0.5rem; border: 1px solid #e5e7eb;"><?= htmlspecialchars($currentRecord->expiryDate ?? '-') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </main>

    <!-- Minimal JavaScript for Autocomplete -->
    <script>
    (function() {
        const suppliers = <?= $suppliersJson ?>;
        const banks = <?= $banksJson ?>;
        const recordId = <?= $currentRecord?->id ?? 'null' ?>;
        const nextUrl = <?= $hasNext ? '"' . $buildUrl($nextId) . '"' : 'null' ?>;

        // Autocomplete Setup
        function setupAutocomplete(inputId, suggestionsId, hiddenId, data, nameKey, previewId) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);
            const hidden = document.getElementById(hiddenId);
            const preview = document.getElementById(previewId);
            
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
                    `<li class="suggestion-item" data-id="${item.id}" data-name="${item[nameKey]}">${item[nameKey]}</li>`
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
                    if (preview) preview.textContent = name;
                    suggestions.classList.remove('open');
                }
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.classList.remove('open');
                }
            });
        }

        setupAutocomplete('supplierInput', 'supplierSuggestions', 'supplierId', suppliers, 'official_name', 'previewSupplier');
        setupAutocomplete('bankInput', 'bankSuggestions', 'bankId', banks, 'official_name', 'previewBank');

        // Form Submit (AJAX)
        const form = document.getElementById('decisionForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
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
    })();
    </script>
</body>
</html>
