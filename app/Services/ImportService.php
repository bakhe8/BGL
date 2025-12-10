<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportedRecord;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Services\ExcelColumnDetector;
use App\Services\CandidateService;
use App\Services\AutoAcceptService;
use App\Services\MatchingService;
use App\Support\XlsxReader;
use RuntimeException;

class ImportService
{
    public function __construct(
        private ImportSessionRepository $sessions,
        private ImportedRecordRepository $records,
        private XlsxReader $xlsxReader = new XlsxReader(),
        private ExcelColumnDetector $detector = new ExcelColumnDetector(),
        private MatchingService $matchingService = null,
        private CandidateService $candidateService = null,
        private AutoAcceptService $autoAcceptService = null,
        private ConflictDetector $conflictDetector = new ConflictDetector(),
    ) {
        $this->matchingService ??= new MatchingService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
            new \App\Repositories\BankRepository(),
        );
        $this->candidateService ??= new CandidateService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
            new \App\Repositories\BankRepository(),
        );
        $this->autoAcceptService ??= new AutoAcceptService($this->records);
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

        $count = 0;
        foreach ($rows as $row) {
            $supplier = $this->colValue($row, $map['supplier'] ?? null);
            $bank = $this->colValue($row, $map['bank'] ?? null);
            $amount = $this->normalizeAmount($this->colValue($row, $map['amount'] ?? null));
            $guarantee = $this->colValue($row, $map['guarantee'] ?? null);
            $expiry = $this->normalizeDate($this->colValue($row, $map['expiry'] ?? null));
            $issue = $this->normalizeDate($this->colValue($row, $map['issue'] ?? null));

            if ($supplier === '' && $bank === '') {
                continue;
            }

            $match = $this->matchingService->matchSupplier($supplier);
            $bankMatch = $this->matchingService->matchBank($bank);
            $candidates = $this->candidateService->supplierCandidates($supplier)['candidates'] ?? [];
            $bankCandidates = $this->candidateService->bankCandidates($bank);
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
                expiryDate: $expiry ?: null,
                issueDate: $issue ?: null,
                matchStatus: $match['match_status'],
                supplierId: $match['supplier_id'] ?? null,
                bankId: $bankMatch['bank_id'] ?? null,
                normalizedSupplier: $match['normalized'] ?? null,
                normalizedBank: $bankMatch['normalized'] ?? null,
            );
            $this->records->create($record);
            $this->sessions->incrementRecordCount($session->id ?? 0);
            $this->autoAcceptService->tryAutoAccept($record, $candidates, $conflicts);
            $count++;
        }

        return [
            'session_id' => $session->id,
            'records_count' => $count,
        ];
    }

    private function colValue(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }
        return isset($row[$index]) ? (string)$row[$index] : '';
    }

    private function normalizeAmount(string $amount): ?string
    {
        if ($amount === '') {
            return null;
        }
        // إزالة الفواصل والمسافات
        $clean = preg_replace('/[^\d\.\-]/', '', $amount);
        return $clean === '' ? null : $clean;
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
