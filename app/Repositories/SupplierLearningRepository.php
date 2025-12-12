<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class SupplierLearningRepository
{
    public function __construct()
    {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS supplier_aliases_learning (
                learning_id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_supplier_name TEXT NOT NULL,
                normalized_supplier_name TEXT NOT NULL UNIQUE,
                learning_status TEXT NOT NULL CHECK(learning_status IN ('supplier_alias','supplier_blocked')),
                linked_supplier_id INTEGER NOT NULL,
                learning_source TEXT DEFAULT 'review',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_supplier_learning_norm ON supplier_aliases_learning(normalized_supplier_name)");
    }

    /**
     * @return array{normalized_supplier_name:string,learning_status:string,linked_supplier_id:int,original_supplier_name:string,learning_source:?string}|null
     */
    public function findByNormalized(string $normalized): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT normalized_supplier_name, learning_status, linked_supplier_id, original_supplier_name, learning_source FROM supplier_aliases_learning WHERE normalized_supplier_name = :n LIMIT 1');
        $stmt->execute(['n' => $normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsert(string $normalized, string $original, string $status, int $linkedSupplierId, string $source = 'review'): void
    {
        $pdo = Database::connection();
        $existing = $this->findByNormalized($normalized);
        if ($existing && $existing['learning_status'] === $status && (int)$existing['linked_supplier_id'] === $linkedSupplierId) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO supplier_aliases_learning (normalized_supplier_name, original_supplier_name, learning_status, linked_supplier_id, learning_source, updated_at)
             VALUES (:n, :o, :s, :sid, :src, CURRENT_TIMESTAMP)
             ON CONFLICT(normalized_supplier_name)
             DO UPDATE SET learning_status=:s, linked_supplier_id=:sid, original_supplier_name=:o, learning_source=:src, updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'n' => $normalized,
            'o' => $original,
            's' => $status,
            'sid' => $linkedSupplierId,
            'src' => $source,
        ]);
    }
}
