<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\SupplierAlternativeName;
use App\Support\Database;
use PDO;

class SupplierAlternativeNameRepository
{
    public function findByNormalized(string $normalizedRawName): ?SupplierAlternativeName
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM supplier_alternative_names WHERE normalized_raw_name = :n LIMIT 1');
        $stmt->execute(['n' => $normalizedRawName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->map($row);
    }

    /**
     * @return array<int, array{id:int, supplier_id:int, raw_name:string, normalized_raw_name:string}>
     */
    public function findAllByNormalized(string $normalizedRawName): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, supplier_id, raw_name, normalized_raw_name FROM supplier_alternative_names WHERE normalized_raw_name = :n');
        $stmt->execute(['n' => $normalizedRawName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id:int, supplier_id:int, raw_name:string, normalized_raw_name:string}>
     */
    public function allNormalized(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, supplier_id, raw_name, normalized_raw_name FROM supplier_alternative_names');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
    * @return array<int, array{id:int, supplier_id:int, raw_name:string, normalized_raw_name:string}>
    */
    public function listBySupplier(int $supplierId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, supplier_id, raw_name, normalized_raw_name FROM supplier_alternative_names WHERE supplier_id = :sid');
        $stmt->execute(['sid' => $supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $supplierId, string $rawName, string $normalizedRawName, string $source = 'manual'): array
    {
        $pdo = Database::connection();
        // منع التكرار
        $existing = $this->findAllByNormalized($normalizedRawName);
        foreach ($existing as $ex) {
            if ((int)$ex['supplier_id'] === $supplierId) {
                return $ex;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO supplier_alternative_names (supplier_id, raw_name, normalized_raw_name, source) VALUES (:sid, :r, :n, :src)');
        $stmt->execute([
            'sid' => $supplierId,
            'r' => $rawName,
            'n' => $normalizedRawName,
            'src' => $source,
        ]);
        $id = (int)$pdo->lastInsertId();
        return [
            'id' => $id,
            'supplier_id' => $supplierId,
            'raw_name' => $rawName,
            'normalized_raw_name' => $normalizedRawName,
        ];
    }

    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM supplier_alternative_names WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function map(array $row): SupplierAlternativeName
    {
        return new SupplierAlternativeName(
            (int)$row['id'],
            (int)$row['supplier_id'],
            $row['raw_name'],
            $row['normalized_raw_name'],
            $row['source'],
            (int)$row['occurrence_count'],
            $row['last_seen_at'] ?? null,
        );
    }
}
