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
}
