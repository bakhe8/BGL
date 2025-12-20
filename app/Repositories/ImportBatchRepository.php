<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * Import Batch Repository
 * 
 * Purpose: Manages BATCHES for grouping IMPORTS from external sources
 * 
 * ⚠️ IMPORTANT: Batches are NOT for actions! Use ImportSessionRepository for actions.
 * 
 * Key Concepts:
 * - Batches group imported data by source (Excel file, manual entry, paste)
 * - Each Excel file = separate batch
 * - Manual entries/pastes = daily batch (one per day)
 * - Used for tracking import source and bulk updates
 * 
 * Batch Types:
 * - excel_import: One batch per Excel file
 * - manual_batch: Daily batch for manual entries
 * - text_paste: Daily batch for pasted text
 * 
 * Usage:
 * ```php
 * // Excel import
 * $batchId = $repo->create([
 *     'batch_type' => 'excel_import',
 *     'filename' => 'guarantees.xlsx'
 * ]);
 * 
 * // Manual entry (daily)
 * $batchId = $repo->getOrCreateDailyManualBatch();
 * 
 * // Then link guarantees to this batch
 * GuaranteeRepository::create([
 *     'import_batch_id' => $batchId,
 *     // ...
 * ]);
 * ```
 * 
 * DO NOT:
 * - Use batches for actions (use ImportSessionRepository instead)
 * - Create multiple batches for same Excel file
 * 
 * @see ImportSessionRepository For action grouping
 * @see docs/sessions-vs-batches.md For complete documentation
 */
class ImportBatchRepository
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Create a new import batch
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO import_batches (
                batch_type, description, filename, total_records, created_at
            ) VALUES (
                :batch_type, :description, :filename, :total_records, CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            'batch_type' => $data['batch_type'],
            'description' => $data['description'] ?? null,
            'filename' => $data['filename'] ?? null,
            'total_records' => $data['total_records'] ?? 0
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Get or create daily manual batch
     */
    public function getOrCreateDailyManualBatch(): int
    {
        $today = date('Y-m-d');
        
        // Look for existing batch today
        $stmt = $this->db->prepare("
            SELECT id FROM import_batches
            WHERE batch_type = 'manual_batch'
              AND DATE(created_at) = :today
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['today' => $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return (int)$result['id'];
        }
        
        // Create new batch
        return $this->create([
            'batch_type' => 'manual_batch',
            'description' => "إدخال يدوي - $today"
        ]);
    }
    
    /**
     * Get or create daily paste batch
     */
    public function getOrCreateDailyPasteBatch(): int
    {
        $today = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT id FROM import_batches
            WHERE batch_type = 'text_paste'
              AND DATE(created_at) = :today
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['today' => $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return (int)$result['id'];
        }
        
        return $this->create([
            'batch_type' => 'text_paste',
            'description' => "لصق مباشر - $today"
        ]);
    }
    
    /**
     * Increment record count
     */
    public function incrementRecordCount(int $batchId, int $by = 1): void
    {
        $this->db->exec("
            UPDATE import_batches 
            SET total_records = total_records + $by
            WHERE id = $batchId
        ");
    }
    
    /**
     * Increment ready count
     */
    public function incrementReadyCount(int $batchId, int $by = 1): void
    {
        $this->db->exec("
            UPDATE import_batches 
            SET ready_records = ready_records + $by
            WHERE id = $batchId
        ");
    }
    
    /**
     * Find batch by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM import_batches WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get all batches
     */
    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM import_batches 
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
