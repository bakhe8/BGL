<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportedRecord;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Repositories\LearningLogRepository;
use App\Repositories\ImportBatchRepository;
use App\Adapters\GuaranteeDataAdapter;
use App\Services\ExcelColumnDetector;
use App\Services\CandidateService;
use App\Services\AutoAcceptService;
use App\Services\MatchingService;
use App\Services\ConflictDetector;
use App\Support\XlsxReader;
use RuntimeException;

/**
 * Import Service
 * 
 * خدمة استيراد ملفات Excel إلى قاعدة البيانات
 * 
 * الوظائف الرئيسية:
 * - قراءة ملفات Excel ومعالجة الصفوف
 * - الكشف التلقائي عن أعمدة البيانات (supplier, bank, amount, etc.)
 * - مطابقة الموردين والبنوك تلقائياً
 * - إنشاء سجلات ImportedRecord (يكتب للجدولين: القديم والجديد)
 * - تطبيق القبول التلقائي للتطابقات العالية
 * 
 * الحدود:
 * - الحد الأقصى للصفوف: 500 (للأداء)
 * - يتم تخطي الصفوف الناقصة البيانات
 * 
 * @package App\Services
 * @see docs/08-Import-Debug.md للتوثيق الكامل
 */
class ImportService
{
    public function __construct(
        private ImportSessionRepository $sessions,
        private ImportedRecordRepository $records,
        private XlsxReader $xlsxReader = new XlsxReader(),
        private ExcelColumnDetector $detector = new ExcelColumnDetector(),
        private ?MatchingService $matchingService = null,
        private ?CandidateService $candidateService = null,
        private ?AutoAcceptService $autoAcceptService = null,
        private ?ConflictDetector $conflictDetector = null,
        private ?LearningLogRepository $learningLog = null,
        private ?ImportBatchRepository $batchRepo = null,
        private ?GuaranteeDataAdapter $adapter = null,
    ) {
        $this->matchingService ??= new MatchingService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
            new \App\Repositories\BankRepository(),
        );
        $this->candidateService ??= new CandidateService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
            new \App\Support\Normalizer(),
            new \App\Repositories\BankRepository(),
        );
        $this->autoAcceptService ??= new AutoAcceptService($this->records);
        $this->conflictDetector ??= new ConflictDetector();
        $this->learningLog ??= new LearningLogRepository();
        $this->batchRepo ??= new ImportBatchRepository();
        $this->adapter ??= new GuaranteeDataAdapter();
    }

    /**
     * @return array{session_id:int, records_count:int, skipped:array}
     */
    public function importExcel(string $filePath): array
    {
        // Create OLD session (for compatibility)
        $session = $this->sessions->create('excel');
        
        // التحقق من إنشاء الجلسة بنجاح
        if (!$session || !$session->id) {
            throw new RuntimeException('فشل إنشاء جلسة الاستيراد. يرجى المحاولة مرة أخرى.');
        }
        
        // Create NEW batch (dual-write)
        $batchId = $this->batchRepo->create([
            'batch_type' => 'excel_import',
            'filename' => basename($filePath),
            'description' => 'استيراد Excel - ' . date('Y-m-d H:i')
        ]);

        $rows = $this->xlsxReader->read($filePath);
        
        // التحقق من الحد الأقصى للصفوف (الأداء)
        // تم الرفع إلى 500 بناءً على طلب المستخدم (كان 100)
        if (count($rows) > 500) {
            throw new RuntimeException(
                sprintf('عفواً، الملف يحتوي على %d صفاً. الحد الأقصى المسموح به هو 500 صف لضمان استقرار النظام.', count($rows))
            );
        }

        if (count($rows) < 2) {
            throw new RuntimeException('لم يتم العثور على صفوف صالحة في الملف.');
        }

        // Smart Header Detection: جرب أول 5 صفوف حتى نجد الرؤوس الصحيحة
        $headerRowIndex = 0;
        $map = [];
        $maxAttempts = min(5, count($rows));

        for ($i = 0; $i < $maxAttempts; $i++) {
            $testMap = $this->detector->detect($rows[$i]);

            // إذا وجدنا supplier و bank → هذه هي الرؤوس!
            if (isset($testMap['supplier']) && isset($testMap['bank'])) {
                $headerRowIndex = $i;
                $map = $testMap;
                break;
            }
        }

        // التحقق من الأعمدة الأساسية
        if (!isset($map['supplier']) || !isset($map['bank'])) {
            throw new RuntimeException('⚠️ لم نستطع التعرف على عمود اسم المورد أو عمود البنك. يرجى التأكد من أن ملف Excel يحتوي على الأعمدة المطلوبة بصيغة معروفة.');
        }

        // البيانات تبدأ من بعد صف الرؤوس
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        // تسريع عمليات SQLite: تجميع الإدخالات في معاملة واحدة
        $pdo = \App\Support\Database::connection();
        
        try {
            $pdo->beginTransaction();
            $count = 0;
            $skipped = [];
            $rowIndex = $headerRowIndex + 1; // Start counting from header position + 1
            $firstRecordId = null;  // Track first record for navigation

            foreach ($dataRows as $row) {
                $rowIndex++;
                $supplier = $this->colValue($row, $map['supplier'] ?? null);
                $bank = $this->colValue($row, $map['bank'] ?? null);
                $amount = $this->normalizeAmount($this->colValue($row, $map['amount'] ?? null));
                $guarantee = $this->colValue($row, $map['guarantee'] ?? null);
                $contractVal = $this->colValue($row, $map['contract'] ?? null);
                $poVal = $this->colValue($row, $map['po'] ?? null);
                $relatedTo = null;
                $finalContract = null;
                if ($contractVal !== '') {
                    $finalContract = $contractVal;
                    $relatedTo = 'contract';
                } elseif ($poVal !== '') {
                    $finalContract = $poVal;
                    $relatedTo = 'purchase_order';
                }
                // Fallback: ensure relatedTo is never NULL
                if (!$relatedTo) {
                    $relatedTo = 'purchase_order'; // Default fallback
                }
                $typeRaw = $this->colValue($row, $map['type'] ?? null);
                $typeVal = trim($typeRaw) !== '' ? trim($typeRaw) : null;
                $commentVal = $this->colValue($row, $map['comment'] ?? null);
                $expiry = $this->normalizeDate($this->colValue($row, $map['expiry'] ?? null));
                $issue = $this->colValue($row, $map['issue'] ?? null);

                // تخطي السطر إذا نقصت البيانات
                $hasSupplierAndBank = ($supplier !== '' && $bank !== '');
                $hasContract = $finalContract !== null && $finalContract !== '';
                $hasGuarantee = $guarantee !== null && $guarantee !== '';
                $hasAmount = $amount !== null && $amount !== '';

                if (!$hasSupplierAndBank) {
                    $skipped[] = "الصف #{$rowIndex}: تم تخطيه لعدم وجود اسم مورد وبنك معاً.";
                    continue;
                }
                if (!$hasContract) {
                    $skipped[] = "الصف #{$rowIndex}: تم تخطيه لعدم وجود رقم عقد أو أمر شراء.";
                    continue;
                }
                if (!$hasGuarantee) {
                    $skipped[] = "الصف #{$rowIndex}: تم تخطيه لعدم وجود رقم ضمان.";
                    continue;
                }
                if (!$hasAmount) {
                    $skipped[] = "الصف #{$rowIndex}: تم تخطيه لعدم وجود مبلغ صالح.";
                    continue;
                }

                $match = $this->matchingService->matchSupplier($supplier);
                $bankMatch = $this->matchingService->matchBank($bank);
                $bankDisplay = $bankMatch['final_name'] ?? null;

                // Fix: Status Override logic respecting 'needs_review'
                $status = $match['match_status'];

                if (empty($match['supplier_id']) || empty($bankMatch['bank_id'])) {
                    $status = 'needs_review';
                }

                $candidates = $this->candidateService->supplierCandidates($supplier)['candidates'] ?? [];
                $bankCandidatesArr = $this->candidateService->bankCandidates($bank)['candidates'] ?? [];
                $bankCandidates = ['normalized' => $bankMatch['normalized'] ?? null, 'candidates' => $bankCandidatesArr];
                $conflicts = $this->conflictDetector->detect(
                    ['supplier' => ['candidates' => $candidates, 'normalized' => $match['normalized'] ?? ''], 'bank' => $bankCandidates],
                    ['raw_supplier_name' => $supplier, 'raw_bank_name' => $bank]
                );

                // Prepare data for adapter (dual-write)
                $recordData = [
                    'guarantee_number' => $guarantee ?: null,
                    'raw_supplier_name' => (string) $supplier,
                    'raw_bank_name' => (string) $bank,
                    'contract_number' => $finalContract,
                    'amount' => $amount ?: null,
                    'issue_date' => $issue ?: null,
                    'expiry_date' => $expiry ?: null,
                    'type' => $typeVal ?: null,
                    'comment' => $commentVal ?: null,
                    'supplier_id' => $match['supplier_id'] ?? null,
                    'bank_id' => $bankMatch['bank_id'] ?? null,
                    'supplier_display_name' => null,
                    'bank_display' => $bankDisplay,
                    'match_status' => $status,
                    'normalized_supplier' => $match['normalized'] ?? null,
                    'normalized_bank' => $bankMatch['normalized'] ?? null,
                    'related_to' => $relatedTo,
                    'import_type' => 'excel',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Use adapter to write to both old and new tables
                $ids = $this->adapter->createGuarantee($recordData, $session->id, $batchId);
                
                // Track first record ID for navigation
                if ($firstRecordId === null) {
                    $firstRecordId = $ids['old_id'];
                }
                
                // For compatibility, get the old record for auto-accept
                $record = $this->records->find($ids['old_id']);

                $this->autoAcceptService->tryAutoAccept($record, $candidates, $conflicts);
                $this->autoAcceptService->tryAutoAcceptBank($record, $bankCandidatesArr, $conflicts);

                // تسجيل التعلم المؤرَّخ للمورد والبنك (للسجلات الجديدة فقط)
                $this->learningLog->create([
                    'raw_input' => (string) $supplier,
                    'normalized_input' => $match['normalized'] ?? '',
                    'suggested_supplier_id' => $match['supplier_id'] ?? null,
                    'decision_result' => $status,
                    'candidate_source' => 'import',
                    'score' => null,
                    'score_raw' => null,
                    'created_at' => date('c'),
                ]);
                $this->learningLog->createBank([
                    'raw_input' => (string) $bank,
                    'normalized_input' => $bankMatch['normalized'] ?? '',
                    'suggested_bank_id' => $bankMatch['bank_id'] ?? null,
                    'decision_result' => $status,
                    'candidate_source' => 'import',
                    'score' => null,
                    'score_raw' => null,
                    'created_at' => date('c'),
                ]);
                $count++;
            }
            $pdo->commit();
            // تحديث عدد السجلات دفعة واحدة بدلاً من كل صف
            $this->sessions->incrementRecordCount($session->id ?? 0, $count);
            $this->batchRepo->incrementRecordCount($batchId, $count);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'session_id' => $session->id,
            'first_record_id' => $firstRecordId,  // Add first record for navigation
            'records_count' => $count,
            'skipped' => $skipped,
            'debug_map' => $map,
            'total_rows' => count($rows),
        ];
    }

    private function colValue(array $row, $index): string
    {
        if ($index === null) {
            return '';
        }
        if (is_array($index)) {
            foreach ($index as $i) {
                if (isset($row[$i]) && trim((string) $row[$i]) !== '') {
                    return (string) $row[$i];
                }
            }
            return '';
        }
        return isset($row[$index]) ? (string) $row[$index] : '';
    }

    private function normalizeAmount(string $amount): ?string
    {
        if (trim($amount) === '') {
            return null;
        }

        // Check for European Format (e.g., 1.234,56)
        // Heuristic: If comma is the last separator, it's likely decimal
        $lastComma = strrpos($amount, ',');
        $lastDot = strrpos($amount, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            // Likely European: swap dot and comma for standard processing
            // Remove dots (thousands)
            $amount = str_replace('.', '', $amount);
            // Replace comma with dot
            $amount = str_replace(',', '.', $amount);
        }

        // إزالة أي رموز غير رقمية مع الحفاظ على العلامة العشرية (التي أصبحت نقطة الآن)
        $clean = preg_replace('/[^\d\.\-]/', '', $amount);
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        $num = (float) $clean;
        // تنسيق بقيمتين عشريتين لتجنب ضجيج الفواصل العائمة
        return number_format($num, 2, '.', '');
    }

    private function normalizeDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        // محاولة تحويل النص إلى تاريخ ISO
        $ts = strtotime($value);
        if ($ts === false) {
            // Try Excel serial date if numeric
            if (is_numeric($value)) {
                $unixDate = ($value - 25569) * 86400;
                return gmdate('Y-m-d', (int) $unixDate);
            }
            return $value; // نعيده كما هو، سيتم مراجعته لاحقًا
        }
        return date('Y-m-d', $ts);
    }
}
