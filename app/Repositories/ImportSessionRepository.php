<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\ImportSession;
use App\Support\Database;
use PDO;

/**
 * Import Session Repository
 * 
 * Purpose: Manages SESSIONS for grouping ACTIONS (extensions, releases, modifications)
 * 
 * ⚠️ IMPORTANT: Sessions are NOT for imports! Use ImportBatchRepository for imports.
 * 
 * Key Concepts:
 * - Sessions group DAILY ACTIONS performed within the system
 * - One session per day for all actions (daily_actions)
 * - Used for audit trail and navigation
 * 
 * Usage:
 * ```php
 * // Get or create today's session
 * $session = $repo->getOrCreateDailySession('daily_actions');
 * 
 * // Create an action record
 * $record = ImportedRecordRepository::create([
 *     'session_id' => $session->id,
 *     'record_type' => 'extension_action',
 *     // ...
 * ]);
 * ```
 * 
 * DO NOT:
 * - Create a new session for each action (use getOrCreateDailySession instead)
 * - Use sessions for imports (use ImportBatchRepository instead)
 * 
 * @see ImportBatchRepository For import grouping
 * @see docs/sessions-vs-batches.md For complete documentation
 */
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
     * ⚠️ CRITICAL: Use this method for ALL actions (extensions, releases)
     * DO NOT create a new session for each action!
     * 
     * How it works:
     * 1. Searches for a session created today with the given type
     * 2. If found, returns the existing session
     * 3. If not found, creates a new session for today
     * 
     * Example:
     * ```php
     * // Morning: First extension of the day
     * $session = $repo->getOrCreateDailySession('daily_actions');
     * // Creates Session #500
     * 
     * // Afternoon: Another extension
     * $session = $repo->getOrCreateDailySession('daily_actions');
     * // Returns SAME Session #500 (not a new one!)
     * ```
     * 
     * Result: All actions performed on the same day share ONE session
     * 
     * @param string $sessionType Type of session (default: 'daily_actions')
     * @return ImportSession The daily session
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
