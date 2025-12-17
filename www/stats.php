<?php
/**
 * Statistics Page - PHP Version
 * 
 * صفحة الإحصائيات - تعرض ملخص البيانات بدون JavaScript
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;

$records = new ImportedRecordRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();

// Get stats
$stats = $records->getStats();

$totalRecords = $stats['total_records'] ?? 0;
$completed = $stats['completed'] ?? 0;
$pending = $stats['pending'] ?? 0;
$suppliersCount = $stats['suppliers_count'] ?? 0;
$topBanks = $stats['top_banks'] ?? [];

// Calculate percentages
$completionRate = $totalRecords > 0 ? round(($completed / $totalRecords) * 100) : 0;

// Get banks count
$banksCount = count($banks->allNormalized());
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإحصائيات - نظام خطابات الضمان</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="/assets/css/output.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .stat-card-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
        .stat-card-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.25rem; }
        .stat-card-label { color: #6b7280; font-size: 0.9rem; }
        .progress-bar { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-top: 0.5rem; }
        .progress-fill { height: 100%; border-radius: 4px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: right; padding: 0.75rem; background: var(--bg-hover); border-bottom: 2px solid var(--border-color); }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .data-table tr:hover { background: var(--bg-hover); }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: bold; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-orange { background: #ffedd5; color: #c2410c; }
    </style>
</head>
<body class="app-shell">
    <!-- Shared Header -->
    <?php include __DIR__ . '/../app/Views/partials/subpage_header.php'; ?>

    <main class="app-main">
        <div class="app-container">
            <div class="page-header">
                <h1 class="page-title">لوحة الإحصائيات</h1>
                <p class="section-subtitle">ملخص أداء النظام والبيانات</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <!-- Card 1: Total -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #dbeafe; color: #2563eb;">
                        <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($totalRecords) ?></div>
                    <div class="stat-card-label">إجمالي الخطابات</div>
                </div>
                
                <!-- Card 2: Completed -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #d1fae5; color: #16a34a;">
                        <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-card-value" style="color: #16a34a;"><?= number_format($completed) ?></div>
                    <div class="stat-card-label">مكتملة (جاهزة/معتمدة)</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $completionRate ?>%; background: #16a34a;"></div>
                    </div>
                </div>
                
                <!-- Card 3: Pending -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #ffedd5; color: #ea580c;">
                        <i data-lucide="alert-circle" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-card-value" style="color: #ea580c;"><?= number_format($pending) ?></div>
                    <div class="stat-card-label">معلقة (تحتاج مراجعة)</div>
                </div>
                
                <!-- Card 4: Suppliers -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #ede9fe; color: #7c3aed;">
                        <i data-lucide="building-2" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($suppliersCount) ?></div>
                    <div class="stat-card-label">الموردين في القاموس</div>
                </div>
                
                <!-- Card 5: Banks -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #fce7f3; color: #db2777;">
                        <i data-lucide="landmark" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($banksCount) ?></div>
                    <div class="stat-card-label">البنوك في القاموس</div>
                </div>
                
                <!-- Card 6: Completion Rate -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #ecfccb; color: #65a30d;">
                        <i data-lucide="pie-chart" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-card-value"><?= $completionRate ?>%</div>
                    <div class="stat-card-label">نسبة الإنجاز</div>
                </div>
            </div>

            <!-- Top Banks Table -->
            <?php if (!empty($topBanks)): ?>
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">أكثر البنوك نشاطاً</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم البنك</th>
                            <th>عدد الخطابات</th>
                            <th>النسبة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topBanks as $index => $bank): 
                            $percentage = $totalRecords > 0 ? round(($bank['count'] / $totalRecords) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($bank['raw_bank_name'] ?? '') ?></strong></td>
                            <td><span class="badge badge-blue"><?= number_format($bank['count']) ?></span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1; max-width: 100px;">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $percentage ?>%; background: #2563eb;"></div>
                                        </div>
                                    </div>
                                    <span><?= $percentage ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Summary Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">ملخص الحالة</h2>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; padding: 1rem 0;">
                    <div style="text-align: center; padding: 1rem;">
                        <div class="flex justify-center mb-2"><i data-lucide="check-circle" class="w-8 h-8 text-green-600"></i></div>
                        <div style="font-weight: bold; font-size: 1.25rem;"><?= $completed ?></div>
                        <div style="color: #6b7280; font-size: 0.875rem;">مكتملة</div>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <div class="flex justify-center mb-2"><i data-lucide="clock" class="w-8 h-8 text-orange-500"></i></div>
                        <div style="font-weight: bold; font-size: 1.25rem;"><?= $pending ?></div>
                        <div style="color: #6b7280; font-size: 0.875rem;">معلقة</div>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <div class="flex justify-center mb-2"><i data-lucide="trending-up" class="w-8 h-8 text-blue-600"></i></div>
                        <div style="font-weight: bold; font-size: 1.25rem;"><?= $completionRate ?>%</div>
                        <div style="color: #6b7280; font-size: 0.875rem;">نسبة الإنجاز</div>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
