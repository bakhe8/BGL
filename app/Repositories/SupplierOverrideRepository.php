<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

class SupplierOverrideRepository
{
    /**
     * @return array<int, array{id:int, supplier_id:int, override_name:string, normalized_override:string}>
     */
    public function allNormalized(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, supplier_id, override_name, LOWER(override_name) as normalized_override FROM supplier_overrides');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
