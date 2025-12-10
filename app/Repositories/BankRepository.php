<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bank;
use App\Support\Database;
use PDO;

class BankRepository
{
    public function findByNormalizedName(string $normalizedName): ?Bank
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM banks WHERE normalized_name = :n LIMIT 1');
        $stmt->execute(['n' => $normalizedName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->map($row);
    }

    private function map(array $row): Bank
    {
        return new Bank(
            (int)$row['id'],
            $row['official_name'],
            $row['display_name'] ?? null,
            $row['normalized_name'],
            (int)($row['is_confirmed'] ?? 0),
            $row['created_at'] ?? null,
        );
    }

    /**
     * @return array<int, array{id:int, official_name:string, normalized_name:string}>
     */
    public function allNormalized(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, official_name, normalized_name FROM banks');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id:int, official_name:string, normalized_name:string}>
     */
    public function findAllByNormalized(string $normalizedName): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, official_name, normalized_name FROM banks WHERE normalized_name = :n');
        $stmt->execute(['n' => $normalizedName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id:int, official_name:string, normalized_name:string}>
     */
    public function search(string $normalizedLike): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, official_name, normalized_name FROM banks WHERE normalized_name LIKE :q');
        $stmt->execute(['q' => "%{$normalizedLike}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): Bank
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO banks (official_name, display_name, normalized_name, is_confirmed) VALUES (:o, :d, :n, :c)');
        $stmt->execute([
            'o' => $data['official_name'],
            'd' => $data['display_name'] ?? null,
            'n' => $data['normalized_name'],
            'c' => $data['is_confirmed'] ?? 0,
        ]);
        $id = (int)$pdo->lastInsertId();
        return new Bank($id, $data['official_name'], $data['display_name'] ?? null, $data['normalized_name'], (int)($data['is_confirmed'] ?? 0), date('c'));
    }

    public function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE banks SET official_name=:o, display_name=:d, normalized_name=:n WHERE id=:id');
        $stmt->execute([
            'o' => $data['official_name'],
            'd' => $data['display_name'] ?? null,
            'n' => $data['normalized_name'],
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM banks WHERE id=:id');
        $stmt->execute(['id' => $id]);
    }
}
