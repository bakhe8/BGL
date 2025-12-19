<?php
/**
 * Batch Print View
 * Displays all approved/ready records for printing
 */

// Assume $allRecords, $allSuppliers, $allBanks, and $sessionId are available from decision-logic.php

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
                <!-- Footer is part of background SVG -->
            </div>
        </div>
     <?php endforeach; ?>
    </div>
</body>
</html>
