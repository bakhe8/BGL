<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * يخزن قرارات التعلم الخاصة بأسماء البنوك (alias أو blocked) بآخر قرار فقط.
 */
class BankLearningRepository
{
    public function __construct()
    {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS bank_aliases_learning (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                input_name TEXT NOT NULL,
                normalized_input TEXT NOT NULL UNIQUE,
                status TEXT CHECK( status IN (\'alias\', \'blocked\') ),
                bank_id INTEGER,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /**
     * @return array{normalized_input:string,status:string,bank_id:?int,input_name:string}|null
     */
    public function findByNormalized(string $normalized): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT normalized_input, status, bank_id, input_name FROM bank_aliases_learning WHERE normalized_input = :n LIMIT 1');
        $stmt->execute(['n' => $normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * INCREMENT USAGE COUNT (NEW - 2025-12-17)
     * Tracks bank alias usage frequency
     */
    public function incrementUsage(string $normalized): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE bank_aliases_learning 
            SET usage_count = COALESCE(usage_count, 0) + 1,
                last_used_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE normalized_input = ?
        ");
        
        return $stmt->execute([$normalized]);
    }
    
    /**
     * GET USAGE STATISTICS (NEW - 2025-12-17)
     * Returns learned bank names ordered by usage frequency
     */
    public function getUsageStats(int $bankId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT input_name, 
                   COALESCE(usage_count, 1) as usage_count, 
                   last_used_at
            FROM bank_aliases_learning
            WHERE bank_id = ?
            AND status = 'alias'
            ORDER BY usage_count DESC, last_used_at DESC
        ");
        
        $stmt->execute([$bankId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * UPSERT (MODIFIED - 2025-12-17)
     * يحفظ آخر قرار للمستخدم (alias أو blocked) مع override تلقائي في حالة التكرار.
     * CHANGE: Now increments usage_count when updating
     */
    public function upsert(string $normalized, string $inputName, string $status, ?int $bankId = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO bank_aliases_learning (
                normalized_input, 
                input_name, 
                status, 
                bank_id,
                usage_count,
                last_used_at,
                updated_at
            )
            VALUES (:n, :r, :s, :bid, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(normalized_input) 
            DO UPDATE SET 
                status = :s, 
                bank_id = :bid, 
                input_name = :r,
                usage_count = COALESCE(bank_aliases_learning.usage_count, 0) + 1,
                last_used_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            'n' => $normalized,
            'r' => $inputName,
            's' => $status,
            'bid' => $bankId,
        ]);
    }
}
