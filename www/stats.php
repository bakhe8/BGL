<?php
/**
 * Smart Analytics Dashboard
 * 
 * لوحة قيادة تحليلية متقدمة تدمج الإحصائيات الأساسية والذكية.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;

$records = new ImportedRecordRepository();
$suppliersRepo = new SupplierRepository();
$banksRepo = new BankRepository();

// 1. Fetch Basic Stats (Original)
$basicStats = $records->getStats();
$totalRecords = $basicStats['total_records'] ?? 0;
$completed = $basicStats['completed'] ?? 0;
$pending = $basicStats['pending'] ?? 0;
$completionRate = $totalRecords > 0 ? round(($completed / $totalRecords) * 100) : 0;
$suppliersCount = $basicStats['suppliers_count'] ?? count($suppliersRepo->allNormalized());
$banksCount = count($banksRepo->allNormalized());
$topBanksCount = $basicStats['top_banks'] ?? []; // Top by Volume

// 2. Fetch Advanced Stats (New)
$advancedStats = $records->getAdvancedStats();
$totalExposure = $advancedStats['financial']['total_exposure'] ?? 0;
$avgAmount = $advancedStats['financial']['avg_amount'] ?? 0;
$automationRate = $advancedStats['automation_rate'] ?? 0;

// 3. Data Quality Stats (New Insights)
$qualityStats = $records->getDataQualityStats();

// 4. Prepare Chart Data (Expiry Forecast)
$expiryLabels = [];
$expiryValues = [];
foreach ($advancedStats['expiry_forecast'] as $row) {
    $dateObj = DateTime::createFromFormat('Y-m', $row['month']);
    $expiryLabels[] = $dateObj ? $dateObj->format('M Y') : $row['month']; 
    $expiryValues[] = $row['value']; 
}

// 5. Prepare Chart Data (Bank Value Share)
$bankValueLabels = [];
$bankValueData = [];
$bankColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280']; 
foreach ($advancedStats['top_banks_value'] as $row) {
    // Format: Arab Bank (50M)
    $val = $row['total_value'] / 1000000; // Millions
    $bankValueLabels[] = ($row['name'] ?? 'غير معروف');
    $bankValueData[] = $row['total_value'];
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحليل البيانات الشامل - BGL</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Scripts -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; }
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Typography */
        h1, h2, h3 { color: #1e293b; }
        
        /* Cards */
        .metric-card {
            background: white; border-radius: 12px; padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;
        }
        .metric-icon { 
            width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
            margin-bottom: 0.75rem;
        }
        .metric-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; }
        .metric-label { color: #64748b; font-size: 0.85rem; font-weight: 500; }
        
        /* Section Dividers */
        .section-divider { margin: 2rem 0 1rem 0; border-bottom: 2px dashed #e2e8f0; }

        /* Tables & Charts Container */
        .card-box {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;
            margin-bottom: 1.5rem;
        }
        .card-header { padding: 1rem; border-bottom: 1px solid #f1f5f9; background: #fff; font-weight: bold; }
        
        .smart-table { width: 100%; border-collapse: collapse; }
        .smart-table th { text-align: right; padding: 0.75rem 1rem; background: #f8fafc; color: #64748b; font-size: 0.8rem; }
        .smart-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.9rem; }
        
        .progress-bar { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 99px; }

        /* Grid Layouts */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    </style>
</head>
<body class="app-shell">

    <!-- Header -->
    <?php include __DIR__ . '/../app/Views/partials/subpage_header.php'; ?>

    <main class="dashboard-container">
        
        <div class="flex items-end justify-between mb-6">
            <div>
                <h1 class="text-3xl font-extrabold mb-1">لوحة الإحصائيات الشاملة</h1>
                <p class="text-gray-500">متابعة الأداء، المالية، وجودة البيانات في مكان واحد</p>
            </div>
            <div class="text-left font-mono text-sm text-gray-400">
                <?= date('Y-m-d') ?>
            </div>
        </div>

        <!-- SECTION 1: Operational KPIs (The Basics) -->
        <h2 class="text-lg font-bold mb-3 text-gray-700">🚀 الأداء التشغيلي</h2>
        <div class="kpi-grid">
            <!-- Total -->
            <div class="metric-card border-l-4 border-l-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="metric-value"><?= number_format($totalRecords) ?></div>
                        <div class="metric-label">إجمالي الخطابات</div>
                    </div>
                    <div class="metric-icon bg-blue-50 text-blue-600"><i data-lucide="files"></i></div>
                </div>
            </div>

            <!-- Completed -->
            <div class="metric-card border-l-4 border-l-green-500">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="metric-value"><?= number_format($completed) ?></div>
                        <div class="metric-label">مكتملة وجاهزة</div>
                    </div>
                    <div class="metric-icon bg-green-50 text-green-600"><i data-lucide="check-circle-2"></i></div>
                </div>
                <div class="mt-2 text-xs text-green-600 font-bold bg-green-50 inline-block px-1 rounded"><?= $completionRate ?>% نسبة الإنجاز</div>
            </div>

            <!-- Pending -->
            <div class="metric-card border-l-4 border-l-orange-500">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="metric-value"><?= number_format($pending) ?></div>
                        <div class="metric-label">معلقة للمراجعة</div>
                    </div>
                    <div class="metric-icon bg-orange-50 text-orange-600"><i data-lucide="clock"></i></div>
                </div>
            </div>

            <!-- Dictionary Size -->
            <div class="metric-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="metric-value text-xl"><?= number_format($suppliersCount) ?> <span class="text-sm font-normal text-gray-400">مورد</span></div>
                        <div class="metric-value text-xl"><?= number_format($banksCount) ?> <span class="text-sm font-normal text-gray-400">بنك</span></div>
                        <div class="metric-label mt-1">حجم القاموس</div>
                    </div>
                    <div class="metric-icon bg-purple-50 text-purple-600"><i data-lucide="book"></i></div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Financial & Intelligence (The Smart Stuff) -->
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">💡 الذكاء المالي والتحليل</h2>
        <div class="kpi-grid">
            <!-- Total Exposure -->
            <div class="metric-card bg-slate-800 text-white border-none">
                <div class="metric-label text-slate-400">إجمالي القيمة المالية</div>
                <div class="metric-value text-white my-2"><?= number_format($totalExposure) ?> <span class="text-sm font-normal">ريال</span></div>
                <div class="text-xs text-slate-400">متوسط الضمان: <?= number_format($avgAmount) ?> ريال</div>
            </div>

            <!-- Automation Rate -->
            <div class="metric-card">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 rounded-full border-4 border-blue-500 flex items-center justify-center font-bold text-blue-700 bg-blue-50">
                        <?= $automationRate ?>%
                    </div>
                    <div>
                        <div class="font-bold text-gray-800">معدل الأتمتة</div>
                        <div class="text-xs text-gray-500">تطابق تلقائي دون تدخل</div>
                    </div>
                </div>
                <div class="progress-bar mt-2"><div class="progress-fill bg-blue-500" style="width: <?= $automationRate ?>%"></div></div>
            </div>
            
            <!-- Future Expiry Count -->
            <div class="metric-card">
                <div class="metric-value"><?= count($advancedStats['expiry_forecast']) ?></div>
                <div class="metric-label">أشهر قادمة بها استحقاق</div>
            </div>
        </div>

        <!-- SECTION 3: Visual Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8 mb-8">
            <!-- Forecast Chart -->
            <div class="card-box lg:col-span-2">
                <div class="card-header flex justify-between items-center">
                    <span>توقعات السيولة (تجويد الضمانات) - 12 شهر</span>
                    <i data-lucide="bar-chart-2" class="w-4 h-4 text-gray-400"></i>
                </div>
                <div class="p-4" style="height: 300px;">
                    <canvas id="expiryChart"></canvas>
                </div>
            </div>

            <!-- Risk Pie Chart -->
            <div class="card-box">
                <div class="card-header flex justify-between items-center">
                    <span>التركيز المالي للبنوك (Risk)</span>
                    <i data-lucide="pie-chart" class="w-4 h-4 text-gray-400"></i>
                </div>
                <div class="p-4 flex justify-center items-center" style="height: 300px;">
                    <canvas id="bankRiskChart"></canvas>
                </div>
            </div>
        </div>

        <!-- SECTION 4: Detailed Tables (Old + New) -->
        <div class="insights-grid">
            
            <!-- 1. Top Banks by Volume (Old Favorite) -->
            <div class="card-box">
                <div class="card-header border-b-blue-500 border-b-2">أكثر البنوك نشاطاً (بالعدد)</div>
                <table class="smart-table">
                    <thead><tr><th>البنك</th><th>العدد</th><th>النسبة</th></tr></thead>
                    <tbody>
                        <?php foreach ($topBanksCount as $bank): 
                             $pct = $totalRecords > 0 ? round(($bank['count']/$totalRecords)*100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($bank['raw_bank_name']) ?></td>
                            <td class="font-bold"><?= $bank['count'] ?></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="progress-bar w-16"><div class="progress-fill bg-blue-500" style="width: <?= $pct ?>%"></div></div>
                                    <span class="text-xs"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. Data Quality: Corrections (New) -->
            <div class="card-box">
                <div class="card-header border-b-orange-500 border-b-2">أعلى التصحيحات اليدوية</div>
                <table class="smart-table">
                    <thead><tr><th>الأصل (Raw)</th><th>المعتمد</th><th>مرات</th></tr></thead>
                    <tbody>
                        <?php foreach ($qualityStats['common_corrections'] as $row): ?>
                        <tr>
                            <td class="text-red-500 font-mono text-xs"><?= htmlspecialchars(mb_substr($row['raw_supplier_name'],0,15)) ?></td>
                            <td class="text-green-600 text-xs"><?= htmlspecialchars(mb_substr($row['supplier_display_name'],0,15)) ?></td>
                            <td><span class="bg-gray-100 px-2 rounded font-bold"><?= $row['count'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($qualityStats['common_corrections'])): ?><tr><td colspan="3" class="text-center text-gray-400">لا توجد بيانات</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 3. Duplicate Risks (New) -->
            <div class="card-box">
                <div class="card-header border-b-red-500 border-b-2 text-red-600">⚠️ ضمانات مكررة محتملة</div>
                <table class="smart-table">
                    <thead><tr><th>رقم الضمان</th><th>البنك</th><th>تكرار</th></tr></thead>
                    <tbody>
                        <?php foreach ($qualityStats['duplicate_guarantees'] as $row): ?>
                        <tr class="bg-red-50">
                            <td class="font-mono font-bold"><?= htmlspecialchars($row['guarantee_number']) ?></td>
                            <td class="text-xs"><?= htmlspecialchars($row['bank']) ?></td>
                            <td class="font-bold text-red-600 text-center"><?= $row['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($qualityStats['duplicate_guarantees'])): ?><tr><td colspan="3" class="text-center text-gray-400">سجل نظيف! ممتاز</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>

    <script>
        lucide.createIcons();

        // Charts
        const ctxExpiry = document.getElementById('expiryChart').getContext('2d');
        new Chart(ctxExpiry, {
            type: 'bar',
            data: {
                labels: <?= json_encode($expiryLabels) ?>,
                datasets: [{
                    label: 'قيمة السيولة',
                    data: <?= json_encode($expiryValues) ?>,
                    backgroundColor: '#3b82f6', borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {display: false} } }
        });

        const ctxBank = document.getElementById('bankRiskChart').getContext('2d');
        new Chart(ctxBank, {
            type: 'pie',
            data: {
                labels: <?= json_encode($bankValueLabels) ?>,
                datasets: [{
                    data: <?= json_encode($bankValueData) ?>,
                    backgroundColor: <?= json_encode($bankColors) ?>,
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {position:'bottom'} } }
        });
    </script>
</body>
</html>
