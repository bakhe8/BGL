<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Settings;
use App\Repositories\SupplierRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankRepository;
use App\Repositories\BankAlternativeNameRepository;
use App\Support\Normalizer;

class SettingsController
{
    public function __construct(
        private Settings $settings = new Settings(),
        private SupplierRepository $suppliers = new SupplierRepository(),
        private SupplierAlternativeNameRepository $supplierAlts = new SupplierAlternativeNameRepository(),
        private BankRepository $banks = new BankRepository(),
        private BankAlternativeNameRepository $bankAlts = new BankAlternativeNameRepository(),
        private Normalizer $normalizer = new Normalizer(),
    ) {
    }

    public function all(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $this->settings->all()]);
    }

    public function save(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $saved = $this->settings->save($payload);
        echo json_encode(['success' => true, 'data' => $saved]);
    }

    public function backup(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $dir = storage_path('backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $timestamp = date('Ymd_His');
        $zipPath = $dir . "/backup_{$timestamp}.zip";
        $zipOk = class_exists(\ZipArchive::class);
        if ($zipOk) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
                $zip->addFile(storage_path('database/app.sqlite'), 'app.sqlite');
                $zip->addFile(storage_path('settings.json'), 'settings.json');
                $zip->close();
            } else {
                $zipOk = false;
            }
        }
        if (!$zipOk) {
            // نسخة بديلة: نسخ الملفات إلى مجلد فرعي
            $folder = $dir . "/backup_{$timestamp}";
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }
            @copy(storage_path('database/app.sqlite'), $folder . '/app.sqlite');
            @copy(storage_path('settings.json'), $folder . '/settings.json');
            $zipPath = $folder;
        }
        echo json_encode(['success' => true, 'path' => $zipPath]);
    }

    public function exportDictionary(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $dir = storage_path('backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $timestamp = date('Ymd_His');
        $path = $dir . "/dictionary_{$timestamp}.json";
        $data = [
            'suppliers' => $this->suppliers->allNormalized(),
            'supplier_alternatives' => $this->supplierAlts->allNormalized(),
            'banks' => $this->banks->allNormalized(),
            'bank_alternatives' => $this->bankAlts->allNormalized(),
        ];
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'path' => $path]);
    }

    public function importDictionary(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $dictionary = $payload['dictionary'] ?? $payload;
        if (!is_array($dictionary)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'تنسيق غير صالح للمعجم']);
            return;
        }
        $stats = ['suppliers' => 0, 'banks' => 0, 'supplier_alternatives' => 0, 'bank_alternatives' => 0];

        // Suppliers
        foreach ($dictionary['suppliers'] ?? [] as $sup) {
            $name = trim((string)($sup['official_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $norm = $this->normalizer->normalizeName($name);
            if ($this->suppliers->findByNormalizedName($norm)) {
                continue;
            }
            $this->suppliers->create([
                'official_name' => $name,
                'display_name' => $sup['display_name'] ?? null,
                'normalized_name' => $norm,
                'is_confirmed' => 1,
            ]);
            $stats['suppliers']++;
        }

        // Banks
        foreach ($dictionary['banks'] ?? [] as $bank) {
            $name = trim((string)($bank['official_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $norm = $this->normalizer->normalizeName($name);
            if ($this->banks->findByNormalizedName($norm)) {
                continue;
            }
            $this->banks->create([
                'official_name' => $name,
                'display_name' => $bank['display_name'] ?? null,
                'normalized_name' => $norm,
                'is_confirmed' => 1,
            ]);
            $stats['banks']++;
        }

        // Supplier alternatives
        foreach ($dictionary['supplier_alternatives'] ?? [] as $alt) {
            $sid = (int)($alt['supplier_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $raw = trim((string)($alt['raw_name'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $norm = $this->normalizer->normalizeName($raw);
            $this->supplierAlts->create($sid, $raw, $norm, $alt['source'] ?? 'import');
            $stats['supplier_alternatives']++;
        }

        // Bank alternatives
        foreach ($dictionary['bank_alternatives'] ?? [] as $alt) {
            $bid = (int)($alt['bank_id'] ?? 0);
            if ($bid <= 0) {
                continue;
            }
            $raw = trim((string)($alt['raw_name'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $norm = $this->normalizer->normalizeName($raw);
            $this->bankAlts->create($bid, $raw, $norm, $alt['source'] ?? 'import');
            $stats['bank_alternatives']++;
        }

        echo json_encode(['success' => true, 'stats' => $stats]);
    }
}
