<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Settings;
use App\Repositories\SupplierRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankRepository;
use App\Support\Normalizer;

/**
 * Settings Controller
 * 
 * يتحكم في إعدادات التطبيق العامة والنسخ الاحتياطي للقواميس
 * 
 * الوظائف الرئيسية:
 * - عرض وحفظ الإعدادات العامة
 * - إنشاء نسخ احتياطية
 * - تصدير واستيراد القواميس (الموردين، البنوك)
 * 
 * @package App\Controllers
 */
class SettingsController
{
    /**
     * Constructor with auto-initialized dependencies
     * 
     * @param Settings $settings Service for app settings management
     * @param SupplierRepository $suppliers Repository for suppliers dictionary
     * @param SupplierAlternativeNameRepository $supplierAlts Repository for supplier aliases
     * @param BankRepository $banks Repository for banks dictionary
     * @param Normalizer $normalizer Text normalization utility
     */
    public function __construct(
        private Settings $settings = new Settings(),
        private SupplierRepository $suppliers = new SupplierRepository(),
        private SupplierAlternativeNameRepository $supplierAlts = new SupplierAlternativeNameRepository(),
        private BankRepository $banks = new BankRepository(),
        private Normalizer $normalizer = new Normalizer(),
    ) {
    }

    /**
     * Get all current settings
     * 
     * @return void JSON response with all settings key-value pairs
     */
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
            mkdir($dir, 0755, true);
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
                mkdir($folder, 0755, true);
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
            mkdir($dir, 0755, true);
        }
        $timestamp = date('Ymd_His');
        $path = $dir . "/dictionary_{$timestamp}.json";
        $data = [
            'suppliers' => $this->suppliers->allNormalized(),
            'supplier_alternatives' => $this->supplierAlts->allNormalized(),
            'banks' => $this->banks->allNormalized(),
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
        $stats = ['suppliers' => 0, 'banks' => 0, 'supplier_alternatives' => 0];

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
            $norm = $this->normalizer->normalizeBankName($name);
            if ($this->banks->findByNormalizedName($norm)) {
                continue;
            }
            $this->banks->create([
                'official_name' => $name,
                'official_name_en' => $bank['official_name_en'] ?? null,
                'normalized_key' => $bank['normalized_key'] ?? $norm,
                'short_code' => $bank['short_code'] ?? null,
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

        echo json_encode(['success' => true, 'stats' => $stats]);
    }
}
