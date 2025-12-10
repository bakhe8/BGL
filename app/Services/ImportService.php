<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportedRecord;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Support\XlsxReader;
use RuntimeException;

class ImportService
{
    public function __construct(
        private ImportSessionRepository $sessions,
        private ImportedRecordRepository $records,
        private XlsxReader $xlsxReader = new XlsxReader(),
    ) {
    }

    /**
     * @return array{session_id:int, records_count:int}
     */
    public function importExcel(string $filePath): array
    {
        $session = $this->sessions->create('excel');

        $rows = $this->xlsxReader->read($filePath);
        if (count($rows) < 2) {
            throw new RuntimeException('لم يتم العثور على صفوف صالحة في الملف.');
        }

        // السطر الأول رؤوس
        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $map = $this->buildColumnMap($headers);

        $count = 0;
        foreach ($rows as $row) {
            $supplier = $this->colValue($row, $map['supplier'] ?? null);
            $bank = $this->colValue($row, $map['bank'] ?? null);
            $amount = $this->colValue($row, $map['amount'] ?? null);
            $guarantee = $this->colValue($row, $map['guarantee'] ?? null);
            $expiry = $this->colValue($row, $map['expiry'] ?? null);
            $issue = $this->colValue($row, $map['issue'] ?? null);

            if ($supplier === '' && $bank === '') {
                continue;
            }

            $record = new ImportedRecord(
                id: null,
                sessionId: $session->id ?? 0,
                rawSupplierName: (string)$supplier,
                rawBankName: (string)$bank,
                amount: $amount ?: null,
                guaranteeNumber: $guarantee ?: null,
                expiryDate: $expiry ?: null,
                issueDate: $issue ?: null,
                matchStatus: 'needs_review',
            );
            $this->records->create($record);
            $this->sessions->incrementRecordCount($session->id ?? 0);
            $count++;
        }

        return [
            'session_id' => $session->id,
            'records_count' => $count,
        ];
    }

    private function normalizeHeader(?string $h): string
    {
        $h = trim((string)$h);
        $h = preg_replace('/[^a-zA-Z0-9]+/u', '', strtolower($h));
        return $h ?? '';
    }

    /**
     * @param string[] $headers
     * @return array{supplier?:int,bank?:int,amount?:int,guarantee?:int,expiry?:int,issue?:int}
     */
    private function buildColumnMap(array $headers): array
    {
        $aliases = [
            'supplier' => ['supplier', 'contractorname'],
            'bank' => ['bankname'],
            'amount' => ['amount', 'bgamount'],
            'guarantee' => ['bankguranteenumber', 'bankguaranteenumber', 'bgguaranteenumber'],
            'expiry' => ['bgexpirydate', 'expirydate'],
            'issue' => ['validitydate', 'issuedate', 'today'],
        ];

        $map = [];
        foreach ($headers as $idx => $h) {
            foreach ($aliases as $field => $list) {
                if (in_array($h, $list, true) && !isset($map[$field])) {
                    $map[$field] = $idx;
                }
            }
        }
        return $map;
    }

    private function colValue(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }
        return isset($row[$index]) ? (string)$row[$index] : '';
    }
}
