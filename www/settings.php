<?php
/**
 * Settings Page - PHP Version
 * 
 * صفحة الإعدادات الرئيسية - تعرض التبويبات الأربعة:
 * - الإعدادات العامة
 * - إدارة الموردين
 * - إدارة البنوك
 * - النسخ الاحتياطي
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Support\Settings;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Support\Config;

$settings = new Settings();
$suppliers = new SupplierRepository();
$banks = new BankRepository();

// Get current tab from query string
$activeTab = $_GET['tab'] ?? 'general';
$validTabs = ['general', 'suppliers', 'banks', 'system'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'general';
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_settings':
            $settingsData = [
                'MATCH_AUTO_THRESHOLD' => (float) ($_POST['autoTh'] ?? Config::MATCH_AUTO_THRESHOLD),
                'MATCH_REVIEW_THRESHOLD' => (float) ($_POST['revTh'] ?? Config::MATCH_REVIEW_THRESHOLD),
                'MATCH_WEAK_THRESHOLD' => (float) ($_POST['weakTh'] ?? 0.80),
                'CONFLICT_DELTA' => (float) ($_POST['confDelta'] ?? Config::CONFLICT_DELTA),
                'WEIGHT_OFFICIAL' => (float) ($_POST['wOfficial'] ?? Config::WEIGHT_OFFICIAL),
                'WEIGHT_ALT_CONFIRMED' => (float) ($_POST['wAltConf'] ?? Config::WEIGHT_ALT_CONFIRMED),
                'WEIGHT_ALT_LEARNED' => (float) ($_POST['wAltLearn'] ?? Config::WEIGHT_ALT_LEARNING),
                'WEIGHT_FUZZY' => (float) ($_POST['wFuzzy'] ?? Config::WEIGHT_FUZZY),
                'CANDIDATES_LIMIT' => (int) ($_POST['candLimit'] ?? 20),
            ];
            $settings->save($settingsData);
            $message = 'تم حفظ الإعدادات بنجاح';
            $messageType = 'success';
            break;
            
        case 'backup':
            $dir = storage_path('backups');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $timestamp = date('Ymd_His');
            $folder = $dir . "/backup_{$timestamp}";
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
            @copy(storage_path('database/app.sqlite'), $folder . '/app.sqlite');
            @copy(storage_path('settings.json'), $folder . '/settings.json');
            $message = "تم إنشاء النسخة الاحتياطية في: {$folder}";
            $messageType = 'success';
            break;
            
        case 'export_dictionary':
            $dir = storage_path('backups');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $timestamp = date('Ymd_His');
            $path = $dir . "/dictionary_{$timestamp}.json";
            $data = [
                'suppliers' => $suppliers->allNormalized(),
                'banks' => $banks->allNormalized(),
            ];
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = "تم تصدير القاموس إلى: {$path}";
            $messageType = 'success';
            break;
    }
}

// Load current settings
$currentSettings = $settings->all();
$settingsValues = [
    'autoTh' => $currentSettings['MATCH_AUTO_THRESHOLD'] ?? Config::MATCH_AUTO_THRESHOLD,
    'revTh' => $currentSettings['MATCH_REVIEW_THRESHOLD'] ?? Config::MATCH_REVIEW_THRESHOLD,
    'weakTh' => $currentSettings['MATCH_WEAK_THRESHOLD'] ?? 0.80,
    'confDelta' => $currentSettings['CONFLICT_DELTA'] ?? Config::CONFLICT_DELTA,
    'wOfficial' => $currentSettings['WEIGHT_OFFICIAL'] ?? Config::WEIGHT_OFFICIAL,
    'wAltConf' => $currentSettings['WEIGHT_ALT_CONFIRMED'] ?? Config::WEIGHT_ALT_CONFIRMED,
    'wAltLearn' => $currentSettings['WEIGHT_ALT_LEARNED'] ?? Config::WEIGHT_ALT_LEARNING,
    'wFuzzy' => $currentSettings['WEIGHT_FUZZY'] ?? Config::WEIGHT_FUZZY,
    'candLimit' => $currentSettings['CANDIDATES_LIMIT'] ?? 20,
];

// Load suppliers and banks for their respective tabs
$suppliersList = $suppliers->allNormalized();
$banksList = $banks->allNormalized();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - نظام خطابات الضمان</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="/assets/css/output.css">
    <style>
        .tabs-nav { display: flex; gap: 1rem; border-bottom: 2px solid var(--border-color); margin-bottom: 2rem; }
        .tab-link { padding: 0.75rem 1.5rem; font-weight: bold; color: var(--text-muted); text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .tab-link:hover { color: var(--text-color); }
        .tab-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .data-table th { text-align: right; padding: 0.75rem; background: var(--bg-hover); border-bottom: 2px solid var(--border-color); font-weight: bold; }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .data-table tr:hover { background: var(--bg-hover); }
        .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.8rem; border-radius: 4px; border: 1px solid; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-outline-primary { color: var(--primary-color); border-color: var(--primary-color); background: none; }
        .btn-outline-danger { color: #dc2626; border-color: #dc2626; background: none; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body class="app-shell">
    <!-- Shared Header -->
    <?php include __DIR__ . '/../app/Views/partials/subpage_header.php'; ?>

    <main class="app-main">
        <div class="app-container">
            <div class="page-header">
                <h1 class="page-title">إدارة النظام</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <div class="tabs-nav">
                <a href="?tab=general" class="tab-link <?= $activeTab === 'general' ? 'active' : '' ?>">الإعدادات العامة</a>
                <a href="?tab=suppliers" class="tab-link <?= $activeTab === 'suppliers' ? 'active' : '' ?>">إدارة الموردين</a>
                <a href="?tab=banks" class="tab-link <?= $activeTab === 'banks' ? 'active' : '' ?>">إدارة البنوك</a>
                <a href="?tab=system" class="tab-link <?= $activeTab === 'system' ? 'active' : '' ?>">النسخ الاحتياطي</a>
            </div>

            <!-- Tab: General Settings -->
            <?php if ($activeTab === 'general'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                
                <section class="card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">قواعد المطابقة</h2>
                    </div>
                    <div class="settings-grid">
                        <div class="form-group">
                            <label class="form-label">نسبة التطابق التلقائي (Auto Match)</label>
                            <input type="number" step="0.01" name="autoTh" class="form-control" value="<?= $settingsValues['autoTh'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">حد المراجعة (Review Threshold)</label>
                            <input type="number" step="0.01" name="revTh" class="form-control" value="<?= $settingsValues['revTh'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">حد التطابق الضعيف (Weak)</label>
                            <input type="number" step="0.01" name="weakTh" class="form-control" value="<?= $settingsValues['weakTh'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">هامش التعارض (Conflict Delta)</label>
                            <input type="number" step="0.01" name="confDelta" class="form-control" value="<?= $settingsValues['confDelta'] ?>">
                        </div>
                    </div>
                </section>

                <section class="card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">الأوزان (Weights)</h2>
                    </div>
                    <div class="settings-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم الرسمي</label>
                            <input type="number" step="0.05" name="wOfficial" class="form-control" value="<?= $settingsValues['wOfficial'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأسماء البديلة (مؤكدة)</label>
                            <input type="number" step="0.05" name="wAltConf" class="form-control" value="<?= $settingsValues['wAltConf'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأسماء البديلة (تعلم)</label>
                            <input type="number" step="0.05" name="wAltLearn" class="form-control" value="<?= $settingsValues['wAltLearn'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المطابقة التقريبية (Fuzzy)</label>
                            <input type="number" step="0.05" name="wFuzzy" class="form-control" value="<?= $settingsValues['wFuzzy'] ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label">عدد الاقتراحات</label>
                        <input type="number" name="candLimit" class="form-control" style="width:100px;" value="<?= $settingsValues['candLimit'] ?>">
                    </div>
                </section>

                <button type="submit" class="btn btn-primary" style="width:100%;">حفظ الإعدادات</button>
            </form>
            <?php endif; ?>

            <!-- Tab: Suppliers -->
            <?php if ($activeTab === 'suppliers'): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span>إجمالي الموردين: <strong><?= count($suppliersList) ?></strong></span>
                    <a href="/settings/supplier-form.php" class="btn btn-primary">+ إضافة مورد</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم الرسمي</th>
                            <th>الاسم المعياري</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliersList as $index => $supplier): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($supplier['official_name'] ?? '') ?></td>
                            <td><code><?= htmlspecialchars($supplier['normalized_name'] ?? '') ?></code></td>
                            <td>
                                <a href="/settings/supplier-form.php?id=<?= $supplier['id'] ?>" class="btn-xs btn-outline-primary">تعديل</a>
                                <a href="/settings/supplier-delete.php?id=<?= $supplier['id'] ?>" class="btn-xs btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المورد؟')">حذف</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Tab: Banks -->
            <?php if ($activeTab === 'banks'): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span>إجمالي البنوك: <strong><?= count($banksList) ?></strong></span>
                    <a href="/settings/bank-form.php" class="btn btn-primary">+ إضافة بنك</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم بالعربي</th>
                            <th>English Name</th>
                            <th>Short Code</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banksList as $index => $bank): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($bank['official_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($bank['official_name_en'] ?? '') ?></td>
                            <td><code><?= htmlspecialchars($bank['short_code'] ?? '') ?></code></td>
                            <td>
                                <a href="/settings/bank-form.php?id=<?= $bank['id'] ?>" class="btn-xs btn-outline-primary">تعديل</a>
                                <a href="/settings/bank-delete.php?id=<?= $bank['id'] ?>" class="btn-xs btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف هذا البنك؟')">حذف</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Tab: System (Backup) -->
            <?php if ($activeTab === 'system'): ?>
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">النسخ الاحتياطي</h2>
                </div>
                <div style="display: grid; gap: 1rem; margin-top: 1rem;">
                    <div style="padding: 1rem; background: #f9fafb; border-radius: 8px;">
                        <h3 style="font-weight: bold; margin-bottom: 0.5rem;">قاعدة البيانات</h3>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="btn btn-secondary">إنشاء نسخة احتياطية كاملة</button>
                        </form>
                    </div>
                    <div style="padding: 1rem; background: #f9fafb; border-radius: 8px;">
                        <h3 style="font-weight: bold; margin-bottom: 0.5rem;">قاموس البيانات (JSON)</h3>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="export_dictionary">
                            <button type="submit" class="btn btn-secondary">تصدير القاموس</button>
                        </form>
                    </div>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
