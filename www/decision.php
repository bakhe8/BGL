<?php
/**
 * Decision Page - PHP Version (Exact Match to Original)
 * 
 * صفحة اتخاذ القرار - نسخة طبق الأصل من decision.html
 * PHP يعرض البيانات، JavaScript للـ Autocomplete والتفاعل
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

// Prevent browser caching to ensure fresh data (especially after adding suppliers/banks)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\ImportSessionRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;

// Dependencies
$importSessionRepo = new \App\Repositories\ImportSessionRepository();
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

// Get all suppliers and banks for autocomplete & lookups
$allSuppliers = $suppliers->allNormalized();
$allBanks = $banks->allNormalized();

// Get candidates for current record
$supplierCandidates = [];
$bankCandidates = [];
if ($currentRecord) {
    $supplierResult = $candidateService->supplierCandidates($currentRecord->rawSupplierName ?? '');
    $supplierCandidates = $supplierResult['candidates'] ?? [];
    $bankResult = $candidateService->bankCandidates($currentRecord->rawBankName ?? '');
    $bankCandidates = $bankResult['candidates'] ?? [];
    
    // ═══════════════════════════════════════════════════════════════════
    // CURRENT SELECTION INDICATOR (ADDED 2025-12-17): Show what's selected
    // ═══════════════════════════════════════════════════════════════════
    if (!empty($currentRecord->supplierId) && !empty($currentRecord->supplierDisplayName)) {
        $selectionBadge = 'الاختيار الحالي';
        $selectionSource = 'dictionary';
        if (!empty($currentRecord->rawSupplierName)) {
            $learned = $supplierLearning->findByNormalized(
                $normalizer->normalizeSupplierName($currentRecord->rawSupplierName)
            );
            if ($learned && $learned['linked_supplier_id'] == $currentRecord->supplierId) {
                $selectionSource = 'learning';
                $selectionBadge = 'من التعلم';
            }
        }
        array_unshift($supplierCandidates, [
            'supplier_id' => $currentRecord->supplierId,
            'name' => $currentRecord->supplierDisplayName,
            'is_current_selection' => true,
            'selection_badge' => $selectionBadge,
            'star_rating' => 3,
            'score' => 1.0,
            'score_raw' => 1.0,
        ]);
    }
    
    // Ensure Display Names are populated if ID exists
    if (!empty($currentRecord->supplierId) && empty($currentRecord->supplierDisplayName)) {
        foreach ($allSuppliers as $s) {
            if ($s['id'] == $currentRecord->supplierId) {
                $currentRecord->supplierDisplayName = $s['official_name'];
                break;
            }
        }
    }
    if (!empty($currentRecord->bankId) && empty($currentRecord->bankDisplay)) {
        foreach ($allBanks as $b) {
            if ($b['id'] == $currentRecord->bankId) {
                $currentRecord->bankDisplay = $b['official_name'];
                break;
            }
        }
    }

    // Auto-select 100% match candidates if not already linked (Threshold 0.99)
    if (empty($currentRecord->supplierId) && !empty($supplierCandidates)) {
        $bestSupplier = $supplierCandidates[0];
        $score = $bestSupplier['score_raw'] ?? $bestSupplier['score'] ?? 0;
        if ($score >= 0.99) {
            $currentRecord->supplierId = $bestSupplier['supplier_id'];
            $currentRecord->supplierDisplayName = $bestSupplier['name'];
        }
    }
    if (empty($currentRecord->bankId) && !empty($bankCandidates)) {
        $bestBank = $bankCandidates[0];
        $score = $bestBank['score_raw'] ?? $bestBank['score'] ?? 0;
        if ($score >= 0.99) {
            $currentRecord->bankId = $bestBank['bank_id'];
            $currentRecord->bankDisplay = $bestBank['name'];
        }
    }
}

// Build query string for navigation
$buildUrl = function($newRecordId = null, $newFilter = null, $newSessionId = null) use ($sessionId, $filter) {
    $params = [];
    $params['session_id'] = $newSessionId ?? $sessionId;
    if ($newRecordId) $params['record_id'] = $newRecordId;
    $params['filter'] = $newFilter ?? $filter;
    return '/decision.php?' . http_build_query($params);
};

// =================================================================================
// BATCH PRINT MODE - INTEGRATED (No Separate File)
// =================================================================================
if (isset($_GET['print_batch']) && $_GET['print_batch'] == '1') {
    // Determine records to print (Approved/Ready only)
    $approvedRecords = array_filter($allRecords, fn($r) => in_array($r->matchStatus, ['ready', 'approved']));
    
    // Helpers
    $hindiDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $toHindi = fn($str) => preg_replace_callback('/[0-9]/', fn($m) => $hindiDigits[$m[0]], strval($str));
    $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $formatDateHindi = function($dateStr) use ($hindiDigits, $months, $toHindi) {
        if (!$dateStr) return '-';
        try {
            $d = new DateTime($dateStr);
            $day = $toHindi($d->format('j'));
            $month = $months[(int)$d->format('n') - 1];
            $year = $toHindi($d->format('Y'));
            return $day . ' ' . $month . ' ' . $year;
        } catch (Exception $e) { return $dateStr; }
    };
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>طباعة الكل - جلسة <?= $sessionId ?></title>
        <!-- Clone Exact Dependencies -->
        <link rel="stylesheet" href="/assets/css/style.css">
        <link rel="stylesheet" href="/assets/css/letter.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { margin: 0; background: #525659; } /* Darker background like PDF viewer */
            .print-wrapper { 
                display: flex; 
                flex-direction: column; 
                align-items: center; 
                padding: 40px 0; 
                min-height: 100vh;
            }
            .letter-preview {
                 background: transparent; 
                 padding: 0; 
                 width: auto; 
                 margin-bottom: 30px;
            }
            .letter-paper { 
                /* Force Exact Dimensions */
                width: 210mm !important;
                height: 297mm !important; /* Fixed height A4 */
                margin: 0;
            }
            @media print {
                /* Reset global visibility since we are in a clean layout */
                body, body * { visibility: visible !important; }
                
                body { background: white; margin: 0; padding: 0; }
                .print-wrapper { display: block; padding: 0; }
                .no-print { display: none !important; }
                
                /* Override letter.css absolute positioning which causes stacking */
                .letter-preview { 
                    position: relative !important; 
                    left: auto !important; 
                    top: auto !important;
                    margin: 0; 
                    page-break-after: always; 
                    width: 100% !important;
                }
                .letter-preview:last-child { page-break-after: auto; }
                .letter-paper { box-shadow: none; border: none; margin: 0; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <button onclick="window.print()" class="bg-black text-white px-6 py-3 rounded-lg shadow-lg font-bold hover:bg-gray-800 transition-colors flex items-center gap-2">
                <span>🖨️</span> طباعة <?= count($approvedRecords) ?> خطاب
            </button>
        </div>

        <div class="print-wrapper">
         <?php foreach ($approvedRecords as $record): 
            // ---------------------------------------------------------
            // RE-USE PRECISE LOGIC FROM MAIN VIEW
            // ---------------------------------------------------------
            $supplierName = $record->supplierDisplayName ?? $record->rawSupplierName;
            // Fallback lookup
            if (empty($record->supplierDisplayName) && !empty($record->supplierId)) {
                $found = array_values(array_filter($allSuppliers, fn($s) => $s['id'] == $record->supplierId))[0] ?? null;
                if ($found) $supplierName = $found['official_name'];
            }
            
            $bankName = $record->bankDisplay ?? $record->rawBankName;
            $bankDetails = null;
            
            // Strong lookup for Bank Details & Name
            if (!empty($record->bankId)) {
                $found = array_values(array_filter($allBanks, fn($b) => $b['id'] == $record->bankId))[0] ?? null;
                if ($found) {
                    $bankDetails = $found;
                    $bankName = $found['official_name']; // FORCE ARABIC NAME
                }
            }

            $bankDept = $bankDetails['department'] ?? 'إدارة الضمانات';
            $bankAddress = array_filter([
                $bankDetails['address_line_1'] ?? 'المقر الرئيسي',
                $bankDetails['address_line_2'] ?? null,
            ]);
            $bankEmail = $bankDetails['contact_email'] ?? null;
            
            $guaranteeNo = $record->guaranteeNumber ?? '-';
            $contractNo = $record->contractNumber ?? '-';
            $amount = number_format((float)($record->amount ?? 0), 2);
            $amountHindi = $toHindi($amount);
            
            $guaranteeDesc = 'خطاب ضمان';
            if ($record->type) {
                $t = strtoupper($record->type);
                if ($t === 'FINAL') $guaranteeDesc = 'الضمان البنكي النهائي';
                elseif ($t === 'ADVANCED') $guaranteeDesc = 'ضمان الدفعة المقدمة البنكي';
            }

            // EXACT Font Logic
            $hasArabic = preg_match('/\p{Arabic}/u', $supplierName);
            $supplierStyle = ($hasArabic === 0) ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;" : "";

            // Renewal Date
            $renewalDate = '-';
            if ($record->expiryDate) {
                 try {
                    $d = new DateTime($record->expiryDate);
                    $d->modify('+1 year');
                     $renewalDate = $formatDateHindi($d->format('Y-m-d')) . 'م';
                 } catch(Exception $e) {}
            }
            
            // Watermark
            $hasSupplier = !empty($record->supplierId);
            $hasBank = !empty($record->bankId);
            $watermarkText = ($hasSupplier && $hasBank) ? 'جاهز' : 'يحتاج قرار';
            $watermarkClass = ($hasSupplier && $hasBank) ? 'status-ready' : 'status-draft';
         ?>
            <div class="letter-preview">
                <div class="letter-paper">
                    <!-- COPY OF LETTER HTML -->
                    <div class="watermark <?= $watermarkClass ?>"><?= $watermarkText ?></div>
                    <div class="header-line">
                        <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">السادة / <span id="letterBank"><?= htmlspecialchars($bankName) ?></span></div>
                        <div class="greeting">المحترمين</div>
                    </div>
                    <div id="letterBankDetails">
                        <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;"><?= htmlspecialchars($bankDept) ?></div>
                        <?php foreach($bankAddress as $line): ?>
                        <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;"><?= $toHindi($line) ?></div>
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
                        <span style="<?= $supplierStyle ?>"><?= htmlspecialchars($supplierName) ?></span> 
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
                    <!-- Footer is part of background SVG -->
                </div>
            </div>
         <?php endforeach; ?>
        </div>
    </body>
    </html>
    <?php
    exit; // STOP RENDERING THE REST OF THE PAGE
}
// =================================================================================

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
                                                            <span><?= $stars ?> <?= htmlspecialchars($cand['name']) ?></span>
                                                        </button>
                                                        <?php
                                                        continue;
                                                    }
                                                    
                                                    // Fuzzy chips: Show only if < 99% AND not selected
                                                    if (($currentRecord->supplierId ?? null) == $cand['supplier_id']) continue;
                                                    if ($score >= 99) continue;
                                                ?>
                                                <button type="button" class="<?= $chipClass ?>"
                                                      data-id="<?= $cand['supplier_id'] ?>"
                                                      data-name="<?= htmlspecialchars($cand['name']) ?>"
                                                      data-type="supplier"
                                                      title="<?= htmlspecialchars($tooltip) ?>">
                                                    <span><?= $stars ?> <?= htmlspecialchars($cand['name']) ?></span>
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
    <script>
    (function() {
        const suppliers = <?= json_encode($allSuppliers) ?>;
        const banks = <?= json_encode($allBanks) ?>;
        const recordId = <?= $currentRecord?->id ?? 'null' ?>;
        const nextUrl = <?= $hasNext ? '"' . $buildUrl($nextId) . '"' : 'null' ?>;

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
            const checkMatch = (val) => {
                // Check if name is in the suppliers list (exact match)
                const exists = suppliers.some(s => s.official_name === val || s.official_name.toLowerCase() === val.toLowerCase());
                if (exists || val.length === 0) {
                    btnAddSupplier.style.display = 'none';
                } else {
                    btnAddSupplier.style.display = 'flex'; // Restore flex display
                    supplierNamePreview.textContent = val;
                }
            };
            
            supplierInput.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                checkMatch(val);
            });

            // 2. Add Action
            btnAddSupplier.addEventListener('click', async () => {
                const name = supplierInput.value.trim();
                if (!name) return;
                
                // Disable button
                const OriginalText = btnAddSupplier.innerHTML;
                btnAddSupplier.disabled = true;
                btnAddSupplier.innerHTML = '⏳ جاري الإضافة...';
                
                try {
                     const res = await fetch('/api/dictionary/suppliers', {
                         method: 'POST',
                         headers: {'Content-Type': 'application/json'},
                         body: JSON.stringify({ official_name: name }) 
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
                          errorDiv.innerHTML = '✓ تمت الإضافة بنجاح';
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

        // Print All Button
        const btnPrintAll = document.getElementById('btnPrintAll');
        if (btnPrintAll) {
             btnPrintAll.addEventListener('click', () => {
                 const urlParams = new URLSearchParams(window.location.search);
                 const sid = urlParams.get('session_id');
                 if (sid) {
                     // Create hidden iframe for printing without leaving page
                     const iframe = document.createElement('iframe');
                     iframe.style.position = 'fixed';
                     iframe.style.right = '0';
                     iframe.style.bottom = '0';
                     iframe.style.width = '0';
                     iframe.style.height = '0';
                     iframe.style.border = '0';
                     iframe.src = '/decision.php?session_id=' + sid + '&print_batch=1';
                     
                     // Helper to clean up
                     iframe.onload = function() {
                         // The iframe has its own window.print() on load. 
                         // We can remove it after a delay or just leave it.
                         setTimeout(() => document.body.removeChild(iframe), 60000);
                     };
                     
                     document.body.appendChild(iframe);
                 } else {
                     alert('لا يوجد رقم جلسة محدد');
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
                        window.location.href = window.location.href; // Force reload with params
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
