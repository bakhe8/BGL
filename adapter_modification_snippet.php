<?php
/**
 * Add this method to GuaranteeDataAdapter class (before closing brace)
 */

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
        return null; // Silently fail - don't break anything
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
        $changes['amount'] = [
            'from' => $original->amount,
            'to' => $data['amount']
        ];
    }
    
    // No changes - return silently
    if (empty($changes)) {
        return null;
    }
    
    // Get daily session
    $sessionRepo = new \App\Repositories\ImportSessionRepository();
    $session = $sessionRepo->getOrCreateDailySession('daily_actions');
    
    // Create ghost record for audit trail
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
        // Silently fail - don't break saveDecision
        error_log("Modification tracking failed: " . $e->getMessage());
        return null;
    }
}
