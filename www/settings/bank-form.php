<?php
/**
 * Bank Form - Add/Edit Bank
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Repositories\BankRepository;
use App\Support\Normalizer;

$banks = new BankRepository();
$normalizer = new Normalizer();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$bank = $id ? $banks->find($id) : null;
$isEdit = $bank !== null;

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $officialName = trim($_POST['official_name'] ?? '');
    $officialNameEn = trim($_POST['official_name_en'] ?? '');
    $shortCode = trim($_POST['short_code'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $addressLine1 = trim($_POST['address_line_1'] ?? '');
    $addressLine2 = trim($_POST['address_line_2'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    
    if ($officialName === '') {
        $message = 'الاسم بالعربي مطلوب';
        $messageType = 'error';
    } else {
        $normalizedKey = $normalizer->normalizeBankName($officialName);
        
        try {
            $data = [
                'official_name' => $officialName,
                'official_name_en' => $officialNameEn ?: null,
                'short_code' => $shortCode ?: null,
                'normalized_key' => $normalizedKey,
                'department' => $department ?: null,
                'address_line_1' => $addressLine1 ?: null,
                'address_line_2' => $addressLine2 ?: null,
                'contact_email' => $contactEmail ?: null,
            ];
            
            if ($isEdit) {
                // Update
                $banks->update($id, $data);
                $message = 'تم تحديث البنك بنجاح';
                $messageType = 'success';
                // Refresh data
                $bank = $banks->find($id);
            } else {
                // Create
                $data['is_confirmed'] = 1;
                $banks->create($data);
                // Redirect to list
                header('Location: /settings.php?tab=banks&msg=created');
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
    <title><?= $isEdit ? 'تعديل البنك' : 'إضافة بنك' ?> - نظام خطابات الضمان</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .form-card { max-width: 700px; margin: 2rem auto; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-section { border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1.5rem; }
        .form-section-title { font-weight: bold; margin-bottom: 1rem; }
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
                <a href="/settings.php?tab=banks" class="app-nav-link is-active">الإعدادات</a>
            </nav>
        </div>
    </header>

    <main class="app-main">
        <div class="app-container">
            <div class="form-card card">
                <div class="card-header">
                    <h2 class="card-title"><?= $isEdit ? 'تعديل البنك' : 'إضافة بنك جديد' ?></h2>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم بالعربي *</label>
                            <input type="text" name="official_name" class="form-control" required
                                   value="<?= htmlspecialchars($bank->officialName ?? $_POST['official_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">English Name</label>
                            <input type="text" name="official_name_en" class="form-control"
                                   value="<?= htmlspecialchars($bank->officialNameEn ?? $_POST['official_name_en'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكود المختصر</label>
                            <input type="text" name="short_code" class="form-control"
                                   value="<?= htmlspecialchars($bank->shortCode ?? $_POST['short_code'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الإدارة (القسم)</label>
                            <input type="text" name="department" class="form-control"
                                   value="<?= htmlspecialchars($bank->department ?? $_POST['department'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">العنوان والاتصال</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">البريد الالكتروني</label>
                                <input type="email" name="contact_email" class="form-control"
                                       value="<?= htmlspecialchars($bank->contactEmail ?? $_POST['contact_email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">السطر الأول</label>
                                <input type="text" name="address_line_1" class="form-control"
                                       value="<?= htmlspecialchars($bank->addressLine1 ?? $_POST['address_line_1'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">السطر الثاني</label>
                                <input type="text" name="address_line_2" class="form-control"
                                       value="<?= htmlspecialchars($bank->addressLine2 ?? $_POST['address_line_2'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'تحديث' : 'إضافة' ?></button>
                        <a href="/settings.php?tab=banks" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
