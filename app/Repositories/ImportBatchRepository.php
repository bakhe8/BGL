<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * Import Batch Repository
 * Manages import batches (groups of imported guarantees)
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
