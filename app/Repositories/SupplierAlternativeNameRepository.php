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
