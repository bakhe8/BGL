<?php
declare(strict_types=1);

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class XlsxReader
{
    /**
     * قراءة ملف XLSX باستخدام PhpSpreadsheet
     *
     * @return array<int, array<int, string|null>>
     */
    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $cells[] = $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
            }
            $nonEmpty = array_filter($cells, fn($v) => $v !== null && $v !== '');
            if ($nonEmpty) {
                $rows[] = $cells;
            }
        }
        return $rows;
    }
}
