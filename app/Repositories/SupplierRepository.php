<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Supplier;
use App\Support\Database;
use PDO;

class SupplierRepository
{
    /**
     * @return array<int, array{id:int, official_name:string, display_name:?string, normalized_name:string}>
     */
    public function findAllByNormalized(string $normalizedName): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, official_name, display_name, normalized_name FROM suppliers WHERE normalized_name = :n');
        $stmt->execute(['n' => $normalizedName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id:int, official_name:string, normalized_name:string}>
     */
    public function allNormalized(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, official_name, normalized_name FROM suppliers');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByNormalizedName(string $normalizedName): ?Supplier
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE normalized_name = :n LIMIT 1');
        $stmt->execute(['n' => $normalizedName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->map($row);
    }

    public function create(array $data): Supplier
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO suppliers (official_name, display_name, normalized_name, is_confirmed) VALUES (:o, :d, :n, :c)');
        $stmt->execute([
            'o' => $data['official_name'],
            'd' => $data['display_name'] ?? null,
            'n' => $data['normalized_name'],
            'c' => $data['is_confirmed'] ?? 0,
        ]);
        $id = (int)$pdo->lastInsertId();
        return new Supplier($id, $data['official_name'], $data['display_name'] ?? null, $data['normalized_name'], (int)($data['is_confirmed'] ?? 0), date('c'));
    }

    private function map(array $row): Supplier
    {
        return new Supplier(
            (int)$row['id'],
            $row['official_name'],
            $row['display_name'] ?? null,
            $row['normalized_name'],
            (int)$row['is_confirmed'],
            $row['created_at'] ?? null,
        );
    }
}
