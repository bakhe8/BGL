<?php
/**
 * =============================================================================
 * TimelineEventRepository
 * =============================================================================
 * 
 * Repository for guarantee_timeline_events table.
 * Handles all database operations for timeline events.
 * 
 * Key Operations:
 * - create(): Add new event
 * - getByGuaranteeNumber(): Get timeline for a guarantee
 * - getSupplierUsageCount(): For weight calculations
 * - getSupplierReversionCount(): For success rate
 * 
 * @version 1.0
 * @created 2025-12-20
 * =============================================================================
 */

namespace App\Repositories;

use App\Support\Database;
use PDO;

class TimelineEventRepository
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Create a new timeline event
     * 
     * @param array $data Event data:
     *   - guarantee_number (required)
     *   - session_id (required)
     *   - event_type (required)
     *   - record_id (optional)
     *   - field_name (optional)
     *   - old_value (optional)
     *   - new_value (optional)
     *   - old_id (optional)
     *   - new_id (optional)
     *   - change_type (optional)
     *   - created_at (optional, defaults to now)
     * 
     * @return int Event ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO guarantee_timeline_events (
                guarantee_number, record_id, session_id,
                event_type, field_name,
                old_value, new_value, old_id, new_id,
                supplier_display_name, bank_display,
                change_type, snapshot_data, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['guarantee_number'],
            $data['record_id'] ?? null,
            $data['session_id'],
            $data['event_type'],
            $data['field_name'] ?? null,
            $data['old_value'] ?? null,
            $data['new_value'] ?? null,
            $data['old_id'] ?? null,
            $data['new_id'] ?? null,
            $data['supplier_display_name'] ?? null,
            $data['bank_display'] ?? null,
            $data['change_type'] ?? null,
            $data['snapshot_data'] ?? null,  // Historical snapshot!
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Get all timeline events for a guarantee
     * 
     * @param string $guaranteeNumber Guarantee number
     * @param int $limit Maximum results (default 50)
     * @return array Events ordered by created_at DESC
     */
    public function getByGuaranteeNumber(string $guaranteeNumber, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id, guarantee_number, record_id, session_id,
                event_type, field_name,
                old_value, new_value, old_id, new_id,
                supplier_display_name, bank_display,
                change_type, snapshot_data, created_at
            FROM guarantee_timeline_events
            WHERE guarantee_number = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$guaranteeNumber, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get events by type (for analytics)
     * 
     * @param string $eventType Event type filter
     * @param int $limit Maximum results
     * @return array Events of specified type
     */
    public function getByEventType(string $eventType, int $limit = 100): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guarantee_timeline_events
            WHERE event_type = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$eventType, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get supplier usage count (selections)
     * 
     * For weight calculation: how many times was this supplier selected?
     * 
     * @param int $supplierId Supplier ID
     * @return int Number of times selected
     */
    public function getSupplierUsageCount(int $supplierId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM guarantee_timeline_events
            WHERE event_type = 'supplier_change'
              AND new_id = ?
        ");
        
        $stmt->execute([$supplierId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get supplier reversion count
     * 
     * For success rate: how many times was this supplier changed AWAY from?
     * 
     * @param int $supplierId Supplier ID
     * @return int Number of reversions
     */
    public function getSupplierReversionCount(int $supplierId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM guarantee_timeline_events
            WHERE event_type = 'supplier_change'
              AND old_id = ?
        ");
        
        $stmt->execute([$supplierId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get bank usage count (for bank weights)
     * 
     * @param int $bankId Bank ID
     * @return int Number of times selected
     */
    public function getBankUsageCount(int $bankId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM guarantee_timeline_events
            WHERE event_type = 'bank_change'
              AND new_id = ?
        ");
        
        $stmt->execute([$bankId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get recent events (for monitoring/debugging)
     * 
     * @param int $limit Maximum results
     * @return array Recent events
     */
    public function getRecent(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guarantee_timeline_events
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get event statistics (for analytics dashboard)
     * 
     * @return array Statistics by event type
     */
    public function getEventStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                event_type,
                COUNT(*) as count,
                MIN(created_at) as first_event,
                MAX(created_at) as latest_event
            FROM guarantee_timeline_events
            GROUP BY event_type
            ORDER BY count DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get supplier change frequency (for pattern detection)
     * 
     * Returns which suppliers are changed most frequently
     * 
     * @param int $limit Top N suppliers
     * @return array Supplier change frequencies
     */
    public function getTopChangedSuppliers(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                new_value as supplier_name,
                new_id as supplier_id,
                COUNT(*) as change_count
            FROM guarantee_timeline_events
            WHERE event_type = 'supplier_change'
              AND new_id IS NOT NULL
            GROUP BY new_id
            ORDER BY change_count DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
