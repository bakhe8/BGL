<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\ImportSession;
use App\Support\Database;
use PDO;

class ImportSessionRepository
{
    public function create(string $sessionType): ImportSession
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO import_sessions (session_type, record_count) VALUES (:type, 0)');
        $stmt->execute(['type' => $sessionType]);

        $id = (int)$pdo->lastInsertId();
        return new ImportSession($id, $sessionType, 0, date('c'));
    }

    public function incrementRecordCount(int $sessionId, int $by = 1): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE import_sessions SET record_count = record_count + :by WHERE id = :id');
        $stmt->execute(['by' => $by, 'id' => $sessionId]);
    }

    /**
     * Get all sessions for dropdown selection
     */
    public function getAllSessions(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT 
                s.id as session_id,
                s.record_count,
                MAX(r.created_at) as last_date
            FROM import_sessions s
            LEFT JOIN imported_records r ON r.session_id = s.id
            GROUP BY s.id
            ORDER BY s.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get or create daily action session
     * Returns the session for today, creating it if it doesn't exist
     * 
     * @param string $sessionType Type of session (e.g., 'daily_actions')
     * @return ImportSession
     */
    public function getOrCreateDailySession(string $sessionType = 'daily_actions'): ImportSession
    {
        $pdo = Database::connection();
        $today = date('Y-m-d');
        
        // Try to find existing session for today
        $stmt = $pdo->prepare("
            SELECT id, session_type, record_count, created_at
            FROM import_sessions
            WHERE session_type = :type
              AND DATE(created_at) = :today
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'type' => $sessionType,
            'today' => $today
        ]);
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return new ImportSession(
                (int)$existing['id'],
                $existing['session_type'],
                (int)$existing['record_count'],
                $existing['created_at']
            );
        }
        
        // Create new daily session
        return $this->create($sessionType);
    }
}
