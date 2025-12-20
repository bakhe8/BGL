<?php
/**
 * =============================================================================
 * TimelineEventService
 * =============================================================================
 * 
 * Centralized service for creating timeline events.
 * 
 * Key Responsibilities:
 * 1. Create timeline events with proper validation
 * 2. Trigger learning updates automatically
 * 3. Update supplier/bank weights
 * 4. Provide clean API for controllers
 * 
 * Design Pattern: Service Layer
 * - Encapsulates business logic
 * - Coordinates between repositories
 * - Handles side effects (learning, weights)
 * 
 * @version 1.0
 * @created 2025-12-20
 * =============================================================================
 */

namespace App\Services;

use App\Repositories\TimelineEventRepository;
use App\Repositories\ImportSessionRepository;
use App\Repositories\SupplierSuggestionRepository;

class TimelineEventService
{
    private TimelineEventRepository $timeline;
    private ImportSessionRepository $sessions;
    private SupplierSuggestionRepository $suggestions;
    
    public function __construct(
        ?TimelineEventRepository $timeline = null,
        ?ImportSessionRepository $sessions = null,
        ?SupplierSuggestionRepository $suggestions = null
    ) {
        $this->timeline = $timeline ?? new TimelineEventRepository();
        $this->sessions = $sessions ?? new ImportSessionRepository();
        $this->suggestions = $suggestions ?? new SupplierSuggestionRepository();
    }
    
    /**
     * =========================================================================
     * SUPPLIER CHANGE
     * =========================================================================
     */
    
    /**
     * Log supplier change event
     * 
     * Automatically:
     * - Creates timeline event
     * - Updates supplier weights (future)
     * - Updates suggestions usage count
     * 
     * @param string $guaranteeNumber Guarantee number
     * @param int $recordId Record ID
     * @param int|null $oldSupplierId Previous supplier ID
     * @param int $newSupplierId New supplier ID
     * @param string|null $oldSupplierName Previous supplier name
     * @param string $newSupplierName New supplier name
     * @param int|null $sessionId Session ID (defaults to daily session)
     * @return int Event ID
     */
    public function logSupplierChange(
        string $guaranteeNumber,
        int $recordId,
        ?int $oldSupplierId,
        int $newSupplierId,
        ?string $oldSupplierName,
        string $newSupplierName,
        ?int $sessionId = null
    ): int {
        // Get session
        if ($sessionId) {
            $session = $this->sessions->find($sessionId);
        } else {
            $session = $this->sessions->getOrCreateDailySession('daily_actions');
        }
        
        // Determine change type
        $changeType = $this->determineSupplierChangeType($oldSupplierId, $oldSupplierName, $newSupplierName);
        
        // Create timeline event
        $eventId = $this->timeline->create([
            'guarantee_number' => $guaranteeNumber,
            'record_id' => $recordId,
            'session_id' => $session->id,
            'event_type' => 'supplier_change',
            'field_name' => 'supplier',
            'old_value' => $oldSupplierName ?? 'غير محدد',
            'new_value' => $newSupplierName,
            'old_id' => $oldSupplierId,
            'new_id' => $newSupplierId,
            'supplier_display_name' => $newSupplierName,  // Store for fast access
            'change_type' => $changeType
        ]);
        
        // Update weights/learning (automatic side effect)
        try {
            $this->updateSupplierWeights($newSupplierId);
        } catch (\Exception $e) {
            // Log error but don't fail the event creation
            error_log("TimelineEventService: Failed to update supplier weights: " . $e->getMessage());
        }
        
        return $eventId;
    }
    
    /**
     * Determine supplier change type
     */
    private function determineSupplierChangeType(
        ?int $oldId,
        ?string $oldName,
        string $newName
    ): string {
        if ($oldId === null) {
            return 'initial_assignment';
        }
        
        if ($oldId && $oldName && $oldName !== $newName) {
            return 'entity_change';
        }
        
        if ($oldId && $oldName === $newName) {
            return 'name_correction';
        }
        
        return 'update';
    }
    
    /**
     * =========================================================================
     * BANK CHANGE
     * =========================================================================
     */
    
    /**
     * Log bank change event
     */
    public function logBankChange(
        string $guaranteeNumber,
        int $recordId,
        ?int $oldBankId,
        int $newBankId,
        ?string $oldBankName,
        string $newBankName,
        ?int $sessionId = null
    ): int {
        if ($sessionId) {
            $session = $this->sessions->find($sessionId);
        } else {
            $session = $this->sessions->getOrCreateDailySession('daily_actions');
        }
        
        return $this->timeline->create([
            'guarantee_number' => $guaranteeNumber,
            'record_id' => $recordId,
            'session_id' => $session->id,
            'event_type' => 'bank_change',
            'field_name' => 'bank',
            'old_value' => $oldBankName ?? 'غير محدد',
            'new_value' => $newBankName,
            'old_id' => $oldBankId,
            'new_id' => $newBankId,
            'bank_display' => $newBankName,  // Store for fast access
            'change_type' => $oldBankId ? 'entity_change' : 'initial_assignment'
        ]);
    }
    
    /**
     * =========================================================================
     * AMOUNT CHANGE
     * =========================================================================
     */
    
    /**
     * Log amount change event
     */
    public function logAmountChange(
        string $guaranteeNumber,
        int $recordId,
        ?string $oldAmount,
        string $newAmount,
        ?int $sessionId = null
    ): int {
        if ($sessionId) {
            $session = $this->sessions->find($sessionId);
        } else {
            $session = $this->sessions->getOrCreateDailySession('daily_actions');
        }
        
        return $this->timeline->create([
            'guarantee_number' => $guaranteeNumber,
            'record_id' => $recordId,
            'session_id' => $session->id,
            'event_type' => 'amount_change',
            'field_name' => 'amount',
            'old_value' => $oldAmount ?? '0',
            'new_value' => $newAmount,
            'change_type' => 'update'
        ]);
    }
    
    /**
     * =========================================================================
     * ACTIONS (Extension, Release, etc)
     * =========================================================================
     */
    
    /**
     * Log extension action
     */
    public function logExtension(
        string $guaranteeNumber,
        int $recordId,
        string $oldExpiryDate,
        string $newExpiryDate,
        int $sessionId
    ): int {
        return $this->timeline->create([
            'guarantee_number' => $guaranteeNumber,
            'record_id' => $recordId,
            'session_id' => $sessionId,
            'event_type' => 'extension',
            'field_name' => 'expiry_date',
            'old_value' => $oldExpiryDate,
            'new_value' => $newExpiryDate,
            'change_type' => 'action'
        ]);
    }
    
    /**
     * Log release action
     */
    public function logRelease(
        string $guaranteeNumber,
        int $recordId,
        int $sessionId
    ): int {
        return $this->timeline->create([
            'guarantee_number' => $guaranteeNumber,
            'record_id' => $recordId,
            'session_id' => $sessionId,
            'event_type' => 'release',
            'change_type' => 'action'
        ]);
    }
    
    /**
     * =========================================================================
     * LEARNING & WEIGHTS (Private)
     * =========================================================================
     */
    
    /**
     * Update supplier weights based on timeline data
     * 
     * CRITICAL: This replaces scattered learning logic!
     * 
     * @param int $supplierId Supplier ID
     */
    private function updateSupplierWeights(int $supplierId): void
    {
        // Get usage count from timeline
        $usageCount = $this->timeline->getSupplierUsageCount($supplierId);
        $reversionCount = $this->timeline->getSupplierReversionCount($supplierId);
        
        // Calculate success rate
        $successRate = $usageCount > 0 
            ? ($usageCount - $reversionCount) / $usageCount 
            : 0;
        
        // Log metrics (for monitoring)
        error_log(sprintf(
            "TimelineEventService: Supplier %d - Usage: %d, Reversions: %d, Success: %.1f%%",
            $supplierId,
            $usageCount,
            $reversionCount,
            $successRate * 100
        ));
        
        // TODO: Update supplier_suggestions weights
        // This would be implemented in Phase 4
        // For now, just log the metrics
    }
    
    /**
     * =========================================================================
     * BULK OPERATIONS
     * =========================================================================
     */
    
    /**
     * Log bulk decision propagation
     * 
     * When a decision is propagated to multiple records,
     * create a single bulk event instead of individual events.
     * 
     * @param array $recordIds Propagated record IDs
     * @param int $sourceRecordId Source record ID
     * @param int $supplierId Supplier ID
     * @param string $supplierName Supplier name
     * @param int $sessionId Session ID
     * @return int Event ID
     */
    public function logBulkPropagation(
        array $recordIds,
        int $sourceRecordId,
        int $supplierId,
        string $supplierName,
        int $sessionId
    ): int {
        // For now, create a single event noting the count
        // In future, could create individual events if needed
        return $this->timeline->create([
            'guarantee_number' => "BULK_{$sourceRecordId}",
            'record_id' => $sourceRecordId,
            'session_id' => $sessionId,
            'event_type' => 'bulk_propagation',
            'new_value' => $supplierName . ' (' . count($recordIds) . ' records)',
            'new_id' => $supplierId,
            'change_type' => 'bulk_update'
        ]);
    }
}
