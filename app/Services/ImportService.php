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
        $count = 0;

        foreach ($rows as $row) {
            // الترتيب المتوقع للأعمدة: المورد، البنك، المبلغ، رقم الضمان، تاريخ الانتهاء، تاريخ الإصدار
            $supplier = $row[0] ?? '';
            $bank = $row[1] ?? '';
            $amount = $row[2] ?? null;
            $guarantee = $row[3] ?? null;
            $expiry = $row[4] ?? null;
            $issue = $row[5] ?? null;

            if ($supplier === '' && $bank === '') {
                continue;
            }

            $record = new ImportedRecord(
                id: null,
                sessionId: $session->id ?? 0,
                rawSupplierName: (string)$supplier,
                rawBankName: (string)$bank,
                amount: $amount ? (string)$amount : null,
                guaranteeNumber: $guarantee ? (string)$guarantee : null,
                expiryDate: $expiry ? (string)$expiry : null,
                issueDate: $issue ? (string)$issue : null,
                matchStatus: 'needs_review',
            );
            $this->records->create($record);
            $this->sessions->incrementRecordCount($session->id ?? 0);
            $count++;
        }

        if ($count === 0) {
            throw new RuntimeException('لم يتم العثور على صفوف صالحة في الملف.');
        }

        return [
            'session_id' => $session->id,
            'records_count' => $count,
        ];
    }
}
