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
