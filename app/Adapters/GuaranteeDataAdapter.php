<?php
declare(strict_types=1);

namespace App\Adapters;

use App\Repositories\ImportedRecordRepository;
use App\Repositories\GuaranteeRepository;
use App\Repositories\GuaranteeActionRepository;
use App\Repositories\ImportBatchRepository;
use App\Repositories\ActionSessionRepository;
use App\Models\ImportedRecord;

/**
 * Guarantee Data Adapter
 * 
 * Purpose: Bridge between old (imported_records) and new (guarantees + actions) architecture
 * Strategy: Dual-write pattern - write to both old and new tables
 * 
 * This allows:
 * - Zero downtime migration
 * - Gradual code transition
 * - Easy rollback if needed
 */
class GuaranteeDataAdapter
{
    private ImportedRecordRepository $oldRepo;
    private GuaranteeRepository $guaranteeRepo;
    private GuaranteeActionRepository $actionRepo;
    private ImportBatchRepository $batchRepo;
    private ActionSessionRepository $sessionRepo;
    
    public function __construct()
    {
        $this->oldRepo = new ImportedRecordRepository();
        $this->guaranteeRepo = new GuaranteeRepository();
        $this->actionRepo = new GuaranteeActionRepository();
        $this->batchRepo = new ImportBatchRepository();
        $this->sessionRepo = new ActionSessionRepository();
    }
    
    /**
     * Create a guarantee record (dual-write)
     * 
     * @param array $data Record data (same format as old imported_records)
     * @param int|null $sessionId Old session ID (for compatibility)
     * @param int|null $batchId New batch ID (if using batches)
     * @return array ['old_id' => int, 'new_id' => int]
     */
    public function createGuarantee(array $data, ?int $sessionId = null, ?int $batchId = null): array
    {
        // 1. Write to OLD table (for backward compatibility)
        $record = new ImportedRecord(
            id: null,
            sessionId: $sessionId ?? 0,
            rawSupplierName: $data['raw_supplier_name'],
            rawBankName: $data['raw_bank_name'],
            amount: $data['amount'] ?? null,
            guaranteeNumber: $data['guarantee_number'],
            contractNumber: $data['contract_number'] ?? null,
            relatedTo: $data['related_to'] ?? null,
            issueDate: $data['issue_date'] ?? null,
            expiryDate: $data['expiry_date'] ?? null,
            type: $data['type'] ?? null,
            comment: $data['comment'] ?? null,
            normalizedSupplier: $data['normalized_supplier'] ?? null,
            normalizedBank: $data['normalized_bank'] ?? null,
            matchStatus: $data['match_status'] ?? 'needs_review',
            supplierId: $data['supplier_id'] ?? null,
            bankId: $data['bank_id'] ?? null,
            bankDisplay: $data['bank_display'] ?? null,
            supplierDisplayName: $data['supplier_display_name'] ?? null,
            createdAt: $data['created_at'] ?? date('Y-m-d H:i:s'),
            recordType: 'import',
            importBatchId: $batchId
        );
        
        $oldRecord = $this->oldRepo->create($record);
        $oldId = $oldRecord->id;
        
        // 2. Write to NEW table (guarantees)
        $guaranteeData = [
            'guarantee_number' => $data['guarantee_number'],
            'raw_supplier_name' => $data['raw_supplier_name'],
            'raw_bank_name' => $data['raw_bank_name'],
            'contract_number' => $data['contract_number'] ?? null,
            'amount' => $data['amount'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'type' => $data['type'] ?? null,
            'comment' => $data['comment'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'bank_id' => $data['bank_id'] ?? null,
            'supplier_display_name' => $data['supplier_display_name'] ?? null,
            'bank_display' => $data['bank_display'] ?? null,
            'match_status' => $data['match_status'] ?? 'needs_review',
            'import_batch_id' => $batchId,
            'import_type' => $data['import_type'] ?? 'excel',
            'import_date' => date('Y-m-d')
        ];
        
        $newId = $this->guaranteeRepo->create($guaranteeData);
        
        // 3. Link old to new
        $this->oldRepo->updateDecision($oldId, [
            'migrated_guarantee_id' => $newId,
            'import_batch_id' => $batchId
        ]);
        
        return [
            'old_id' => $oldId,
            'new_id' => $newId
        ];
    }
    
    /**
     * Create an action (extension, release, etc) - dual-write
     * 
     * @param array $data Action data
     * @param int|null $legacySessionId Old session ID (for compatibility)
     * @param int|null $actionSessionId New action session ID (optional)
     * @return array ['old_id' => int, 'new_id' => int]
     */
    public function createAction(array $data, ?int $legacySessionId = null, ?int $actionSessionId = null): array
    {
        // Determine record_type based on action_type
        $recordType = match($data['action_type']) {
            'extension' => 'extension_action',
            'release' => 'release_action',
            default => $data['action_type'] . '_action'
        };
        
        // 1. Write to OLD table (for compatibility)
        $record = new ImportedRecord(
            id: null,
            sessionId: $legacySessionId ?? 0,
            rawSupplierName: $data['raw_supplier_name'] ?? '',
            rawBankName: $data['raw_bank_name'] ?? '',
            amount: $data['amount'] ?? null,
            guaranteeNumber: $data['guarantee_number'],
            contractNumber: $data['contract_number'] ?? null,
            relatedTo: null,
            issueDate: $data['issue_date'] ?? null,
            expiryDate: $data['new_expiry_date'] ?? $data['expiry_date'] ?? null,
            type: $data['type'] ?? null,
            comment: $data['notes'] ?? $data['comment'] ?? null,
            normalizedSupplier: null,
            normalizedBank: null,
            matchStatus: $data['action_status'] === 'issued' ? 'ready' : 'needs_review',
            supplierId: $data['supplier_id'] ?? null,
            bankId: $data['bank_id'] ?? null,
            bankDisplay: $data['bank_display'] ?? null,
            supplierDisplayName: $data['supplier_display_name'] ?? null,
            createdAt: date('Y-m-d H:i:s'),
            recordType: $recordType  // CRITICAL FIX: Set record type!
        );
        
        $oldRecord = $this->oldRepo->create($record);
        $oldId = $oldRecord->id;
        
        // 2. Write to NEW table (guarantee_actions)
        $actionData = [
            'guarantee_number' => $data['guarantee_number'],
            'guarantee_id' => $data['guarantee_id'] ?? null,
            'action_type' => $data['action_type'],
            'action_session_id' => $actionSessionId,
            'action_date' => $data['action_date'] ?? date('Y-m-d'),
            'previous_expiry_date' => $data['previous_expiry_date'] ?? null,
            'new_expiry_date' => $data['new_expiry_date'] ?? null,
            'previous_amount' => $data['previous_amount'] ?? null,
            'new_amount' => $data['new_amount'] ?? $data['amount'] ?? null,
            'notes' => $data['notes'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'bank_id' => $data['bank_id'] ?? null,
            'supplier_display_name' => $data['supplier_display_name'] ?? null,
            'bank_display' => $data['bank_display'] ?? null,
            'action_status' => $data['action_status'] ?? 'issued',
            'is_locked' => $data['is_locked'] ?? ($data['action_status'] === 'issued' ? 1 : 0),
            'created_by' => $data['created_by'] ?? null,
            'issued_at' => $data['action_status'] === 'issued' ? date('Y-m-d H:i:s') : null
        ];
        
        $newId = $this->actionRepo->create($actionData);
        
        // 3. Link old to new
        $this->oldRepo->updateDecision($oldId, [
            'migrated_action_id' => $newId
        ]);
        
        return [
            'old_id' => $oldId,
            'new_id' => $newId
        ];
    }
    
    /**
     * Update guarantee (dual-update)
     */
    public function updateGuarantee(int $oldId, array $data): void
    {
        // 1. Update OLD table
        $this->oldRepo->updateDecision($oldId, $data);
        
        // 2. Find and update NEW table
        $oldRecord = $this->oldRepo->find($oldId);
        if ($oldRecord && $oldRecord->id) {
            // Get migrated guarantee ID from old record
            $db = \App\Support\Database::connection();
            $stmt = $db->prepare("
                SELECT migrated_guarantee_id 
                FROM imported_records 
                WHERE id = ?
            ");
            $stmt->execute([$oldId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result && $result['migrated_guarantee_id']) {
                $this->guaranteeRepo->update((int)$result['migrated_guarantee_id'], $data);
            }
        }
    }
    
    /**
     * Bulk update supplier - dual operation
     */
    public function bulkUpdateSupplier(
        int $sessionId,
        ?int $batchId,
        string $rawName,
        int $supplierId,
        ?string $displayName
    ): array {
        // 1. Update OLD table
        $oldIds = $this->oldRepo->bulkUpdateSupplierByRawName(
            $sessionId,
            $rawName,
            0, // excludeId
            $supplierId,
            $displayName
        );
        
        // 2. Update NEW table (if batch exists)
        $newIds = [];
        if ($batchId) {
            $newIds = $this->guaranteeRepo->bulkUpdateSupplier(
                $batchId,
                $rawName,
                $supplierId,
                $displayName
            );
        }
        
        return [
            'old_ids' => $oldIds,
            'new_ids' => $newIds
        ];
    }
    
    /**
     * Helper: Determine if data is import or action
     */
    private function isAction(array $data): bool
    {
        return isset($data['record_type']) && 
               in_array($data['record_type'], ['extension_action', 'release_action']);
    }
    
    /**
     * Helper: Determine if data is import
     */
    private function isImport(array $data): bool
    {
        return !$this->isAction($data);
    }
    
    /**
     * Create a modification record (passive tracking - audit trail only)
     * 
     * @param array $data Modified data
     * @param int $originalRecordId ID of the record being modified
     * @return array|null Returns modification info or null if no changes
     */
    public function createModification(array $data, int $originalRecordId): ?array
    {
        $original = $this->oldRepo->find($originalRecordId);
        if (!$original) {
            return null;
        }
        
        // Detect changes
        $changes = [];
        
        if (isset($data['supplier_id']) && $data['supplier_id'] != $original->supplierId) {
            $changes['supplier'] = [
                'from' => $original->supplierDisplayName ?? $original->rawSupplierName,
                'to' => $data['supplier_display_name'] ?? 'Unknown',
            ];
        }
        
        if (isset($data['bank_id']) && $data['bank_id'] != $original->bankId) {
            $changes['bank'] = [
                'from' => $original->bankDisplay ?? $original->rawBankName,
                'to' => $data['bank_display'] ?? 'Unknown',
            ];
        }
        
        if (isset($data['amount']) && $data['amount'] != $original->amount) {
            $changes['amount'] = ['from' => $original->amount, 'to' => $data['amount']];
        }
        
        if (empty($changes)) {
            return null;
        }
        
        $sessionRepo = new \App\Repositories\ImportSessionRepository();
        $session = $sessionRepo->getOrCreateDailySession('daily_actions');
        
        $record = new ImportedRecord(
            id: null,
            sessionId: $session->id,
            rawSupplierName: $original->rawSupplierName,
            rawBankName: $original->rawBankName,
            amount: $data['amount'] ?? $original->amount,
            guaranteeNumber: $original->guaranteeNumber,
            contractNumber: $original->contractNumber,
            relatedTo: $original->relatedTo,
            issueDate: $original->issueDate,
            expiryDate: $original->expiryDate,
            type: $original->type,
            comment: json_encode(['changes' => $changes, 'modified_from' => $originalRecordId]),
            normalizedSupplier: $original->normalizedSupplier,
            normalizedBank: $original->normalizedBank,
            matchStatus: 'ready',
            supplierId: $data['supplier_id'] ?? $original->supplierId,
            bankId: $data['bank_id'] ?? $original->bankId,
            bankDisplay: $data['bank_display'] ?? $original->bankDisplay,
            supplierDisplayName: $data['supplier_display_name'] ?? $original->supplierDisplayName,
            createdAt: date('Y-m-d H:i:s'),
            recordType: 'modification',
            importBatchId: null
        );
        
        try {
            $newRecord = $this->oldRepo->create($record);
            return ['id' => $newRecord->id, 'changes' => $changes];
        } catch (\Exception $e) {
            error_log("Modification tracking failed: " . $e->getMessage());
            return null;
        }
    }
}
