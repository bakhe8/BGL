<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * Guarantee Repository
 * Manages guarantee data (imports & manual entries)
 */
class GuaranteeRepository
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Create new guarantee record
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO guarantees (
                guarantee_number, raw_supplier_name, raw_bank_name,
                contract_number, amount, issue_date, expiry_date, type, comment,
                supplier_id, bank_id, supplier_display_name, bank_display, match_status,
                import_batch_id, import_type, import_date,
                created_at, updated_at
            ) VALUES (
                :guarantee_number, :raw_supplier_name, :raw_bank_name,
                :contract_number, :amount, :issue_date, :expiry_date, :type, :comment,
                :supplier_id, :bank_id, :supplier_display_name, :bank_display, :match_status,
                :import_batch_id, :import_type, :import_date,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
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
            'import_batch_id' => $data['import_batch_id'] ?? null,
            'import_type' => $data['import_type'],
            'import_date' => $data['import_date'] ?? date('Y-m-d')
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Update guarantee
     */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = ['id' => $id];
        
        $allowed = [
            'raw_supplier_name', 'raw_bank_name', 'contract_number', 'amount',
            'issue_date', 'expiry_date', 'type', 'comment',
            'supplier_id', 'bank_id', 'supplier_display_name', 'bank_display',
            'match_status'
        ];
        
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        $sql = "UPDATE guarantees SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * Find by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM guarantees WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find by guarantee number
     */
    public function findByNumber(string $guaranteeNumber): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guarantees 
            WHERE guarantee_number = ?
            ORDER BY import_date DESC, created_at DESC
        ");
        $stmt->execute([$guaranteeNumber]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all by batch
     */
    public function allByBatch(int $batchId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guarantees 
            WHERE import_batch_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Bulk update supplier by raw name and batch
     */
    public function bulkUpdateSupplier(int $batchId, string $rawName, int $supplierId, ?string $displayName): array
    {
        // Find records to update (not ready status)
        $stmt = $this->db->prepare("
            SELECT id FROM guarantees
            WHERE import_batch_id = :batch
              AND raw_supplier_name = :raw
              AND match_status != 'ready'
        ");
        $stmt->execute(['batch' => $batchId, 'raw' => $rawName]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids)) {
            return [];
        }
        
        // Update them
        $inQuery = implode(',', array_map('intval', $ids));
        $this->db->exec("
            UPDATE guarantees
            SET supplier_id = $supplierId,
                supplier_display_name = " . ($displayName ? $this->db->quote($displayName) : 'NULL') . ",
                match_status = CASE 
                    WHEN bank_id IS NOT NULL THEN 'ready'
                    ELSE 'needs_review'
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id IN ($inQuery)
        ");
        
        return $ids;
    }
}
