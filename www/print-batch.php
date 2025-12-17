<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;

// Prevent browser caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$importSessionRepo = new \App\Repositories\ImportSessionRepository();
$records = new ImportedRecordRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();

// Get Session ID and Filter
$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 1000;

if (!$sessionId) {
    die("Error: Session ID is required.");
}

// Fetch all records for this session
$allRecords = $records->allBySession($sessionId);
// Filter for Approved/Ready only
$approvedRecords = array_filter($allRecords, function($r) {
    return in_array($r->matchStatus, ['ready', 'approved']);
});

if (empty($approvedRecords)) {
    die("No approved records found for this session.");
}

// Fetch Dictionaries for Lookup
$allBanks = $banks->allNormalized();
$allSuppliers = $suppliers->allNormalized();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة الكل - جلسة <?= $sessionId ?></title>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة الكل - جلسة <?= $sessionId ?></title>
    
    <!-- Exact Dependencies from decision.php -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/letter.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Print batch specifics */
        body { margin: 0; background: #cccccc; }
        .print-container { padding: 20px; display: flex; flex-direction: column; align-items: center; }
        
        /* Ensure distinct pages */
        .letter-preview {
             background: transparent; 
             padding: 0; 
             width: auto; 
             margin-bottom: 20px;
        }
        
        .letter-preview .letter-paper {
            margin: 0;
            page-break-after: always;
        }
        
        .letter-preview:last-child .letter-paper {
            page-break-after: auto;
        }

        @media print {
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-container { padding: 0; display: block; }
            .no-print { display: none !important; }
            .letter-preview { margin: 0; }
            .letter-preview .letter-paper { box-shadow: none; border: none; margin: 0; width: 100%; min-height: 297mm; }
        }

        <?php
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
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="position: fixed; top: 20px; left: 20px; z-index: 9999;">
        <button onclick="window.print()" style="padding: 12px 24px; font-size: 16px; background: #000; color: #fff; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">🖨️ طباعة الكل (<?= count($approvedRecords) ?> خطاب)</button>
    </div>

    <div class="print-container">
        <?php foreach ($approvedRecords as $record): 
            // Data Prep Logic
            $supplierName = $record->supplierDisplayName ?? $record->rawSupplierName;
            if (empty($record->supplierDisplayName) && !empty($record->supplierId)) {
                 foreach ($allSuppliers as $s) {
                     if ($s['id'] == $record->supplierId) {
                         $supplierName = $s['official_name']; break;
                     }
                 }
            }
            
            $bankName = $record->bankDisplay ?? $record->rawBankName;
            $bankDetails = array_values(array_filter($allBanks, fn($b) => $b['id'] == $record->bankId))[0] ?? null;
            if (!$bankDetails && !empty($record->bankId)) {
                 foreach($allBanks as $b) {
                     if ($b['id'] == $record->bankId) {
                         $bankDetails = $b; break;
                     }
                 }
            }
    
            // Bank Address Ops
            $bankDept = $bankDetails['department'] ?? 'إدارة الضمانات';
            $bankAddress = array_filter([
                $bankDetails['address_line_1'] ?? 'المقر الرئيسي',
                $bankDetails['address_line_2'] ?? null,
            ]);
            $bankEmail = $bankDetails['contact_email'] ?? null;
            
            // Formatting
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
            
            // Font Logic
            $hasArabic = preg_match('/\p{Arabic}/u', $supplierName);
            $isEnglish = ($hasArabic === 0);
            $supplierStyle = $isEnglish ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;" : "";
    
            // Renewal Date Logic
            $renewalDate = '-';
            if ($record->expiryDate) {
                 try {
                    $d = new DateTime($record->expiryDate);
                    $d->modify('+1 year');
                     $renewalDate = $formatDateHindi($d->format('Y-m-d')) . 'م';
                 } catch(Exception $e) {}
            }

            // Watermark Logic
            $hasSupplier = !empty($record->supplierId);
            $hasBank = !empty($record->bankId);
            $watermarkText = ($hasSupplier && $hasBank) ? 'جاهز' : 'يحتاج قرار';
            $watermarkClass = ($hasSupplier && $hasBank) ? 'status-ready' : 'status-draft';
        ?>
        
        <!-- Exact Structure from decision.php -->
        <div class="letter-preview">
            <div class="letter-paper">
                
                <!-- Watermark -->
                <div class="watermark <?= $watermarkClass ?>"><?= $watermarkText ?></div>
                
                <div class="header-line">
                  <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">السادة / <span><?= htmlspecialchars($bankName) ?></span></div>
                  <div class="greeting">المحترمين</div>
                </div>

                <div>
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
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
