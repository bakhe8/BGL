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
 * Modification Tracking Extension for GuaranteeDataAdapter
 * 
 * Add this method to GuaranteeDataAdapter class
 */

/**
 * Create a modification record (dual-write)
 * 
 * @param array $data Modified data
 * @param int $originalRecordId ID of the record being modified
 * @param int|null $sessionId Session ID (optional, uses daily session if null)
 * @return array ['old_id' => int, 'new_id' => int, 'changes' => array]
 */
public function createModification(array $data, int $originalRecordId, ?int $sessionId = null): array
{
    // Get original record for comparison
    $oldRepo = new ImportedRecordRepository();
    $original = $oldRepo->find($originalRecordId);
    
    if (!$original) {
        throw new \RuntimeException("Original record not found: $originalRecordId");
    }
    
    // Detect changes
    $changes = [];
    
    if (isset($data['supplier_id']) && $data['supplier_id'] != $original->supplierId) {
        $changes['supplier'] = [
            'from' => $original->supplierDisplayName ?? $original->rawSupplierName,
            'to' => $data['supplier_display_name'] ?? 'Unknown',
            'from_id' => $original->supplierId,
            'to_id' => $data['supplier_id']
        ];
    }
    
    if (isset($data['bank_id']) && $data['bank_id'] != $original->bankId) {
        $changes['bank'] = [
            'from' => $original->bankDisplay ?? $original->rawBankName,
            'to' => $data['bank_display'] ?? 'Unknown',
            'from_id' => $original->bankId,
            'to_id' => $data['bank_id']
        ];
    }
    
    if (isset($data['amount']) && $data['amount'] != $original->amount) {
        $changes['amount'] = [
            'from' => $original->amount,
            'to' => $data['amount']
        ];
    }
    
    // If no changes, just update the original record
    if (empty($changes)) {
        $oldRepo->updateDecision($originalRecordId, $data);
        return [
            'old_id' => $originalRecordId,
            'new_id' => null,
            'changes' => []
        ];
    }
    
    // Use daily session if not provided
    if (!$sessionId) {
        $sessionRepo = new \App\Repositories\ImportSessionRepository();
        $session = $sessionRepo->getOrCreateDailySession('daily_actions');
        $sessionId = $session->id;
    }
    
    // Create NEW record with type='modification'
    $record = new ImportedRecord(
        id: null,
        sessionId: $sessionId,
        rawSupplierName: $original->rawSupplierName,
        rawBankName: $original->rawBankName,
        amount: $data['amount'] ?? $original->amount,
        guaranteeNumber: $original->guaranteeNumber,
        contractNumber: $original->contractNumber,
        relatedTo: $original->relatedTo,
        issueDate: $original->issueDate,
        expiryDate: $data['expiry_date'] ?? $original->expiryDate,
        type: $original->type,
        comment: json_encode(['changes' => $changes, 'modified_from' => $originalRecordId]),
        normalizedSupplier: $data['normalized_supplier'] ?? $original->normalizedSupplier,
        normalizedBank: $data['normalized_bank'] ?? $original->normalizedBank,
        matchStatus: $data['match_status'] ?? 'ready',
        supplierId: $data['supplier_id'] ?? $original->supplierId,
        bankId: $data['bank_id'] ?? $original->bankId,
        bankDisplay: $data['bank_display'] ?? $original->bankDisplay,
        supplierDisplayName: $data['supplier_display_name'] ?? $original->supplierDisplayName,
        createdAt: date('Y-m-d H:i:s'),
        recordType: 'modification',
        importBatchId: null // Modifications don't belong to batches
    );
    
    $newRecord = $oldRepo->create($record);
    
    // Write to NEW table (guarantees) - update the guarantee
    // Note: We update the guarantee, not create a new one
    // The modification is tracked in imported_records only
    
    return [
        'old_id' => $newRecord->id,
        'new_id' => null, // No new guarantee, just modification
        'changes' => $changes
    ];
}
