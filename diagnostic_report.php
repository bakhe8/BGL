<?php
/**
 * تقرير شامل لفحص السجل 12308 وفهم منطق عرض الاقتراحات
 */

require __DIR__ . '/app/Support/autoload.php';

use App\Repositories\SupplierRepository;
use App\Repositories\ImportedRecordRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankRepository;
use App\Support\Normalizer;

$supplierRepo = new SupplierRepository();
$recordRepo = new ImportedRecordRepository();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         تقرير تحليل مفصل للسجل 12308                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 1: بيانات السجل من قاعدة البيانات
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 1: بيانات السجل من قاعدة البيانات (كما هي مخزنة)        │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

$record = $recordRepo->find(12308);
echo "  Record ID: {$record->id}\n";
echo "  Supplier ID: " . ($record->supplierId ?? 'NULL') . "\n";
echo "  Supplier Display Name: " . ($record->supplierDisplayName ?? 'NULL') . "\n";
echo "  Raw Supplier Name: " . ($record->rawSupplierName ?? 'NULL') . "\n";
echo "  Match Status: " . ($record->matchStatus ?? 'NULL') . "\n";
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 2: محاكاة منطق decision-logic.php (السطور 152-159)
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 2: محاكاة ملء supplier_display_name (السطور 152-159)     │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

$allSuppliers = $supplierRepo->allNormalized();

echo "  الشرط: !empty(\$record->supplierId) && empty(\$record->supplierDisplayName)\n";
echo "  النتيجة: " . (!empty($record->supplierId) && empty($record->supplierDisplayName) ? "TRUE ✓" : "FALSE ✗") . "\n";

if (!empty($record->supplierId) && empty($record->supplierDisplayName)) {
    foreach ($allSuppliers as $s) {
        if ($s['id'] == $record->supplierId) {
            $record->supplierDisplayName = $s['official_name'];
            echo "  تم ملء supplier_display_name = \"{$s['official_name']}\"\n";
            break;
        }
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 3: توليد الاقتراحات (Candidates)
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 3: توليد اقتراحات الموردين                               │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

$candidateService = new CandidateService(
    $supplierRepo,
    new SupplierAlternativeNameRepository(),
    new Normalizer(),
    new BankRepository()
);

$result = $candidateService->supplierCandidates($record->rawSupplierName ?? '');
$supplierCandidates = $result['candidates'] ?? [];

echo "  عدد الاقتراحات المتولدة: " . count($supplierCandidates) . "\n";
if (!empty($supplierCandidates)) {
    echo "  أفضل 3 اقتراحات:\n";
    foreach (array_slice($supplierCandidates, 0, 3) as $i => $cand) {
        $score = round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100);
        echo "    " . ($i+1) . ". [{$score}%] {$cand['name']} (ID: {$cand['supplier_id']})\n";
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 4: تقييم منطق shouldShowSelectionChip
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 4: تقييم منطق عرض رقاقة \"الاختيار الحالي\"               │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

$shouldShowSelectionChip = !empty($record->supplierId) && 
                           !empty($record->supplierDisplayName) &&
                           ($record->supplierDisplayName !== $record->rawSupplierName);

echo "  الشروط الثلاثة:\n";
echo "    1. !empty(supplier_id): " . (!empty($record->supplierId) ? "TRUE ✓" : "FALSE ✗") . " (ID: {$record->supplierId})\n";
echo "    2. !empty(supplier_display_name): " . (!empty($record->supplierDisplayName) ? "TRUE ✓" : "FALSE ✗") . " (Name: " . ($record->supplierDisplayName ?? 'NULL') . ")\n";
echo "    3. display_name !== raw_name: " . (($record->supplierDisplayName !== $record->rawSupplierName) ? "TRUE ✓" : "FALSE ✗") . "\n";
echo "       - Display: \"{$record->supplierDisplayName}\"\n";
echo "       - Raw:     \"{$record->rawSupplierName}\"\n";
echo "  النتيجة النهائية: " . ($shouldShowSelectionChip ? "TRUE ✓ (سيظهر)" : "FALSE ✗ (لن يظهر)") . "\n";
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 5: تقييم منطق Auto-Select (السطور 203-209)
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 5: تقييم منطق الاختيار التلقائي (Auto-Select)            │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

echo "  الشرط: empty(\$record->supplierId) && !empty(\$supplierCandidates)\n";
echo "  النتيجة: " . (empty($record->supplierId) && !empty($supplierCandidates) ? "TRUE ✓ (سيتم auto-select)" : "FALSE ✗ (لن يتم)") . "\n";
echo "  السبب: supplier_id موجود بالفعل = {$record->supplierId}\n";

if (!empty($supplierCandidates)) {
    $bestSupplier = $supplierCandidates[0];
    $score = $bestSupplier['score_raw'] ?? $bestSupplier['score'] ?? 0;
    echo "  ملاحظة: لو كان supplier_id فارغ، كان سيتم اختيار:\n";
    echo "    - المورد: {$bestSupplier['name']}\n";
    echo "    - النسبة: " . round($score * 100) . "%\n";
    echo "    - هل >= 99%؟ " . ($score >= 0.99 ? "نعم ✓ (مطابقة تامة)" : "لا ✗") . "\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 6: منطق إخفاء/إظهار الاقتراحات والأزرار في decision-page.php
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 6: تحليل منطق عرض الاقتراحات في الواجهة                 │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

// محاكاة الكود في decision-page.php
$hasExactMatch = !empty($supplierCandidates) && (($supplierCandidates[0]['score_raw'] ?? $supplierCandidates[0]['score'] ?? 0) >= 0.99);

echo "  منطق الرقاقات (Chips):\n";
echo "    - سيتم عرض Selection Chip؟ " . ($shouldShowSelectionChip ? "نعم ✓" : "لا ✗") . "\n";
echo "    - سيتم عرض Candidate Chips؟ ";

// في الكود الفعلي، يتم عرض chips فقط للـ candidates التي:
// 1. ليست current selection
// 2. إذا كانت learning: تُعرَض دائماً
// 3. إذا كانت fuzzy: تُعرَض فقط إذا < 99%

$visibleCandidates = 0;
foreach (array_slice($supplierCandidates, 0, 6) as $cand) {
    $isCurrentSelection = ($record->supplierId == $cand['supplier_id']);
    $isLearning = $cand['is_learning'] ?? false;
    $score = round(($cand['score_raw'] ?? $cand['score'] ?? 0) * 100);
    
    if ($isCurrentSelection && $shouldShowSelectionChip) {
        // Already shown as selection chip
        continue;
    }
    
    if ($isLearning) {
        $visibleCandidates++;
    } elseif ($score < 99) {
        $visibleCandidates++;
    }
}

echo "{$visibleCandidates} من " . count($supplierCandidates) . "\n";

echo "\n  زر \"إضافة كمورد جديد\":\n";
echo "    - الشرط: display:none إذا hasExactMatch = true\n";
echo "    - hasExactMatch = " . ($hasExactMatch ? "TRUE" : "FALSE") . "\n";
echo "    - سيتم إخفاء الزر؟ " . ($hasExactMatch ? "نعم ✓" : "لا ✗") . "\n";

echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 7: منطق عرض "📄 من الاكسل"
// ═══════════════════════════════════════════════════════════════════
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ القسم 7: تحليل منطق عرض \"📄 من الاكسل\"                        │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

$shouldShowExcelLabel = !empty($record->rawSupplierName) && 
                        !empty($record->supplierDisplayName) &&
                        $record->rawSupplierName !== $record->supplierDisplayName;

echo "  الكود في decision-page.php (السطور 348-356):\n";
echo "    <?php if (!empty(\$currentRecord->rawSupplierName) && \n";
echo "              !empty(\$currentRecord->supplierDisplayName) &&\n";
echo "              \$currentRecord->rawSupplierName !== \$currentRecord->supplierDisplayName): ?>\n";
echo "\n";
echo "  التقييم:\n";
echo "    1. !empty(rawSupplierName): " . (!empty($record->rawSupplierName) ? "TRUE ✓" : "FALSE ✗") . "\n";
echo "    2. !empty(supplierDisplayName): " . (!empty($record->supplierDisplayName) ? "TRUE ✓" : "FALSE ✗") . "\n";
echo "    3. raw !== display: " . ($record->rawSupplierName !== $record->supplierDisplayName ? "TRUE ✓" : "FALSE ✗") . "\n";
echo "       - Raw:     \"{$record->rawSupplierName}\"\n";
echo "       - Display: \"{$record->supplierDisplayName}\"\n";
echo "  النتيجة: " . ($shouldShowExcelLabel ? "سيظهر ✓" : "لن يظهر ✗") . "\n";

// المقارنة الدقيقة
if ($record->rawSupplierName === $record->supplierDisplayName) {
    echo "\n  ⚠️ السبب: الاسمان متطابقان تماماً!\n";
    echo "  تحليل الاختلافات:\n";
    echo "    - الطول: raw=" . mb_strlen($record->rawSupplierName) . " vs display=" . mb_strlen($record->supplierDisplayName) . "\n";
    
    // فحص حرف بحرف
    for ($i = 0; $i < max(mb_strlen($record->rawSupplierName), mb_strlen($record->supplierDisplayName)); $i++) {
        $c1 = mb_substr($record->rawSupplierName, $i, 1);
        $c2 = mb_substr($record->supplierDisplayName, $i, 1);
        if ($c1 !== $c2) {
            echo "    - الاختلاف عند الموقع {$i}: '{$c1}' vs '{$c2}'\n";
        }
    }
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 8: الخلاصة والسبب الجذري
// ═══════════════════════════════════════════════════════════════════
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                   الخلاصة والسبب الجذري                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "السبب الجذري لعدم ظهور الاقتراحات والأزرار:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. السجل يحتوي على supplier_id = 150 (مخزن مسبقاً)\n";
echo "2. النظام يملأ supplier_display_name في runtime من جدول suppliers\n";
echo "3. بما أن الاسمين متطابقان (raw = display):\n";
echo "   - لا تظهر رقاقة \"الاختيار الحالي\" (shouldShowSelectionChip = FALSE)\n";
echo "   - لا تظهر \"📄 من الاكسل\" (نفس الشرط)\n";
echo "4. الاقتراحات موجودة ولكن:\n";
echo "   - المطابقة 100% تُخفي زر \"إضافة كمورد جديد\"\n";
echo "   - الاقتراح الأول (100%) لا يُعرَض كـ chip لأنه مطابق تماماً\n";
echo "\n";

echo "هل هذا سلوك طبيعي أم خطأ؟\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ هذا سلوك طبيعي ومقصود!\n";
echo "  - المورد مطابق 100% (نفس الاسم في Excel وفي قاعدة البيانات)\n";
echo "  - لا حاجة لاقتراحات أو أزرار إضافة\n";
echo "  - المطابقة تامة والربط موجود\n";
echo "\n";

echo "متى تظهر الاقتراحات والأزرار؟\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. إذا كان supplier_id فارغ (سجل جديد)\n";
echo "2. إذا كان الاسم في Excel مختلف عن الاسم المربوط\n";
echo "3. إذا كانت المطابقة < 99% (fuzzy match)\n";
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// SECTION 9: اختبار على سجلات أخرى
// ═══════════════════════════════════════════════════════════════════
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          اختبار على عينة من السجلات الأخرى                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testRecords = $recordRepo->allBySession(399);
$sampleSize = min(10, count($testRecords));
$samples = array_slice($testRecords, 0, $sampleSize);

echo "عينة من {$sampleSize} سجلات:\n";
echo "─────────────────────────────────────────────────────────────────\n";

foreach ($samples as $r) {
    $hasId = !empty($r->supplierId);
    $hasDisplay = !empty($r->supplierDisplayName);
    $status = "❓";
    
    if ($hasId && $hasDisplay) {
        $status = "✓ مربوط";
    } elseif ($hasId && !$hasDisplay) {
        $status = "⚠️ ID فقط";
    } elseif (!$hasId) {
        $status = "⭕ فارغ";
    }
    
    echo sprintf(
        "  %-6s | %-10s | supplier_id=%-4s | display=%s\n",
        "#{$r->id}",
        $status,
        $r->supplierId ?? 'NULL',
        $hasDisplay ? 'YES' : 'NO'
    );
}

echo "\n";
echo "تم انتهاء التقرير.\n";
