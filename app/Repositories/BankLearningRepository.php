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
     * يحفظ آخر قرار للمستخدم (alias أو blocked) مع override تلقائي في حالة التكرار.
     */
    public function upsert(string $normalized, string $inputName, string $status, ?int $bankId = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO bank_aliases_learning (normalized_input, input_name, status, bank_id, updated_at)
             VALUES (:n, :r, :s, :bid, CURRENT_TIMESTAMP)
             ON CONFLICT(normalized_input) DO UPDATE SET status=:s, bank_id=:bid, input_name=:r, updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'n' => $normalized,
            'r' => $inputName,
            's' => $status,
            'bid' => $bankId,
        ]);
    }
}
