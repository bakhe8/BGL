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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Amiri:wght@400;700&display=swap');
        
        body {
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
            font-family: 'Cairo', sans-serif;
        }

        .letter-paper {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 0 auto 20px auto;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            padding: 40mm 20mm 20mm 20mm; /* A4 Margins */
            box-sizing: border-box;
            page-break-after: always; /* Critical for batch printing */
        }

        .letter-paper:last-child {
            page-break-after: auto;
        }

        /* Print Specifics */
        @media print {
            body { 
                background: white; 
                padding: 0; 
                margin: 0;
            }
            .letter-paper {
                box-shadow: none;
                border: none;
                margin: 0;
                width: 100%;
                min-height: 297mm;
                page-break-after: always;
            }
            .no-print { display: none !important; }
        }

        /* Typography & Layout from decision.php */
        .header-line { margin-bottom: 25px; font-size: 16pt; line-height: 1.6; color: #000; font-family: 'Times New Roman', Times, serif; }
        .greeting { margin-top: 15px; margin-bottom: 15px; font-weight: bold; text-align: center; font-size: 16pt; }
        .subject-line { font-weight: bold; text-decoration: underline; margin: 25px 0; text-align: center; font-size: 16pt; font-family: 'Times New Roman', Times, serif; }
        .body-text { text-align: justify; line-height: 2.2; margin-bottom: 25px; font-size: 16pt; font-family: 'Times New Roman', Times, serif; font-weight: 500; }
        .fw-800-sharp { font-weight: 900; -webkit-font-smoothing: antialiased; }
        .closing { margin-top: 40px; float: left; text-align: center; font-size: 16pt; font-family: 'Times New Roman', Times, serif; margin-left: 20mm; font-weight: bold; }
        
        /* Helper for Hindi Digits */
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

    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; background: #000; color: #fff; border: none; border-radius: 5px; cursor: pointer;">🖨️ طباعة الكل (<?= count($approvedRecords) ?> خطاب)</button>
    </div>

    <?php foreach ($approvedRecords as $record): 
        // Data Prep Logic (Simulating decision.php)
        
        // 1. Resolve Supplier & Bank Names
        $supplierName = $record->supplierDisplayName ?? $record->rawSupplierName;
        // Search specific specific if missing
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
             // Fallback lookup
             foreach($allBanks as $b) {
                 if ($b['id'] == $record->bankId) {
                     $bankDetails = $b; break;
                 }
             }
        }

        // 2. Bank Address Ops
        $bankDept = $bankDetails['department'] ?? 'إدارة الضمانات';
        $bankAddress = array_filter([
            $bankDetails['address_line_1'] ?? 'المقر الرئيسي',
            $bankDetails['address_line_2'] ?? null,
        ]);
        
        // 3. Formatting
        $guaranteeNo = $record->guaranteeNumber ?? '-';
        $contractNo = $record->contractNumber ?? '-';
        $amount = number_format((float)($record->amount ?? 0), 2);
        
        // 4. English Font Check
        $isEnglish = !preg_match('/[\x{0600}-\x{06FF}]/u', $supplierName);
        $supplierStyle = $isEnglish ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;" : "";

        // 5. Renewal Date Logic
        $renewalDate = '-';
        if ($record->expiryDate) {
             try {
                $d = new DateTime($record->expiryDate);
                $d->modify('+1 year');
                 $renewalDate = $formatDateHindi($d->format('Y-m-d')) . 'م';
             } catch(Exception $e) {}
        }
        
    ?>
    <div class="letter-paper">
        <!-- Header -->
        <div class="header-line">
            <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">السادة / <span><?= htmlspecialchars($bankName) ?></span></div>
            <div class="greeting">المحترمين</div>
        </div>

        <div style="margin-bottom: 20px;">
           <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;"><?= htmlspecialchars($bankDept) ?></div>
           <?php foreach($bankAddress as $line): ?>
           <div><?= $toHindi($line) ?></div>
           <?php endforeach; ?>
        </div>

        <div class="subject-line">الموضوع: تجديد خطاب ضمان رقم (<?= $toHindi($guaranteeNo) ?>)</div>

        <div class="body-text">
            <p>
                السلام عليكم ورحمة الله وبركاته،،،
                <br><br>
                بالإشارة إلى الموضوع أعلاه، وإلى خطاب الضمان رقم <strong>(<?= $toHindi($guaranteeNo) ?>)</strong>
                الصادر من قبلكم لصالح / <strong style="<?= $supplierStyle ?>"><?= htmlspecialchars($supplierName) ?></strong>
                بمبلغ وقدره <strong>(<?= $toHindi($amount) ?>)</strong> ريال سعودي،
                والذي ينتهي في <strong><?= $formatDateHindi($record->expiryDate) ?>م</strong>
                مقابل ضمان العقد رقم <strong>(<?= $toHindi($contractNo) ?>)</strong>.
                <br><br>
                نأمل منكم تجديد خطاب الضمان المذكور أعلاه لمدة سنة أخرى، ليكون تاريخ الانتهاء الجديد هو <strong><?= $renewalDate ?></strong>، وخصم المصاريف البنكية من حسابنا الجاري طرفكم.
            </p>
        </div>

        <div class="closing">
            وتقبلوا خالص تحياتنا،،،
            <br><br><br>
            <strong>شركة مشاريع باخيت العامة المحدودة</strong>
        </div>
    </div>
    <?php endforeach; ?>

</body>
</html>
