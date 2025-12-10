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
            session_id, raw_supplier_name, raw_bank_name, amount, guarantee_number, issue_date, expiry_date,
            normalized_supplier, normalized_bank, match_status, supplier_id, bank_id
        ) VALUES (
            :session_id, :raw_supplier_name, :raw_bank_name, :amount, :guarantee_number, :issue_date, :expiry_date,
            :normalized_supplier, :normalized_bank, :match_status, :supplier_id, :bank_id
        )');

        $stmt->execute([
            'session_id' => $record->sessionId,
            'raw_supplier_name' => $record->rawSupplierName,
            'raw_bank_name' => $record->rawBankName,
            'amount' => $record->amount,
            'guarantee_number' => $record->guaranteeNumber,
            'issue_date' => $record->issueDate,
            'expiry_date' => $record->expiryDate,
            'normalized_supplier' => $record->normalizedSupplier,
            'normalized_bank' => $record->normalizedBank,
            'match_status' => $record->matchStatus,
            'supplier_id' => $record->supplierId,
            'bank_id' => $record->bankId,
        ]);

        $record->id = (int)$pdo->lastInsertId();
        return $record;
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
            $row['issue_date'] ?? null,
            $row['expiry_date'] ?? null,
            $row['normalized_supplier'] ?? null,
            $row['normalized_bank'] ?? null,
            $row['match_status'] ?? null,
            $row['supplier_id'] ? (int)$row['supplier_id'] : null,
            $row['bank_id'] ? (int)$row['bank_id'] : null,
            $row['created_at'] ?? null,
        ), $rows);
    }
}
