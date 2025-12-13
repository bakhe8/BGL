<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportedRecord;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Repositories\LearningLogRepository;
use App\Services\ExcelColumnDetector;
use App\Services\CandidateService;
use App\Services\AutoAcceptService;
use App\Services\MatchingService;
use App\Services\ConflictDetector;
use App\Support\XlsxReader;
use RuntimeException;

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
    }

    /**
     * @return array{session_id:int, records_count:int, skipped:array}
     */
    public function importExcel(string $filePath): array
    {
        $session = $this->sessions->create('excel');

        $rows = $this->xlsxReader->read($filePath);
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
        $pdo->beginTransaction();
        $count = 0;
        $skipped = [];
        $rowIndex = $headerRowIndex + 1; // Start counting from header position + 1

        foreach ($dataRows as $row) {
            $rowIndex++;
            $supplier = $this->colValue($row, $map['supplier'] ?? null);
            $bank = $this->colValue($row, $map['bank'] ?? null);
            $amount = $this->normalizeAmount($this->colValue($row, $map['amount'] ?? null));
            $guarantee = $this->colValue($row, $map['guarantee'] ?? null);
            $contractVal = $this->colValue($row, $map['contract'] ?? null);
            $poVal = $this->colValue($row, $map['po'] ?? null);
            $contractSource = null;
            $finalContract = null;
            if ($contractVal !== '') {
                $finalContract = $contractVal;
                $contractSource = 'contract';
            } elseif ($poVal !== '') {
                $finalContract = $poVal;
                $contractSource = 'po';
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

            // منع التكرار (Duplicate Check) - DISABLED BY USER REQUEST to allow history
            // if ($this->records->existsByGuarantee($guarantee, $bank)) {
            //     $skipped[] = "الصف #{$rowIndex}: تم تخطيه (رقم الضمان مكرر: $guarantee)";
            //     continue;
            // }

            $match = $this->matchingService->matchSupplier($supplier);
            $bankMatch = $this->matchingService->matchBank($bank);
            $bankDisplay = $bankMatch['final_name'] ?? null;

            // Fix: Status Override logic respecting 'needs_review'
            // Default to what MatchingService said
            $status = $match['match_status'];

            // Only force ready if we have IDs AND both are CONFIDENT matches
            // If match_status is 'needs_review' (fuzzy), we KEEP it needs_review.
            // But if one is ready and other is missing? 
            // The constraint is: We need both SupplierID and BankID to be even considered 'ready'.
            // If we have IDs but status is 'needs_review', it STAYS 'needs_review'.
            if (empty($match['supplier_id']) || empty($bankMatch['bank_id'])) {
                // Even if one was 'ready', if the other is missing, it's not ready.
                $status = 'needs_review';
            }

            $candidates = $this->candidateService->supplierCandidates($supplier)['candidates'] ?? [];
            $bankCandidatesArr = $this->candidateService->bankCandidates($bank)['candidates'] ?? [];
            $bankCandidates = ['normalized' => $bankMatch['normalized'] ?? null, 'candidates' => $bankCandidatesArr];
            $conflicts = $this->conflictDetector->detect(
                ['supplier' => ['candidates' => $candidates, 'normalized' => $match['normalized'] ?? ''], 'bank' => $bankCandidates],
                ['raw_supplier_name' => $supplier, 'raw_bank_name' => $bank]
            );

            $record = new ImportedRecord(
                id: null,
                sessionId: $session->id ?? 0,
                rawSupplierName: (string) $supplier,
                rawBankName: (string) $bank,
                amount: $amount ?: null,
                guaranteeNumber: $guarantee ?: null,
                contractNumber: $finalContract,
                contractSource: $contractSource,
                expiryDate: $expiry ?: null,
                issueDate: $issue ?: null,
                type: $typeVal ?: null,
                comment: $commentVal ?: null,
                matchStatus: $status,
                supplierId: $match['supplier_id'] ?? null,
                bankId: $bankMatch['bank_id'] ?? null,
                normalizedSupplier: $match['normalized'] ?? null,
                normalizedBank: $bankMatch['normalized'] ?? null,
                bankDisplay: $bankDisplay,
                supplierDisplayName: null,
            );
            $this->records->create($record);

            // Attempt Auto-Accept only if status allows or enhances it
            $this->autoAcceptService->tryAutoAccept($record, $candidates, $conflicts);
            $this->autoAcceptService->tryAutoAcceptBank($record, $bankCandidatesArr, $conflicts);

            // تسجيل التعلم المؤرَّخ للمورد والبنك (للسجلات الجديدة فقط)
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

        return [
            'session_id' => $session->id,
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
