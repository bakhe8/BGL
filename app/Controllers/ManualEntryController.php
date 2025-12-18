<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;
use App\Repositories\ImportSessionRepository;
use App\Models\ImportedRecord;
use App\Services\MatchingService;
use App\Services\CandidateService;
use App\Services\ConflictDetector;
use App\Services\AutoAcceptService;

/**
 * Manual Entry Controller
 * 
 * Handles manual single-record entry from the UI.
 * Creates a regular ImportedRecord that integrates seamlessly with the existing workflow.
 * 
 * Flow:
 * 1. Receive data from API (/api/import/manual)
 * 2. Validate required fields
 * 3. Create import session (type: 'manual')
 * 4. Create ImportedRecord
 * 5. Run matching for supplier and bank
 * 6. Apply auto-accept if applicable
 * 7. Return record_id and session_id
 */
class ManualEntryController
{
    public function __construct(
        private ImportSessionRepository $sessions,
        private ImportedRecordRepository $records,
        private MatchingService $matchingService,
        private ?CandidateService $candidateService = null,
        private ?ConflictDetector $conflictDetector = null,
        private ?AutoAcceptService $autoAcceptService = null,
    ) {
        // Auto-wire dependencies (similar to TextImportController)
        if (!$this->candidateService) {
            $this->candidateService = new CandidateService(
                new \App\Repositories\SupplierRepository(),
                new \App\Repositories\SupplierAlternativeNameRepository(),
                new \App\Support\Normalizer(),
                new \App\Repositories\BankRepository()
            );
        }
        if (!$this->autoAcceptService) {
            $this->autoAcceptService = new AutoAcceptService($this->records);
        }
        if (!$this->conflictDetector) {
            $this->conflictDetector = new ConflictDetector();
        }
    }

    /**
     * Handle manual entry request
     * 
     * @param array $input Expected fields: supplier, bank, guarantee_number, contract_number, amount
     * @return void Outputs JSON response
     */
    public function handle(array $input): void
    {
        // Validate required fields
        $errors = $this->validateInput($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'بيانات غير صحيحة: ' . implode(', ', $errors)
            ]);
            return;
        }

        try {
            // 1. Create session (type: 'manual')
            $session = $this->sessions->create('manual');
            
            if (!$session || !$session->id) {
                throw new \RuntimeException('فشل إنشاء جلسة الإدخال اليدوي');
            }

            // 2. Create ImportedRecord
            $record = new ImportedRecord(
                id: null,
                sessionId: $session->id,
                rawSupplierName: trim($input['supplier']),
                rawBankName: trim($input['bank']),
                amount: $this->normalizeAmount($input['amount']),
                guaranteeNumber: trim($input['guarantee_number']),
                contractNumber: trim($input['contract_number']),
                relatedTo: $input['related_to'] ?? 'contract',
                expiryDate: !empty($input['expiry_date']) ? $this->normalizeDate($input['expiry_date']) : null,
                issueDate: !empty($input['issue_date']) ? $this->normalizeDate($input['issue_date']) : null,
                type: !empty($input['type']) ? strtoupper(trim($input['type'])) : null,
                comment: !empty($input['comment']) ? trim($input['comment']) : 'إدخال يدوي',
                matchStatus: 'needs_review',
                supplierId: null,
                bankId: null,
                normalizedSupplier: null,
                normalizedBank: null,
                bankDisplay: null,
                supplierDisplayName: null
            );

            // 3. Save record
            $savedRecord = $this->records->create($record);

            // 4. Run matching
            $this->processMatching($savedRecord);

            // 5. Update session count
            $this->sessions->incrementRecordCount($session->id, 1);

            // 6. Return success
            echo json_encode([
                'success' => true,
                'record_id' => $savedRecord->id,
                'session_id' => $session->id,
                'message' => 'تم إنشاء السجل بنجاح'
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'فشل إنشاء السجل: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate input fields
     * 
     * @param array $input
     * @return array List of error messages
     */
    private function validateInput(array $input): array
    {
        $errors = [];

        // Required fields
        if (empty($input['supplier']) || trim($input['supplier']) === '') {
            $errors[] = 'اسم المورد مطلوب';
        }
        if (empty($input['bank']) || trim($input['bank']) === '') {
            $errors[] = 'اسم البنك مطلوب';
        }
        if (empty($input['guarantee_number']) || trim($input['guarantee_number']) === '') {
            $errors[] = 'رقم الضمان مطلوب';
        }
        if (empty($input['contract_number']) || trim($input['contract_number']) === '') {
            $errors[] = 'رقم العقد مطلوب';
        }
        
        // NEW: validate related_to (required field)
        if (empty($input['related_to']) || !in_array($input['related_to'], ['contract', 'purchase_order'])) {
            $errors[] = 'يجب تحديد نوع المستند (عقد أو أمر شراء)';
        }
        
        if (empty($input['amount']) || trim($input['amount']) === '') {
            $errors[] = 'المبلغ مطلوب';
        } else {
            // Validate amount is numeric
            $normalized = $this->normalizeAmount($input['amount']);
            if ($normalized === null) {
                $errors[] = 'المبلغ يجب أن يكون رقماً صحيحاً';
            }
        }

        return $errors;
    }

    /**
     * Process matching for supplier and bank
     * 
     * @param ImportedRecord $record
     * @return void
     */
    private function processMatching(ImportedRecord $record): void
    {
        // 1. Match Supplier
        $supplierMatch = $this->matchingService->matchSupplier($record->rawSupplierName);
        $record->supplierId = $supplierMatch['supplier_id'] ?? null;
        $record->normalizedSupplier = $supplierMatch['normalized'] ?? null;
        $record->matchStatus = $supplierMatch['match_status'];

        // 2. Match Bank
        $bankMatch = $this->matchingService->matchBank($record->rawBankName);
        $record->bankId = $bankMatch['bank_id'] ?? null;
        $record->normalizedBank = $bankMatch['normalized'] ?? null;
        $record->bankDisplay = $bankMatch['final_name'] ?? null;

        // 3. Get candidates for auto-accept
        $candidates = $this->candidateService->supplierCandidates($record->rawSupplierName)['candidates'] ?? [];
        $bankCandidatesArr = $this->candidateService->bankCandidates($record->rawBankName)['candidates'] ?? [];
        
        $conflicts = $this->conflictDetector->detect(
            ['supplier' => ['candidates' => $candidates], 'bank' => ['candidates' => $bankCandidatesArr]],
            ['raw_supplier_name' => $record->rawSupplierName, 'raw_bank_name' => $record->rawBankName]
        );

        // 4. Try auto-accept
        $this->autoAcceptService->tryAutoAccept($record, $candidates, $conflicts);
        $this->autoAcceptService->tryAutoAcceptBank($record, $bankCandidatesArr, $conflicts);
        
        // 5. Update record
        $this->records->update($record);
    }

    /**
     * Normalize amount to standard format
     * 
     * @param string $amount
     * @return string|null
     */
    private function normalizeAmount(string $amount): ?string
    {
        if (trim($amount) === '') {
            return null;
        }

        // Remove any non-numeric characters except dot and comma
        $clean = preg_replace('/[^\d\.\,\-]/', '', $amount);
        
        // Handle European format (1.234,56 -> 1234.56)
        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');
        
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            // European format
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        
        // Remove any remaining commas (if US format)
        $clean = str_replace(',', '', $clean);
        
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        
        $num = (float) $clean;
        return number_format($num, 2, '.', '');
    }

    /**
     * Normalize date to ISO format (YYYY-MM-DD)
     * 
     * @param string $value
     * @return string|null
     */
    private function normalizeDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        
        $ts = strtotime($value);
        if ($ts === false) {
            return $value; // Return as-is if can't parse
        }
        
        return date('Y-m-d', $ts);
    }
}
