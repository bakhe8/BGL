<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class SupplierLearningRepository
{
    public function __construct()
    {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS supplier_aliases_learning (
                learning_id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_supplier_name TEXT NOT NULL,
                normalized_supplier_name TEXT NOT NULL UNIQUE,
                learning_status TEXT NOT NULL CHECK(learning_status IN ('supplier_alias','supplier_blocked')),
                linked_supplier_id INTEGER NOT NULL,
                learning_source TEXT DEFAULT 'review',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_supplier_learning_norm ON supplier_aliases_learning(normalized_supplier_name)");
    }

    /**
     * @return array{normalized_supplier_name:string,learning_status:string,linked_supplier_id:int,original_supplier_name:string,learning_source:?string}|null
     */
    public function findByNormalized(string $normalized): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT normalized_supplier_name, learning_status, linked_supplier_id, original_supplier_name, learning_source FROM supplier_aliases_learning WHERE normalized_supplier_name = :n LIMIT 1');
        $stmt->execute(['n' => $normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ══════════════════════════════════════════════════════════════════════
     * INCREMENT USAGE COUNT (NEW - 2025-12-17)
     * ══════════════════════════════════════════════════════════════════════
     * Called every time user saves a decision using a learned supplier name.
     * Tracks user preferences by incrementing usage_count and updating last_used_at.
     * 
     * @param string $normalized Normalized supplier name to find the record
     * @return bool True if successfully incremented
     */
    public function incrementUsage(string $normalized): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE supplier_aliases_learning 
            SET usage_count = COALESCE(usage_count, 0) + 1,
                last_used_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE normalized_supplier_name = ?
        ");
        
        return $stmt->execute([$normalized]);
    }
    
    /**
     * ══════════════════════════════════════════════════════════════════════
     * GET USAGE STATISTICS (NEW - 2025-12-17)
     * ══════════════════════════════════════════════════════════════════════
     * Returns all learned names for a supplier ordered by usage frequency.
     * Used by CandidateService to prioritize user's preferred names.
     * 
     * @param int $supplierId Supplier ID
     * @return array List of learned names with usage stats [
     *     ['original_supplier_name' => string, 'usage_count' => int, 'last_used_at' => string],
     *     ...
     * ]
     */
    public function getUsageStats(int $supplierId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT original_supplier_name, 
                   COALESCE(usage_count, 1) as usage_count, 
                   last_used_at
            FROM supplier_aliases_learning
            WHERE linked_supplier_id = ?
            AND learning_status = 'supplier_alias'
            ORDER BY usage_count DESC, last_used_at DESC
        ");
        
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ══════════════════════════════════════════════════════════════════════
     * UPSERT (MODIFIED - 2025-12-17)
     * ══════════════════════════════════════════════════════════════════════
     * CHANGE: Now increments usage_count when updating existing records
     */
    public function upsert(string $normalized, string $original, string $status, int $linkedSupplierId, string $source = 'review'): void
    {
        $pdo = Database::connection();
        $existing = $this->findByNormalized($normalized);
        
        // If exists with same status and supplier, increment usage
        if ($existing && $existing['learning_status'] === $status && (int)$existing['linked_supplier_id'] === $linkedSupplierId) {
            $this->incrementUsage($normalized);  // ← NEW: Track usage
            return;
        }
        // INSERT or UPDATE with usage tracking
        $stmt = $pdo->prepare("
            INSERT INTO supplier_aliases_learning (
                normalized_supplier_name, 
                original_supplier_name, 
                learning_status, 
                linked_supplier_id, 
                learning_source, 
                usage_count,
                last_used_at,
                updated_at
            )
            VALUES (:n, :o, :s, :sid, :src, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(normalized_supplier_name)
            DO UPDATE SET 
                learning_status = :s, 
                linked_supplier_id = :sid, 
                original_supplier_name = :o, 
                learning_source = :src,
                usage_count = COALESCE(supplier_aliases_learning.usage_count, 0) + 1,
                last_used_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            'n' => $normalized,
            'o' => $original,
            's' => $status,
            'sid' => $linkedSupplierId,
            'src' => $source,
        ]);
    }
}
