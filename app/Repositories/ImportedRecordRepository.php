<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\ImportedRecord;
use App\Support\Database;
use PDO;

class ImportedRecordRepository
{
    public function create(ImportedRecord $record): ImportedRecord
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO imported_records (
            session_id, raw_supplier_name, raw_bank_name, amount, guarantee_number, contract_number, contract_source, issue_date, expiry_date, type, comment,
            normalized_supplier, normalized_bank, match_status, supplier_id, bank_id, bank_display, supplier_display_name, created_at
        ) VALUES (
            :session_id, :raw_supplier_name, :raw_bank_name, :amount, :guarantee_number, :contract_number, :contract_source, :issue_date, :expiry_date, :type, :comment,
            :normalized_supplier, :normalized_bank, :match_status, :supplier_id, :bank_id, :bank_display, :supplier_display_name, :created_at
        )');

        $stmt->execute([
            'session_id' => $record->sessionId,
            'raw_supplier_name' => $record->rawSupplierName,
            'raw_bank_name' => $record->rawBankName,
            'amount' => $record->amount,
            'guarantee_number' => $record->guaranteeNumber,
            'contract_number' => $record->contractNumber,
            'contract_source' => $record->contractSource,
            'issue_date' => $record->issueDate,
            'expiry_date' => $record->expiryDate,
            'type' => $record->type,
            'comment' => $record->comment,
            'normalized_supplier' => $record->normalizedSupplier,
            'normalized_bank' => $record->normalizedBank,
            'match_status' => $record->matchStatus,
            'supplier_id' => $record->supplierId,
            'bank_id' => $record->bankId,
            'bank_display' => $record->bankDisplay,
            'supplier_display_name' => $record->supplierDisplayName ?? null,
            'created_at' => $record->createdAt ?? date('Y-m-d H:i:s'),
        ]);

        $record->id = (int)$pdo->lastInsertId();
        return $record;
    }

    public function updateDecision(int $id, array $data): void
    {
        $this->ensureDecisionColumns();
        $pdo = Database::connection();
        $fields = [];
        $params = ['id' => $id];

        $allowed = [
            'raw_supplier_name' => 'raw_supplier_name',
            'raw_bank_name' => 'raw_bank_name',
            'amount' => 'amount',
            'guarantee_number' => 'guarantee_number',
            'contract_number' => 'contract_number',
            'contract_source' => 'contract_source',
            'issue_date' => 'issue_date',
            'expiry_date' => 'expiry_date',
            'type' => 'type',
            'comment' => 'comment',
            'match_status' => 'match_status',
            'supplier_id' => 'supplier_id',
            'bank_id' => 'bank_id',
            'decision_result' => 'decision_result',
            'bank_display' => 'bank_display',
            'supplier_display_name' => 'supplier_display_name',
        ];

        foreach ($allowed as $key => $col) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$col} = :{$key}";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return;
        }

        $sql = 'UPDATE imported_records SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @return ImportedRecord[]
     */
    public function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM imported_records ORDER BY id DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => new ImportedRecord(
            (int)$row['id'],
            (int)$row['session_id'],
            $row['raw_supplier_name'],
            $row['raw_bank_name'],
            $row['amount'] ?? null,
            $row['guarantee_number'] ?? null,
            $row['contract_number'] ?? null,
            $row['contract_source'] ?? null,
            $row['issue_date'] ?? null,
            $row['expiry_date'] ?? null,
            $row['type'] ?? null,
            $row['comment'] ?? null,
            $row['normalized_supplier'] ?? null,
            $row['normalized_bank'] ?? null,
            $row['match_status'] ?? null,
            $row['supplier_id'] ? (int)$row['supplier_id'] : null,
            $row['bank_id'] ? (int)$row['bank_id'] : null,
            $row['bank_display'] ?? null,
            $row['supplier_display_name'] ?? null,
            $row['created_at'] ?? null,
        ), $rows);
    }

    /**
     * @return ImportedRecord[]
     */
    public function allBySession(int $sessionId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM imported_records WHERE session_id = :sid ORDER BY id DESC');
        $stmt->execute(['sid' => $sessionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => new ImportedRecord(
            (int)$row['id'],
            (int)$row['session_id'],
            $row['raw_supplier_name'],
            $row['raw_bank_name'],
            $row['amount'] ?? null,
            $row['guarantee_number'] ?? null,
            $row['contract_number'] ?? null,
            $row['contract_source'] ?? null,
            $row['issue_date'] ?? null,
            $row['expiry_date'] ?? null,
            $row['type'] ?? null,
            $row['comment'] ?? null,
            $row['normalized_supplier'] ?? null,
            $row['normalized_bank'] ?? null,
            $row['match_status'] ?? null,
            $row['supplier_id'] ? (int)$row['supplier_id'] : null,
            $row['bank_id'] ? (int)$row['bank_id'] : null,
            $row['bank_display'] ?? null,
            $row['supplier_display_name'] ?? null,
            $row['created_at'] ?? null,
        ), $rows);
    }

    public function find(int $id): ?ImportedRecord
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM imported_records WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return new ImportedRecord(
            (int)$row['id'],
            (int)$row['session_id'],
            $row['raw_supplier_name'],
            $row['raw_bank_name'],
            $row['amount'] ?? null,
            $row['guarantee_number'] ?? null,
            $row['contract_number'] ?? null,
            $row['contract_source'] ?? null,
            $row['issue_date'] ?? null,
            $row['expiry_date'] ?? null,
            $row['type'] ?? null,
            $row['comment'] ?? null,
            $row['normalized_supplier'] ?? null,
            $row['normalized_bank'] ?? null,
            $row['match_status'] ?? null,
            $row['supplier_id'] ? (int)$row['supplier_id'] : null,
            $row['bank_id'] ? (int)$row['bank_id'] : null,
            $row['bank_display'] ?? null,
            $row['supplier_display_name'] ?? null,
            $row['created_at'] ?? null,
        );
    }

    private function ensureDecisionColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $pdo = Database::connection();
        $cols = [];
        $res = $pdo->query("PRAGMA table_info('imported_records')");
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $cols[] = $row['name'];
        }
        if (!in_array('decision_result', $cols, true)) {
            $pdo->exec("ALTER TABLE imported_records ADD COLUMN decision_result TEXT NULL");
        }
        $checked = true;
    }
}
