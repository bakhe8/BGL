<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BankAlternativeName;
use App\Support\Database;
use PDO;

class BankAlternativeNameRepository
{
    public function findByNormalized(string $normalizedRawName): ?BankAlternativeName
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM bank_alternative_names WHERE normalized_raw_name = :n LIMIT 1');
        $stmt->execute(['n' => $normalizedRawName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->map($row);
    }

    /**
     * @return array<int, array{id:int, bank_id:int, raw_name:string, normalized_raw_name:string}>
     */
    public function findAllByNormalized(string $normalizedRawName): array
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, bank_id, raw_name, normalized_raw_name FROM bank_alternative_names WHERE normalized_raw_name = :n');
        $stmt->execute(['n' => $normalizedRawName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id:int, bank_id:int, raw_name:string, normalized_raw_name:string}>
     */
    public function allNormalized(): array
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, bank_id, raw_name, normalized_raw_name FROM bank_alternative_names');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id:int, bank_id:int, raw_name:string, normalized_raw_name:string}>
     */
    public function listByBank(int $bankId): array
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, bank_id, raw_name, normalized_raw_name FROM bank_alternative_names WHERE bank_id = :bid');
        $stmt->execute(['bid' => $bankId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $bankId, string $rawName, string $normalizedRawName, string $source = 'manual'): array
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $existing = $this->findAllByNormalized($normalizedRawName);
        foreach ($existing as $ex) {
            if ((int)$ex['bank_id'] === $bankId) {
                return $ex;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO bank_alternative_names (bank_id, raw_name, normalized_raw_name, source) VALUES (:bid, :r, :n, :src)');
        $stmt->execute([
            'bid' => $bankId,
            'r' => $rawName,
            'n' => $normalizedRawName,
            'src' => $source,
        ]);
        $id = (int)$pdo->lastInsertId();
        return [
            'id' => $id,
            'bank_id' => $bankId,
            'raw_name' => $rawName,
            'normalized_raw_name' => $normalizedRawName,
        ];
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM bank_alternative_names WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function map(array $row): BankAlternativeName
    {
        return new BankAlternativeName(
            (int)$row['id'],
            (int)$row['bank_id'],
            $row['raw_name'],
            $row['normalized_raw_name'],
            $row['source'],
            (int)$row['occurrence_count'],
            $row['last_seen_at'] ?? null,
        );
    }

    private function ensureTable(): void
    {
        static $created = false;
        if ($created) {
            return;
        }
        $pdo = Database::connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS bank_alternative_names (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bank_id INTEGER NOT NULL,
            raw_name TEXT NOT NULL,
            normalized_raw_name TEXT NOT NULL,
            source TEXT DEFAULT \"manual\",
            occurrence_count INTEGER DEFAULT 0,
            last_seen_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
        $created = true;
    }
}
