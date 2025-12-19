<?php
require_once __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;

// Get Record ID or Guarantee Number
$recordId = $_GET['id'] ?? null;
$guaranteeNo = $_GET['guarantee_no'] ?? null;

if (!$recordId && !$guaranteeNo) {
    die("معرف السجل أو رقم الضمان مفقود.");
}

// Connect to Database
$db = Database::connect();

// Fetch Record
if ($recordId) {
    $stmt = $db->prepare("SELECT * FROM imported_records WHERE id = :id");
    $stmt->execute([':id' => $recordId]);
} else {
    $stmt = $db->prepare("
        SELECT * FROM imported_records 
        WHERE guarantee_number = :g_no 
        ORDER BY session_id DESC, id DESC 
        LIMIT 1
    ");
    $stmt->execute([':g_no' => $guaranteeNo]);
}

$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("لم يتم العثور على سجل لهذا الضمان.");
}

// Prepare Data for Display
$supplierName = $record['supplier_display_name'] ?? $record['raw_supplier_name'];
$bankName = $record['bank_display'] ?? $record['raw_bank_name'];
$guaranteeNo = $record['guarantee_number'];
$amount = number_format((float)($record['amount'] ?? 0), 2);
$contractNo = $record['contract_number'] ?? '-';

// Hindi Numbers Helper
$hindiDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
function toHindi($str, $hindiDigits) {
    return preg_replace_callback('/[0-9]/', fn($m) => $hindiDigits[$m[0]], $str);
}

$amountHindi = toHindi($amount, $hindiDigits);
$guaranteeNoHindi = toHindi($guaranteeNo, $hindiDigits);
$contractNoHindi = toHindi($contractNo, $hindiDigits);

// Fetch proper bank details if available (Optional enhancement)
$bankStmt = $db->prepare("SELECT * FROM banks WHERE official_name LIKE :name OR normalized_key LIKE :name LIMIT 1");
$bankStmt->execute([':name' => '%' . $bankName . '%']);
$bankDetails = $bankStmt->fetch(PDO::FETCH_ASSOC);

$bankDept = $bankDetails['department'] ?? 'إدارة الضمانات';
$bankAddress1 = $bankDetails['address_line_1'] ?? 'المقر الرئيسي';
$bankAddress2 = $bankDetails['address_line_2'] ?? '';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>خطاب إفراج - <?= htmlspecialchars($guaranteeNo) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/letter.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f3f4f6; padding: 20px; font-family: 'Tajawal', sans-serif; }
        .letter-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 0; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        @media print {
            body { background: white; padding: 0; }
            .letter-container { box-shadow: none; margin: 0; width: 100%; max-width: none; }
            .no-print { display: none !important; }
        }
        
        /* Reuse letter styles from decision page */
        .watermark.release {
            color: rgba(220, 38, 38, 0.08); /* Red tint for Release */
            border-color: rgba(220, 38, 38, 0.2);
            transform: rotate(-30deg) scale(1.1);
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 8rem;
            font-weight: 900;
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
        }
        
        .letter-content {
            position: relative;
            z-index: 1;
            padding: 1.5in 1in 1in 1in; /* Standard margins */
            font-size: 14pt;
            line-height: 1.8;
            color: #000;
            text-align: justify;
        }

        .fw-800-sharp { font-weight: 800; }
        .header-line { margin-bottom: 20px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold;">🖨️ طباعة الخطاب</button>
        <button onclick="window.close()" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px;">إغلاق</button>
    </div>

    <div class="letter-container">
        <div class="letter-content">
            
            <div class="watermark release">إفراج</div>

            <!-- Header -->
            <div class="header-line">
                <div class="fw-800-sharp">السادة / <?= htmlspecialchars($bankDetails['official_name'] ?? $bankName) ?></div>
                <div class="greeting">المحترمين</div>
            </div>

            <div style="margin-bottom: 30px;">
                <div class="fw-800-sharp"><?= htmlspecialchars($bankDept) ?></div>
                <?php if($bankAddress1): ?><div><?= htmlspecialchars(toHindi($bankAddress1, $hindiDigits)) ?></div><?php endif; ?>
                <?php if($bankAddress2): ?><div><?= htmlspecialchars(toHindi($bankAddress2, $hindiDigits)) ?></div><?php endif; ?>
            </div>

            <div style="text-align: right; margin: 15px 0;">السَّلام عليكُم ورحمَة الله وبركاتِه</div>

            <!-- Subject -->
            <div style="display: flex; gap: 10px; font-weight: bold; margin-bottom: 20px; text-decoration: underline; text-underline-offset: 5px;">
                <span style="flex: 0 0 70px;">الموضوع:</span>
                <span>
                    إفراج عن الضمان البنكي رقم (<?= $guaranteeNoHindi ?>)
                    <?php if ($contractNo !== '-'): ?>
                    والعائد للعقد رقم (<?= $contractNoHindi ?>)
                    <?php endif; ?>
                </span>
            </div>

            <!-- Body -->
            <div style="text-indent: 50px; margin-bottom: 20px;">
                إشارة إلى الضمان البنكي الموضح أعلاه، والصادر منكم لصالحنا على حساب 
                <strong><?= htmlspecialchars($supplierName) ?></strong> 
                بمبلغ قدره (<strong><?= $amountHindi ?></strong>) ريال.
            </div>

            <div style="text-indent: 50px; margin-bottom: 20px;">
                نحيطكم علماً بانتهاء الغرض من الضمان المذكور، وعليه نأمل منكم <strong>الإفراج عن الضمان</strong> وإلغاء قيده من سجلاتكم وإعادته لنا.
            </div>

            <div style="text-indent: 50px; margin-bottom: 40px;">
                ولكم جزيل الشكر،،،
            </div>

            <!-- Signature -->
            <div class="fw-800-sharp" style="text-align: center; margin-top: 60px; margin-right: 250px;">
                <div style="margin-bottom: 50px;">مُدير الإدارة العامَّة للعمليَّات المحاسبيَّة</div>
                <div>سَامِي بن عبَّاس الفايز</div>
            </div>

            <!-- Footer Code -->
            <div style="position: absolute; left: 0; bottom: 0; font-size: 9pt; display: flex; gap: 20px; color: #666;">
                <span>MBC:09-2</span>
                <span>BAMZ</span>
            </div>

        </div>
    </div>

</body>
</html>
