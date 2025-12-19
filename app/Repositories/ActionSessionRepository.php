<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * Action Session Repository
 * Manages action sessions (optional grouping for batch actions)
 */
class ActionSessionRepository
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Create new action session
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO action_sessions (
                session_date, description, total_actions, created_at
            ) VALUES (
                :session_date, :description, :total_actions, CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            'session_date' => $data['session_date'] ?? date('Y-m-d'),
            'description' => $data['description'] ?? null,
            'total_actions' => $data['total_actions'] ?? 0
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Get or create today's action session
     */
    public function getOrCreateDailySession(): int
    {
        $today = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT id FROM action_sessions
            WHERE session_date = :today
              AND is_locked = 0
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['today' => $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return (int)$result['id'];
        }
        
        return $this->create([
            'session_date' => $today,
            'description' => "جلسة إجراءات - $today"
        ]);
    }
    
    /**
     * Lock session (prevent modifications)
     */
    public function lock(int $sessionId): void
    {
        $this->db->exec("
            UPDATE action_sessions 
            SET is_locked = 1, locked_at = CURRENT_TIMESTAMP
            WHERE id = $sessionId
        ");
    }
    
    /**
     * Increment action count
     */
    public function incrementActionCount(int $sessionId, int $by = 1): void
    {
        $this->db->exec("
            UPDATE action_sessions 
            SET total_actions = total_actions + $by
            WHERE id = $sessionId
        ");
    }
    
    /**
     * Increment issued count
     */
    public function incrementIssuedCount(int $sessionId, int $by = 1): void
    {
        $this->db->exec("
            UPDATE action_sessions 
            SET issued_actions = issued_actions + $by
            WHERE id = $sessionId
        ");
    }
    
    /**
     * Find session by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM action_sessions WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
