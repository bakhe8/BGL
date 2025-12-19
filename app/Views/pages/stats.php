<?php
/**
 * Smart Analytics Dashboard
 * 
 * لوحة قيادة تحليلية متقدمة تدمج الإحصائيات الأساسية والذكية.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\UserDecisionRepository;
use App\Repositories\BankLearningRepository;

$records = new ImportedRecordRepository();
$suppliersRepo = new SupplierRepository();
$banksRepo = new BankRepository();
$decisionsRepo = new UserDecisionRepository();
$bankLearningRepo = new BankLearningRepository();

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

// 6. NEW: Import Methods Stats
$importMethodsStats = $records->getImportMethodStats();
$importMethodLabels = [];
$importMethodCounts = [];
$importMethodColors = ['#3b82f6', '#10b981', '#f59e0b']; // Blue, Green, Orange
foreach ($importMethodsStats as $row) {
    $sourceName = match($row['source']) {
        'excel' => '📥 Excel',
        'paste' => '📋 Smart Paste',
        'manual' => '✍️ إدخال يدوي',
        default => $row['source']
    };
    $importMethodLabels[] = $sourceName;
    $importMethodCounts[] = $row['count'];
}

// 7. NEW: Guarantee Types Stats
$guaranteeTypesStats = $records->getGuaranteeTypeStats();

// 8. NEW: Top Suppliers
$topSuppliersByCount = $records->getTopSuppliersByCount(10);
$topSuppliersByValue = $records->getTopSuppliersByValue(10);

// 9. NEW: Alerts
$expiringGuarantees = $records->getExpiringGuarantees(30);
// User requested simpler logic: just oldest pending, no specific age limit
$oldIncompleteRecords = $records->getOldIncompleteRecords(20);

// 10. NEW: Temporal Trends
$temporalTrends = $records->getTemporalTrends(6);
$trendLabels = [];
$trendCounts = [];
foreach ($temporalTrends as $row) {
    $dateObj = DateTime::createFromFormat('Y-m', $row['month']);
    $trendLabels[] = $dateObj ? $dateObj->format('M Y') : $row['month'];
    $trendCounts[] = $row['count'];
}

// 11. NEW: Contract vs PO Stats
$contractVsPoStats = $records->getContractVsPOStats();

// ═══════════════════════════════════════════════════════════════════
// 12. NEW: Decision Intelligence Stats (2025-12-19)
// ═══════════════════════════════════════════════════════════════════
$decisionStats = $decisionsRepo->getDecisionsBySource();
$mostChosenSuppliers = $decisionsRepo->getTopChosenSuppliersGlobal(5);
$bankLearningStats = $bankLearningRepo->getUsageStats(10);

// Prepare decision stats for chart
$decisionSourceLabels = [];
$decisionSourceCounts = [];
$decisionSourceColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];
$sourceLabels = [
    'user_click' => '👆 اختيار يدوي',
    'propagation' => '📤 نشر تلقائي',
    'auto_select' => '🤖 قبول تلقائي',
    'import' => '📥 من الاستيراد'
];
foreach ($decisionStats as $row) {
    $decisionSourceLabels[] = $sourceLabels[$row['decision_source']] ?? $row['decision_source'];
    $decisionSourceCounts[] = $row['count'];
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
    <?php include __DIR__ . '/../partials/subpage_header.php'; ?>

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

            <!-- Suppliers Count -->
            <div class="metric-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="metric-value"><?= number_format($suppliersCount) ?></div>
                        <div class="metric-label">مورد مسجل</div>
                    </div>
                    <div class="metric-icon bg-purple-50 text-purple-600"><i data-lucide="building-2"></i></div>
                </div>
            </div>

            <!-- Banks Count -->
            <div class="metric-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="metric-value"><?= number_format($banksCount) ?></div>
                        <div class="metric-label">بنك معتمد</div>
                    </div>
                    <div class="metric-icon bg-indigo-50 text-indigo-600"><i data-lucide="landmark"></i></div>
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

        <!-- SECTION 5: NEW - Import Methods & Guarantee Types -->
        <div class="section-divider"></div>
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">📊 تحليل طرق الإدخال و الأنواع</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Import Methods Pie Chart -->
            <div class="card-box">
                <div class="card-header flex justify-between items-center">
                    <span>طرق إدخال البيانات</span>
                    <i data-lucide="pie-chart" class="w-4 h-4 text-gray-400"></i>
                </div>
               <div class="p-4" style="height: 280px;">
                    <canvas id="importMethodsChart"></canvas>
                </div>
                <div class="p-4 bg-gray-50 border-t">
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <?php foreach ($importMethodsStats as $idx => $row): ?>
                        <div class="text-center">
                            <div class="font-bold text-gray-800"><?= number_format($row['count']) ?></div>
                            <div class="text-gray-500">
                                <?php 
                                echo match($row['source']) {
                                    'excel' => '📥 Excel',
                                    'paste' => '📋 Smart Paste',
                                    'manual' => '✍️ يدوي',
                                    default => $row['source']
                                };
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Guarantee Types Table -->
            <div class="card-box">
                <div class="card-header">أنواع الضمانات</div>
                <table class="smart-table">
                    <thead><tr><th>النوع</th><th>العدد</th><th>القيمة</th><th>المتوسط</th></tr></thead>
                    <tbody>
                        <?php foreach ($guaranteeTypesStats as $row): ?>
                        <tr>
                            <td class="font-bold"><?= htmlspecialchars($row['type_name']) ?></td>
                            <td><?= number_format($row['count']) ?></td>
                            <td><?= number_format($row['total_value']) ?></td>
                            <td class="text-xs text-gray-500"><?= number_format($row['avg_value'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($guaranteeTypesStats)): ?><tr><td colspan="4" class="text-center text-gray-400">لا توجد بيانات</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION: Contracts vs Purchase Orders -->
        <div class="section-divider"></div>
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">📜 العقود مقابل أوامر الشراء</h2>
        <!-- Binary Comparison Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php 
                $contractStats = array_filter($contractVsPoStats, fn($r) => $r['doc_type'] === 'contract');
                $contractStats = reset($contractStats) ?: ['count' => 0, 'total_value' => 0];
                
                $poStats = array_filter($contractVsPoStats, fn($r) => $r['doc_type'] === 'purchase_order');
                $poStats = reset($poStats) ?: ['count' => 0, 'total_value' => 0];
                
                $totalVal = ($contractStats['total_value'] ?? 0) + ($poStats['total_value'] ?? 0);
                $contractPerc = $totalVal > 0 ? round(($contractStats['total_value'] ?? 0) / $totalVal * 100, 1) : 0;
                $poPerc = $totalVal > 0 ? round(($poStats['total_value'] ?? 0) / $totalVal * 100, 1) : 0;
            ?>
            
            <!-- Contracts Card -->
            <div class="metric-card border-l-4 border-l-blue-600 bg-white shadow-sm p-6 rounded-lg relative overflow-hidden">
                <div class="flex justify-between items-start z-10 relative">
                    <div>
                        <div class="text-gray-500 font-medium mb-1">عقود (Contracts)</div>
                        <div class="text-3xl font-bold text-gray-800 mb-2"><?= number_format($contractStats['count']) ?></div>
                        <div class="text-sm font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded inline-block">
                            <?= number_format($contractStats['total_value'] ?? 0) ?> ريال
                        </div>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                        <i data-lucide="file-text" class="w-8 h-8"></i>
                    </div>
                </div>
                <!-- Percentage Bar -->
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>النسبة من إجمالي القيمة</span>
                        <span><?= $contractPerc ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $contractPerc ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- PO Card -->
            <div class="metric-card border-l-4 border-l-green-600 bg-white shadow-sm p-6 rounded-lg relative overflow-hidden">
                <div class="flex justify-between items-start z-10 relative">
                    <div>
                        <div class="text-gray-500 font-medium mb-1">أوامر شراء (Purchase Orders)</div>
                        <div class="text-3xl font-bold text-gray-800 mb-2"><?= number_format($poStats['count']) ?></div>
                        <div class="text-sm font-semibold text-green-600 bg-green-50 px-2 py-1 rounded inline-block">
                            <?= number_format($poStats['total_value'] ?? 0) ?> ريال
                        </div>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full text-green-600">
                        <i data-lucide="shopping-cart" class="w-8 h-8"></i>
                    </div>
                </div>
                <!-- Percentage Bar -->
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>النسبة من إجمالي القيمة</span>
                        <span><?= $poPerc ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: <?= $poPerc ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 6: NEW - Top Suppliers Analysis -->
        <div class="section-divider"></div>
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">🏢 تحليل الموردين</h2>
        <div class="insights-grid">
            <!-- Top Suppliers by Count -->
            <div class="card-box">
                <div class="card-header border-b-purple-500 border-b-2">أكثر 10 موردين نشاطاً (بالعدد)</div>
                <table class="smart-table">
                    <thead><tr><th>المورد</th><th>العدد</th><th>النسبة</th></tr></thead>
                    <tbody>
                        <?php foreach ($topSuppliersByCount as $row): ?>
                        <tr>
                            <td class="font-bold"><?= htmlspecialchars(mb_substr($row['name'], 0, 30)) ?></td>
                            <td class="text-center font-bold text-purple-600"><?= number_format($row['count']) ?></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="progress-bar w-20">
                                        <div class="progress-fill bg-purple-500" style="width: <?= min(100, $row['percentage']) ?>%"></div>
                                    </div>
                                    <span class="text-xs"><?= $row['percentage'] ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($topSuppliersByCount)): ?><tr><td colspan="3" class="text-center text-gray-400">لا توجد بيانات</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Suppliers by Value -->
            <div class="card-box">
                <div class="card-header border-b-green-500 border-b-2">أكثر 10 موردين بالقيمة المالية</div>
                <table class="smart-table">
                    <thead><tr><th>المورد</th><th>القيمة الإجمالية</th><th>العدد</th></tr></thead>
                    <tbody>
                        <?php foreach ($topSuppliersByValue as $row): ?>
                        <tr>
                            <td class="font-bold"><?= htmlspecialchars(mb_substr($row['name'], 0, 30)) ?></td>
                            <td class="text-center font-bold text-green-600"><?= number_format($row['total_value']) ?> <span class="text-xs text-gray-400">ريال</span></td>
                            <td class="text-center text-xs"><?= number_format($row['count']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($topSuppliersByValue)): ?><tr><td colspan="3" class="text-center text-gray-400">لا توجد بيانات</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 7: NEW - Temporal Trends -->
        <div class="section-divider"></div>
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">📅 الاتجاهات الزمنية</h2>
        <div class="card-box mb-8">
            <div class="card-header flex justify-between items-center">
                <span>معدل الإدخال (آخر 6 أشهر)</span>
                <i data-lucide="trending-up" class="w-4 h-4 text-gray-400"></i>
            </div>
            <div class="p-4" style="height: 280px;">
                <canvas id="temporalTrendChart"></canvas>
            </div>
        </div>

        <!-- SECTION 8: NEW - Alerts & Warnings -->
        <div class="section-divider"></div>
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">🚨 تنبيهات وإشعارات</h2>
        <div class="insights-grid">
            <!-- Expiring Guarantees -->
            <div class="card-box">
                <div class="card-header border-b-orange-500 border-b-2 text-orange-600">⚠️ ضمانات قريبة الانتهاء (خلال 30 يوم)</div>
                <table class="smart-table">
                    <thead><tr><th>رقم الضمان</th><th>المورد</th><th>الأيام المتبقية</th></tr></thead>
                    <tbody>
                        <?php foreach ($expiringGuarantees as $row): ?>
                        <tr class="<?= $row['days_remaining'] <= 7 ? 'bg-red-50' : 'bg-orange-50' ?>">
                            <td class="font-mono font-bold"><?= htmlspecialchars($row['guarantee_number']) ?></td>
                            <td class="text-xs"><?= htmlspecialchars(mb_substr($row['supplier'], 0, 20)) ?></td>
                            <td class="text-center font-bold <?= $row['days_remaining'] <= 7 ? 'text-red-600' : 'text-orange-600' ?>">
                                <?= $row['days_remaining'] ?> يوم
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($expiringGuarantees)): ?><tr><td colspan="3" class="text-center text-green-600">لا توجد ضمانات قريبة الانتهاء! 👍</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pending Records (Oldest First) -->
            <div class="card-box">
                <div class="card-header border-b-yellow-500 border-b-2 text-yellow-600">⏳ أقدم السجلات المعلقة</div>
                <table class="smart-table">
                    <thead><tr><th>رقم الضمان</th><th>المورد</th><th>العمر</th></tr></thead>
                    <tbody>
                        <?php foreach ($oldIncompleteRecords as $row): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= htmlspecialchars($row['guarantee_number']) ?></td>
                            <td class="text-xs"><?= htmlspecialchars(mb_substr($row['supplier'], 0, 20)) ?></td>
                            <td class="text-center font-bold text-gray-500"><?= $row['age_days'] ?> يوم</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($oldIncompleteRecords)): ?><tr><td colspan="3" class="text-center text-green-600">كل السجلات محدثة! ممتاز 🎉</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 9: NEW - Decision Intelligence (2025-12-19) -->
        <div class="section-divider"></div>
        <h2 class="text-lg font-bold mb-3 text-gray-700 mt-8">🧠 ذكاء القرارات</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Decision Sources Chart -->
            <div class="card-box">
                <div class="card-header flex justify-between items-center">
                    <span>مصادر القرارات</span>
                    <i data-lucide="pie-chart" class="w-4 h-4 text-gray-400"></i>
                </div>
                <div class="p-4" style="height: 280px;">
                    <canvas id="decisionSourceChart"></canvas>
                </div>
            </div>

            <!-- Most Chosen Suppliers -->
            <div class="card-box">
                <div class="card-header border-b-purple-500 border-b-2">الموردون الأكثر اختياراً</div>
                <table class="smart-table">
                    <thead><tr><th>المورد</th><th>الاختيارات</th></tr></thead>
                    <tbody>
                        <?php foreach ($mostChosenSuppliers as $row): ?>
                        <tr>
                            <td class="font-bold"><?= htmlspecialchars(mb_substr($row['display_name'] ?? '', 0, 25)) ?></td>
                            <td class="text-center">
                                <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded font-bold"><?= $row['choice_count'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($mostChosenSuppliers)): ?><tr><td colspan="2" class="text-center text-gray-400">لا توجد قرارات بعد</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bank Learning Stats -->
            <div class="card-box">
                <div class="card-header border-b-blue-500 border-b-2">تعلم البنوك</div>
                <table class="smart-table">
                    <thead><tr><th>البنك</th><th>الاستخدام</th></tr></thead>
                    <tbody>
                        <?php foreach ($bankLearningStats as $row): ?>
                        <tr>
                            <td class="text-xs"><?= htmlspecialchars(mb_substr($row['input_name'] ?? '', 0, 20)) ?></td>
                            <td class="text-center">
                                <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded font-bold"><?= $row['usage_count'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($bankLearningStats)): ?><tr><td colspan="2" class="text-center text-gray-400">لا يوجد تعلم بعد</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // Original Charts
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

        // NEW: Import Methods Pie Chart
        const ctxImportMethods = document.getElementById('importMethodsChart').getContext('2d');
        new Chart(ctxImportMethods, {
            type: 'pie',
            data: {
                labels: <?= json_encode($importMethodLabels) ?>,
                datasets: [{
                    data: <?= json_encode($importMethodCounts) ?>,
                    backgroundColor: <?= json_encode($importMethodColors) ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: 'Tajawal', size: 12 },
                            padding: 10
                        }
                    }
                }
            }
        });

        // NEW: Temporal Trends Line Chart
        const ctxTemporal = document.getElementById('temporalTrendChart').getContext('2d');
        new Chart(ctxTemporal, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'عدد السجلات',
                    data: <?= json_encode($trendCounts) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // NEW: Decision Source Pie Chart (2025-12-19)
        const ctxDecisionSource = document.getElementById('decisionSourceChart').getContext('2d');
        new Chart(ctxDecisionSource, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($decisionSourceLabels) ?>,
                datasets: [{
                    data: <?= json_encode($decisionSourceCounts) ?>,
                    backgroundColor: <?= json_encode($decisionSourceColors) ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: 'Tajawal', size: 11 },
                            padding: 8
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
