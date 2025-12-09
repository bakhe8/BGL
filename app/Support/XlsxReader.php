<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;

class XlsxReader
{
    /**
     * قراءة ملف XLSX باستخدام Expand-Archive (PowerShell) ثم تحليل XML.
     *
     * @return array<int, array<int, string|null>>
     */
    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $tmpDir = storage_path('uploads/tmp_' . uniqid('', true));
        if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Failed to create temp dir');
        }

        $psCmd = 'Expand-Archive -Path ' . escapeshellarg($filePath) .
            ' -DestinationPath ' . escapeshellarg($tmpDir) . ' -Force';
        $cmd = 'powershell -NoLogo -NoProfile -Command "' . $psCmd . '"';
        exec($cmd, $_, $code);
        if ($code !== 0) {
            $this->cleanup($tmpDir);
            throw new RuntimeException('فشل فك ضغط ملف XLSX (تأكد من توفر PowerShell).');
        }

        $sharedStringsPath = $tmpDir . '/xl/sharedStrings.xml';
        $sheetPath = $tmpDir . '/xl/worksheets/sheet1.xml';

        $sharedStrings = $this->parseSharedStrings($sharedStringsPath);
        $rows = $this->parseSheet($sheetPath, $sharedStrings);

        $this->cleanup($tmpDir);
        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function parseSharedStrings(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $xml = simplexml_load_file($path);
        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $i => $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $run) {
                    $text .= (string)$run->t;
                }
            }
            $strings[(int)$i] = $text;
        }
        return $strings;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseSheet(string $path, array $sharedStrings): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException('لم يتم العثور على الورقة الأولى داخل ملف XLSX.');
        }
        $xml = simplexml_load_file($path);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('تعذر قراءة الورقة الأولى.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $cells[] = $this->cellValue($cell, $sharedStrings);
            }
            // تجاهل الصفوف الفارغة بالكامل
            $nonEmpty = array_filter($cells, fn($v) => $v !== null && $v !== '');
            if ($nonEmpty) {
                $rows[] = $cells;
            }
        }
        return $rows;
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string)($cell['t'] ?? '');
        if ($type === 's') {
            $idx = (int)$cell->v;
            return $sharedStrings[$idx] ?? '';
        }
        if ($type === 'inlineStr' && isset($cell->is->t)) {
            return (string)$cell->is->t;
        }
        if (isset($cell->v)) {
            return (string)$cell->v;
        }
        return null;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }
}
