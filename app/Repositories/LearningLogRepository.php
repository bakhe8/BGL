<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

class LearningLogRepository
{
    public function create(array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO learning_log (raw_input, normalized_input, suggested_supplier_id, decision_result, candidate_source, score, score_raw, created_at) VALUES (:r, :n, :s, :d, :src, :score, :score_raw, :ts)');
        $stmt->execute([
            'r' => $data['raw_input'],
            'n' => $data['normalized_input'],
            's' => $data['suggested_supplier_id'] ?? null,
            'd' => $data['decision_result'],
            'src' => $data['candidate_source'] ?? null,
            'score' => $data['score'] ?? null,
            'score_raw' => $data['score_raw'] ?? null,
            'ts' => $data['created_at'] ?? date('c'),
        ]);
    }

    public function createBank(array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO bank_learning_log (raw_input, normalized_input, suggested_bank_id, decision_result, candidate_source, score, score_raw, created_at) VALUES (:r, :n, :b, :d, :src, :score, :score_raw, :ts)');
        $stmt->execute([
            'r' => $data['raw_input'],
            'n' => $data['normalized_input'],
            'b' => $data['suggested_bank_id'] ?? null,
            'd' => $data['decision_result'],
            'src' => $data['candidate_source'] ?? null,
            'score' => $data['score'] ?? null,
            'score_raw' => $data['score_raw'] ?? null,
            'ts' => $data['created_at'] ?? date('c'),
        ]);
    }
}
