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
        $stmt = $pdo->prepare('SELECT * FROM banks WHERE normalized_key = :n LIMIT 1');
        $stmt->execute(['n' => $normalizedName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->map($row);
    }

    public function findByNormalizedKey(string $key): ?Bank
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM banks WHERE normalized_key = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->map($row) : null;
    }

    public function find(int $id): ?Bank
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM banks WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->map($row) : null;
    }

    private function map(array $row): Bank
    {
        return new Bank(
            (int) $row['id'],
            $row['official_name'],
            $row['official_name_en'] ?? null,
            $row['official_name_ar'] ?? $row['official_name'] ?? null,  // ✅ Fixed
            $row['normalized_key'] ?? null,
            $row['short_code'] ?? null,
            (int) ($row['is_confirmed'] ?? 0),
            $row['created_at'] ?? null,
        );
    }

    /** @return array<int, array{id:int, official_name:string, official_name_en:?string, normalized_key:?string, short_code:?string, is_confirmed:int, created_at:?string, updated_at:?string}> */
    public function allNormalized(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, official_name, official_name_en, normalized_key, short_code, is_confirmed, created_at, updated_at FROM banks');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id:int, official_name:string, official_name_en:?string, normalized_key:?string, short_code:?string, is_confirmed:int, created_at:?string, updated_at:?string}> */
    /** @return array<int, array{id:int, official_name:string, official_name_en:?string, normalized_key:?string, short_code:?string, is_confirmed:int, created_at:?string, updated_at:?string}> */
    public function search(string $normalizedLike): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, official_name, official_name_en, normalized_key, short_code, is_confirmed, created_at, updated_at FROM banks WHERE normalized_key LIKE :q OR official_name LIKE :q');
        $stmt->execute(['q' => "%{$normalizedLike}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): Bank
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO banks (official_name, official_name_en, normalized_key, short_code, is_confirmed) VALUES (:o, :oe, :nk, :sc, :c)');
        $stmt->execute([
            'o' => $data['official_name'],
            'oe' => $data['official_name_en'] ?? null,
            'nk' => $data['normalized_key'] ?? null,
            'sc' => $data['short_code'] ?? null,
            'c' => $data['is_confirmed'] ?? 0,
        ]);
        $id = (int) $pdo->lastInsertId();
        return new Bank($id, $data['official_name'], $data['official_name_en'] ?? null, $data['official_name'], $data['normalized_key'] ?? null, $data['short_code'] ?? null, (int) ($data['is_confirmed'] ?? 0), date('c'));
    }

    public function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE banks SET official_name=:o, official_name_en=:oe, normalized_key=:nk, short_code=:sc WHERE id=:id');
        $stmt->execute([
            'o' => $data['official_name'],
            'oe' => $data['official_name_en'] ?? null,
            'nk' => $data['normalized_key'] ?? null,
            'sc' => $data['short_code'] ?? null,
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
