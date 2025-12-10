<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

class LearningLogRepository
{
    public function create(array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO learning_log (raw_input, normalized_input, suggested_supplier_id, decision_result) VALUES (:r, :n, :s, :d)');
        $stmt->execute([
            'r' => $data['raw_input'],
            'n' => $data['normalized_input'],
            's' => $data['suggested_supplier_id'] ?? null,
            'd' => $data['decision_result'],
        ]);
    }
}
