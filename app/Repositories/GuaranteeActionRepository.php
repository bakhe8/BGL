<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * Guarantee Action Repository
 * Manages guarantee actions (extension, release, etc)
 */
class GuaranteeActionRepository
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Create new action
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO guarantee_actions (
                guarantee_number, guarantee_id,
                action_type, action_session_id, action_date,
                previous_expiry_date, new_expiry_date,
                previous_amount, new_amount, notes,
                supplier_id, bank_id, supplier_display_name, bank_display,
                action_status, is_locked,
                created_at, created_by, issued_at
            ) VALUES (
                :guarantee_number, :guarantee_id,
                :action_type, :action_session_id, :action_date,
                :previous_expiry_date, :new_expiry_date,
                :previous_amount, :new_amount, :notes,
                :supplier_id, :bank_id, :supplier_display_name, :bank_display,
                :action_status, :is_locked,
                CURRENT_TIMESTAMP, :created_by, :issued_at
            )
        ");
        
        $stmt->execute([
            'guarantee_number' => $data['guarantee_number'],
            'guarantee_id' => $data['guarantee_id'] ?? null,
            'action_type' => $data['action_type'],
            'action_session_id' => $data['action_session_id'] ?? null,
            'action_date' => $data['action_date'] ?? date('Y-m-d'),
            'previous_expiry_date' => $data['previous_expiry_date'] ?? null,
            'new_expiry_date' => $data['new_expiry_date'] ?? null,
            'previous_amount' => $data['previous_amount'] ?? null,
            'new_amount' => $data['new_amount'] ?? null,
            'notes' => $data['notes'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'bank_id' => $data['bank_id'] ?? null,
            'supplier_display_name' => $data['supplier_display_name'] ?? null,
            'bank_display' => $data['bank_display'] ?? null,
            'action_status' => $data['action_status'] ?? 'draft',
            'is_locked' => $data['is_locked'] ?? 0,
            'created_by' => $data['created_by'] ?? null,
            'issued_at' => $data['issued_at'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Find by guarantee number
     */
    public function findByGuaranteeNumber(string $guaranteeNumber): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guarantee_actions
            WHERE guarantee_number = ?
            ORDER BY action_date DESC, created_at DESC
        ");
        $stmt->execute([$guaranteeNumber]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark as issued (and lock)
     */
    public function markAsIssued(int $actionId): void
    {
        $this->db->exec("
            UPDATE guarantee_actions
            SET action_status = 'issued',
                is_locked = 1,
                issued_at = CURRENT_TIMESTAMP
            WHERE id = $actionId
        ");
    }
    
    /**
     * Find by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM guarantee_actions WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
