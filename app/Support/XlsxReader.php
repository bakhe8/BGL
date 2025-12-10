<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxReader
{
    /**
     * قراءة ملف XLSX باستخدام ZipArchive لتحليل XML.
     *
     * @return array<int, array<int, string|null>>
     */
    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('فشل فتح ملف XLSX (ZipArchive).');
        }

        $sharedStringsXml = $this->getEntryContent($zip, 'xl/sharedStrings.xml');
        $sheetXml = $this->getFirstSheetContent($zip);

        $sharedStrings = $this->parseSharedStrings($sharedStringsXml);
        $rows = $this->parseSheet($sheetXml, $sharedStrings);

        $zip->close();
        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function parseSharedStrings(?string $xmlContent): array
    {
        if (!$xmlContent) {
            return [];
        }

        $xml = simplexml_load_string($xmlContent);
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
    private function parseSheet(?string $xmlContent, array $sharedStrings): array
    {
        if (!$xmlContent) {
            throw new RuntimeException('لم يتم العثور على الورقة الأولى داخل ملف XLSX.');
        }
        $xml = simplexml_load_string($xmlContent);
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

    private function getEntryContent(ZipArchive $zip, string $entry): ?string
    {
        $index = $zip->locateName($entry, ZipArchive::FL_NODIR);
        if ($index === false) {
            return null;
        }
        $stream = $zip->getStream($entry);
        if (!$stream) {
            return null;
        }
        $content = stream_get_contents($stream);
        fclose($stream);
        return $content === false ? null : $content;
    }

    private function getFirstSheetContent(ZipArchive $zip): ?string
    {
        // ابحث عن أول ملف داخل xl/worksheets/
        $first = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_NODIR);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }
            $name = $stat['name'];
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $first = $i;
                break;
            }
        }
        if ($first === null) {
            return null;
        }
        return $zip->getFromIndex($first) ?: null;
    }
}
