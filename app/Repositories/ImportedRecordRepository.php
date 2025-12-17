<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\ImportedRecord;
use App\Support\Database;
use PDO;

class ImportedRecordRepository
{
    /**
     * إنشاء سجل مستورد جديد
     * 
     * @param ImportedRecord $record السجل المراد إنشاؤه
     * @return ImportedRecord السجل بعد الإنشاء مع ID
     * @throws \PDOException عند فشل الإدراج
     */
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

        $record->id = (int) $pdo->lastInsertId();
        return $record;
    }

    public function existsByGuarantee(string $guaranteeNumber, string $bankName): bool
    {
        $pdo = Database::connection();
        // Check for exact guarantee number match AND same bank (raw name)
        // efficient enough for now.
        $stmt = $pdo->prepare('SELECT 1 FROM imported_records WHERE guarantee_number = :g AND raw_bank_name = :b LIMIT 1');
        $stmt->execute(['g' => $guaranteeNumber, 'b' => $bankName]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * تحديث بيانات السجل (Update Full Record)
     */
    public function update(ImportedRecord $record): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE imported_records SET
            raw_supplier_name = :raw_supplier_name,
            raw_bank_name = :raw_bank_name,
            amount = :amount,
            guarantee_number = :guarantee_number,
            contract_number = :contract_number,
            contract_source = :contract_source,
            issue_date = :issue_date,
            expiry_date = :expiry_date,
            type = :type,
            comment = :comment,
            normalized_supplier = :normalized_supplier,
            normalized_bank = :normalized_bank,
            match_status = :match_status,
            supplier_id = :supplier_id,
            bank_id = :bank_id,
            bank_display = :bank_display,
            supplier_display_name = :supplier_display_name
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $record->id,
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
            'supplier_display_name' => $record->supplierDisplayName,
        ]);
    }

    /**
     * تحديث قرار المورد/البنك لسجل معين
     * 
     * @param int $id معرّف السجل
     * @param array $data البيانات المراد تحديثها (match_status, supplier_id, bank_id, etc)
     * @return void
     */
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
            'normalized_supplier' => 'normalized_supplier',
            'normalized_bank' => 'normalized_bank',
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
     * جلب جميع السجلات المستوردة
     * 
     * @return ImportedRecord[] مصفوفة من السجلات مرتبة حسب المعرف تنازلياً
     */
    public function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM imported_records ORDER BY id DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => $this->mapRow($row), $rows);
    }

    /**
     * جلب قائمة الجلسات المتاحة
     * 
     * @return array قائمة بالجلسات مع التواريخ وعدد السجلات
     */
    public function getAvailableSessions(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT session_id, COUNT(*) as record_count, MAX(created_at) as last_date 
            FROM imported_records 
            GROUP BY session_id 
            ORDER BY session_id DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب جميع السجلات المستوردة من جلسة معينة
     * 
     * @param int $sessionId معرّف الجلسة
     * @return ImportedRecord[] مصفوفة من السجلات مرتبة حسب المعرف تنازلياً
     */
    public function allBySession(int $sessionId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM imported_records WHERE session_id = :sid ORDER BY id DESC');
        $stmt->execute(['sid' => $sessionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => $this->mapRow($row), $rows);
    }

    /**
     * جلب سجل مستورد واحد بالمعرّف
     * 
     * @param int $id معرّف السجل
     * @return ImportedRecord|null السجل أو null إذا لم يُوجد
     */
    public function find(int $id): ?ImportedRecord
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM imported_records WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->mapRow($row);
    }

    /**
     * تحويل صف من قاعدة البيانات إلى ImportedRecord object
     * 
     * @param array $row صف من قاعدة البيانات
     * @return ImportedRecord
     */
    private function mapRow(array $row): ImportedRecord
    {
        return new ImportedRecord(
            (int) $row['id'],
            (int) $row['session_id'],
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
            $row['supplier_id'] ? (int) $row['supplier_id'] : null,
            $row['bank_id'] ? (int) $row['bank_id'] : null,
            $row['bank_display'] ?? null,
            $row['supplier_display_name'] ?? null,
            $row['created_at'] ?? null,
        );
    }

    public function getIdsBySessionAndRawSupplier(int $sessionId, string $rawName, int $excludeId): array
    {
        $pdo = Database::connection();
        // Ignore records that are currently being saved (excludeId)
        $stmt = $pdo->prepare('SELECT id FROM imported_records WHERE session_id = :sid AND raw_supplier_name = :r AND id != :ex');
        $stmt->execute(['sid' => $sessionId, 'r' => $rawName, 'ex' => $excludeId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getIdsBySessionAndRawBank(int $sessionId, string $rawName, int $excludeId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM imported_records WHERE session_id = :sid AND raw_bank_name = :r AND id != :ex');
        $stmt->execute(['sid' => $sessionId, 'r' => $rawName, 'ex' => $excludeId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * تحديث جماعي لجميع السجلات بنفس اسم المورد الخام في نفس الجلسة
     * يُستخدم لنشر القرار فورياً على السجلات المتشابهة
     * 
     * @return array قائمة بمعرّفات السجلات التي تم تحديثها
     */
    public function bulkUpdateSupplierByRawName(
        int $sessionId,
        string $rawSupplierName,
        int $excludeId,
        int $supplierId,
        ?string $supplierDisplayName = null
    ): array {
        $pdo = Database::connection();
        $updatedIds = [];

        // 1. Find records to be updated (Ready status)
        $sqlFindReady = 'SELECT id FROM imported_records 
                WHERE session_id = :sess 
                  AND raw_supplier_name = :raw 
                  AND id != :ex 
                  AND (supplier_id IS NULL OR supplier_id = 0)
                  AND bank_id IS NOT NULL AND bank_id > 0';
        
        $stmtFindReady = $pdo->prepare($sqlFindReady);
        $stmtFindReady->execute([
            'sess' => $sessionId,
            'raw' => $rawSupplierName,
            'ex' => $excludeId,
        ]);
        $idsReady = $stmtFindReady->fetchAll(PDO::FETCH_COLUMN);

        // 2. Find records to be updated (Review status)
        $sqlFindReview = 'SELECT id FROM imported_records 
                WHERE session_id = :sess 
                  AND raw_supplier_name = :raw 
                  AND id != :ex 
                  AND (supplier_id IS NULL OR supplier_id = 0)
                  AND (bank_id IS NULL OR bank_id = 0)';
        
        $stmtFindReview = $pdo->prepare($sqlFindReview);
        $stmtFindReview->execute([
            'sess' => $sessionId,
            'raw' => $rawSupplierName,
            'ex' => $excludeId,
        ]);
        $idsReview = $stmtFindReview->fetchAll(PDO::FETCH_COLUMN);

        // Merge all IDs
        $updatedIds = array_merge($idsReady, $idsReview);

        if (empty($updatedIds)) {
            return [];
        }

        // 3. Update 'Ready' records
        if (!empty($idsReady)) {
            $inQueryReady = implode(',', array_map('intval', $idsReady));
            $pdo->exec("UPDATE imported_records 
                       SET supplier_id = $supplierId, 
                           supplier_display_name = " . ($supplierDisplayName ? $pdo->quote($supplierDisplayName) : 'NULL') . ",
                           match_status = 'ready'
                       WHERE id IN ($inQueryReady)");
        }

        // 4. Update 'Review' records
        if (!empty($idsReview)) {
            $inQueryReview = implode(',', array_map('intval', $idsReview));
            $pdo->exec("UPDATE imported_records 
                       SET supplier_id = $supplierId, 
                           supplier_display_name = " . ($supplierDisplayName ? $pdo->quote($supplierDisplayName) : 'NULL') . ",
                           match_status = 'needs_review'
                       WHERE id IN ($inQueryReview)");
        }

        return $updatedIds;
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
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['name'];
        }
        if (!in_array('decision_result', $cols, true)) {
            $pdo->exec("ALTER TABLE imported_records ADD COLUMN decision_result TEXT NULL");
        }
        $checked = true;
    }
    public function getStats(): array
    {
        $pdo = Database::connection();
        
        // Total Records
        $total = $pdo->query('SELECT COUNT(*) FROM imported_records')->fetchColumn();
        
        // Completed (Ready or Approved)
        $completed = $pdo->query("SELECT COUNT(*) FROM imported_records WHERE match_status IN ('ready', 'approved')")->fetchColumn();
        
        // Pending
        $pending = $pdo->query("SELECT COUNT(*) FROM imported_records WHERE match_status IS NULL OR match_status = 'needs_review'")->fetchColumn();
        
        // Unique Suppliers
        $suppliers = $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();

        // 1. Status Distribution
        // "Completed" = ready/approved
        // "Pending" = needs_review/null
        // We can just query counts directly or group by. Since we already have counts, let's just use them.
        // Actually, let's get a break down for the chart: Ready vs Approved vs Needs Review vs New.
        // Simplified for Pie: Completed vs Pending.
        $statusDist = [
            'completed' => (int)$completed,
            'pending' => (int)$pending
        ];

        // 2. Top 5 Banks
        // Group by normalized bank or raw bank? Raw bank is what we found mostly.
        // Let's use raw_bank_name for now as it's the source of truth for volume.
        $stmt = $pdo->query("
            SELECT raw_bank_name, COUNT(*) as count 
            FROM imported_records 
            WHERE raw_bank_name IS NOT NULL AND raw_bank_name != ''
            GROUP BY raw_bank_name 
            ORDER BY count DESC 
            LIMIT 5
        ");
        $topBanks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_records' => (int)$total,
            'completed' => (int)$completed,
            'pending' => (int)$pending,
            'suppliers_count' => (int)$suppliers,
            'status_distribution' => $statusDist,
            'top_banks' => $topBanks
        ];
    }
}
