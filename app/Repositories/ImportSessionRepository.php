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
}
