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
            'related_to' => $record->relatedTo,
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
            LIMIT 10
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
    public function getAdvancedStats(): array
    {
        $pdo = Database::connection();
        
        // 1. Financial Overview
        $fin = $pdo->query("
            SELECT 
                SUM(amount) as total_exposure,
                COUNT(*) as total_guarantees,
                AVG(amount) as avg_amount
            FROM imported_records
        ")->fetch(PDO::FETCH_ASSOC);

        // 2. Top Banks by Value (Financial Risk)
        // Groups by the Display Name if available, otherwise Raw Name
        $topBanksValue = $pdo->query("
            SELECT 
                COALESCE(NULLIF(bank_display, ''), raw_bank_name) as name, 
                SUM(amount) as total_value,
                COUNT(*) as count
            FROM imported_records 
            WHERE amount > 0 AND (bank_display IS NOT NULL OR raw_bank_name IS NOT NULL)
            GROUP BY COALESCE(NULLIF(bank_display, ''), raw_bank_name)
            ORDER BY total_value DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Automation Insight
        // 'ready' usually implies automatic high-confidence match
        $autoCount = $pdo->query("SELECT COUNT(*) FROM imported_records WHERE match_status = 'ready'")->fetchColumn();
        $total = $fin['total_guarantees'] ?: 1;
        $automationRate = round(($autoCount / $total) * 100, 1);

        // 4. Expiry Forecast (Next 12 Months)
        // Uses SQLite date functions assuming YYYY-MM-DD format
        $expiry = $pdo->query("
            SELECT 
                strftime('%Y-%m', expiry_date) as month,
                COUNT(*) as count,
                SUM(amount) as value
            FROM imported_records
            WHERE expiry_date IS NOT NULL AND expiry_date != '' 
            -- AND expiry_date >= date('now') -- Optional: only future
            GROUP BY month
            ORDER BY month ASC
            LIMIT 12
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. Top Suppliers by Volume
        $topSuppliers = $pdo->query("
            SELECT 
                COALESCE(NULLIF(supplier_display_name, ''), raw_supplier_name) as name,
                COUNT(*) as count,
                SUM(amount) as total_value
            FROM imported_records
            GROUP BY COALESCE(NULLIF(supplier_display_name, ''), raw_supplier_name)
            ORDER BY count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'financial' => $fin,
            'top_banks_value' => $topBanksValue,
            'automation_rate' => $automationRate,
            'expiry_forecast' => $expiry,
            'top_suppliers' => $topSuppliers
        ];
    }
    public function getDataQualityStats(): array
    {
        $pdo = Database::connection();
        
        // 1. Duplicate Guarantees (Potential Double Entry or Renewal History)
        $duplicates = $pdo->query("
            SELECT guarantee_number, COUNT(*) as count, 
                   GROUP_CONCAT(DISTINCT session_id) as sessions,
                   MAX(raw_bank_name) as bank
            FROM imported_records
            WHERE guarantee_number IS NOT NULL AND guarantee_number != ''
            GROUP BY guarantee_number
            HAVING count > 1
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 2. High Variation Suppliers (Messy Data Sources)
        // Suppliers that appear with many different raw name spellings
        $variations = $pdo->query("
            SELECT 
                COALESCE(supplier_display_name, 'Unknown') as official_name,
                COUNT(DISTINCT raw_supplier_name) as distinct_forms
            FROM imported_records
            WHERE supplier_id IS NOT NULL
            GROUP BY supplier_id
            HAVING distinct_forms > 1
            ORDER BY distinct_forms DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Most Frequent Manual Corrections
        // Records where the Raw Name differs significantly from the Final Name
        // indicating valuable work done by the system/user
        $corrections = $pdo->query("
            SELECT 
                raw_supplier_name, 
                supplier_display_name,
                COUNT(*) as count
            FROM imported_records
            WHERE supplier_id IS NOT NULL 
              AND raw_supplier_name != supplier_display_name
            GROUP BY raw_supplier_name, supplier_display_name
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'duplicate_guarantees' => $duplicates,
            'supplier_variations' => $variations,
            'common_corrections' => $corrections
        ];
    }

    /**
     * إحصائيات طرق الإدخال
     * Import Methods Statistics
     */
    public function getImportMethodStats(): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT 
                COALESCE(s.session_type, 'unknown') as source,
                COUNT(r.id) as count,
                SUM(CASE WHEN r.match_status IN ('ready', 'approved') THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN r.amount IS NOT NULL THEN CAST(r.amount AS REAL) ELSE 0 END) as total_value
            FROM imported_records r
            JOIN import_sessions s ON r.session_id = s.id
            GROUP BY s.session_type
            ORDER BY count DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * إحصائيات أنواع الضمانات
     * Guarantee Types Statistics
     */
    public function getGuaranteeTypeStats(): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT 
                COALESCE(UPPER(type), 'غير محدد') as type_name,
                COUNT(*) as count,
                SUM(CASE WHEN amount IS NOT NULL THEN CAST(amount AS REAL) ELSE 0 END) as total_value,
                AVG(CASE WHEN amount IS NOT NULL THEN CAST(amount AS REAL) ELSE NULL END) as avg_value
            FROM imported_records
            GROUP BY UPPER(type)
            ORDER BY count DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * أكثر الموردين نشاطاً (بالعدد)
     * Top Suppliers by Count
     */
    public function getTopSuppliersByCount(int $limit = 10): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT 
                s.official_name as name,
                COUNT(r.id) as count,
                ROUND(COUNT(r.id) * 100.0 / (SELECT COUNT(*) FROM imported_records), 2) as percentage
            FROM imported_records r
            JOIN suppliers s ON r.supplier_id = s.id
            WHERE r.supplier_id IS NOT NULL
            GROUP BY s.id, s.official_name
            ORDER BY count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * أكثر الموردين بالقيمة المالية
     * Top Suppliers by Value
     */
    public function getTopSuppliersByValue(int $limit = 10): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT 
                s.official_name as name,
                SUM(CAST(r.amount AS REAL)) as total_value,
                AVG(CAST(r.amount AS REAL)) as avg_value,
                COUNT(*) as count
            FROM imported_records r
            JOIN suppliers s ON r.supplier_id = s.id
            WHERE r.supplier_id IS NOT NULL AND r.amount IS NOT NULL AND r.amount != ''
            GROUP BY s.id, s.official_name
            ORDER BY total_value DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * الضمانات القريبة من الانتهاء
     * Expiring Guarantees Alert
     */
    public function getExpiringGuarantees(int $days = 30): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                guarantee_number,
                COALESCE(supplier_display_name, raw_supplier_name) as supplier,
                COALESCE(bank_display, raw_bank_name) as bank,
                expiry_date,
                amount,
                CAST(julianday(expiry_date) - julianday('now') AS INTEGER) as days_remaining
            FROM imported_records
            WHERE expiry_date IS NOT NULL 
              AND expiry_date != ''
              AND julianday(expiry_date) - julianday('now') BETWEEN 0 AND :days
            ORDER BY days_remaining ASC
            LIMIT 20
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * السجلات المعلقة (الأقدم أولاً)
     * Oldest Pending Records (No specific age limit)
     */
    public function getOldIncompleteRecords(int $limit = 20): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                COALESCE(supplier_display_name, raw_supplier_name) as supplier,
                COALESCE(bank_display, raw_bank_name) as bank,
                guarantee_number,
                created_at,
                CAST(julianday('now') - julianday(created_at) AS INTEGER) as age_days
            FROM imported_records
            WHERE match_status IN ('needs_review', 'pending')
            -- User requested to remove 7-day filter as guarantees last a year
            -- showing oldest pending first
            ORDER BY created_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * اتجاهات زمنية - السجلات حسب الشهر
     * Temporal Trends
     */
    public function getTemporalTrends(int $months = 12): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT 
                strftime('%Y-%m', created_at) as month,
                COUNT(*) as count,
                SUM(CASE WHEN amount IS NOT NULL THEN CAST(amount AS REAL) ELSE 0 END) as total_value
            FROM imported_records
            WHERE created_at >= date('now', '-' || :months || ' months')
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * مقارنة العقود مقابل أوامر الشراء
     * Contracts vs Purchase Orders Analysis
     */
    public function getContractVsPOStats(): array
    {
        $pdo = Database::connection();
        
        // Now using actual related_to field instead of heuristic
        $stmt = $pdo->query("
            SELECT 
                related_to as doc_type,
                COUNT(*) as count,
                SUM(CASE WHEN amount IS NOT NULL THEN CAST(amount AS REAL) ELSE 0 END) as total_value,
                AVG(CASE WHEN amount IS NOT NULL THEN CAST(amount AS REAL) ELSE NULL END) as avg_value
            FROM imported_records
            WHERE related_to IS NOT NULL
            GROUP BY related_to
            ORDER BY total_value DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

