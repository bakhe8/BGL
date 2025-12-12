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
     * @return array{session_id:int, records_count:int}
     */
    public function importExcel(string $filePath): array
    {
        $session = $this->sessions->create('excel');

        $rows = $this->xlsxReader->read($filePath);
        if (count($rows) < 2) {
            throw new RuntimeException('لم يتم العثور على صفوف صالحة في الملف.');
        }

        // السطر الأول رؤوس
        $headers = array_shift($rows);
        $map = $this->detector->detect($headers);

        // التحقق من الأعمدة الأساسية
        if (!isset($map['supplier']) || !isset($map['bank'])) {
            throw new RuntimeException('⚠️ لم نستطع التعرف على عمود اسم المورد أو عمود البنك. يرجى التأكد من أن ملف Excel يحتوي على الأعمدة المطلوبة بصيغة معروفة.');
        }

        // تسريع عمليات SQLite: تجميع الإدخالات في معاملة واحدة
        $pdo = \App\Support\Database::connection();
        $pdo->beginTransaction();
        $count = 0;
        foreach ($rows as $row) {
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
            $typeVal = trim($typeRaw) !== '' ? trim($typeRaw) : null; // يُقرأ ويُخزن بعد تطبيع بسيط (trim)
            $commentVal = $this->colValue($row, $map['comment'] ?? null);
            $expiry = $this->normalizeDate($this->colValue($row, $map['expiry'] ?? null));
            $issue = $this->colValue($row, $map['issue'] ?? null); // يُخزن كما هو بدون تطبيع

            // تخطي السطر إذا لم يوجد مورد وبنك معاً، أو لم يوجد عقد/PO، أو لم يوجد رقم ضمان، أو لم يوجد مبلغ
            $hasSupplierAndBank = ($supplier !== '' && $bank !== '');
            $hasContract = $finalContract !== null && $finalContract !== '';
            $hasGuarantee = $guarantee !== null && $guarantee !== '';
            $hasAmount = $amount !== null && $amount !== '';
            if (!$hasSupplierAndBank || !$hasContract || !$hasGuarantee || !$hasAmount) {
                continue;
            }

            $match = $this->matchingService->matchSupplier($supplier);
            $bankMatch = $this->matchingService->matchBank($bank);
            $bankDisplay = $bankMatch['final_name'] ?? null;
            $status = $match['match_status'];
            if (!empty($match['supplier_id']) && !empty($bankMatch['bank_id'])) {
                $status = 'ready';
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
                rawSupplierName: (string)$supplier,
                rawBankName: (string)$bank,
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
            $this->autoAcceptService->tryAutoAccept($record, $candidates, $conflicts);
            $this->autoAcceptService->tryAutoAcceptBank($record, $bankCandidatesArr, $conflicts);
            // تسجيل التعلم المؤرَّخ للمورد والبنك (للسجلات الجديدة فقط)
            $this->learningLog->create([
                'raw_input' => (string)$supplier,
                'normalized_input' => $match['normalized'] ?? '',
                'suggested_supplier_id' => $match['supplier_id'] ?? null,
                'decision_result' => $status,
                'candidate_source' => 'import',
                'score' => null,
                'score_raw' => null,
                'created_at' => date('c'),
            ]);
            $this->learningLog->createBank([
                'raw_input' => (string)$bank,
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
        ];
    }

    private function colValue(array $row, $index): string
    {
        if ($index === null) {
            return '';
        }
        if (is_array($index)) {
            foreach ($index as $i) {
                if (isset($row[$i]) && trim((string)$row[$i]) !== '') {
                    return (string)$row[$i];
                }
            }
            return '';
        }
        return isset($row[$index]) ? (string)$row[$index] : '';
    }

    private function normalizeAmount(string $amount): ?string
    {
        if (trim($amount) === '') {
            return null;
        }
        // إزالة أي رموز غير رقمية مع الحفاظ على العلامة العشرية
        $clean = preg_replace('/[^\d\.\-]/', '', $amount);
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        $num = (float)$clean;
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
            return $value; // نعيده كما هو، سيتم مراجعته لاحقًا
        }
        return date('Y-m-d', $ts);
    }
}
