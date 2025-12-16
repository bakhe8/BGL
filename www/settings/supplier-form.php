<?php
/**
 * Supplier Form - Add/Edit Supplier
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Repositories\SupplierRepository;
use App\Support\Normalizer;

$suppliers = new SupplierRepository();
$normalizer = new Normalizer();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$supplier = $id ? $suppliers->find($id) : null;
$isEdit = $supplier !== null;

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $officialName = trim($_POST['official_name'] ?? '');
    
    if ($officialName === '') {
        $message = 'الاسم الرسمي مطلوب';
        $messageType = 'error';
    } else {
        $normalized = $normalizer->normalizeSupplierName($officialName);
        $key = $normalizer->makeSupplierKey($officialName);
        
        try {
            if ($isEdit) {
                // Update
                $suppliers->update($id, [
                    'official_name' => $officialName,
                    'normalized_name' => $normalized,
                    'supplier_normalized_key' => $key,
                ]);
                $message = 'تم تحديث المورد بنجاح';
                $messageType = 'success';
                // Refresh data
                $supplier = $suppliers->find($id);
            } else {
                // Create
                $newSupplier = $suppliers->create([
                    'official_name' => $officialName,
                    'normalized_name' => $normalized,
                    'supplier_normalized_key' => $key,
                    'is_confirmed' => 1,
                ]);
                // Redirect to list
                header('Location: /settings.php?tab=suppliers&msg=created');
                exit;
            }
        } catch (\Throwable $e) {
            $message = 'خطأ: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'تعديل المورد' : 'إضافة مورد' ?> - نظام خطابات الضمان</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .form-card { max-width: 600px; margin: 2rem auto; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
    </style>
</head>
<body class="app-shell">
    <header class="app-header">
        <div class="app-header-inner">
            <div>
                <span class="app-logo">BL</span>
                <span class="app-title">نظام إدارة خطابات الضمان</span>
            </div>
            <nav class="app-nav">
                <a href="/" class="app-nav-link">الرئيسية</a>
                <a href="/settings.php?tab=suppliers" class="app-nav-link is-active">الإعدادات</a>
            </nav>
        </div>
    </header>

    <main class="app-main">
        <div class="app-container">
            <div class="form-card card">
                <div class="card-header">
                    <h2 class="card-title"><?= $isEdit ? 'تعديل المورد' : 'إضافة مورد جديد' ?></h2>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">الاسم الرسمي *</label>
                        <input type="text" name="official_name" class="form-control" required
                               value="<?= htmlspecialchars($supplier->officialName ?? $_POST['official_name'] ?? '') ?>">
                    </div>
                    
                    <?php if ($isEdit): ?>
                    <div class="form-group" style="margin-top: 1rem;">
                        <label class="form-label">الاسم المعياري (تلقائي)</label>
                        <input type="text" class="form-control" disabled
                               value="<?= htmlspecialchars($supplier->normalizedName ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'تحديث' : 'إضافة' ?></button>
                        <a href="/settings.php?tab=suppliers" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
