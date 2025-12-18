<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;
use App\Repositories\ImportSessionRepository;
use App\Services\TextParsingService;
use App\Services\ImportService;
use App\Models\ImportedRecord;
use App\Services\MatchingService;
use App\Services\CandidateService;
use App\Services\ConflictDetector;
use App\Services\AutoAcceptService;

/**
 * Handles "Smart Paste" (Text Import) requests.
 * 
 * Orchestrates the full flow:
 * 1. Receives unstructured text from API.
 * 2. Uses TextParsingService to extraction structured data (supporting Bulk import).
 * 3. Creates a new Import Session.
 * 4. Creates ImportedRecords via Repository.
 * 5. Triggers Matching Engine (Supplier/Bank detection) via MatchingService.
 * 6. Returns result (Session ID + Record ID) to frontend.
 */
class TextImportController
{
    public function __construct(
        private TextParsingService $parser,
        private ImportSessionRepository $sessions,
        private ImportedRecordRepository $records,
        private MatchingService $matchingService,
        // Optional deps
        private ?CandidateService $candidateService = null,
        private ?ConflictDetector $conflictDetector = null,
        private ?AutoAcceptService $autoAcceptService = null,
    ) {
         // Auto-wire dependencies if not provided (similar to ImportService)
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
     * Main handler for text import.
     * 
     * @param array $input Expects ['text' => '...']
     * @return void Outputs JSON response
     */
    public function handle(array $input): void
    {
        $text = $input['text'] ?? '';
        if (empty(trim($text))) {
             echo json_encode(['success' => false, 'error' => 'No text provided']);
             return;
        }

        // 1. Parse Text (Bulk Support)
        $parsedResults = $this->parser->parseBulk($text); // Returns array of arrays
        
        if (empty($parsedResults)) {
             echo json_encode(['success' => false, 'error' => 'No valid data found']);
             return;
        }

        // 3. Create Session (Once)
        $session = $this->sessions->create('smart_paste');
        $firstRecordId = null;
        $count = 0;
        
        // 4. Create ImportedRecord Loop
        foreach ($parsedResults as $data) {
            $record = $this->createRecordFromData($data, $session->id);
            $this->processMatching($record);
            
            if (!$firstRecordId) $firstRecordId = $record->id;
            $count++;
        }
        
        // Update Session Count
        if ($count > 0) {
            $this->sessions->incrementRecordCount($session->id, $count);
        }

        echo json_encode(['success' => true, 'record_id' => $firstRecordId, 'session_id' => $session->id, 'count' => $count]);
    }

    private function createRecordFromData(array $data, int $sessionId): ImportedRecord
    {
        // Detect related_to from contract_number pattern or use provided value
        $relatedTo = $data['related_to'] ?? null;
        if (!$relatedTo && !empty($data['contract_number'])) {
            // Auto-detect from contract number pattern
            $contract = $data['contract_number'];
            if (preg_match('/^C\//i', $contract) || stripos($contract, 'contract') !== false) {
                $relatedTo = 'contract';
            } else {
                $relatedTo = 'purchase_order';
            }
        }
        // Default fallback
        if (!$relatedTo) {
            $relatedTo = 'purchase_order';  // Safe default for Smart Paste
        }
        
        // Map parsed data to ImportedRecord properties
        return $this->records->create(new ImportedRecord(
            id: null,
            sessionId: $sessionId,
            rawSupplierName: $data['supplier'] ?? 'Unknown Supplier',
            rawBankName: $data['bank'] ?? 'Unknown Bank',
            amount: isset($data['amount']) ? (string)$data['amount'] : null,
            guaranteeNumber: $data['guarantee_number'],
            contractNumber: $data['contract_number'] ?? null,
            relatedTo: $relatedTo,
            issueDate: null,
            expiryDate: $data['expiry_date'],
            type: $data['type'],
            comment: 'Smart Paste Import',
            matchStatus: 'needs_review',
            supplierId: null,
            bankId: null,
            bankDisplay: null,
            supplierDisplayName: null
        ));
    }

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

        // 3. Candidates & Conflicts (for Autopilot)
        $candidates = $this->candidateService->supplierCandidates($record->rawSupplierName)['candidates'] ?? [];
        $bankCandidatesArr = $this->candidateService->bankCandidates($record->rawBankName)['candidates'] ?? [];
        
        $conflicts = $this->conflictDetector->detect(
             ['supplier' => ['candidates' => $candidates], 'bank' => ['candidates' => $bankCandidatesArr]],
             ['raw_supplier_name' => $record->rawSupplierName, 'raw_bank_name' => $record->rawBankName]
        );

        // 4. Auto Accept?
        $this->autoAcceptService->tryAutoAccept($record, $candidates, $conflicts);
        $this->autoAcceptService->tryAutoAcceptBank($record, $bankCandidatesArr, $conflicts);
        
        // 5. Update Record
        $this->records->update($record);
    }
}
