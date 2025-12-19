<?php
require_once __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;
use App\Models\ImportedRecord;

// Get Record ID
$recordId = $_GET['id'] ?? null;

if (!$recordId) {
    die("معرف السجل مفقود.");
}

// Connect
$db = Database::connect();

// Fetch Record
$stmt = $db->prepare("SELECT * FROM imported_records WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $recordId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("السجل غير موجود.");
}

// Helper to hydrate object (simplified version of Repository map)
// We need this because the view logic expects an object or array with specific keys.
// Let's use an object to match decision-page logic style
$record = (object) [
    'id' => $row['id'],
    'supplierId' => $row['supplier_id'],
    'bankId' => $row['bank_id'],
    'rawSupplierName' => $row['raw_supplier_name'], // Fixed
    'rawBankName' => $row['raw_bank_name'], // Fixed
    'guaranteeNumber' => $row['guarantee_number'],
    'contractNumber' => $row['contract_number'],
    'amount' => $row['amount'],
    'expiryDate' => $row['expiry_date'],
    'type' => $row['type'],
    'matchStatus' => $row['match_status'] ?? 'pending',
    'supplierDisplayName' => null, // Will fetch
    'bankDisplay' => null // Will fetch
];

// Fetch Supplier Details
$supplierName = $record->rawSupplierName;
if ($record->supplierId) {
    $supStmt = $db->prepare("SELECT official_name FROM suppliers WHERE id = :id");
    $supStmt->execute([':id' => $record->supplierId]);
    $sup = $supStmt->fetch(PDO::FETCH_COLUMN);
    if ($sup) {
        $record->supplierDisplayName = $sup;
        $supplierName = $sup;
    }
}

// Fetch Bank Details
$bankName = $record->rawBankName;
$bankDetails = null;

if ($record->bankId) {
    $bankStmt = $db->prepare("SELECT * FROM banks WHERE id = :id");
    $bankStmt->execute([':id' => $record->bankId]);
    $bankDetails = $bankStmt->fetch(PDO::FETCH_ASSOC);
    if ($bankDetails) {
        $bankName = $bankDetails['official_name'];
        $record->bankDisplay = $bankName;
    }
}

// --- Helpers ---
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

// --- Prepare Data for View ---
$bankDept = $bankDetails['department'] ?? 'إدارة الضمانات';
$bankAddress = array_filter([
    $bankDetails['address_line_1'] ?? 'المقر الرئيسي',
    $bankDetails['address_line_2'] ?? null,
]);
$bankEmail = $bankDetails['contact_email'] ?? null;

$guaranteeNo = $record->guaranteeNumber ?? '-';
$contractNo = $record->contractNumber ?? '-';
$amountVal = number_format((float)($record->amount ?? 0), 2);
$amountHindi = $toHindi($amountVal);

$guaranteeDesc = 'خطاب ضمان';
if ($record->type) {
    $t = strtoupper($record->type);
    if ($t === 'FINAL') $guaranteeDesc = 'الضمان البنكي النهائي';
    elseif ($t === 'ADVANCED') $guaranteeDesc = 'ضمان الدفعة المقدمة البنكي';
}

$hasArabic = preg_match('/\p{Arabic}/u', $supplierName ?? ''); // Added null safety
$supplierStyle = ($hasArabic === 0) ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;" : "";

$renewalDate = '-';
if ($record->expiryDate) {
     try {
        $d = new DateTime($record->expiryDate);
        $d->modify('+1 year');
         $renewalDate = $formatDateHindi($d->format('Y-m-d')) . 'م';
     } catch(Exception $e) {}
}

$hasSupplier = !empty($record->supplierId);
$hasBank = !empty($record->bankId);
$watermarkText = ($hasSupplier && $hasBank) ? 'جاهز' : 'يحتاج قرار';
$watermarkClass = ($hasSupplier && $hasBank) ? 'status-ready' : 'status-draft';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة خطاب - <?= htmlspecialchars($guaranteeNo) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/letter.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { margin: 0; background: #525659; font-family: 'Tajawal', sans-serif; }
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
            width: 210mm !important;
            height: 297mm !important;
            margin: 0;
            background: white;
        }
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .print-wrapper { display: block; padding: 0; }
            .no-print { display: none !important; }
            .letter-preview { margin: 0; width: 100% !important; }
            .letter-paper { box-shadow: none; border: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-lg shadow-lg font-bold hover:bg-blue-700 transition-colors flex items-center gap-2">
            <span>🖨️</span> طباعة الخطاب
        </button>
        <button onclick="window.close()" class="bg-gray-600 text-white px-6 py-3 rounded-lg shadow-lg font-bold hover:bg-gray-700 transition-colors flex items-center gap-2 mt-2">
            إغلاق
        </button>
    </div>

    <div class="print-wrapper">
        <div class="letter-preview">
            <div class="letter-paper">
                <div class="watermark <?= $watermarkClass ?>"> <?= $watermarkText ?></div>
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
            </div>
        </div>
    </div>
</body>
</html>
