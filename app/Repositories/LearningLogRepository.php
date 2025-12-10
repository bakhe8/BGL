<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

class LearningLogRepository
{
    public function create(array $data): void
    {
        $this->ensureColumns();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO learning_log (raw_input, normalized_input, suggested_supplier_id, decision_result, candidate_source, score, score_raw) VALUES (:r, :n, :s, :d, :src, :score, :score_raw)');
        $stmt->execute([
            'r' => $data['raw_input'],
            'n' => $data['normalized_input'],
            's' => $data['suggested_supplier_id'] ?? null,
            'd' => $data['decision_result'],
            'src' => $data['candidate_source'] ?? null,
            'score' => $data['score'] ?? null,
            'score_raw' => $data['score_raw'] ?? null,
        ]);
    }

    private function ensureColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $pdo = Database::connection();
        $cols = [];
        $res = $pdo->query("PRAGMA table_info('learning_log')");
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $cols[] = $row['name'];
        }
        $missing = [
            'candidate_source' => "ALTER TABLE learning_log ADD COLUMN candidate_source TEXT NULL",
            'score' => "ALTER TABLE learning_log ADD COLUMN score REAL NULL",
            'score_raw' => "ALTER TABLE learning_log ADD COLUMN score_raw REAL NULL",
        ];
        foreach ($missing as $col => $sql) {
            if (!in_array($col, $cols, true)) {
                $pdo->exec($sql);
            }
        }
        $checked = true;
    }
}
