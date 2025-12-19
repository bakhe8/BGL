<?php
/**
 * User Decision Repository
 * 
 * Logs every user decision (selection or propagation).
 * Solves the "where did this name come from?" problem.
 * 
 * @see docs/09-Supplier-System-Refactoring.md
 */

namespace App\Repositories;

use App\Support\Database;
use PDO;

class UserDecisionRepository
{
    private PDO $db;
    
    // Decision source types
    public const SOURCE_USER_CLICK = 'user_click';      // User clicked on a chip
    public const SOURCE_USER_TYPED = 'user_typed';      // User typed a name
    public const SOURCE_AUTO_SELECT = 'auto_select';    // System auto-selected (99%+)
    public const SOURCE_PROPAGATION = 'propagation';    // Copied from another record
    public const SOURCE_IMPORT = 'import';              // Came from Excel
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Log a user decision
     * 
     * @param int $recordId The record being updated
     * @param int $sessionId Import session
     * @param string $rawName Original name from Excel
     * @param string $normalizedName Normalized version
     * @param int $supplierId Chosen supplier
     * @param string $displayName Display name used
     * @param string $source Decision source (use class constants)
     * @return int Inserted decision ID
     */
    public function logDecision(
        int $recordId,
        int $sessionId,
        string $rawName,
        string $normalizedName,
        int $supplierId,
        string $displayName,
        string $source
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO user_decisions (
                record_id, session_id, raw_name, normalized_name,
                chosen_supplier_id, chosen_display_name, decision_source,
                decided_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $recordId,
            $sessionId,
            $rawName,
            $normalizedName,
            $supplierId,
            $displayName,
            $source,
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Get the last decision for a record
     * Used for showing current selection badge
     * 
     * @return array|null Decision data or null if none
     */
    public function getLastDecision(int $recordId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                chosen_supplier_id,
                chosen_display_name,
                decision_source,
                decided_at
            FROM user_decisions
            WHERE record_id = ?
            ORDER BY decided_at DESC
            LIMIT 1
        ");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Get most frequently chosen suppliers for a normalized name
     * Useful for suggestions prioritization
     * 
     * @return array [{supplier_id, display_name, choice_count}, ...]
     */
    public function getMostChosenSuppliers(string $normalizedName, int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                chosen_supplier_id as supplier_id,
                chosen_display_name as display_name,
                COUNT(*) as choice_count
            FROM user_decisions
            WHERE normalized_name = ?
              AND decision_source != 'propagation'  -- Don't count propagated
            GROUP BY chosen_supplier_id
            ORDER BY choice_count DESC
            LIMIT ?
        ");
        $stmt->execute([$normalizedName, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get decision history for a record
     * Shows all changes made to a record over time
     */
    public function getRecordHistory(int $recordId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                ud.*,
                s.official_name as supplier_official_name
            FROM user_decisions ud
            LEFT JOIN suppliers s ON ud.chosen_supplier_id = s.id
            WHERE ud.record_id = ?
            ORDER BY ud.decided_at DESC
        ");
        $stmt->execute([$recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log multiple propagated decisions at once
     * 
     * @param array $recordIds Array of record IDs that were propagated
     * @param int $sessionId Session ID
     * @param string $rawName Raw name from Excel
     * @param string $normalizedName Normalized name
     * @param int $supplierId Chosen supplier
     * @param string $displayName Display name
     * @return int Count of decisions logged
     */
    public function logPropagation(
        array $recordIds,
        int $sessionId,
        string $rawName,
        string $normalizedName,
        int $supplierId,
        string $displayName
    ): int {
        $count = 0;
        foreach ($recordIds as $recordId) {
            $this->logDecision(
                $recordId,
                $sessionId,
                $rawName,
                $normalizedName,
                $supplierId,
                $displayName,
                self::SOURCE_PROPAGATION
            );
            $count++;
        }
        return $count;
    }
    
    // ═══════════════════════════════════════════════════════════════════
    // GLOBAL STATISTICS (NEW - 2025-12-19)
    // Used in stats.php for displaying decision intelligence
    // ═══════════════════════════════════════════════════════════════════
    
    /**
     * Get decision counts grouped by source (for charts)
     * @return array [{decision_source, count}, ...]
     */
    public function getDecisionsBySource(): array
    {
        return $this->db->query("
            SELECT decision_source, COUNT(*) as count 
            FROM user_decisions 
            GROUP BY decision_source
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get globally most chosen suppliers (across all names)
     * @return array [{supplier_id, display_name, choice_count}, ...]
     */
    public function getTopChosenSuppliersGlobal(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                chosen_supplier_id as supplier_id,
                chosen_display_name as display_name,
                COUNT(*) as choice_count
            FROM user_decisions
            WHERE decision_source NOT IN ('propagation', 'import')
            GROUP BY chosen_supplier_id
            ORDER BY choice_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get decision source display label
     * Used for showing badges in UI
     */
    public static function getSourceLabel(string $source): string
    {
        return match($source) {
            self::SOURCE_USER_CLICK => 'من الاقتراحات',
            self::SOURCE_USER_TYPED => 'حفظ المستخدم',
            self::SOURCE_AUTO_SELECT => 'تطابق تام',
            self::SOURCE_PROPAGATION => 'نُشر تلقائياً',
            self::SOURCE_IMPORT => 'من الاستيراد',
            default => 'الاختيار الحالي',
        };
    }
    
    /**
     * Get statistics for admin/debugging
     */
    public function getStatistics(): array
    {
        return [
            'total_decisions' => $this->db->query("SELECT COUNT(*) FROM user_decisions")->fetchColumn(),
            'by_source' => $this->db->query("
                SELECT decision_source, COUNT(*) as count 
                FROM user_decisions 
                GROUP BY decision_source
            ")->fetchAll(PDO::FETCH_KEY_PAIR),
            'by_session' => $this->db->query("
                SELECT session_id, COUNT(*) as count 
                FROM user_decisions 
                GROUP BY session_id 
                ORDER BY count DESC 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
