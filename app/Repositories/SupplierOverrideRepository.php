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
        $this->ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, supplier_id, override_name, COALESCE(normalized_override, LOWER(override_name)) as normalized_override FROM supplier_overrides');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function ensureTable(): void
    {
        static $created = false;
        if ($created) {
            return;
        }
        $pdo = Database::connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS supplier_overrides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            supplier_id INTEGER NOT NULL,
            override_name TEXT NOT NULL,
            normalized_override TEXT,
            notes TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
        // تأكد من وجود العمود normalized_override في قواعد بيانات قديمة
        $cols = [];
        $res = $pdo->query("PRAGMA table_info('supplier_overrides')");
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $cols[] = $row['name'];
        }
        if (!in_array('normalized_override', $cols, true)) {
            $pdo->exec("ALTER TABLE supplier_overrides ADD COLUMN normalized_override TEXT");
        }
        $created = true;
    }
}
